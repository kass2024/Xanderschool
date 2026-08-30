export const DEFAULT_REMOTE_URL = 'https://schoolmis.xanderglobalacademy.com';

export type AppPhase = 'setup' | 'syncing' | 'starting' | 'ready' | 'error';

export interface DesktopSettings {
  remoteUrl: string;
  token: string;
  email: string;
  staffName: string;
  schoolId: number;
  schoolName: string;
  lastSyncAt: string | null;
}

export interface SyncProgress {
  stage: string;
  table?: string;
  current: number;
  total: number;
  message: string;
}

export interface DesktopState {
  phase: AppPhase;
  online: boolean;
  localUrl: string | null;
  settings: DesktopSettings | null;
  progress: SyncProgress | null;
  pending: number;
  lastSyncAt: string | null;
  lastError: string | null;
  phpReady: boolean;
}

export interface LoginPayload {
  remoteUrl: string;
  email: string;
  password: string;
}
