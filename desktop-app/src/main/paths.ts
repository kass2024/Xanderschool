import { app } from 'electron';
import { existsSync, mkdirSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

const here = dirname(fileURLToPath(import.meta.url));

export function isPackaged(): boolean {
  return app.isPackaged;
}

export function schoolRoot(): string {
  if (app.isPackaged) {
    return join(process.resourcesPath, 'school');
  }
  return join(here, '../../..');
}

export function publicDir(): string {
  return join(schoolRoot(), 'public');
}

export function rewriteScript(): string {
  return join(schoolRoot(), 'system', 'Commands', 'Server', 'rewrite.php');
}

export function userDataDir(): string {
  return app.getPath('userData');
}

export function dataDir(): string {
  const dir = join(userDataDir(), 'data');
  if (!existsSync(dir)) mkdirSync(dir, { recursive: true });
  return dir;
}

export function writableDir(): string {
  const dir = join(userDataDir(), 'writable');
  if (!existsSync(dir)) mkdirSync(dir, { recursive: true });
  for (const sub of ['cache', 'logs', 'session', 'uploads', 'desktop']) {
    const p = join(dir, sub);
    if (!existsSync(p)) mkdirSync(p, { recursive: true });
  }
  return dir;
}

export function sqlitePath(): string {
  return join(dataDir(), 'xander-school.db');
}

export function settingsPath(): string {
  return join(userDataDir(), 'desktop-settings.json');
}

export function findPhpExe(): { exe: string; ini?: string } | null {
  const bundled = join(
    app.isPackaged ? process.resourcesPath : join(here, '../..'),
    'php-runtime',
    'php.exe',
  );
  const bundledIni = join(dirname(bundled), 'php.ini');
  if (existsSync(bundled)) {
    return { exe: bundled, ini: existsSync(bundledIni) ? bundledIni : undefined };
  }

  const candidates = [
    'C:\\xampp7\\php\\php.exe',
    'C:\\xampp\\php\\php.exe',
    'C:\\php\\php.exe',
  ];
  for (const c of candidates) {
    if (existsSync(c)) return { exe: c };
  }
  return null;
}
