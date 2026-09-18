<?php

namespace App\Services\Import;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

/**
 * Reads the "Name Entry" sheet of the 2025 car show workbook into plain arrays.
 *
 * Only the "Name Entry" sheet is loaded: the workbook has a veryHidden VBA sheet
 * with an empty r:id that crashes listWorksheetInfo()/listWorksheetNames(), so those
 * are never called. Macros are never executed; PhpSpreadsheet ignores vbaProject.bin.
 *
 * Entry # is a formula (`=A2+1`); every formula cell in A..J is read as its cached
 * value (getOldCalculatedValue()), never recalculated. Rows with a blank Name are
 * scaffolding (VLOOKUP formulas with nothing to look up) and are skipped.
 */
class EntrantWorkbookReader
{
    public const SHEET = 'Name Entry';

    private const CONTACT_BLANKS = ['', 'n/a', 'na', 'none', '-', '0'];

    /**
     * @return array{scanned:int, rows:list<array{
     *   row:int, entry_number:int|null, name:string|null, car:string|null,
     *   address:string|null, city:string|null, state:string|null, zip:string|null,
     *   phone:string|null, email:string|null, class_id:int|null,
     * }>}
     */
    public function read(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Workbook not found at {$path}.");
        }

        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly([self::SHEET]);
        $spreadsheet = $reader->load($path);

        $sheet = $spreadsheet->getSheetByName(self::SHEET);
        if (! $sheet) {
            throw new RuntimeException('Sheet "'.self::SHEET.'" was not found in the workbook.');
        }

        return $this->readSheet($sheet);
    }

    /**
     * @return array{scanned:int, rows:list<array<string,mixed>>}
     */
    public function readSheet(Worksheet $sheet): array
    {
        $highestRow = $sheet->getHighestDataRow();
        $rows = [];
        $scanned = 0;

        for ($r = 2; $r <= $highestRow; $r++) {
            $scanned++;

            $name = $this->normalizeName($this->cellValue($sheet, "B{$r}"));
            if ($name === null) {
                continue; // blank scaffolding row
            }

            $rows[] = [
                'row' => $r,
                'entry_number' => $this->toIntOrNull($this->cellValue($sheet, "A{$r}")),
                'name' => $name,
                'car' => $this->trimOrNull($this->cellValue($sheet, "C{$r}")),
                'address' => $this->normalizeContact($this->cellValue($sheet, "D{$r}")),
                'city' => $this->normalizeContact($this->cellValue($sheet, "E{$r}")),
                'state' => $this->normalizeContact($this->cellValue($sheet, "F{$r}")),
                'zip' => $this->normalizeContact($this->cellValue($sheet, "G{$r}")),
                'phone' => $this->normalizeContact($this->cellValue($sheet, "H{$r}")),
                'email' => $this->normalizeContact($this->cellValue($sheet, "I{$r}")),
                'class_id' => $this->toIntOrNull($this->cellValue($sheet, "J{$r}")),
            ];
        }

        return ['scanned' => $scanned, 'rows' => $rows];
    }

    /** The cell's cached value: formula cells use getOldCalculatedValue(), never recalculated. */
    private function cellValue(Worksheet $sheet, string $coordinate): mixed
    {
        $cell = $sheet->getCell($coordinate);

        return $this->cellCachedValue($cell);
    }

    private function cellCachedValue(Cell $cell): mixed
    {
        return $cell->isFormula() ? $cell->getOldCalculatedValue() : $cell->getValue();
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_float($value)) {
            // Excel stores numbers as floats; whole numbers should not print as "30507.0".
            return rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
        }

        return (string) $value;
    }

    private function collapseWhitespace(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function trimOrNull(mixed $value): ?string
    {
        $value = $this->collapseWhitespace($this->stringify($value));

        return $value === '' ? null : $value;
    }

    private function normalizeName(mixed $value): ?string
    {
        return $this->trimOrNull($value);
    }

    private function normalizeContact(mixed $value): ?string
    {
        $value = $this->trimOrNull($value);
        if ($value === null) {
            return null;
        }

        return in_array(mb_strtolower($value), self::CONTACT_BLANKS, true) ? null : $value;
    }

    private function toIntOrNull(mixed $value): ?int
    {
        $value = $this->trimOrNull($value);
        if ($value === null) {
            return null;
        }
        if (! preg_match('/^-?\d+$/', $value)) {
            return null;
        }

        return (int) $value;
    }
}
