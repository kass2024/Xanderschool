import { createRequire } from 'module';
import { existsSync, readFileSync, writeFileSync } from 'fs';
import { profileDir, sqlitePath } from './paths';
import {
  remoteIds,
  remoteProfilePhoto,
  remotePull,
  remotePush,
  remoteSchema,
  type SchemaTable,
} from './remote-api';
import type { SyncProgress } from '../shared/types';

const require = createRequire(import.meta.url);

type Database = import('better-sqlite3').Database;

let db: Database | null = null;

async function syncProfilePhotos(
  remoteUrl: string,
  token: string,
  names: Set<string>,
  onProgress?: (p: SyncProgress) => void,
): Promise<void> {
  const validNames = [...names].filter((name) => {
    const safe = name.trim();
    return safe !== '' && safe === safe.replace(/[^a-zA-Z0-9._-]/g, '') && safe !== '.' && safe !== '..';
  });
  let completed = 0;
  for (const name of validNames) {
    const destination = `${profileDir()}/${name}`;
    if (!existsSync(destination)) {
      const image = await remoteProfilePhoto(remoteUrl, token, name);
      if (image && image.length > 0) writeFileSync(destination, image);
    }
    completed += 1;
    onProgress?.({
      stage: 'pull',
      current: completed,
      total: validNames.length,
      message: `Syncing student photos (${completed}/${validNames.length})`,
    });
  }
}

function openDb(): Database {
  if (db) return db;
  const Database = require('better-sqlite3') as typeof import('better-sqlite3');
  db = new Database(sqlitePath());
  db.pragma('journal_mode = WAL');
  db.pragma('synchronous = NORMAL');
  db.pragma('busy_timeout = 60000');
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
  setApplying(db, false);
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

function tablePk(table: SchemaTable): string {
  return table.columns.find((c) => c.primary_key)?.name || table.columns.find((c) => c.name === 'id')?.name || '';
}

function createTable(conn: Database, table: SchemaTable): void {
  if (!table.columns.length) return;
  const pkCols = table.columns.filter((c) => c.primary_key).map((c) => c.name);
  const lines = table.columns.map((c) => {
    const pk = pkCols.length === 1 && c.primary_key;
    return `${quoteIdent(c.name)} ${sqliteType(c.type, pk)}${pk ? ' PRIMARY KEY' : ''}`;
  });
  const exists = conn
    .prepare(`SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?`)
    .get(table.name);
  if (!exists) {
    conn.exec(`CREATE TABLE ${quoteIdent(table.name)} (${lines.join(', ')})`);
    return;
  }

  const existing = new Set(
    (conn.prepare(`PRAGMA table_info(${quoteIdent(table.name)})`).all() as Array<{ name: string }>).map(
      (column) => column.name,
    ),
  );
  for (const column of table.columns) {
    if (!existing.has(column.name)) {
      conn.exec(`ALTER TABLE ${quoteIdent(table.name)} ADD COLUMN ${quoteIdent(column.name)} ${sqliteType(column.type, false)}`);
    }
  }
}

function installTriggers(conn: Database, table: SchemaTable): void {
  const pk = tablePk(table);
  if (!pk) return;
  const safe = table.name.replace(/[^a-zA-Z0-9_]/g, '');
  if (table.writable === false) {
    conn.exec(`DROP TRIGGER IF EXISTS trg_${safe}_ai`);
    conn.exec(`DROP TRIGGER IF EXISTS trg_${safe}_au`);
    conn.exec(`DROP TRIGGER IF EXISTS trg_${safe}_ad`);
    return;
  }
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
  const present = table.columns.map((c) => c.name);
  const names = present.map(quoteIdent).join(',');
  const placeholders = present.map((n) => `@${n}`).join(',');
  const pk = tablePk(table);
  const updateColumns = present.filter((name) => name !== pk);
  const update = pk && updateColumns.length
    ? conn.prepare(
        `UPDATE ${quoteIdent(table.name)} SET ${updateColumns
          .map((name) => `${quoteIdent(name)} = @${name}`)
          .join(', ')} WHERE ${quoteIdent(pk)} = @__pk`,
      )
    : null;
  const insert = conn.prepare(`INSERT OR REPLACE INTO ${quoteIdent(table.name)} (${names}) VALUES (${placeholders})`);
  const tx = conn.transaction((batch: Array<Record<string, unknown>>) => {
    for (const row of batch) {
      const params: Record<string, unknown> = {};
      for (const n of present) {
        const v = row[n];
        params[n] = v === undefined ? null : v;
      }
      const updated = pk && update ? update.run({ ...params, __pk: params[pk] }).changes > 0 : false;
      if (!updated) insert.run(params);
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

function syncTables(schema: { tables: SchemaTable[] }): SchemaTable[] {
  const tables = schema.tables.filter((table) => table.name && !table.name.startsWith('_') && table.columns.length > 0);
  const fallback: Record<string, number> = {
    students: 1,
    classes: 2,
    staffs: 3,
    fees_records: 7,
    school_fees: 5,
    extra_fees: 6,
    cash_requests: 8,
    required_materials: 14,
    class_required_materials: 15,
    student_material_checks: 16,
    hostels: 17,
    hostel_allocations: 18,
  };
  return tables.sort((a, b) => {
    const pa = a.priority ?? fallback[a.name] ?? 100;
    const pb = b.priority ?? fallback[b.name] ?? 100;
    return pa - pb || a.name.localeCompare(b.name);
  });
}

function shouldReconcileDeletes(table: SchemaTable): boolean {
  // Never auto-delete writable business records from the offline app during a full refresh.
  // Pull/upsert still keeps remote edits flowing down, but destructive reconcile is limited
  // to shared read-only reference data so locally entered records are not lost.
  return table.writable === false;
}

function shouldUseLiveFullPull(table: SchemaTable): boolean {
  // Legacy school data is not consistent about touching updated_at on every edit.
  // For writable tenant tables, use a stronger pull path during background sync so
  // remote edits show up on the desktop even when timestamp-based deltas are incomplete.
  return table.writable !== false && !isGlobalTable(table);
}

const GLOBAL_TABLES = new Set([
  'packages',
  'posts',
  'faculty',
  'levels',
  'countries',
  'provinces',
  'districts',
  'sectors',
  'cells',
  'villages',
  'soma_cell',
  'soma_village',
  'ubudehe',
  'permissions',
  'type_permission',
  'master_central_posts',
  'course_category',
  'budget_permissions',
  'post_budget_permissions',
  'schools',
]);

function isGlobalTable(table: SchemaTable): boolean {
  return GLOBAL_TABLES.has(table.name);
}

function schoolList(conn: Database): Array<{ id: number; name: string }> {
  try {
    return conn
      .prepare(`SELECT id, name FROM "schools" ORDER BY name ASC`)
      .all() as Array<{ id: number; name: string }>;
  } catch {
    return [];
  }
}

async function pullFullTable(
  conn: Database,
  remoteUrl: string,
  token: string,
  table: SchemaTable,
  progress: { current: number; total: number; label: string },
  onProgress: (p: SyncProgress) => void,
  scopeSchoolId?: number,
): Promise<void> {
  let afterId = 0;
  let pulled = 0;
  const photoNames = new Set<string>();
  onProgress({
    stage: 'pull',
    table: table.name,
    current: progress.current,
    total: progress.total,
    message: `${progress.label}: ${table.name}`,
  });
  while (true) {
    const page = await remotePull(remoteUrl, token, table.name, afterId, undefined, true, scopeSchoolId);
    if (!page.ok) throw new Error(page.error || `Failed to pull ${table.name}`);
    if (page.rows?.length) {
      upsertRows(conn, table, page.rows);
      pulled += page.rows.length;
      if (table.name === 'students') {
        for (const row of page.rows) {
          if (typeof row.photo === 'string') photoNames.add(row.photo);
        }
      }
    }
    afterId = page.next_after_id || afterId;
    onProgress({
      stage: 'pull',
      table: table.name,
      current: progress.current,
      total: progress.total,
      message: `${progress.label}: ${table.name} (${pulled} rows)`,
    });
    if (!page.has_more || !page.rows?.length) break;
  }
  installTriggers(conn, table);
  if (table.name === 'students' && photoNames.size) {
    await syncProfilePhotos(remoteUrl, token, photoNames, (p) =>
      onProgress({
        ...p,
        current: progress.current,
        total: progress.total,
        message: `${progress.label}: ${p.message}`,
      }),
    );
  }
}

function resetLocalData(conn: Database): void {
  const objects = conn
    .prepare(
      `SELECT type, name FROM sqlite_master
       WHERE (type = 'table' AND name NOT LIKE '_sync_%' AND name NOT LIKE 'sqlite_%')
          OR (type = 'trigger' AND name LIKE 'trg_%')`,
    )
    .all() as Array<{ type: string; name: string }>;
  for (const object of objects) {
    conn.exec(`DROP ${object.type === 'trigger' ? 'TRIGGER' : 'TABLE'} IF EXISTS ${quoteIdent(object.name)}`);
  }
  conn.exec(`DELETE FROM _sync_queue; DELETE FROM _sync_meta;`);
}

type PendingItem = {
  id: number;
  table_name: string;
  row_pk: string;
  op: string;
};

function readPending(conn: Database, limit = 400): PendingItem[] {
  return conn
    .prepare(
      `SELECT id, table_name, row_pk, op FROM _sync_queue
       WHERE status = 'pending' ORDER BY id LIMIT ${Math.max(1, Math.floor(limit))}`,
    )
    .all() as PendingItem[];
}

function localRow(conn: Database, table: SchemaTable, rowPk: string): Record<string, unknown> | undefined {
  const pk = tablePk(table);
  if (!pk) return undefined;
  return conn
    .prepare(`SELECT * FROM ${quoteIdent(table.name)} WHERE ${quoteIdent(pk)} = ?`)
    .get(rowPk) as Record<string, unknown> | undefined;
}

async function pushPending(
  conn: Database,
  remoteUrl: string,
  token: string,
  tables: SchemaTable[],
  onProgress?: (p: SyncProgress) => void,
): Promise<number> {
  const pending = readPending(conn);
  if (!pending.length) return 0;

  const byKey = new Map<string, { latest: PendingItem; items: PendingItem[] }>();
  for (const item of pending) {
    const key = `${item.table_name}\u0000${item.row_pk}`;
    const group = byKey.get(key);
    if (group) {
      group.latest = item;
      group.items.push(item);
    } else {
      byKey.set(key, { latest: item, items: [item] });
    }
  }

  const tableMap = new Map(tables.map((table) => [table.name, table]));
  const groups = [...byKey.values()].filter((group) => tableMap.has(group.latest.table_name));
  const ignored = groups.filter((group) => tableMap.get(group.latest.table_name)?.writable === false);
  if (ignored.length) {
    const markIgnored = conn.prepare(`UPDATE _sync_queue SET status = 'ignored', error = ? WHERE id = ?`);
    conn.transaction(() => {
      for (const group of ignored) {
        for (const item of group.items) markIgnored.run('Table is read-only for desktop sync', item.id);
      }
    })();
  }
  const writableGroups = groups.filter((group) => tableMap.get(group.latest.table_name)?.writable !== false);
  if (!writableGroups.length) return 0;
  const changes = writableGroups.map((group) => {
    const table = tableMap.get(group.latest.table_name)!;
    const op = group.latest.op === 'delete' ? 'delete' : 'upsert';
    if (op === 'delete') {
      return { table: table.name, op, pk: group.latest.row_pk };
    }
    const row = localRow(conn, table, group.latest.row_pk);
    const change: { table: string; op: string; pk: string; row: Record<string, unknown>; photo_base64?: string } = {
      table: table.name,
      op,
      pk: group.latest.row_pk,
      row: row || { [tablePk(table)]: group.latest.row_pk },
    };
    if (table.name === 'students' && typeof change.row.photo === 'string') {
      const name = change.row.photo.trim();
      if (name && name === name.replace(/[^a-zA-Z0-9._-]/g, '')) {
        try {
          const file = `${profileDir()}/${name}`;
          if (existsSync(file)) change.photo_base64 = readFileSync(file).toString('base64');
        } catch {
          /* the row can still sync without its optional asset */
        }
      }
    }
    return change;
  });

  onProgress?.({
    stage: 'push',
    current: 0,
    total: changes.length,
    message: `Uploading ${changes.length} local change(s)…`,
  });
  const result = await remotePush(remoteUrl, token, changes);
  if (!result.ok) throw new Error('Remote server rejected the local changes.');

  const failed = new Map<number, string>();
  for (const error of result.errors || []) {
    if (typeof error === 'object' && error !== null && 'index' in error) {
      const index = Number(error.index);
      if (Number.isInteger(index)) {
        failed.set(
          index,
          'message' in error && typeof error.message === 'string'
            ? error.message
            : 'error' in error && typeof error.error === 'string'
              ? error.error
              : 'Remote rejected change',
        );
      }
    }
  }
  const successful = writableGroups.filter((_group, index) => !failed.has(index));
  const markSynced = conn.prepare(`UPDATE _sync_queue SET status = 'synced', error = NULL WHERE id = ?`);
  const markFailed = conn.prepare(`UPDATE _sync_queue SET status = 'pending', error = ? WHERE id = ?`);
  conn.transaction(() => {
    writableGroups.forEach((group, index) => {
      for (const item of group.items) {
        if (failed.has(index)) markFailed.run(failed.get(index), item.id);
        else markSynced.run(item.id);
      }
    });
  })();

  if (successful.length) setMeta(conn, 'last_push', new Date().toISOString());
  if (failed.size) {
    onProgress?.({
      stage: 'push',
      current: successful.length,
      total: changes.length,
      message: `${successful.length} change(s) uploaded; ${failed.size} will retry`,
    });
  }
  return typeof result.applied === 'number' ? result.applied : successful.length;
}

async function reconcileDeletes(
  conn: Database,
  remoteUrl: string,
  token: string,
  table: SchemaTable,
): Promise<number> {
  const pk = tablePk(table);
  const pkColumn = table.columns.find((column) => column.name === pk);
  if (!pk || !pkColumn || !/int|decimal|numeric|bigint/i.test(pkColumn.type)) return 0;

  const remote = new Set<string>();
  let afterId = 0;
  while (true) {
    const page = await remoteIds(remoteUrl, token, table.name, afterId);
    if (!page.ok) throw new Error(`Failed to reconcile ${table.name}`);
    if (page.skipped) return 0;
    for (const id of page.ids || []) remote.add(String(id));
    afterId = page.next_after_id || afterId;
    if (!page.has_more || !(page.ids || []).length) break;
  }

  const pendingKeys = new Set(
    readPending(conn, 100000)
      .filter((item) => item.table_name === table.name)
      .map((item) => item.row_pk),
  );
  const local = conn
    .prepare(`SELECT ${quoteIdent(pk)} AS row_pk FROM ${quoteIdent(table.name)}`)
    .all() as Array<{ row_pk: string | number }>;
  const remove = conn.prepare(`DELETE FROM ${quoteIdent(table.name)} WHERE ${quoteIdent(pk)} = ?`);
  let deleted = 0;
  for (const row of local) {
    const id = String(row.row_pk);
    if (!remote.has(id) && !pendingKeys.has(id)) {
      deleted += remove.run(row.row_pk).changes;
    }
  }
  return deleted;
}

export async function initialSync(
  remoteUrl: string,
  token: string,
  onProgress: (p: SyncProgress) => void,
): Promise<void> {
  const conn = openDb();
  onProgress({ stage: 'schema', current: 0, total: 1, message: 'Downloading school schema…' });
  const schema = await remoteSchema(remoteUrl, token);
  const tables = syncTables(schema);
  resetLocalData(conn);
  setApplying(conn, true);
  try {
    tables.forEach((table, index) => {
      onProgress({
        stage: 'schema',
        table: table.name,
        current: index + 1,
        total: tables.length,
        message: `Preparing ${table.name}`,
      });
      createTable(conn, table);
    });
    const schoolTable = tables.find((table) => table.name === 'schools');
    const globalTables = tables.filter((table) => table !== schoolTable && isGlobalTable(table));
    const tenantTables = tables.filter((table) => table !== schoolTable && !isGlobalTable(table));
    const schoolsProgressBase = globalTables.length + (schoolTable ? 1 : 0);

    if (schoolTable) {
      await pullFullTable(conn, remoteUrl, token, schoolTable, {
        current: 1,
        total: Math.max(1, schoolsProgressBase),
        label: 'Syncing schools list',
      }, onProgress);
    }

    for (let i = 0; i < globalTables.length; i++) {
      await pullFullTable(conn, remoteUrl, token, globalTables[i], {
        current: (schoolTable ? 1 : 0) + i + 1,
        total: Math.max(1, schoolsProgressBase),
        label: 'Syncing shared data',
      }, onProgress);
    }

    const schools = schoolList(conn);
    const totalSchoolSteps = Math.max(1, schools.length * Math.max(1, tenantTables.length));
    let schoolStep = 0;
    for (const school of schools) {
      for (const table of tenantTables) {
        schoolStep += 1;
        await pullFullTable(conn, remoteUrl, token, table, {
          current: schoolStep,
          total: totalSchoolSteps,
          label: `School ${schoolStep > 0 ? Math.ceil(schoolStep / Math.max(1, tenantTables.length)) : 1}/${Math.max(1, schools.length)} - ${school.name}`,
        }, onProgress, school.id);
      }
    }

    const now = new Date().toISOString();
    setMeta(conn, 'last_pull', now);
    setMeta(conn, 'last_full_sync', now);
  } finally {
    setApplying(conn, false);
  }
}

export async function incrementalSync(
  remoteUrl: string,
  token: string,
  onProgress?: (p: SyncProgress) => void,
  full = false,
): Promise<{ pushed: number; pulled: number }> {
  const conn = openDb();
  let pushed = 0;
  let pulled = 0;

  const schema = await remoteSchema(remoteUrl, token);
  const tables = syncTables(schema);
  pushed = await pushPending(conn, remoteUrl, token, tables, onProgress);
  const since = getMeta(conn, 'last_pull') || '';
  setApplying(conn, true);
  try {
    for (let i = 0; i < tables.length; i++) {
      const table = tables[i];
      const forceFullPull = full || shouldUseLiveFullPull(table);
      createTable(conn, table);
      const hasTimestamp = table.columns.some((column) => column.name === 'updated_at' || column.name === 'created_at');
      const highWaterKey = `after_id:${table.name}`;
      let afterId = forceFullPull ? 0 : Number(getMeta(conn, highWaterKey) || 0);
      const photoNames = new Set<string>();
      onProgress?.({
        stage: 'pull',
        table: table.name,
        current: i + 1,
        total: tables.length,
        message: `${forceFullPull ? 'Refreshing' : 'Checking'} ${table.name}…`,
      });
      while (true) {
        const page = await remotePull(
          remoteUrl,
          token,
          table.name,
          afterId,
          forceFullPull || !hasTimestamp ? undefined : since || undefined,
          forceFullPull,
        );
        if (!page.ok) throw new Error(page.error || `Failed to pull ${table.name}`);
        if (page.rows?.length) {
          upsertRows(conn, table, page.rows);
          pulled += page.rows.length;
          if (table.name === 'students') {
            for (const row of page.rows) {
              if (typeof row.photo === 'string') photoNames.add(row.photo);
            }
          }
        }
        afterId = page.next_after_id || afterId;
        if (!forceFullPull && !hasTimestamp && afterId > 0) setMeta(conn, highWaterKey, String(afterId));
        if (!page.has_more || !page.rows?.length) break;
      }
      installTriggers(conn, table);
      if (table.name === 'students' && photoNames.size) {
        await syncProfilePhotos(remoteUrl, token, photoNames, onProgress);
      }
      if (forceFullPull && shouldReconcileDeletes(table)) await reconcileDeletes(conn, remoteUrl, token, table);
    }
  } finally {
    setApplying(conn, false);
  }
  // Second push after pull — heals cases where local finance/material totals were ahead
  const pushedAgain = await pushPending(conn, remoteUrl, token, tables, onProgress);
  pushed += pushedAgain;
  const now = new Date().toISOString();
  setMeta(conn, 'last_pull', now);
  if (full) setMeta(conn, 'last_full_sync', now);
  return { pushed, pulled };
}
