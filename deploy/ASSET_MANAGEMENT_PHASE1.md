# Asset Management — Phase 1

## Stack
- CodeIgniter 4 + PHP 7.4, MySQLi, jQuery/DataTables views
- Session auth (`soma_school_id`), menu clearance (`MenuClearance`)

## What shipped (Phase 1)
- Top-level **Asset Management** menu; Library URLs unchanged under it
- Tables: `asset_locations`, `asset_categories`, `asset_category_fields`, `assets`, `asset_status_history`, `asset_settings`
- Runtime `ensureSchema()` + per-school seed of default categories/locations
- Screens: Dashboard, Assets (CRUD + history), Locations (tree/archive), Categories (+ custom fields), Settings
- Later-phase menu items show placeholder pages

## Backward compatibility
- `/book_management`, `/borrowed_report` unchanged
- Legacy `library` menu group retained in MenuClearance for existing clearance rows
- No automatic conversion of library books into fixed assets

## Permissions (defaults)
- Full access posts 1, 3, 18: all keys
- Posts 7 (Secretary), 12 (Store keeper), 13 (Librarian): Asset Management + Library
- Re-save Level clearance for posts that already have custom DB overrides

## Next phases
2 Import Excel · 3 Custody/RFID checkout · 4 Transfers/maintenance/audits · 5 Finance/reports · 6 AI assists
