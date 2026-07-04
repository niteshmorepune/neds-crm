<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Builds a minimal .xlsx matching Hitech billing software's real per-employee
 * attendance export format (verified against an actual export on
 * 2026-07-04): header row, then S.No./Date(serial)/Entry Time/Exit Time/
 * Entry Type/Exit Type/Entry Device/Exit Device columns, times as
 * "HH : MM : SS" text. Shared by HitechAttendanceParserTest and
 * HitechAttendanceImportTest.
 *
 * @param  array<int, array{date: string, entry: ?string, exit: ?string}>  $rows
 */
function buildHitechXlsx(array $rows): string
{
    $sharedStrings = ['S.No.', 'Date', 'Entry Time', 'Exit Time', 'Entry Type', 'Exit Type', 'Entry Device', 'Exit Device'];
    $stringIndex = function (string $value) use (&$sharedStrings): int {
        $i = array_search($value, $sharedStrings, true);
        if ($i === false) {
            $sharedStrings[] = $value;
            $i = array_key_last($sharedStrings);
        }

        return $i;
    };

    $sheetRows = '<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c><c r="D1" t="s"><v>3</v></c><c r="E1" t="s"><v>4</v></c><c r="F1" t="s"><v>5</v></c><c r="G1" t="s"><v>6</v></c><c r="H1" t="s"><v>7</v></c></row>';

    foreach ($rows as $i => $row) {
        $r = $i + 2;
        $serial = (strtotime($row['date']) - strtotime('1899-12-30')) / 86400;
        $entryIdx = $row['entry'] ? $stringIndex($row['entry']) : null;
        $exitIdx = $row['exit'] ? $stringIndex($row['exit']) : null;
        $typeIdx = $stringIndex('Automated Device');
        $deviceIdx = $stringIndex('1');

        $sheetRows .= "<row r=\"{$r}\"><c r=\"A{$r}\" t=\"s\"><v>{$stringIndex((string) ($i + 1))}</v></c>";
        $sheetRows .= "<c r=\"B{$r}\"><v>{$serial}</v></c>";
        $sheetRows .= $entryIdx !== null ? "<c r=\"C{$r}\" t=\"s\"><v>{$entryIdx}</v></c>" : "<c r=\"C{$r}\"/>";
        $sheetRows .= $exitIdx !== null ? "<c r=\"D{$r}\" t=\"s\"><v>{$exitIdx}</v></c>" : "<c r=\"D{$r}\"/>";
        $sheetRows .= "<c r=\"E{$r}\" t=\"s\"><v>{$typeIdx}</v></c>";
        $sheetRows .= $exitIdx !== null ? "<c r=\"F{$r}\" t=\"s\"><v>{$typeIdx}</v></c>" : "<c r=\"F{$r}\"/>";
        $sheetRows .= "<c r=\"G{$r}\" t=\"s\"><v>{$deviceIdx}</v></c>";
        $sheetRows .= $exitIdx !== null ? "<c r=\"H{$r}\" t=\"s\"><v>{$deviceIdx}</v></c>" : "<c r=\"H{$r}\"/>";
        $sheetRows .= '</row>';
    }

    $sheetXml = '<?xml version="1.0" encoding="utf-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheetRows.'</sheetData></worksheet>';

    $sstItems = implode('', array_map(fn ($s) => '<si><t>'.htmlspecialchars($s).'</t></si>', $sharedStrings));
    $sstXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($sharedStrings).'" uniqueCount="'.count($sharedStrings).'">'.$sstItems.'</sst>';

    $path = tempnam(sys_get_temp_dir(), 'hitech').'.xlsx';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->addFromString('xl/sharedStrings.xml', $sstXml);
    $zip->close();

    return $path;
}
