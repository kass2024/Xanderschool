import { existsSync, readFileSync, renameSync, unlinkSync, writeFileSync } from 'fs';
import { safeStorage } from 'electron';
import type { DesktopSettings } from '../shared/types';
import { DEFAULT_REMOTE_URL } from '../shared/types';
import { settingsPath } from './paths';

const empty: DesktopSettings = {
  remoteUrl: DEFAULT_REMOTE_URL,
  token: '',
  email: '',
  password: '',
  staffName: '',
  schoolId: 0,
  schoolName: '',
  lastSyncAt: null,
  provisioned: false,
};

export function loadSettings(): DesktopSettings {
  const candidates = [settingsPath(), `${settingsPath()}.bak`];
  for (const file of candidates) {
    try {
      if (!existsSync(file)) continue;
      const raw = JSON.parse(readFileSync(file, 'utf-8')) as Partial<DesktopSettings>;
      const settings = { ...empty, ...raw };
      if (typeof settings.token === 'string' && settings.token.startsWith('enc:') && safeStorage.isEncryptionAvailable()) {
        settings.token = safeStorage.decryptString(Buffer.from(settings.token.slice(4), 'base64'));
      }
      if (typeof settings.password === 'string' && settings.password.startsWith('enc:') && safeStorage.isEncryptionAvailable()) {
        settings.password = safeStorage.decryptString(Buffer.from(settings.password.slice(4), 'base64'));
      }
      return settings;
    } catch {
      /* try the crash-safe backup */
    }
  }
  return { ...empty };
}

export function saveSettings(next: DesktopSettings): void {
  const persisted = { ...next };
  if (persisted.token && !safeStorage.isEncryptionAvailable()) {
    throw new Error('Secure credential storage is unavailable.');
  }
  if (safeStorage.isEncryptionAvailable()) {
    if (persisted.token) persisted.token = `enc:${safeStorage.encryptString(persisted.token).toString('base64')}`;
    if (persisted.password) persisted.password = `enc:${safeStorage.encryptString(persisted.password).toString('base64')}`;
  }
  const target = settingsPath();
  const temp = `${target}.tmp`;
  const backup = `${target}.bak`;
  writeFileSync(temp, JSON.stringify(persisted, null, 2), 'utf-8');
  if (existsSync(backup)) unlinkSync(backup);
  if (existsSync(target)) renameSync(target, backup);
  renameSync(temp, target);
}

export function clearSettings(): void {
  saveSettings({ ...empty });
}
