import { app, BrowserWindow, ipcMain, net, session, shell } from 'electron';
import { join } from 'path';
import { existsSync } from 'fs';
import { pathToFileURL } from 'url';
import type { DesktopState, LoginPayload, SyncProgress } from '../shared/types';
import { loadSettings, saveSettings, clearSettings } from './settings';
import { remoteHealth, remoteLogin } from './remote-api';
import { closeDb, incrementalSync, initialSync, pendingCount } from './sync-engine';
import { localBaseUrl, startPhpServer, stopPhpServer } from './php-server';
import { sqlitePath, userDataDir } from './paths';

let mainWindow: BrowserWindow | null = null;
let syncTimer: ReturnType<typeof setInterval> | null = null;
let syncSoonTimer: ReturnType<typeof setTimeout> | null = null;
let online = false;
let wasOnline = false;
let phase: DesktopState['phase'] = 'setup';
let lastError: string | null = null;
let progress: SyncProgress | null = null;
let phpReady = false;
let syncing = false;
let autoLoginArmed = false;
let showingSchool = false;
let lastLightSyncAt = 0;
let lastFullSyncAt = 0;
let syncSoonFull = false;

const NETWORK_CHECK_MS = 5000;
const LIGHT_SYNC_MS = 15_000;
const PENDING_SYNC_MS = 1_500;
const FULL_SYNC_MS = 5 * 60_000;
const MUTATION_SYNC_DEBOUNCE_MS = 900;

function getPreloadPath(): string {
  const mjs = join(__dirname, '../preload/preload.mjs');
  const js = join(__dirname, '../preload/preload.js');
  return existsSync(mjs) ? mjs : js;
}

function rendererUrl(): string | null {
  if (!app.isPackaged && process.env.ELECTRON_RENDERER_URL) {
    return process.env.ELECTRON_RENDERER_URL;
  }
  return null;
}

function emitState(): void {
  const settings = loadSettings();
  const state: DesktopState = {
    phase,
    online,
    localUrl: localBaseUrl(),
    settings: settings.token && settings.provisioned ? { ...settings, password: '' } : null,
    progress,
    pending: pendingCount(),
    lastSyncAt: settings.lastSyncAt,
    lastError,
    phpReady,
  };
  if (!showingSchool) {
    mainWindow?.webContents.send('desktop:state', state);
  }
}

function overlayScript(state: {
  online: boolean;
  pending: number;
  syncing: boolean;
  lastSyncAt: string | null;
}): string {
  const dot = state.online ? '#22c55e' : '#94a3b8';
  const label = !state.online
    ? 'Offline · saved on this PC'
    : state.syncing
      ? 'Online · saving to live server…'
      : state.pending > 0
        ? `Online · ${state.pending} waiting for live sync`
        : 'Online · live sync on';
  return `
    (function () {
      var id = 'xander-desktop-chip';
      var el = document.getElementById(id);
      if (!el) {
        el = document.createElement('div');
        el.id = id;
        el.style.cssText = [
          'position:fixed',
          'right:14px',
          'bottom:14px',
          'z-index:2147483646',
          'display:flex',
          'align-items:center',
          'gap:8px',
          'padding:7px 12px',
          'border-radius:8px',
          'background:#0b1f4a',
          'color:#fff',
          'font:12px/1.2 Segoe UI,sans-serif',
          'box-shadow:0 8px 24px rgba(11,31,74,.28)',
          'pointer-events:auto',
        ].join(';');
        el.innerHTML = '<span data-chip-dot style="width:8px;height:8px;border-radius:50%;background:${dot};display:inline-block"></span><span data-chip-label></span><button data-chip-reconnect style="border:0;border-radius:5px;padding:3px 7px;background:#fff;color:#0b1f4a;font:11px Segoe UI,sans-serif;cursor:pointer">Reconnect</button>';
        document.body.appendChild(el);
        window.addEventListener('online', function () {
          if (window.desktopAPI && window.desktopAPI.networkChanged) window.desktopAPI.networkChanged(true);
        });
        window.addEventListener('offline', function () {
          if (window.desktopAPI && window.desktopAPI.networkChanged) window.desktopAPI.networkChanged(false);
        });
      }
      var lab = el.querySelector('[data-chip-label]');
      if (lab) lab.textContent = ${JSON.stringify(label)};
      var d = el.querySelector('[data-chip-dot]');
      if (d) d.style.background = ${JSON.stringify(dot)};
      var reconnect = el.querySelector('[data-chip-reconnect]');
      if (reconnect) reconnect.onclick = function () {
        if (window.desktopAPI && window.desktopAPI.logout) window.desktopAPI.logout();
      };
    })();
  `;
}

function injectOverlay(): void {
  if (!showingSchool || !mainWindow) return;
  const settings = loadSettings();
  void mainWindow.webContents
    .executeJavaScript(
      overlayScript({
        online,
        pending: pendingCount(),
        syncing,
        lastSyncAt: settings.lastSyncAt,
      }),
    )
    .catch(() => undefined);
}

function isLocalDesktopRequest(url: string): boolean {
  return /^http:\/\/127\.0\.0\.1:\d+\//i.test(url);
}

function isMutatingRequest(method: string): boolean {
  return ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method.toUpperCase());
}

function scheduleBackgroundSync(delayMs = MUTATION_SYNC_DEBOUNCE_MS, full = false): void {
  syncSoonFull = syncSoonFull || full;
  if (syncSoonTimer) return;
  syncSoonTimer = setTimeout(() => {
    syncSoonTimer = null;
    const nextFull = syncSoonFull;
    syncSoonFull = false;
    void runBackgroundSync(nextFull);
  }, Math.max(0, delayMs));
}

function installMutationSyncHooks(): void {
  const partition = session.fromPartition('persist:xander-school');
  partition.webRequest.onCompleted((details) => {
    if (!phpReady || syncing) return;
    if (!isLocalDesktopRequest(details.url)) return;
    if (!isMutatingRequest(details.method)) return;
    if ((details.statusCode ?? 0) < 200 || (details.statusCode ?? 0) >= 400) return;
    if (details.url.includes('/api/desktop/')) return;
    scheduleBackgroundSync();
  });
}

async function showRenderer(): Promise<void> {
  showingSchool = false;
  if (!mainWindow) return;
  const dev = rendererUrl();
  if (dev) {
    if (mainWindow.webContents.getURL() === dev) return;
    await mainWindow.loadURL(dev);
    return;
  }
  const target = pathToFileURL(join(__dirname, '../renderer/index.html')).toString();
  const current = mainWindow.webContents.getURL();
  if (current === target || current.startsWith(`${target}#`)) return;
  await mainWindow.loadFile(join(__dirname, '../renderer/index.html'));
}

async function showSchool(path = '/dashboard'): Promise<void> {
  const base = localBaseUrl();
  if (!mainWindow || !base) return;
  showingSchool = true;
  const target = `${base}${path.startsWith('/') ? path : `/${path}`}`;
  await mainWindow.loadURL(target);
}

function isLoginUrl(url: string): boolean {
  try {
    const u = new URL(url);
    return /\/login\/?$/.test(u.pathname) || u.pathname.endsWith('/login');
  } catch {
    return url.includes('/login');
  }
}

async function tryAutoLogin(): Promise<void> {
  if (!autoLoginArmed || !mainWindow) return;
  const settings = loadSettings();
  if (!settings.email || !settings.password) return;
  const url = mainWindow.webContents.getURL();
  if (!isLoginUrl(url)) return;

  const js = `
    (function(){
      var e = document.getElementById('email');
      var p = document.getElementById('examplePassword');
      var f = document.getElementById('frm_login');
      if (!e || !p || !f) return 'no-form';
      e.value = ${JSON.stringify(settings.email)};
      p.value = ${JSON.stringify(settings.password)};
      f.submit();
      return 'submitted';
    })();
  `;
  try {
    const result = await mainWindow.webContents.executeJavaScript(js);
    if (result === 'submitted') autoLoginArmed = false;
  } catch {
    /* login page still loading */
  }
}

async function probeOnline(): Promise<boolean> {
  const settings = loadSettings();
  if (!settings.remoteUrl) {
    online = false;
    return false;
  }
  if (!net.isOnline()) {
    online = false;
    return false;
  }
  online = await remoteHealth(settings.remoteUrl);
  return online;
}

async function refreshDesktopToken(): Promise<void> {
  const settings = loadSettings();
  if (!settings.remoteUrl || !settings.email || !settings.password) {
    return;
  }
  const result = await remoteLogin(settings.remoteUrl, settings.email, settings.password, 'Xander School Desktop');
  if (!result.ok || !result.token) {
    throw new Error(result.error || 'Desktop sign-in failed');
  }
  settings.token = result.token;
  if (result.staff?.name) settings.staffName = result.staff.name;
  if (result.school) {
    settings.schoolId = result.school.id;
    settings.schoolName = result.school.name;
  }
  saveSettings(settings);
}

async function runBackgroundSync(full = false): Promise<void> {
  if (syncing) return;
  const settings = loadSettings();
  if (!settings.token || !settings.provisioned) return;
  const isOn = await probeOnline();
  emitState();
  injectOverlay();
  if (!isOn) {
    lastError = null;
    emitState();
    injectOverlay();
    return;
  }
  syncing = true;
  injectOverlay();
  try {
    const result = await incrementalSync(settings.remoteUrl, settings.token, (p) => {
      progress = p;
      emitState();
    }, full);
    closeDb();
    const now = Date.now();
    lastLightSyncAt = now;
    if (full) lastFullSyncAt = now;
    settings.lastSyncAt = new Date().toISOString();
    saveSettings(settings);
    lastError = null;
    progress = {
      stage: 'idle',
      current: 1,
      total: 1,
      message: `Synced (↑${result.pushed} ↓${result.pulled})`,
    };
  } catch (e) {
    lastError = e instanceof Error ? e.message : String(e);
    closeDb();
  } finally {
    syncing = false;
    emitState();
    injectOverlay();
  }
}

async function networkTick(): Promise<void> {
  const settings = loadSettings();
  if (!settings.token || !phpReady) return;
  const serverUp = await probeOnline();
  const becameOnline = serverUp && !wasOnline;
  wasOnline = serverUp;
  injectOverlay();
  if (!showingSchool) emitState();
  if (!serverUp || syncing) return;

  const now = Date.now();
  const pending = pendingCount();
  if (becameOnline) {
    await runBackgroundSync(true);
    return;
  }
  if (pending > 0 && now - lastLightSyncAt >= PENDING_SYNC_MS) {
    await runBackgroundSync(false);
    return;
  }
  if (now - lastFullSyncAt >= FULL_SYNC_MS) {
    await runBackgroundSync(true);
    return;
  }
  if (now - lastLightSyncAt >= LIGHT_SYNC_MS) {
    await runBackgroundSync(false);
  }
}

async function bootReadyApp(skipStartupSync = false): Promise<void> {
  phase = 'starting';
  phpReady = false;
  emitState();
  closeDb();
  const url = await startPhpServer();
  phpReady = true;
  const canSync = await probeOnline();
  emitState();
  if (!skipStartupSync && canSync) {
    phase = 'syncing';
    progress = { stage: 'login', current: 0, total: 1, message: 'Refreshing desktop access…' };
    emitState();
    await refreshDesktopToken();
    const settings = loadSettings();
    progress = { stage: 'pull', current: 0, total: 1, message: 'Syncing full server data before login…' };
    emitState();
    const result = await incrementalSync(settings.remoteUrl, settings.token, (p) => {
      progress = p;
      emitState();
    }, true);
    closeDb();
    settings.lastSyncAt = new Date().toISOString();
    saveSettings(settings);
    lastError = null;
    progress = {
      stage: 'idle',
      current: 1,
      total: 1,
      message: `Startup sync finished (↑${result.pushed} ↓${result.pulled})`,
    };
  }
  phase = 'ready';
  autoLoginArmed = true;
  await showSchool('/login');
  emitState();
  injectOverlay();
  if (syncTimer) clearInterval(syncTimer);
  syncTimer = setInterval(() => {
    void networkTick();
  }, NETWORK_CHECK_MS);
  if (!canSync) void networkTick();
  void url;
}

function createWindow(): void {
  mainWindow = new BrowserWindow({
    width: 1440,
    height: 920,
    minWidth: 1100,
    minHeight: 700,
    backgroundColor: '#f4f1ea',
    autoHideMenuBar: true,
    title: 'Xander School',
    icon: existsSync(join(__dirname, '../../icon.png')) ? join(__dirname, '../../icon.png') : undefined,
    webPreferences: {
      preload: getPreloadPath(),
      nodeIntegration: false,
      contextIsolation: true,
      sandbox: false,
      partition: 'persist:xander-school',
    },
  });

  mainWindow.webContents.setWindowOpenHandler(({ url }) => {
    if (url.startsWith('http://127.0.0.1') || url.startsWith('file:')) {
      return { action: 'allow' };
    }
    void shell.openExternal(url);
    return { action: 'deny' };
  });

  mainWindow.webContents.on('did-finish-load', () => {
    if (showingSchool) {
      void tryAutoLogin();
      injectOverlay();
    } else {
      emitState();
    }
  });

  mainWindow.on('closed', () => {
    mainWindow = null;
  });

  void showRenderer();
  mainWindow.once('ready-to-show', () => mainWindow?.show());
}

app.whenReady().then(async () => {
  const partition = session.fromPartition('persist:xander-school');
  partition.setPermissionRequestHandler((_wc, _perm, cb) => cb(true));
  installMutationSyncHooks();
  createWindow();
  const settings = loadSettings();
  if (settings.token && settings.provisioned) {
    try {
      await bootReadyApp(true);
    } catch (e) {
      phase = 'error';
      lastError = e instanceof Error ? e.message : String(e);
      showingSchool = false;
      await showRenderer();
      emitState();
    }
  } else {
    phase = 'setup';
    emitState();
  }
});

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') app.quit();
});

app.on('before-quit', () => {
  if (syncTimer) clearInterval(syncTimer);
  if (syncSoonTimer) clearTimeout(syncSoonTimer);
  closeDb();
  void stopPhpServer();
});

ipcMain.handle('desktop:get-state', async () => {
  const settings = loadSettings();
  const state: DesktopState = {
    phase,
    online,
    localUrl: localBaseUrl(),
    settings: settings.token ? { ...settings, password: '' } : null,
    progress,
    pending: pendingCount(),
    lastSyncAt: settings.lastSyncAt,
    lastError,
    phpReady,
  };
  return state;
});

ipcMain.handle('desktop:login', async (_e, payload: LoginPayload) => {
  try {
    phase = 'syncing';
    lastError = null;
    progress = { stage: 'login', current: 0, total: 1, message: 'Signing in…' };
    showingSchool = false;
    await showRenderer();
    emitState();
    const remoteUrl = payload.remoteUrl.replace(/\/+$/, '');
    const result = await remoteLogin(remoteUrl, payload.email, payload.password, 'Xander School Desktop');
    if (!result.ok || !result.token || !result.school) {
      throw new Error(result.error || 'Login failed. Check email, password, and school URL.');
    }
    const settings = {
      remoteUrl,
      token: result.token,
      email: result.staff?.email || payload.email,
      password: payload.password,
      staffName: result.staff?.name || '',
      schoolId: result.school.id,
      schoolName: result.school.name,
      lastSyncAt: null as string | null,
      provisioned: false,
    };
    saveSettings(settings);
    progress = { stage: 'schema', current: 0, total: 1, message: 'Downloading full server data…' };
    emitState();
    await initialSync(remoteUrl, result.token, (p) => {
      progress = p;
      emitState();
    });
    closeDb();
    settings.lastSyncAt = new Date().toISOString();
    settings.provisioned = true;
    saveSettings(settings);
    await bootReadyApp();
    return { ok: true };
  } catch (e) {
    phase = 'setup';
    phpReady = false;
    lastError = e instanceof Error ? e.message : String(e);
    closeDb();
    await stopPhpServer();
    showingSchool = false;
    await showRenderer();
    emitState();
    return { ok: false, error: lastError };
  }
});

ipcMain.handle('desktop:sync-now', async () => {
  await runBackgroundSync(true);
  return { ok: !lastError, error: lastError };
});

ipcMain.handle('desktop:network-changed', async (_e, isOn: boolean) => {
  if (isOn) {
    scheduleBackgroundSync(250, true);
    void networkTick();
  } else {
    online = false;
    wasOnline = false;
    injectOverlay();
  }
  return { ok: true };
});

ipcMain.handle('desktop:logout', async () => {
  if (syncTimer) clearInterval(syncTimer);
  await stopPhpServer();
  closeDb();
  clearSettings();
  phpReady = false;
  phase = 'setup';
  lastError = null;
  progress = null;
  showingSchool = false;
  await showRenderer();
  emitState();
  return { ok: true };
});

ipcMain.handle('desktop:open-data', async () => {
  await shell.openPath(userDataDir());
});

ipcMain.handle('desktop:open-db-folder', async () => {
  await shell.showItemInFolder(sqlitePath());
});

ipcMain.handle('window-minimize', () => mainWindow?.minimize());
ipcMain.handle('window-maximize', () => {
  if (!mainWindow) return false;
  if (mainWindow.isMaximized()) mainWindow.unmaximize();
  else mainWindow.maximize();
  return mainWindow.isMaximized();
});
ipcMain.handle('window-close', () => mainWindow?.close());
ipcMain.handle('window-is-maximized', () => mainWindow?.isMaximized() ?? false);
