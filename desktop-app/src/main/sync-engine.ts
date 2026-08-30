import { createRequire } from 'module';
import { sqlitePath } from './paths';
import {
  remotePull,
  remotePush,
  remoteSchema,
  type SchemaTable,
} from './remote-api';
import type { SyncProgress } from '../shared/types';

const require = createRequire(import.meta.url);

type Database = import('better-sqlite3').Database;

let db: Database | null = null;

function openDb(): Database {
  if (db) return db;
  const Database = require('better-sqlite3') as typeof import('better-sqlite3');
  db = new Database(sqlitePath());
  db.pragma('journal_mode = WAL');
  db.pragma('synchronous = NORMAL');
  db.pragma('busy_timeout = 8000');
  db.pragma('foreign_keys = OFF');
  db.pragma('temp_store = MEMORY');
  db.exec(`
    CREATE TABLE IF NOT EXISTS _sync_meta (k TEXT PRIMARY KEY, v TEXT);
    CREATE TABLE IF NOT EXISTS _sync_queue (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      table_name TEXT NOT NULL,
      row_pk TEXT,
      op TEXT NOT NULL,
      status TEXT NOT NULL DEFAULT 'pending',
      error TEXT,
      created_at TEXT NOT NULL
    );
  `);
  return db;
}

export function closeDb(): void {
  try {
    db?.close();
  } catch {
    /* ignore */
  }
  db = null;
}

function quoteIdent(name: string): string {
  return `"${name.replace(/"/g, '""')}"`;
}

function sqliteType(mysqlType: string, isPk: boolean): string {
  const t = (mysqlType || 'TEXT').toLowerCase();
  if (isPk && /int/.test(t)) return 'INTEGER';
  if (/tinyint|smallint|mediumint|bigint|int|year|bool/.test(t)) return 'INTEGER';
  if (/decimal|float|double|numeric|real/.test(t)) return 'REAL';
  if (/blob|binary/.test(t)) return 'BLOB';
  return 'TEXT';
}

function setApplying(conn: Database, on: boolean): void {
  conn.prepare(`INSERT INTO _sync_meta(k,v) VALUES('applying', ?) ON CONFLICT(k) DO UPDATE SET v=excluded.v`).run(
    on ? '1' : '0',
  );
}

function setMeta(conn: Database, k: string, v: string): void {
  conn.prepare(`INSERT INTO _sync_meta(k,v) VALUES(?,?) ON CONFLICT(k) DO UPDATE SET v=excluded.v`).run(k, v);
}

function getMeta(conn: Database, k: string): string | null {
  const row = conn.prepare(`SELECT v FROM _sync_meta WHERE k = ?`).get(k) as { v: string } | undefined;
  return row?.v ?? null;
}

function createTable(conn: Database, table: SchemaTable): void {
  if (!table.columns.length) return;
  const pkCols = table.columns.filter((c) => c.primary_key).map((c) => c.name);
  const hasId = table.columns.some((c) => c.name === 'id');
  const lines = table.columns.map((c) => {
    const pk = pkCols.length === 1 && c.primary_key;
    return `${quoteIdent(c.name)} ${sqliteType(c.type, pk)}${pk ? ' PRIMARY KEY' : ''}`;
  });
  conn.exec(`CREATE TABLE IF NOT EXISTS ${quoteIdent(table.name)} (${lines.join(', ')})`);
  if (hasId && pkCols.length === 0) {
    // already created without PK; leave as-is
  }
}

function installTriggers(conn: Database, table: SchemaTable): void {
  const pk = table.columns.find((c) => c.primary_key)?.name || (table.columns.some((c) => c.name === 'id') ? 'id' : '');
  if (!pk) return;
  const safe = table.name.replace(/[^a-zA-Z0-9_]/g, '');
  const when = `WHEN COALESCE((SELECT v FROM _sync_meta WHERE k = 'applying'), '0') != '1'`;
  conn.exec(`DROP TRIGGER IF EXISTS trg_${safe}_ai`);
  conn.exec(`DROP TRIGGER IF EXISTS trg_${safe}_au`);
  conn.exec(`DROP TRIGGER IF EXISTS trg_${safe}_ad`);
  conn.exec(`
    CREATE TRIGGER trg_${safe}_ai AFTER INSERT ON ${quoteIdent(table.name)}
    ${when}
    BEGIN
      INSERT INTO _sync_queue(table_name, row_pk, op, created_at)
      VALUES ('${table.name}', NEW.${quoteIdent(pk)}, 'upsert', datetime('now'));
    END;
  `);
  conn.exec(`
    CREATE TRIGGER trg_${safe}_au AFTER UPDATE ON ${quoteIdent(table.name)}
    ${when}
    BEGIN
      INSERT INTO _sync_queue(table_name, row_pk, op, created_at)
      VALUES ('${table.name}', NEW.${quoteIdent(pk)}, 'upsert', datetime('now'));
    END;
  `);
  conn.exec(`
    CREATE TRIGGER trg_${safe}_ad AFTER DELETE ON ${quoteIdent(table.name)}
    ${when}
    BEGIN
      INSERT INTO _sync_queue(table_name, row_pk, op, created_at)
      VALUES ('${table.name}', OLD.${quoteIdent(pk)}, 'delete', datetime('now'));
    END;
  `);
}

function upsertRows(conn: Database, table: SchemaTable, rows: Array<Record<string, unknown>>): void {
  if (!rows.length) return;
  const cols = table.columns.map((c) => c.name).filter((n) => Object.prototype.hasOwnProperty.call(rows[0], n) || true);
  const present = table.columns.map((c) => c.name);
  const names = present.map(quoteIdent).join(',');
  const placeholders = present.map((n) => `@${n}`).join(',');
  const pk = table.columns.find((c) => c.primary_key)?.name || 'id';
  const updates = present
    .filter((n) => n !== pk)
    .map((n) => `${quoteIdent(n)}=excluded.${quoteIdent(n)}`)
    .join(',');
  const sql =
    updates.length > 0
      ? `INSERT INTO ${quoteIdent(table.name)} (${names}) VALUES (${placeholders}) ON CONFLICT(${quoteIdent(pk)}) DO UPDATE SET ${updates}`
      : `INSERT OR REPLACE INTO ${quoteIdent(table.name)} (${names}) VALUES (${placeholders})`;
  const stmt = conn.prepare(sql);
  const tx = conn.transaction((batch: Array<Record<string, unknown>>) => {
    for (const row of batch) {
      const params: Record<string, unknown> = {};
      for (const n of present) {
        const v = row[n];
        params[n] = v === undefined ? null : v;
      }
      stmt.run(params);
    }
  });
  tx(rows);
}

export function pendingCount(): number {
  try {
    const conn = openDb();
    const row = conn.prepare(`SELECT COUNT(*) AS c FROM _sync_queue WHERE status = 'pending'`).get() as { c: number };
    return row.c;
  } catch {
    return 0;
  }
}

export async function initialSync(
  remoteUrl: string,
  token: string,
  onProgress: (p: SyncProgress) => void,
): Promise<void> {
  const conn = openDb();
  onProgress({ stage: 'schema', current: 0, total: 1, message: 'Downloading school schema…' });
  const schema = await remoteSchema(remoteUrl, token);
  const tables = schema.tables.filter((t) => t.name && !t.name.startsWith('_'));
  setApplying(conn, true);
  tables.forEach((t, i) => {
    onProgress({
      stage: 'schema',
      table: t.name,
      current: i + 1,
      total: tables.length,
      message: `Preparing ${t.name}`,
    });
    createTable(conn, t);
  });

  for (let i = 0; i < tables.length; i++) {
    const table = tables[i];
    let afterId = 0;
    let pulled = 0;
    onProgress({
      stage: 'pull',
      table: table.name,
      current: i + 1,
      total: tables.length,
      message: `Syncing ${table.name}…`,
    });
    // eslint-disable-next-line no-constant-condition
    while (true) {
      const page = await remotePull(remoteUrl, token, table.name, afterId);
      if (!page.ok) throw new Error(page.error || `Failed to pull ${table.name}`);
      if (page.rows?.length) {
        upsertRows(conn, table, page.rows);
        pulled += page.rows.length;
      }
      afterId = page.next_after_id || afterId;
      onProgress({
        stage: 'pull',
        table: table.name,
        current: i + 1,
        total: tables.length,
        message: `Syncing ${table.name} (${pulled} rows)`,
      });
      if (!page.has_more || !page.rows?.length) break;
    }
    installTriggers(conn, table);
  }

  const now = new Date().toISOString();
  setMeta(conn, 'last_pull', now);
  setMeta(conn, 'last_full_sync', now);
  setApplying(conn, false);
}

export async function incrementalSync(
  remoteUrl: string,
  token: string,
  onProgress?: (p: SyncProgress) => void,
): Promise<{ pushed: number; pulled: number }> {
  const conn = openDb();
  let pushed = 0;
  let pulled = 0;

  const pending = conn
    .prepare(`SELECT id, table_name, row_pk, op FROM _sync_queue WHERE status = 'pending' ORDER BY id LIMIT 200`)
    .all() as Array<{ id: number; table_name: string; row_pk: string; op: string }>;

  if (pending.length) {
    onProgress?.({
      stage: 'push',
      current: 0,
      total: pending.length,
      message: `Uploading ${pending.length} local change(s)…`,
    });
    const changes = pending.map((item) => {
      if (item.op === 'delete') {
        return { table: item.table_name, op: 'delete', pk: item.row_pk };
      }
      let row: Record<string, unknown> = { id: item.row_pk };
      try {
        const found = conn.prepare(`SELECT * FROM ${quoteIdent(item.table_name)} WHERE id = ?`).get(item.row_pk) as
          | Record<string, unknown>
          | undefined;
        if (found) row = found;
      } catch {
        /* table may use a different pk */
      }
      return { table: item.table_name, op: 'upsert', pk: item.row_pk, row };
    });
    const res = await remotePush(remoteUrl, token, changes);
    const mark = conn.prepare(`UPDATE _sync_queue SET status = 'synced' WHERE id = ?`);
    const tx = conn.transaction(() => {
      for (const item of pending) mark.run(item.id);
    });
    tx();
    pushed = res.applied ?? pending.length;
    setMeta(conn, 'last_push', new Date().toISOString());
  }

  const schema = await remoteSchema(remoteUrl, token);
  const tables = schema.tables.filter((t) => t.name && !t.name.startsWith('_'));
  const since = getMeta(conn, 'last_pull') || '';
  setApplying(conn, true);
  try {
    for (let i = 0; i < tables.length; i++) {
      const table = tables[i];
      createTable(conn, table);
      let afterId = 0;
      onProgress?.({
        stage: 'pull',
        table: table.name,
        current: i + 1,
        total: tables.length,
        message: `Refreshing ${table.name}…`,
      });
      // eslint-disable-next-line no-constant-condition
      while (true) {
        const page = await remotePull(remoteUrl, token, table.name, afterId, since || undefined);
        if (!page.ok) break;
        if (page.rows?.length) {
          upsertRows(conn, table, page.rows);
          pulled += page.rows.length;
        }
        afterId = page.next_after_id || afterId;
        if (!page.has_more || !page.rows?.length) break;
      }
      installTriggers(conn, table);
    }
  } finally {
    setApplying(conn, false);
  }
  setMeta(conn, 'last_pull', new Date().toISOString());
  return { pushed, pulled };
}
