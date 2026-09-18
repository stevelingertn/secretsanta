<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EventStatus;
use App\Models\Category;
use App\Models\Event;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends AdminController
{
    private const REPORTS = [
        'votes-by-car' => ['title' => 'Votes by car', 'description' => 'Every car with contestant vote subtotals and any system tiebreak votes.'],
        'votes-by-category' => ['title' => 'Votes by class', 'description' => 'Ranked contestant votes within each class, with the award outcome.'],
        'entries-by-class' => ['title' => 'Entries by class', 'description' => 'Registered car counts per class and the event total.'],
        'awards' => ['title' => 'Awards', 'description' => 'Best Overall and each class award, provisional until results are final.'],
        'reconciliation' => ['title' => 'Reconciliation', 'description' => 'Vote counts checked against contestant voting capacity.'],
    ];

    public function __construct(private ReportService $reports) {}

    public function index(Request $request): View
    {
        $event = $this->event($request);

        return view('admin.reports.index', ['event' => $event, 'reports' => self::REPORTS]);
    }

    public function show(Request $request, string $report): View|StreamedResponse
    {
        $event = $this->event($request);
        abort_unless(array_key_exists($report, self::REPORTS), 404);

        $status = $this->statusLabel($event);
        $generatedAt = now();
        $title = self::REPORTS[$report]['title'];

        $data = match ($report) {
            'votes-by-car' => $this->votesByCarData($request, $event),
            'votes-by-category' => ['rows' => $this->reports->votesByCategory($event)],
            'entries-by-class' => $this->reports->entriesByClass($event),
            'awards' => $this->reports->awards($event),
            'reconciliation' => $this->reports->reconciliation($event),
        };

        if ($request->query('format') === 'csv') {
            return $this->csv($report, $event, $data);
        }

        $view = 'admin.reports.'.$report;
        $viewData = array_merge($data, [
            'event' => $event,
            'title' => $title,
            'status' => $status,
            'generatedAt' => $generatedAt,
            'report' => $report,
            'print' => $request->query('print') === '1',
        ]);

        if ($report === 'votes-by-car') {
            $viewData['categories'] = Category::query()->orderBy('name')->get();
        }

        return view($view, $viewData);
    }

    private function votesByCarData(Request $request, Event $event): array
    {
        $categoryId = $request->integer('category') ?: null;
        $search = $request->string('q')->trim()->toString() ?: null;

        return [
            'rows' => $this->reports->votesByCar($event, $categoryId, $search),
            'filters' => [
                'category' => $categoryId,
                'q' => $search,
            ],
        ];
    }

    private function statusLabel(Event $event): string
    {
        if ($event->status === EventStatus::Finalized) {
            return 'Final';
        }

        $results = app(\App\Services\Results\ResultsCalculator::class)->calculate($event);

        return $results['pending'] ? 'Provisional: ties pending' : 'Provisional';
    }

    private function csv(string $report, Event $event, array $data): StreamedResponse
    {
        $filename = $report.'-'.$event->year.'-'.now()->format('YmdHis').'.csv';

        [$headers, $rows] = match ($report) {
            'votes-by-car' => [
                ['Entry number', 'Vehicle', 'Class', 'Online votes', 'Manual votes', 'Contestant votes', 'System tiebreak votes', 'Tiebreak scope'],
                $data['rows']->map(fn ($row) => [
                    $row['entry_number'],
                    $row['vehicle'],
                    $row['class'],
                    $row['online_votes'],
                    $row['manual_votes'],
                    $row['contestant_votes'],
                    collect($row['tiebreak_votes'])->sum('votes'),
                    collect($row['tiebreak_votes'])->pluck('scope_label')->implode('; '),
                ])->all(),
            ],
            'votes-by-category' => [
                ['Class', 'Entry number', 'Vehicle', 'Contestant votes', 'Class total', 'Outcome'],
                collect($data['rows'])->flatMap(fn ($group) => $group['cars']->map(fn ($row) => [
                    $group['category']->name,
                    $row['car']->entry_number,
                    $row['car']->vehicle(),
                    $row['votes'],
                    $group['total_votes'],
                    $group['outcome']?->status,
                ]))->all(),
            ],
            'entries-by-class' => [
                ['Class', 'Entries'],
                collect($data['rows'])->map(fn ($row) => [$row['category']->name, $row['count']])
                    ->push(['Total', $data['total']])->all(),
            ],
            'awards' => [
                ['Award', 'Outcome', 'Entry number', 'Vehicle', 'Owner', 'Contestant votes', 'System votes', 'Explanation'],
                collect($data['rows'])->map(fn ($row) => [
                    $row['title'], $row['outcome'], $row['entry_number'], $row['vehicle'],
                    $row['owner_name'], $row['contestant_votes'], $row['system_votes'], $row['explanation'],
                ])->all(),
            ],
            'reconciliation' => [
                ['Metric', 'Value'],
                [
                    ['Registered cars', $data['registered_cars']],
                    ['Default capacity (cars x votes per car)', $data['default_capacity']],
                    ['Contestants', $data['contestants']],
                    ['Contestants with allowance override', $data['contestants_with_override']],
                    ['Effective capacity', $data['effective_capacity']],
                    ['Online votes', $data['online_votes']],
                    ['Manual votes', $data['manual_votes']],
                    ['Contestant votes', $data['contestant_votes']],
                    ['Remaining capacity', $data['remaining_capacity']],
                    ['Within capacity', $data['within_capacity'] ? 'Pass' : 'Fail'],
                    ['Participants over their allowance', $data['over_capacity_participants']],
                    ['Sanity check', $data['sanity_passed'] ? 'Pass' : 'Fail'],
                ],
            ],
            default => [[], []],
        };

        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
