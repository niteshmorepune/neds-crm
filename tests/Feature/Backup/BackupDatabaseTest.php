<?php

use App\Support\PdoDumper;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

it('skips gracefully when the database driver is not mysql', function () {
    Storage::fake('local');
    Process::fake();
    // Default test connection is sqlite.

    $this->artisan('app:backup-database')->assertSuccessful();

    Process::assertNothingRan();
    expect(Storage::disk('local')->allFiles('backups'))->toBeEmpty();
});

it('dumps, gzips, and writes a daily and weekly backup on mysql', function () {
    Storage::fake('local');
    Mail::fake();
    Process::fake(['*' => Process::result(output: '-- SMOKE SQL DUMP --')]);

    config([
        'database.default' => 'mysql',
        'database.connections.mysql.database' => 'neds_crm',
        'database.connections.mysql.password' => 'secret',
        'backup.notify_email' => null,
    ]);

    $this->artisan('app:backup-database')->assertSuccessful();

    $daily = Storage::disk('local')->files('backups');
    $weekly = Storage::disk('local')->files('backups/weekly');

    expect($daily)->toHaveCount(1)
        ->and($daily[0])->toEndWith('.sql.gz')
        ->and($weekly)->toHaveCount(1);

    // Content is the gzipped dump output.
    expect(gzdecode(Storage::disk('local')->get($daily[0])))->toContain('-- SMOKE SQL DUMP --');

    Process::assertRan(fn ($process) => in_array('neds_crm', $process->command, true)
        && collect($process->command)->contains(fn ($a) => str_contains($a, 'mysqldump')));

    // Restore the sqlite default so RefreshDatabase tears down cleanly.
    config(['database.default' => 'sqlite']);
});

it('still succeeds and writes the backup when the confirmation email fails', function () {
    Storage::fake('local');
    Process::fake(['*' => Process::result(output: '-- DUMP --')]);
    // The dump succeeds, but sending the notification throws (e.g. SMTP rejected).
    Mail::shouldReceive('raw')->once()->andThrow(new RuntimeException('554 Client host rejected'));

    config([
        'database.default' => 'mysql',
        'database.connections.mysql.database' => 'neds_crm',
        'backup.notify_email' => 'ops@neds.test',
    ]);

    $this->artisan('app:backup-database')->assertSuccessful();

    expect(Storage::disk('local')->files('backups'))->toHaveCount(1);

    config(['database.default' => 'sqlite']);
});

it('falls back to PdoDumper when mysqldump exits with a non-zero code', function () {
    Storage::fake('local');
    Mail::fake();

    Process::fake(['*' => Process::result(exitCode: 1, errorOutput: 'mysqldump: command not found')]);

    $this->instance(PdoDumper::class, new class extends PdoDumper
    {
        public function dump(array $db): string
        {
            return '-- PDO FALLBACK SQL --';
        }
    });

    config([
        'database.default' => 'mysql',
        'database.connections.mysql.database' => 'neds_crm',
        'backup.notify_email' => null,
    ]);

    try {
        $this->artisan('app:backup-database')->assertSuccessful();

        $files = Storage::disk('local')->files('backups');
        expect($files)->toHaveCount(1)
            ->and(gzdecode(Storage::disk('local')->get($files[0])))->toContain('-- PDO FALLBACK SQL --');
    } finally {
        config(['database.default' => 'sqlite']);
    }
});

it('falls back to PdoDumper when Process throws (proc_open disabled)', function () {
    Storage::fake('local');
    Mail::fake();

    // Simulate proc_open being disabled at the PHP level — Process::run() throws.
    Process::fake(fn () => throw new RuntimeException('proc_open() has been disabled for security reasons'));

    $this->instance(PdoDumper::class, new class extends PdoDumper
    {
        public function dump(array $db): string
        {
            return '-- PDO EXCEPTION FALLBACK --';
        }
    });

    config([
        'database.default' => 'mysql',
        'database.connections.mysql.database' => 'neds_crm',
        'backup.notify_email' => null,
    ]);

    try {
        $this->artisan('app:backup-database')->assertSuccessful();

        $files = Storage::disk('local')->files('backups');
        expect($files)->toHaveCount(1)
            ->and(gzdecode(Storage::disk('local')->get($files[0])))->toContain('-- PDO EXCEPTION FALLBACK --');
    } finally {
        config(['database.default' => 'sqlite']);
    }
});

it('returns failure when both mysqldump and PDO dump fail', function () {
    Storage::fake('local');

    Process::fake(['*' => Process::result(exitCode: 1)]);

    $this->instance(PdoDumper::class, new class extends PdoDumper
    {
        public function dump(array $db): string
        {
            throw new RuntimeException('PDO connection refused');
        }
    });

    config([
        'database.default' => 'mysql',
        'database.connections.mysql.database' => 'neds_crm',
        'backup.notify_email' => null,
    ]);

    try {
        $this->artisan('app:backup-database')->assertFailed();

        expect(Storage::disk('local')->allFiles('backups'))->toBeEmpty();
    } finally {
        config(['database.default' => 'sqlite']);
    }
});
