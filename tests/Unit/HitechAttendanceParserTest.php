<?php

use App\Support\HitechAttendanceParser;

it('parses date, entry, and exit from a Hitech export', function () {
    $path = buildHitechXlsx([
        ['date' => '2026-07-01', 'entry' => '09 : 01 : 56', 'exit' => '17 : 57 : 33'],
        ['date' => '2026-07-02', 'entry' => '08 : 59 : 44', 'exit' => '17 : 59 : 17'],
    ]);

    $rows = (new HitechAttendanceParser)->parse($path);
    unlink($path);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['date'])->toBe('2026-07-01')
        ->and($rows[0]['entry'])->toBe('09:01:56')
        ->and($rows[0]['exit'])->toBe('17:57:33')
        ->and($rows[1]['date'])->toBe('2026-07-02');
});

it('leaves exit null for a day with no recorded checkout', function () {
    $path = buildHitechXlsx([
        ['date' => '2026-07-03', 'entry' => '09 : 12 : 06', 'exit' => null],
    ]);

    $rows = (new HitechAttendanceParser)->parse($path);
    unlink($path);

    expect($rows[0]['entry'])->toBe('09:12:06')
        ->and($rows[0]['exit'])->toBeNull();
});

it('throws when the file is not a valid xlsx', function () {
    $path = tempnam(sys_get_temp_dir(), 'notxlsx').'.xlsx';
    file_put_contents($path, 'not a zip file');

    (new HitechAttendanceParser)->parse($path);
})->throws(RuntimeException::class);
