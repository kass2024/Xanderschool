import { net } from 'electron';

export interface RemoteLoginResult {
  ok: boolean;
  token?: string;
  expires_at?: string;
  full_sync?: boolean;
  staff?: { id: number; name: string; email: string; post_title: string };
  school?: { id: number; name: string };
  error?: string;
}

export interface SchemaTable {
  name: string;
  writable?: boolean;
  priority?: number;
  columns: Array<{
    name: string;
    type: string;
    max_length?: number | null;
    nullable?: boolean;
    default?: unknown;
    primary_key?: boolean;
  }>;
}

function normalizeBase(url: string): string {
  return url.replace(/\/+$/, '');
}

function requestJson<T>(
  method: string,
  url: string,
  body?: unknown,
  token?: string,
  timeoutMs = 45000,
): Promise<T> {
  return new Promise((resolve, reject) => {
    const req = net.request({ method, url });
    req.setHeader('Accept', 'application/json');
    if (token) req.setHeader('Authorization', `Bearer ${token}`);
    if (body !== undefined) req.setHeader('Content-Type', 'application/json');

    const timer = setTimeout(() => {
      req.abort();
      reject(new Error('Request timed out'));
    }, timeoutMs);

    req.on('response', (res) => {
      const chunks: Buffer[] = [];
      res.on('data', (c) => chunks.push(Buffer.isBuffer(c) ? c : Buffer.from(c)));
      res.on('end', () => {
        clearTimeout(timer);
        const text = Buffer.concat(chunks).toString('utf-8');
        let parsed: unknown;
        try {
          parsed = JSON.parse(text);
        } catch {
          reject(new Error(text.slice(0, 240) || `HTTP ${res.statusCode}`));
          return;
        }
        if ((res.statusCode ?? 500) < 200 || (res.statusCode ?? 500) >= 300) {
          const message =
            typeof parsed === 'object' &&
            parsed !== null &&
            'error' in parsed &&
            typeof parsed.error === 'string'
              ? parsed.error
              : `Remote server returned HTTP ${res.statusCode}`;
          reject(new Error(message));
          return;
        }
        resolve(parsed as T);
      });
    });
    req.on('error', (err) => {
      clearTimeout(timer);
      reject(err);
    });
    if (body !== undefined) req.write(JSON.stringify(body));
    req.end();
  });
}

export async function remoteHealth(base: string): Promise<boolean> {
  try {
    const data = await requestJson<{ ok?: boolean }>(
      'GET',
      `${normalizeBase(base)}/api/desktop/health`,
      undefined,
      undefined,
      8000,
    );
    return !!data.ok;
  } catch {
    return false;
  }
}

export async function remoteLogin(
  base: string,
  email: string,
  password: string,
  deviceName: string,
): Promise<RemoteLoginResult> {
  return requestJson<RemoteLoginResult>('POST', `${normalizeBase(base)}/api/desktop/login`, {
    email,
    password,
    device_name: deviceName,
    full_sync: 1,
  });
}

export async function remoteSchema(base: string, token: string): Promise<{ tables: SchemaTable[] }> {
  return requestJson('GET', `${normalizeBase(base)}/api/desktop/schema`, undefined, token, 120000);
}

export async function remotePull(
  base: string,
  token: string,
  table: string,
  afterId: number,
  updatedSince?: string,
  full = false,
): Promise<{
  ok: boolean;
  table: string;
  pk: string;
  count: number;
  rows: Array<Record<string, unknown>>;
  next_after_id: number;
  has_more: boolean;
  skipped?: boolean;
  error?: string;
}> {
  const q = new URLSearchParams({
    table,
    after_id: String(afterId),
    limit: '400',
  });
  if (full) q.set('full', '1');
  else if (updatedSince) q.set('updated_since', updatedSince);
  return requestJson(
    'GET',
    `${normalizeBase(base)}/api/desktop/pull?${q.toString()}`,
    undefined,
    token,
    120000,
  );
}

export async function remoteIds(
  base: string,
  token: string,
  table: string,
  afterId: number,
): Promise<{
  ok: boolean;
  table: string;
  pk: string;
  ids: Array<string | number>;
  next_after_id: number;
  has_more: boolean;
  skipped?: boolean;
}> {
  const q = new URLSearchParams({
    table,
    after_id: String(afterId),
    limit: '2000',
  });
  return requestJson(
    'GET',
    `${normalizeBase(base)}/api/desktop/ids?${q.toString()}`,
    undefined,
    token,
    120000,
  );
}

export async function remotePush(
  base: string,
  token: string,
  changes: Array<{ table: string; op: string; pk?: unknown; row?: Record<string, unknown>; photo_base64?: string }>,
): Promise<{ ok: boolean; applied: number; errors: unknown[] }> {
  return requestJson('POST', `${normalizeBase(base)}/api/desktop/push`, { changes }, token, 120000);
}

export async function remoteProfilePhoto(base: string, token: string, name: string): Promise<Buffer | null> {
  return new Promise((resolve, reject) => {
    const q = new URLSearchParams({ name });
    const req = net.request({
      method: 'GET',
      url: `${normalizeBase(base)}/api/desktop/photo?${q.toString()}`,
    });
    req.setHeader('Accept', 'image/jpeg,image/png');
    req.setHeader('Authorization', `Bearer ${token}`);
    const timer = setTimeout(() => {
      req.abort();
      reject(new Error('Photo request timed out'));
    }, 120000);
    req.on('response', (res) => {
      const chunks: Buffer[] = [];
      res.on('data', (chunk) => chunks.push(Buffer.isBuffer(chunk) ? chunk : Buffer.from(chunk)));
      res.on('end', () => {
        clearTimeout(timer);
        if (res.statusCode === 404) {
          resolve(null);
          return;
        }
        if ((res.statusCode ?? 500) < 200 || (res.statusCode ?? 500) >= 300) {
          reject(new Error(`Remote photo returned HTTP ${res.statusCode}`));
          return;
        }
        resolve(Buffer.concat(chunks));
      });
    });
    req.on('error', (error) => {
      clearTimeout(timer);
      reject(error);
    });
    req.end();
  });
}
