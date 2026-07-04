<?php

namespace App\Support;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Parses a Hitech billing software single-employee attendance export
 * (.xlsx, columns: S.No. | Date | Entry Time | Exit Time | Entry Type |
 * Exit Type | Entry Device | Exit Device). Hand-rolled against .xlsx's
 * raw XML rather than a full spreadsheet library, since the export has a
 * small, fixed, already-verified format.
 */
class HitechAttendanceParser
{
    /**
     * @return array<int, array{date: string, entry: ?string, exit: ?string}>
     */
    public function parse(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('Could not open the uploaded file as an Excel workbook.');
        }

        $sharedStrings = $this->readSharedStrings($zip);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            throw new RuntimeException('The uploaded file does not look like a Hitech attendance export.');
        }

        $sheet = new SimpleXMLElement($sheetXml);
        $rows = [];

        foreach ($sheet->sheetData->row as $row) {
            if ((int) $row['r'] === 1) {
                continue; // header row
            }

            $cells = [];
            foreach ($row->c as $cell) {
                preg_match('/^([A-Z]+)/', (string) $cell['r'], $m);
                $cells[$m[1]] = $this->cellValue($cell, $sharedStrings);
            }

            if (! isset($cells['B']) || $cells['B'] === null) {
                continue;
            }

            $rows[] = [
                'date' => $this->excelSerialToDate((float) $cells['B']),
                'entry' => $this->normaliseTime($cells['C'] ?? null),
                'exit' => $this->normaliseTime($cells['D'] ?? null),
            ];
        }

        return $rows;
    }

    /** @return array<int, string> */
    private function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $strings = [];
        foreach ((new SimpleXMLElement($xml))->si as $si) {
            $strings[] = (string) $si->t;
        }

        return $strings;
    }

    /** @param array<int, string> $sharedStrings */
    private function cellValue(SimpleXMLElement $cell, array $sharedStrings): ?string
    {
        if (! isset($cell->v)) {
            return null;
        }

        $value = (string) $cell->v;

        return (string) $cell['t'] === 's'
            ? ($sharedStrings[(int) $value] ?? null)
            : $value;
    }

    private function excelSerialToDate(float $serial): string
    {
        // Excel's (1900 date system) day zero is 1899-12-30.
        return gmdate('Y-m-d', (int) round(($serial - 25569) * 86400));
    }

    /** Hitech formats times as "09 : 01 : 56" — strip the spaces around colons. */
    private function normaliseTime(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return str_replace(' ', '', $value);
    }
}
