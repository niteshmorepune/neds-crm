<?php

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
