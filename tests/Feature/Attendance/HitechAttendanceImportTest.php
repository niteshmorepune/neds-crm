<?php

use App\Enums\UserRole;
use App\Livewire\HitechAttendanceImport;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->manager = User::factory()->role(UserRole::Manager)->create();
    $this->staff = User::factory()->role(UserRole::Sales)->create();
});

it('blocks a non-manager from the import screen', function () {
    Livewire::actingAs($this->staff)->test(HitechAttendanceImport::class)
        ->assertForbidden();
});

it('previews parsed rows against existing attendance without writing anything yet', function () {
    Attendance::create([
        'user_id' => $this->staff->id,
        'date' => '2026-07-04',
        'status' => 'present',
        'check_in_at' => Carbon::parse('2026-07-04 12:04:57', 'Asia/Kolkata')->utc(),
    ]);

    $path = buildHitechXlsx([
        ['date' => '2026-07-04', 'entry' => '09 : 09 : 23', 'exit' => '17 : 47 : 29'],
    ]);
    $upload = UploadedFile::fake()->createWithContent('attendance.xlsx', file_get_contents($path));

    Livewire::actingAs($this->manager)->test(HitechAttendanceImport::class)
        ->set('userId', $this->staff->id)
        ->set('file', $upload)
        ->call('parse')
        ->assertSet('step', 2);

    // Nothing written yet — this is only a preview.
    $attendance = Attendance::where('user_id', $this->staff->id)->whereDate('date', '2026-07-04')->first();
    expect($attendance->check_in_at->timezone('Asia/Kolkata')->format('H:i:s'))->toBe('12:04:57')
        ->and($attendance->check_out_at)->toBeNull();
});

it('imports and overwrites the day once confirmed, without erasing an unrelated existing value on a blank cell', function () {
    $existingCheckIn = Carbon::parse('2026-07-04 12:04:57', 'Asia/Kolkata')->utc();
    Attendance::create([
        'user_id' => $this->staff->id,
        'date' => '2026-07-04',
        'status' => 'present',
        'check_in_at' => $existingCheckIn,
    ]);

    $path = buildHitechXlsx([
        ['date' => '2026-07-04', 'entry' => '09 : 09 : 23', 'exit' => '17 : 47 : 29'],
        ['date' => '2026-07-05', 'entry' => '09 : 00 : 00', 'exit' => null],
    ]);
    $upload = UploadedFile::fake()->createWithContent('attendance.xlsx', file_get_contents($path));

    Livewire::actingAs($this->manager)->test(HitechAttendanceImport::class)
        ->set('userId', $this->staff->id)
        ->set('file', $upload)
        ->call('parse')
        ->call('import');

    $day4 = Attendance::where('user_id', $this->staff->id)->whereDate('date', '2026-07-04')->first();
    expect($day4->check_in_at->timezone('Asia/Kolkata')->format('H:i:s'))->toBe('09:09:23')
        ->and($day4->check_out_at->timezone('Asia/Kolkata')->format('H:i:s'))->toBe('17:47:29');

    // A day with no existing row and a blank exit cell: check-in is created, check-out stays null.
    $day5 = Attendance::where('user_id', $this->staff->id)->whereDate('date', '2026-07-05')->first();
    expect($day5)->not->toBeNull()
        ->and($day5->check_in_at->timezone('Asia/Kolkata')->format('H:i:s'))->toBe('09:00:00')
        ->and($day5->check_out_at)->toBeNull();
});

it('rejects a non-xlsx upload', function () {
    $upload = UploadedFile::fake()->create('attendance.csv', 10);

    Livewire::actingAs($this->manager)->test(HitechAttendanceImport::class)
        ->set('userId', $this->staff->id)
        ->set('file', $upload)
        ->call('parse')
        ->assertHasErrors(['file']);
});

it('shows a friendly error instead of a 500 when the uploaded xlsx has malformed worksheet XML', function () {
    // 2026-08-04 regression: SimpleXMLElement throws a plain \Exception (not
    // RuntimeException) on malformed XML, so a real-world export with an
    // unexpected internal shape (e.g. a genuine biometric export renamed to
    // .xlsx, or a corrupted file) crashed with an uncaught 500 instead of a
    // validation error.
    $path = tempnam(sys_get_temp_dir(), 'badxlsx').'.xlsx';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet><sheetData><row><not valid xml');
    $zip->close();
    $upload = UploadedFile::fake()->createWithContent('attendance.xlsx', file_get_contents($path));
    unlink($path);

    Livewire::actingAs($this->manager)->test(HitechAttendanceImport::class)
        ->set('userId', $this->staff->id)
        ->set('file', $upload)
        ->call('parse')
        ->assertHasErrors(['file'])
        ->assertSet('step', 1);
});

it('imports an operator-corrected punch time that has no seconds', function () {
    // 2026-08-04 regression: real Kiran Katte export had Entry Time
    // "09 : 08 : 11" (Automated Device) but Exit Time "18:28" (Operator,
    // hand-corrected, no seconds) — the row was silently skipped entirely
    // instead of importing both fields.
    $path = buildHitechXlsx([
        ['date' => '2026-08-03', 'entry' => '09 : 08 : 11', 'exit' => '18:28'],
    ]);
    $upload = UploadedFile::fake()->createWithContent('attendance.xlsx', file_get_contents($path));

    Livewire::actingAs($this->manager)->test(HitechAttendanceImport::class)
        ->set('userId', $this->staff->id)
        ->set('file', $upload)
        ->call('parse')
        ->call('import');

    $day = Attendance::where('user_id', $this->staff->id)->whereDate('date', '2026-08-03')->first();
    expect($day)->not->toBeNull()
        ->and($day->check_in_at->timezone('Asia/Kolkata')->format('H:i:s'))->toBe('09:08:11')
        ->and($day->check_out_at->timezone('Asia/Kolkata')->format('H:i:s'))->toBe('18:28:00');
});

it('skips a row with an unparsable punch time instead of crashing the whole import', function () {
    // 2026-08-04 regression: Carbon::createFromFormat() throws when a punch
    // cell doesn't match Hitech's usual "H:i:s" shape (e.g. a missed-punch
    // marker) — import() had no try/catch at all around it, so one bad row
    // crashed the entire confirmed import with an uncaught 500.
    $path = buildHitechXlsx([
        ['date' => '2026-07-04', 'entry' => '09 : 09 : 23', 'exit' => '17 : 47 : 29'],
        ['date' => '2026-07-05', 'entry' => 'N/A', 'exit' => null],
    ]);
    $upload = UploadedFile::fake()->createWithContent('attendance.xlsx', file_get_contents($path));

    Livewire::actingAs($this->manager)->test(HitechAttendanceImport::class)
        ->set('userId', $this->staff->id)
        ->set('file', $upload)
        ->call('parse')
        ->call('import');

    $day4 = Attendance::where('user_id', $this->staff->id)->whereDate('date', '2026-07-04')->first();
    expect($day4->check_in_at->timezone('Asia/Kolkata')->format('H:i:s'))->toBe('09:09:23');

    // The bad row is skipped entirely, not saved with a garbage value.
    $day5 = Attendance::where('user_id', $this->staff->id)->whereDate('date', '2026-07-05')->first();
    expect($day5)->toBeNull();
});
