<?php

namespace Tests\Feature\Import;

use App\Models\Car;
use App\Models\Category;
use App\Services\Import\EntrantImporter;
use App\Services\Import\EntrantWorkbookReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\Concerns\BuildsShow;
use Tests\TestCase;

class EntrantImportTest extends TestCase
{
    use BuildsShow, RefreshDatabase;

    /**
     * Builds a synthetic "Name Entry" workbook exercising every rule in the spec:
     * an exact-name/exact-email merge, an exact-name/conflicting-email split,
     * a shared-email/different-name split, an "N/A" email, an invalid class,
     * a duplicate entry number, and trailing blank/VLOOKUP-style scaffolding rows.
     */
    private function buildWorkbook(): string
    {
        $ss = new Spreadsheet;
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle(EntrantWorkbookReader::SHEET);

        $headers = ['Entry #', 'Name', 'Car', 'Address', 'City', 'State', 'Zip', 'Phone', 'Email', 'Entry Class'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue([$i + 1, 1], $h);
        }

        $rows = [
            // row, name, car, email, phone, class
            ['Alice Smith', '1965 Mustang', 'alice@example.com', '555-1111', 1],
            ['Alice Smith', '1966 Mustang', 'alice@example.com', '555-1111', 2],
            ['Alice Smith', 'Old Truck', 'alice2@example.com', '555-2222', 1],
            ['Bob Jones', 'Camaro', 'alice@example.com', null, 2],
            ['Carol Lee', 'Woodie Wagon', 'N/A', '0', 1],
            ['Dan Park', 'Invalid Class Car', 'dan@example.com', null, 99],
        ];

        $r = 2;
        $sheet->setCellValue("A{$r}", 1);
        foreach ($rows as $i => $row) {
            [$name, $car, $email, $phone, $class] = $row;
            if ($i > 0) {
                $sheet->setCellValue("A{$r}", '=A'.($r - 1).'+1');
            }
            $sheet->setCellValue("B{$r}", $name);
            $sheet->setCellValue("C{$r}", $car);
            $sheet->setCellValue("D{$r}", '123 Main St');
            $sheet->setCellValue("E{$r}", 'Oakwood');
            $sheet->setCellValue("F{$r}", 'GA');
            $sheet->setCellValue("G{$r}", '30507');
            $sheet->setCellValue("H{$r}", $phone);
            $sheet->setCellValue("I{$r}", $email);
            $sheet->setCellValue("J{$r}", $class);
            $r++;
        }

        // Duplicate entry number: literal 5, same as Carol's row above.
        $dupRow = $r;
        $sheet->setCellValue("A{$dupRow}", 5);
        $sheet->setCellValue("B{$dupRow}", 'Eve Stone');
        $sheet->setCellValue("C{$dupRow}", 'Duplicate Number Car');
        $sheet->setCellValue("J{$dupRow}", 1);
        $r++;

        // Blank scaffolding rows: VLOOKUP-style formulas, no name.
        for ($i = 0; $i < 5; $i++) {
            $sheet->setCellValue("A{$r}", '=A'.($r - 1).'+1');
            $sheet->setCellValue("C{$r}", '=""');
            $sheet->setCellValue("I{$r}", '=""');
            $r++;
        }

        $path = tempnam(sys_get_temp_dir(), 'entrants').'.xlsx';
        IOFactory::createWriter($ss, 'Xlsx')->save($path);

        return $path;
    }

    public function test_reader_skips_blank_scaffolding_and_reads_cached_formula_values(): void
    {
        $path = $this->buildWorkbook();

        $sheet = app(EntrantWorkbookReader::class)->read($path);

        $this->assertSame(12, $sheet['scanned']); // rows 2..13
        $this->assertCount(7, $sheet['rows']); // 6 real entrants + 1 duplicate-number row
        $this->assertSame(1, $sheet['rows'][0]['entry_number']);
        $this->assertSame('Alice Smith', $sheet['rows'][0]['name']);
        $this->assertNull($sheet['rows'][4]['email']); // Carol's "N/A" email
        $this->assertNull($sheet['rows'][4]['phone']); // Carol's "0" phone
    }

    public function test_import_merges_reports_and_creates_owners_and_cars(): void
    {
        $event = $this->makeEvent();
        $this->makeCategory(1, 'Class One');
        $this->makeCategory(2, 'Class Two');
        $sheet = app(EntrantWorkbookReader::class)->read($this->buildWorkbook());

        $result = app(EntrantImporter::class)->import($event, $sheet['rows'], null);

        $this->assertSame(4, $result['people_created']); // Alice, Alice-conflict, Bob, Carol
        $this->assertSame(5, $result['cars_created']);
        $this->assertSame(0, $result['unchanged']);
        $this->assertSame(2, $result['skipped']); // invalid class, duplicate entry number
        $this->assertCount(4, $result['issues']);
        $this->assertStringContainsString('ambiguous: same name, different contact', implode(' | ', $result['issues']));
        $this->assertStringContainsString('shared email, not merged', implode(' | ', $result['issues']));
        $this->assertStringContainsString('invalid or missing class', implode(' | ', $result['issues']));
        $this->assertStringContainsString('duplicate entry number', implode(' | ', $result['issues']));

        $this->assertSame(5, Car::where('event_id', $event->id)->count());

        $carOne = Car::where('event_id', $event->id)->where('entry_number', 1)->first();
        $carTwo = Car::where('event_id', $event->id)->where('entry_number', 2)->first();
        $this->assertSame($carOne->participant_id, $carTwo->participant_id); // merged owner

        $carThree = Car::where('event_id', $event->id)->where('entry_number', 3)->first();
        $this->assertNotSame($carOne->participant_id, $carThree->participant_id); // conflicting contact, not merged

        $carFour = Car::where('event_id', $event->id)->where('entry_number', 4)->first();
        $this->assertNotSame($carOne->participant_id, $carFour->participant_id); // shared email, not merged

        // Voter number is the owner's lowest car entry number.
        $this->assertSame(1, $carOne->participant->fresh()->voter_number);
        $this->assertSame(3, $carThree->participant->fresh()->voter_number);
        $this->assertSame(4, $carFour->participant->fresh()->voter_number);

        // Every participant created has a login code hash (never a plain code stored).
        foreach ($event->participants()->get() as $participant) {
            $this->assertNotEmpty($participant->login_code_hash);
        }

        // No votes were ever created by the import.
        $this->assertSame(0, \App\Models\Vote::where('event_id', $event->id)->count());
        $this->assertSame(0, \App\Models\BallotSubmission::where('event_id', $event->id)->count());
    }

    public function test_import_is_idempotent(): void
    {
        $event = $this->makeEvent();
        $this->makeCategory(1, 'Class One');
        $this->makeCategory(2, 'Class Two');
        $rows = app(EntrantWorkbookReader::class)->read($this->buildWorkbook())['rows'];
        $importer = app(EntrantImporter::class);

        $importer->import($event, $rows, null);
        $participantsAfterFirst = $event->participants()->count();
        $carsAfterFirst = Car::where('event_id', $event->id)->count();

        $second = $importer->import($event, $rows, null);

        $this->assertSame(0, $second['people_created']);
        $this->assertSame(0, $second['cars_created']);
        $this->assertSame(5, $second['unchanged']);
        $this->assertSame(2, $second['skipped']);
        $this->assertSame($participantsAfterFirst, $event->participants()->count());
        $this->assertSame($carsAfterFirst, Car::where('event_id', $event->id)->count());
    }
}
