<?php

namespace App\Console\Commands;

use App\Support\PdoDumper;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * Nightly MySQL backup for shared hosting. Tries mysqldump first; if
 * proc_open is disabled or mysqldump fails, falls back to a pure-PHP
 * PDO export. Gzips to storage/app/backups, prunes old copies, emails
 * a confirmation.
 *
 * Restore: gunzip the chosen file and pipe it back in, e.g.
 *   gunzip < storage/app/backups/neds-YYYY-MM-DD-HHMMSS.sql.gz | mysql -u USER -p DB
 * See docs/backup-restore.md.
 */
class BackupDatabase extends Command
{
    protected $signature = 'app:backup-database';

    protected $description = 'Dump the MySQL database to storage, prune old backups, and email confirmation.';

    private const DAILY_DIR = 'backups';

    private const WEEKLY_DIR = 'backups/weekly';

    private const DAILY_RETENTION_DAYS = 14;

    private const WEEKLY_RETENTION_WEEKS = 8;

    public function handle(PdoDumper $pdoDumper): int
    {
        $db = config('database.connections.'.config('database.default'));

        if (($db['driver'] ?? null) !== 'mysql') {
            $this->warn('Backups require the mysql driver; skipping ('.($db['driver'] ?? 'unknown').').');

            return self::SUCCESS;
        }

        $now = CarbonImmutable::now();
        $filename = self::DAILY_DIR.'/neds-'.$now->format('Y-m-d-His').'.sql.gz';

        $sql = $this->dumpViaMysqldump($db);

        if ($sql === null) {
            $this->warn('mysqldump unavailable; using pure-PHP PDO fallback.');
            Log::warning('app:backup-database: mysqldump failed, attempting PDO fallback.');

            try {
                $sql = $pdoDumper->dump($db);
            } catch (\Throwable $e) {
                $this->error('PDO fallback also failed: '.$e->getMessage());
                Log::error('app:backup-database: PDO fallback failed.', ['error' => $e->getMessage()]);

                return self::FAILURE;
            }
        }

        Storage::disk('local')->put($filename, gzencode($sql, 6));

        // Weekly copy (one per ISO week), kept longer.
        $weekly = self::WEEKLY_DIR.'/neds-'.$now->format('o-\WW').'.sql.gz';
        Storage::disk('local')->put($weekly, Storage::disk('local')->get($filename));

        $this->prune($now);

        $sizeKb = round(strlen(Storage::disk('local')->get($filename)) / 1024, 1);
        $this->notify($filename, $sizeKb);

        $this->info("Backup written to {$filename} ({$sizeKb} KB).");

        return self::SUCCESS;
    }

    /**
     * Run mysqldump via a subprocess. Returns the raw SQL string on success,
     * or null if mysqldump is missing, returns a non-zero exit code, or if
     * proc_open is disabled on the host (which causes Process to throw).
     *
     * @param  array<string, mixed>  $db
     */
    private function dumpViaMysqldump(array $db): ?string
    {
        $binary = config('backup.mysqldump_binary', 'mysqldump');

        $args = [
            $binary,
            '--host='.($db['host'] ?? '127.0.0.1'),
            '--port='.($db['port'] ?? '3306'),
            '--user='.($db['username'] ?? 'root'),
            '--single-transaction',
            '--no-tablespaces',
            '--skip-lock-tables',
            $db['database'],
        ];

        try {
            // Pass the password via env (MYSQL_PWD) so it never appears in
            // the process list / logs.
            $result = Process::env(['MYSQL_PWD' => (string) ($db['password'] ?? '')])
                ->run($args);
        } catch (\Throwable) {
            return null;
        }

        return $result->successful() ? $result->output() : null;
    }

    /** Delete daily backups older than the retention window, and stale weeklies. */
    private function prune(CarbonImmutable $now): void
    {
        $disk = Storage::disk('local');

        foreach ($disk->files(self::DAILY_DIR) as $path) {
            if ($this->ageInDays($disk->lastModified($path), $now) > self::DAILY_RETENTION_DAYS) {
                $disk->delete($path);
            }
        }

        foreach ($disk->files(self::WEEKLY_DIR) as $path) {
            if ($this->ageInDays($disk->lastModified($path), $now) > self::WEEKLY_RETENTION_WEEKS * 7) {
                $disk->delete($path);
            }
        }
    }

    private function ageInDays(int $timestamp, CarbonImmutable $now): int
    {
        return (int) CarbonImmutable::createFromTimestamp($timestamp)->diffInDays($now);
    }

    private function notify(string $filename, float $sizeKb): void
    {
        $to = config('backup.notify_email');

        if (blank($to)) {
            return;
        }

        // Best-effort: a mail/SMTP problem must never fail an otherwise-good
        // backup (the dump is already written at this point).
        try {
            Mail::raw("Database backup completed.\n\nFile: {$filename}\nSize: {$sizeKb} KB\nTime: ".now()->toDateTimeString(), function ($message) use ($to) {
                $message->to($to)->subject('NEDS CRM — database backup OK');
            });
        } catch (\Throwable $e) {
            $this->warn('Backup succeeded but the confirmation email could not be sent: '.$e->getMessage());
            Log::warning('Backup notification email failed.', ['exception' => $e->getMessage()]);
        }
    }
}
