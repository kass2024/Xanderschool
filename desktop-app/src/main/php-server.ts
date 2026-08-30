import { spawn, type ChildProcess } from 'child_process';
import { createServer } from 'net';
import { appendFileSync } from 'fs';
import { join } from 'path';
import http from 'http';
import { findPhpExe, publicDir, rewriteScript, sqlitePath, writableDir } from './paths';

let phpProcess: ChildProcess | null = null;
let activePort = 0;

function findFreePort(start = 47821): Promise<number> {
  return new Promise((resolve, reject) => {
    const tryPort = (port: number) => {
      const srv = createServer();
      srv.unref();
      srv.on('error', () => {
        if (port > start + 40) reject(new Error('No free port found'));
        else tryPort(port + 1);
      });
      srv.listen(port, '127.0.0.1', () => {
        srv.close(() => resolve(port));
      });
    };
    tryPort(start);
  });
}

function waitForHttp(url: string, timeoutMs = 25000): Promise<void> {
  const start = Date.now();
  return new Promise((resolve, reject) => {
    const tick = () => {
      const req = http.get(url, (res) => {
        const code = res.statusCode ?? 0;
        res.resume();
        if (code >= 200 && code < 400) {
          resolve();
          return;
        }
        if (Date.now() - start > timeoutMs) {
          reject(new Error(`Local school server returned HTTP ${code} for ${url}`));
          return;
        }
        setTimeout(tick, 400);
      });
      req.on('error', () => {
        if (Date.now() - start > timeoutMs) {
          reject(new Error('Local school server did not start in time'));
          return;
        }
        setTimeout(tick, 400);
      });
      req.setTimeout(2000, () => {
        req.destroy();
      });
    };
    tick();
  });
}

export function localBaseUrl(): string | null {
  return activePort ? `http://127.0.0.1:${activePort}` : null;
}

export async function startPhpServer(): Promise<string> {
  await stopPhpServer();
  const php = findPhpExe();
  if (!php) {
    throw new Error(
      'PHP was not found. Install XAMPP PHP or run npm run fetch-php inside desktop-app, then retry.',
    );
  }
  const port = await findFreePort();
  const pub = publicDir();
  const router = rewriteScript();
  const args = ['-d', 'display_errors=0', '-S', `127.0.0.1:${port}`, '-t', pub, router];
  if (php.ini) args.unshift('-c', php.ini);

  const logFile = join(writableDir(), 'php-server.log');
  phpProcess = spawn(php.exe, args, {
    cwd: pub,
    windowsHide: true,
    env: {
      ...process.env,
      CI_ENVIRONMENT: 'production',
      XANDER_DESKTOP: '1',
      XANDER_SQLITE_PATH: sqlitePath(),
      XANDER_WRITEPATH: writableDir(),
      XANDER_BASE_URL: `http://127.0.0.1:${port}/`,
    },
  });
  const log = (buf: Buffer) => {
    try {
      appendFileSync(logFile, buf);
    } catch {
      /* ignore */
    }
  };
  phpProcess.stdout?.on('data', log);
  phpProcess.stderr?.on('data', log);

  phpProcess.on('exit', () => {
    if (activePort === port) {
      phpProcess = null;
      activePort = 0;
    }
  });

  activePort = port;
  const url = `http://127.0.0.1:${port}`;
  await waitForHttp(`${url}/login`);
  return url;
}

export async function stopPhpServer(): Promise<void> {
  const proc = phpProcess;
  phpProcess = null;
  activePort = 0;
  if (!proc) return;
  await new Promise<void>((resolve) => {
    const done = () => resolve();
    proc.once('exit', done);
    try {
      proc.kill();
    } catch {
      done();
    }
    setTimeout(() => {
      try {
        proc.kill('SIGKILL');
      } catch {
        /* ignore */
      }
      done();
    }, 1500);
  });
}
