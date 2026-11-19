# Backup and Restore

This backup subsystem is isolated and does not change existing modules.

## What gets backed up
- MySQL database dump (`database.sql`)
- `storage/` directory (uploads/documents)
- `metadata.json` (timestamp/source info)

Archive location:
- `backups/backup_YYYYmmdd_HHMMSS.zip`

## Manual backup

CLI:
```bash
php tools/backup.php
php tools/backup.php --kind=weekly
```

Web (admin only):
- `POST /tools/backup.php`
- Requires valid session and CSRF token.
- Optional: `backup_kind=weekly` for weekly restore points.

Download backup (admin only):
- `GET /tools/backup_download.php?file=<backup_file>.zip`

Web restore (admin only, destructive):
- `POST /tools/restore_web.php`
- Required fields:
  - `csrf`
  - `backup_file`
  - `restore_storage` (`1` to restore storage files too)

## Restore

CLI only:
```bash
php tools/restore.php --file=backup_YYYYmmdd_HHMMSS.zip
php tools/restore.php --file=backup_YYYYmmdd_HHMMSS.zip --no-storage
```

## Automated daily backup (Windows)

Use Task Scheduler with:
- Program/script: `C:\xampp\php\php.exe`
- Add arguments: `C:\xampp\htdocs\sukaniggadih\newtypeshichap2\tools\backup.php`

Or run:
- `tools/backup_daily.bat`
- `tools/backup_weekly.bat`

## Notes
- Backup logs are stored in MySQL table `backup_logs` (auto-created).
- Retention keeps the latest 14 backup archives by default.
- You can override binaries with env vars:
  - `MYSQLDUMP_BIN`
  - `MYSQL_BIN`
