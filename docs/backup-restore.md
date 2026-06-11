# Database backups & restore

## What runs
`php artisan app:backup-database` (scheduled nightly at **02:00 Asia/Kolkata**
via `routes/console.php`) dumps the MySQL database with `mysqldump`, gzips it,
and stores it on the local disk:

- **Daily:** `storage/app/backups/neds-YYYY-MM-DD-HHMMSS.sql.gz` — kept **14 days**.
- **Weekly:** `storage/app/backups/weekly/neds-YYYY-Www.sql.gz` (one per ISO
  week) — kept **8 weeks**.

A confirmation email is sent to `BACKUP_NOTIFY_EMAIL` when set.

## Configuration (.env)
```
DB_DUMP_BINARY=mysqldump        # full path if mysqldump isn't on PATH
BACKUP_NOTIFY_EMAIL=ops@example.com
```
The password is passed to `mysqldump` via the `MYSQL_PWD` environment variable,
so it never appears in the process list.

## Restore
1. Pick the backup file (newest daily, or a weekly snapshot).
2. Decompress and pipe it back into MySQL:

   ```bash
   gunzip < storage/app/backups/neds-2026-06-11-020000.sql.gz \
     | mysql -h 127.0.0.1 -u <user> -p <database>
   ```

   On Windows (dev), use the full mysql path under
   `C:\Program Files\MySQL\MySQL Server 8.4\bin\`.

3. After restore: `php artisan config:cache && php artisan migrate --force`
   (in case the dump predates a migration), then smoke-test login.

## Notes
- The backup covers the **database only**. Uploaded files live in
  `storage/app` and are part of the Hostinger account backup; copy them
  separately if you need point-in-time file recovery.
- To pull backups off-server, download the `storage/app/backups` folder via
  SFTP/hPanel on a schedule, or sync to external storage.
