import { useEffect, useState } from 'react';
import {
  CloudOff,
  Cloud,
  Database,
  FolderOpen,
  Loader2,
  LogOut,
  Minus,
  RefreshCw,
  Square,
  Wifi,
  WifiOff,
  X,
} from 'lucide-react';
import { DEFAULT_REMOTE_URL } from '@shared/types';
import type { DesktopState } from '@shared/types';

const empty: DesktopState = {
  phase: 'setup',
  online: false,
  localUrl: null,
  settings: null,
  progress: null,
  pending: 0,
  lastSyncAt: null,
  lastError: null,
  phpReady: false,
};

export default function App() {
  const [state, setState] = useState<DesktopState>(empty);
  const [remoteUrl, setRemoteUrl] = useState(DEFAULT_REMOTE_URL);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [busy, setBusy] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);

  useEffect(() => {
    void window.desktopAPI.getState().then((s) => {
      setState(s);
      if (s.settings?.remoteUrl) setRemoteUrl(s.settings.remoteUrl);
      if (s.settings?.email) setEmail(s.settings.email);
    });
    return window.desktopAPI.onState(setState);
  }, []);

  const onLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    setBusy(true);
    setFormError(null);
    const res = await window.desktopAPI.login({ remoteUrl, email, password });
    setBusy(false);
    if (!res.ok) setFormError(res.error || 'Login failed');
  };

  const chrome = (
    <header className="titlebar h-10 shrink-0 flex items-center justify-between px-3 bg-[#0f3d34] text-white">
      <div className="flex items-center gap-2 min-w-0">
        <div className="w-6 h-6 rounded bg-[#1b6b5a] flex items-center justify-center text-[11px] font-bold">
          XS
        </div>
        <span className="text-sm font-semibold truncate">Xander School</span>
        {state.settings?.schoolName && (
          <span className="text-xs text-white/70 truncate">· {state.settings.schoolName}</span>
        )}
      </div>
      <div className="flex items-center no-drag">
        <button className="win-btn" onClick={() => void window.desktopAPI.windowMinimize()} aria-label="Minimize">
          <Minus size={14} />
        </button>
        <button className="win-btn" onClick={() => void window.desktopAPI.windowMaximize()} aria-label="Maximize">
          <Square size={12} />
        </button>
        <button className="win-btn win-close" onClick={() => void window.desktopAPI.windowClose()} aria-label="Close">
          <X size={14} />
        </button>
      </div>
    </header>
  );

  const status = (
    <footer className="h-[34px] shrink-0 flex items-center justify-between px-3 bg-[#08241e] text-white/90 text-[12px]">
      <div className="flex items-center gap-3 min-w-0">
        {state.online ? (
          <span className="flex items-center gap-1 text-emerald-300">
            <Wifi size={13} /> Online
          </span>
        ) : (
          <span className="flex items-center gap-1 text-amber-300">
            <WifiOff size={13} /> Offline — data stays on this PC
          </span>
        )}
        {state.pending > 0 && (
          <span className="flex items-center gap-1 text-sky-300">
            <CloudOff size={13} /> {state.pending} waiting to sync
          </span>
        )}
        {state.progress?.message && state.phase !== 'ready' && (
          <span className="truncate text-white/70">{state.progress.message}</span>
        )}
      </div>
      <div className="flex items-center gap-2 no-drag">
        {state.lastSyncAt && (
          <span className="text-white/50 hidden sm:inline">
            Last sync {new Date(state.lastSyncAt).toLocaleString()}
          </span>
        )}
        <button
          className="status-btn"
          disabled={state.phase === 'syncing'}
          onClick={() => void window.desktopAPI.syncNow()}
          title="Sync now"
        >
          <RefreshCw size={13} className={state.phase === 'syncing' ? 'animate-spin' : ''} />
          Sync
        </button>
        <button className="status-btn" onClick={() => void window.desktopAPI.openDbFolder()} title="Open local database">
          <Database size={13} />
        </button>
        <button className="status-btn" onClick={() => void window.desktopAPI.openData()} title="Open data folder">
          <FolderOpen size={13} />
        </button>
        {state.settings && (
          <button className="status-btn" onClick={() => void window.desktopAPI.logout()} title="Disconnect">
            <LogOut size={13} />
          </button>
        )}
      </div>
    </footer>
  );

  if (state.phase === 'setup' || state.phase === 'error') {
    return (
      <div className="h-full flex flex-col bg-[#eef6f4]">
        {chrome}
        <main className="flex-1 overflow-auto flex items-center justify-center p-6">
          <form onSubmit={onLogin} className="w-full max-w-md bg-white rounded-xl border border-[#d5ebe5] p-8">
            <h1 className="text-xl font-semibold text-[#0f3d34]">Xander School Desktop</h1>
            <p className="mt-2 text-sm text-[#3d5c56] leading-relaxed">
              Full school system on this PC with SQLite. When internet is available, saves go to the live server and
              remain copied on this PC. When internet is down, work stays local and syncs automatically when the
              connection returns.
            </p>
            <label className="block mt-6 text-xs font-medium text-[#0f3d34]">School server</label>
            <input
              className="mt-1 w-full rounded-md border border-[#c5ddd7] px-3 py-2 text-sm"
              value={remoteUrl}
              onChange={(e) => setRemoteUrl(e.target.value)}
              placeholder="https://schoolmis.xanderglobalacademy.com"
            />
            <label className="block mt-4 text-xs font-medium text-[#0f3d34]">Staff email</label>
            <input
              className="mt-1 w-full rounded-md border border-[#c5ddd7] px-3 py-2 text-sm"
              type="email"
              autoComplete="username"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
            />
            <label className="block mt-4 text-xs font-medium text-[#0f3d34]">Password</label>
            <input
              className="mt-1 w-full rounded-md border border-[#c5ddd7] px-3 py-2 text-sm"
              type="password"
              autoComplete="current-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              minLength={6}
              required
            />
            {(formError || state.lastError) && (
              <p className="mt-4 text-sm text-red-700 bg-red-50 border border-red-100 rounded-md px-3 py-2">
                {formError || state.lastError}
              </p>
            )}
            <button
              type="submit"
              disabled={busy}
              className="mt-6 w-full h-10 rounded-md bg-[#1b6b5a] text-white text-sm font-medium hover:bg-[#155548] disabled:opacity-60 flex items-center justify-center gap-2"
            >
              {busy ? <Loader2 size={16} className="animate-spin" /> : <Cloud size={16} />}
              {busy ? 'Connecting…' : 'Sign in and download school data'}
            </button>
          </form>
        </main>
        {status}
      </div>
    );
  }

  if (state.phase === 'syncing' || state.phase === 'starting') {
    const pct =
      state.progress && state.progress.total > 0
        ? Math.round((state.progress.current / state.progress.total) * 100)
        : 12;
    return (
      <div className="h-full flex flex-col bg-[#0f3d34] text-white">
        {chrome}
        <main className="flex-1 flex items-center justify-center p-8">
          <div className="w-full max-w-lg">
            <div className="flex items-center gap-3 mb-4">
              <Loader2 className="animate-spin" />
              <h2 className="text-lg font-semibold">
                {state.phase === 'starting' ? 'Starting local school server' : 'Preparing offline copy'}
              </h2>
            </div>
            <p className="text-sm text-white/80 mb-4">{state.progress?.message || 'Please wait…'}</p>
            <div className="h-2 rounded-full bg-white/15 overflow-hidden">
              <div className="h-full bg-emerald-300 transition-all" style={{ width: `${Math.min(100, pct)}%` }} />
            </div>
            {state.progress?.table && (
              <p className="mt-3 text-xs text-white/60">Table: {state.progress.table}</p>
            )}
          </div>
        </main>
        {status}
      </div>
    );
  }

  return (
    <div className="h-full flex flex-col bg-[#0f3d34]">
      {chrome}
      <div className="flex-1 bg-[#f4f7f6]" />
      {status}
    </div>
  );
}
