import { net } from 'electron';

export interface RemoteLoginResult {
  ok: boolean;
  token?: string;
  expires_at?: string;
  staff?: { id: number; name: string; email: string; post_title: string };
  school?: { id: number; name: string };
  error?: string;
}

export interface SchemaTable {
  name: string;
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
        try {
          resolve(JSON.parse(text) as T);
        } catch {
          reject(new Error(text.slice(0, 240) || `HTTP ${res.statusCode}`));
        }
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
): Promise<{
  ok: boolean;
  table: string;
  pk: string;
  count: number;
  rows: Array<Record<string, unknown>>;
  next_after_id: number;
  has_more: boolean;
  error?: string;
}> {
  const q = new URLSearchParams({
    table,
    after_id: String(afterId),
    limit: '400',
  });
  if (updatedSince) q.set('updated_since', updatedSince);
  return requestJson(
    'GET',
    `${normalizeBase(base)}/api/desktop/pull?${q.toString()}`,
    undefined,
    token,
    120000,
  );
}

export async function remotePush(
  base: string,
  token: string,
  changes: Array<{ table: string; op: string; pk?: unknown; row?: Record<string, unknown> }>,
): Promise<{ ok: boolean; applied: number; errors: unknown[] }> {
  return requestJson('POST', `${normalizeBase(base)}/api/desktop/push`, { changes }, token, 120000);
}
