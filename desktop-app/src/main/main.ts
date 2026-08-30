import { app, BrowserView, BrowserWindow, ipcMain, shell } from 'electron';
import { join } from 'path';
import { existsSync } from 'fs';
import type { DesktopState, LoginPayload, SyncProgress } from '../shared/types';
import { loadSettings, saveSettings, clearSettings } from './settings';
import { remoteHealth, remoteLogin } from './remote-api';
import { closeDb, incrementalSync, initialSync, pendingCount } from './sync-engine';
import { localBaseUrl, startPhpServer, stopPhpServer } from './php-server';
import { sqlitePath, userDataDir } from './paths';

const TITLE = 40;
const STATUS = 34;

let mainWindow: BrowserWindow | null = null;
let schoolView: BrowserView | null = null;
let syncTimer: ReturnType<typeof setInterval> | null = null;
let online = false;
let phase: DesktopState['phase'] = 'setup';
let lastError: string | null = null;
let progress: SyncProgress | null = null;
let phpReady = false;
let syncing = false;

function getPreloadPath(): string {
  const mjs = join(__dirname, '../preload/preload.mjs');
  const js = join(__dirname, '../preload/preload.js');
  return existsSync(mjs) ? mjs : js;
}

function emitState(): void {
  const settings = loadSettings();
  const state: DesktopState = {
    phase,
    online,
    localUrl: localBaseUrl(),
    settings: settings.token ? settings : null,
    progress,
    pending: pendingCount(),
    lastSyncAt: settings.lastSyncAt,
    lastError,
    phpReady,
  };
  mainWindow?.webContents.send('desktop:state', state);
}

function layoutView(): void {
  if (!mainWindow || !schoolView) return;
  const { width, height } = mainWindow.getContentBounds();
  schoolView.setBounds({
    x: 0,
    y: TITLE,
    width,
    height: Math.max(100, height - TITLE - STATUS),
  });
}

function showSchool(url: string): void {
  if (!mainWindow) return;
  if (!schoolView) {
    schoolView = new BrowserView({
      webPreferences: {
        nodeIntegration: false,
        contextIsolation: true,
        sandbox: true,
      },
    });
    schoolView.setBackgroundColor('#f4f7f6');
    mainWindow.addBrowserView(schoolView);
    schoolView.webContents.setWindowOpenHandler(({ url: target }) => {
      shell.openExternal(target);
      return { action: 'deny' };
    });
  }
  layoutView();
  schoolView.webContents.loadURL(url);
}

function hideSchool(): void {
  if (mainWindow && schoolView) {
    mainWindow.removeBrowserView(schoolView);
  }
}

async function probeOnline(): Promise<boolean> {
  const settings = loadSettings();
  if (!settings.remoteUrl) return false;
  online = await remoteHealth(settings.remoteUrl);
  return online;
}

async function runBackgroundSync(force = false): Promise<void> {
  if (syncing) return;
  const settings = loadSettings();
  if (!settings.token) return;
  const isOn = await probeOnline();
  emitState();
  if (!isOn && !force) return;
  if (!isOn) {
    lastError = 'Server is not reachable. Changes stay on this PC until it is back.';
    emitState();
    return;
  }
  syncing = true;
  try {
    const result = await incrementalSync(settings.remoteUrl, settings.token, (p) => {
      progress = p;
      emitState();
    });
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
  } finally {
    syncing = false;
    emitState();
  }
}

async function bootReadyApp(): Promise<void> {
  phase = 'starting';
  phpReady = false;
  emitState();
  const url = await startPhpServer();
  phpReady = true;
  phase = 'ready';
  showSchool(`${url}/login`);
  emitState();
  void probeOnline().then(() => emitState());
  if (syncTimer) clearInterval(syncTimer);
  syncTimer = setInterval(() => {
    void runBackgroundSync();
  }, 45000);
  void runBackgroundSync();
}

function createWindow(): void {
  mainWindow = new BrowserWindow({
    width: 1440,
    height: 900,
    minWidth: 1024,
    minHeight: 680,
    frame: false,
    backgroundColor: '#0f3d34',
    show: false,
    webPreferences: {
      preload: getPreloadPath(),
      nodeIntegration: false,
      contextIsolation: true,
      sandbox: false,
    },
  });

  if (!app.isPackaged && process.env.ELECTRON_RENDERER_URL) {
    mainWindow.loadURL(process.env.ELECTRON_RENDERER_URL);
  } else {
    mainWindow.loadFile(join(__dirname, '../renderer/index.html'));
  }

  mainWindow.once('ready-to-show', () => mainWindow?.show());
  mainWindow.on('resize', layoutView);
  mainWindow.on('closed', () => {
    mainWindow = null;
    schoolView = null;
  });
}

app.whenReady().then(async () => {
  createWindow();
  const settings = loadSettings();
  if (settings.token) {
    try {
      await bootReadyApp();
    } catch (e) {
      phase = 'error';
      lastError = e instanceof Error ? e.message : String(e);
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
  closeDb();
  void stopPhpServer();
});

ipcMain.handle('desktop:get-state', async () => {
  const settings = loadSettings();
  const state: DesktopState = {
    phase,
    online,
    localUrl: localBaseUrl(),
    settings: settings.token ? settings : null,
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
      staffName: result.staff?.name || '',
      schoolId: result.school.id,
      schoolName: result.school.name,
      lastSyncAt: null as string | null,
    };
    saveSettings(settings);
    progress = { stage: 'schema', current: 0, total: 1, message: 'Downloading school data…' };
    emitState();
    await initialSync(remoteUrl, result.token, (p) => {
      progress = p;
      emitState();
    });
    settings.lastSyncAt = new Date().toISOString();
    saveSettings(settings);
    await bootReadyApp();
    return { ok: true };
  } catch (e) {
    phase = 'setup';
    phpReady = false;
    hideSchool();
    lastError = e instanceof Error ? e.message : String(e);
    emitState();
    return { ok: false, error: lastError };
  }
});

ipcMain.handle('desktop:sync-now', async () => {
  await runBackgroundSync(true);
  return { ok: !lastError, error: lastError };
});

ipcMain.handle('desktop:logout', async () => {
  if (syncTimer) clearInterval(syncTimer);
  hideSchool();
  await stopPhpServer();
  closeDb();
  clearSettings();
  phpReady = false;
  phase = 'setup';
  lastError = null;
  progress = null;
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
