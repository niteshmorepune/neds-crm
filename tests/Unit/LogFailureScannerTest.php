<?php

use App\Support\LogFailureScanner;
use Illuminate\Support\Carbon;

/**
 * Points the scanner at an isolated temp directory with synthetic fixture
 * lines instead of the real storage/logs -- keeps this fully independent of
 * whatever the real logging pipeline does during a test run.
 */
function makeLogDir(): string
{
    $dir = sys_get_temp_dir().'/log-scanner-test-'.uniqid();
    mkdir($dir);

    return $dir;
}

function writeLogLine(string $dir, Carbon $at, string $message, array $context = []): void
{
    $date = $at->toDateString();
    $path = $dir.'/laravel-'.$date.'.log';
    $line = '['.$at->toDateTimeString().'] production.WARNING: '.$message.' '.json_encode($context)."\n";
    file_put_contents($path, $line, FILE_APPEND);
}

afterEach(function () {
    if (isset($this->logDir) && is_dir($this->logDir)) {
        array_map('unlink', glob($this->logDir.'/*'));
        rmdir($this->logDir);
    }
});

it('counts matching lines within the window and ignores non-matching ones', function () {
    $dir = makeLogDir();
    $this->logDir = $dir;

    writeLogLine($dir, now()->subMinutes(10), 'SendOfferRecommendationReadyJob: wadesk.in returned non-2xx', ['lead_id' => 1, 'status' => 404]);
    writeLogLine($dir, now()->subMinutes(5), 'SendOfferRecommendationReadyJob: wadesk.in returned non-2xx', ['lead_id' => 2, 'status' => 404]);
    writeLogLine($dir, now()->subMinutes(5), 'Some unrelated warning', []);

    $scanner = new LogFailureScanner($dir);
    $result = $scanner->countSince('SendOfferRecommendationReadyJob: wadesk.in returned non-2xx', now()->subHour());

    expect($result['count'])->toBe(2);
});

it('excludes lines older than the window', function () {
    $dir = makeLogDir();
    $this->logDir = $dir;

    writeLogLine($dir, now()->subHours(3), 'SendOfferRecommendationReadyJob: wadesk.in returned non-2xx', ['status' => 404]);

    $scanner = new LogFailureScanner($dir);
    $result = $scanner->countSince('SendOfferRecommendationReadyJob: wadesk.in returned non-2xx', now()->subHour());

    expect($result['count'])->toBe(0);
});

it('spans a midnight rollover -- reads both yesterday\'s and today\'s log files', function () {
    $dir = makeLogDir();
    $this->logDir = $dir;

    $since = now()->subDay()->endOfDay()->subMinutes(30); // yesterday, 23:30

    // One matching line in each day's own file -- both should count.
    writeLogLine($dir, $since->copy()->addMinutes(15), 'Razorpay order creation exception', ['error' => 'cURL error 28']); // yesterday 23:45
    writeLogLine($dir, now(), 'Razorpay order creation exception', ['error' => 'cURL error 28']); // today

    $scanner = new LogFailureScanner($dir);
    $result = $scanner->countSince('Razorpay order creation exception', $since);

    expect($result['count'])->toBe(2);
});

it('does not crash when yesterday\'s log file does not exist at all', function () {
    $dir = makeLogDir();
    $this->logDir = $dir;

    $since = now()->subDay()->endOfDay()->subMinutes(30);
    writeLogLine($dir, now(), 'Razorpay order creation exception', ['error' => 'cURL error 28']); // today only

    $scanner = new LogFailureScanner($dir);
    $result = $scanner->countSince('Razorpay order creation exception', $since);

    expect($result['count'])->toBe(1);
});

it('extracts the nested body.error reason and counts occurrences', function () {
    $dir = makeLogDir();
    $this->logDir = $dir;

    writeLogLine($dir, now()->subMinutes(5), 'SendOfferRecommendationReadyJob: wadesk.in returned non-2xx', [
        'lead_id' => 1, 'status' => 404, 'body' => json_encode(['error' => 'Template not found or not approved']),
    ]);
    writeLogLine($dir, now()->subMinutes(4), 'SendOfferRecommendationReadyJob: wadesk.in returned non-2xx', [
        'lead_id' => 2, 'status' => 404, 'body' => json_encode(['error' => 'Template not found or not approved']),
    ]);
    writeLogLine($dir, now()->subMinutes(3), 'SendOfferRecommendationReadyJob: wadesk.in returned non-2xx', [
        'lead_id' => 3, 'status' => 500, 'body' => json_encode(['error' => 'Number of parameters does not match']),
    ]);

    $scanner = new LogFailureScanner($dir);
    $result = $scanner->countSince('SendOfferRecommendationReadyJob: wadesk.in returned non-2xx', now()->subHour());

    expect($result['count'])->toBe(3)
        ->and(array_key_first($result['reasons']))->toBe('Template not found or not approved')
        ->and($result['reasons']['Template not found or not approved'])->toBe(2)
        ->and($result['reasons']['Number of parameters does not match'])->toBe(1);
});

it('falls back to the bare error field when there is no nested body', function () {
    $dir = makeLogDir();
    $this->logDir = $dir;

    writeLogLine($dir, now()->subMinutes(5), 'Razorpay order creation exception', ['error' => 'cURL error 28: Connection timed out']);

    $scanner = new LogFailureScanner($dir);
    $result = $scanner->countSince('Razorpay order creation exception', now()->subHour());

    expect($result['reasons'])->toHaveKey('cURL error 28: Connection timed out');
});

it('returns zero and no reasons when the log file does not exist at all', function () {
    $dir = makeLogDir();
    $this->logDir = $dir;

    $scanner = new LogFailureScanner($dir);
    $result = $scanner->countSince('Anything', now()->subHour());

    expect($result['count'])->toBe(0)
        ->and($result['reasons'])->toBe([]);
});
