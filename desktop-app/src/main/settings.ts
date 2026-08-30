import { existsSync, readFileSync, writeFileSync } from 'fs';
import type { DesktopSettings } from '../shared/types';
import { DEFAULT_REMOTE_URL } from '../shared/types';
import { settingsPath } from './paths';

const empty: DesktopSettings = {
  remoteUrl: DEFAULT_REMOTE_URL,
  token: '',
  email: '',
  staffName: '',
  schoolId: 0,
  schoolName: '',
  lastSyncAt: null,
};

export function loadSettings(): DesktopSettings {
  try {
    if (!existsSync(settingsPath())) return { ...empty };
    const raw = JSON.parse(readFileSync(settingsPath(), 'utf-8')) as Partial<DesktopSettings>;
    return { ...empty, ...raw };
  } catch {
    return { ...empty };
  }
}

export function saveSettings(next: DesktopSettings): void {
  writeFileSync(settingsPath(), JSON.stringify(next, null, 2), 'utf-8');
}

export function clearSettings(): void {
  saveSettings({ ...empty });
}
