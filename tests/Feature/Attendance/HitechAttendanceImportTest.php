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
