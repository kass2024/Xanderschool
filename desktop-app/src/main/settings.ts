import { existsSync, readFileSync, writeFileSync } from 'fs';
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
};

export function loadSettings(): DesktopSettings {
  try {
    if (!existsSync(settingsPath())) return { ...empty };
    const raw = JSON.parse(readFileSync(settingsPath(), 'utf-8')) as Partial<DesktopSettings>;
    const settings = { ...empty, ...raw };
    if (typeof settings.password === 'string' && settings.password.startsWith('enc:') && safeStorage.isEncryptionAvailable()) {
      settings.password = safeStorage.decryptString(Buffer.from(settings.password.slice(4), 'base64'));
    }
    return settings;
  } catch {
    return { ...empty };
  }
}

export function saveSettings(next: DesktopSettings): void {
  const persisted = { ...next };
  if (persisted.password && safeStorage.isEncryptionAvailable()) {
    persisted.password = `enc:${safeStorage.encryptString(persisted.password).toString('base64')}`;
  }
  writeFileSync(settingsPath(), JSON.stringify(persisted, null, 2), 'utf-8');
}

export function clearSettings(): void {
  saveSettings({ ...empty });
}
