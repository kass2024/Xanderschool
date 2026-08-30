import type { DesktopState, LoginPayload } from '../../shared/types';

export interface DesktopAPI {
  getState: () => Promise<DesktopState>;
  login: (payload: LoginPayload) => Promise<{ ok: boolean; error?: string }>;
  syncNow: () => Promise<{ ok: boolean; error?: string | null }>;
  networkChanged: (isOn: boolean) => Promise<{ ok: boolean }>;
  logout: () => Promise<{ ok: boolean }>;
  openData: () => Promise<void>;
  openDbFolder: () => Promise<void>;
  onState: (callback: (state: DesktopState) => void) => () => void;
  windowMinimize: () => Promise<void>;
  windowMaximize: () => Promise<boolean>;
  windowClose: () => Promise<void>;
  windowIsMaximized: () => Promise<boolean>;
}

declare global {
  interface Window {
    desktopAPI: DesktopAPI;
  }
}

export {};
