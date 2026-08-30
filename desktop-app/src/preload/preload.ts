import { contextBridge, ipcRenderer } from 'electron';
import type { DesktopState, LoginPayload } from '../shared/types';

contextBridge.exposeInMainWorld('desktopAPI', {
  getState: (): Promise<DesktopState> => ipcRenderer.invoke('desktop:get-state'),
  login: (payload: LoginPayload) => ipcRenderer.invoke('desktop:login', payload),
  syncNow: () => ipcRenderer.invoke('desktop:sync-now'),
  networkChanged: (isOn: boolean) => ipcRenderer.invoke('desktop:network-changed', isOn),
  logout: () => ipcRenderer.invoke('desktop:logout'),
  openData: () => ipcRenderer.invoke('desktop:open-data'),
  openDbFolder: () => ipcRenderer.invoke('desktop:open-db-folder'),
  onState: (callback: (state: DesktopState) => void) => {
    const listener = (_: unknown, state: DesktopState) => callback(state);
    ipcRenderer.on('desktop:state', listener);
    return () => ipcRenderer.removeListener('desktop:state', listener);
  },
  windowMinimize: () => ipcRenderer.invoke('window-minimize'),
  windowMaximize: () => ipcRenderer.invoke('window-maximize'),
  windowClose: () => ipcRenderer.invoke('window-close'),
  windowIsMaximized: () => ipcRenderer.invoke('window-is-maximized'),
});
