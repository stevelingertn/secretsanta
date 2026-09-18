<?php

namespace App\Services\Import;

use App\Exceptions\VotingException;
use App\Models\Car;
use App\Models\Category;
use App\Models\Event;
use App\Models\Participant;
use App\Models\User;
use App\Services\RegistrationService;
use RuntimeException;

/**
 * Turns normalized "Name Entry" rows (see EntrantWorkbookReader) into contestants and
 * cars for one event, via RegistrationService so freeze rules and voter numbers stay
 * correct. Owner matching is conservative: only exact, case-insensitive name matches
 * with no conflicting email/phone are merged. Re-running with the same rows changes
 * nothing (a car already registered under an entry number is left alone).
 */
class EntrantImporter
{
    public function __construct(private RegistrationService $registration) {}

    /**
     * @param  list<array<string,mixed>>  $rows  rows from EntrantWorkbookReader (non-blank names only)
     * @return array{
     *   issues: list<string>,
     *   people_created: int, cars_created: int, unchanged: int, skipped: int,
     * }
     */
    public function import(Event $event, array $rows, ?User $admin): array
    {
        $categoryIds = array_flip(Category::query()->pluck('id')->all());
        if (! $categoryIds) {
            throw new RuntimeException('No categories are seeded. Run app:seed-categories before importing.');
        }

        $issues = [];
        $counts = ['people_created' => 0, 'cars_created' => 0, 'unchanged' => 0, 'skipped' => 0];

        [$valid, $issues] = $this->validateRows($rows, $categoryIds, $issues, $counts);

        // Ascending entry number, so each owner's lowest car number becomes their voter number.
        usort($valid, fn (array $a, array $b) => $a['entry_number'] <=> $b['entry_number']);

        /** @var list<array{name_key:string, email:?string, phone:?string, participant:Participant}> $owners */
        $owners = [];

        foreach ($valid as $row) {
            $existingCar = Car::query()
                ->where('event_id', $event->id)
                ->where('entry_number', $row['entry_number'])
                ->first();

            if ($existingCar) {
                $counts['unchanged']++;
                $this->rememberOwner($owners, $row, $existingCar->participant);

                continue;
            }

            $match = $this->matchOwner($owners, $row);

            if ($match['participant']) {
                $participant = $match['participant'];
            } else {
                [$participant] = $this->registration->createContestant($event, [
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'phone' => $row['phone'],
                    'address' => $row['address'],
                    'city' => $row['city'],
                    'state' => $row['state'],
                    'zip' => $row['zip'],
                ], $admin);
                $counts['people_created']++;
                $this->rememberOwner($owners, $row, $participant);

                if ($match['flag']) {
                    $issues[] = "Row {$row['row']} (entry #{$row['entry_number']}): {$match['flag']}.";
                }
            }

            try {
                $this->registration->registerCar($participant, [
                    'category_id' => $row['class_id'],
                    'entry_number' => $row['entry_number'],
                    'description' => $row['car'],
                ], $admin);
                $counts['cars_created']++;
            } catch (VotingException $e) {
                $issues[] = "Row {$row['row']} (entry #{$row['entry_number']}): {$e->getMessage()}";
                $counts['skipped']++;
            }
        }

        return array_merge($counts, ['issues' => $issues]);
    }

    /**
     * @param  list<array<string,mixed>>  $rows
     * @param  array<int,int>  $categoryIds
     * @param  list<string>  $issues
     * @param  array{people_created:int,cars_created:int,unchanged:int,skipped:int}  $counts
     * @return array{0: list<array<string,mixed>>, 1: list<string>}
     */
    private function validateRows(array $rows, array $categoryIds, array $issues, array &$counts): array
    {
        $seen = [];
        $valid = [];

        foreach ($rows as $row) {
            $label = "Row {$row['row']}";

            if ($row['entry_number'] === null || $row['entry_number'] < 1) {
                $issues[] = "{$label}: missing or invalid entry number.";
                $counts['skipped']++;

                continue;
            }

            $label = "Row {$row['row']} (entry #{$row['entry_number']})";

            if (isset($seen[$row['entry_number']])) {
                $issues[] = "{$label}: duplicate entry number, first used at row {$seen[$row['entry_number']]}.";
                $counts['skipped']++;

                continue;
            }

            if ($row['class_id'] === null || ! isset($categoryIds[$row['class_id']])) {
                $issues[] = "{$label}: invalid or missing class.";
                $counts['skipped']++;

                continue;
            }

            if ($row['car'] === null) {
                $issues[] = "{$label}: missing car description.";
                $counts['skipped']++;

                continue;
            }

            $seen[$row['entry_number']] = $row['row'];
            $valid[] = $row;
        }

        return [$valid, $issues];
    }

    /**
     * @param  list<array{name_key:string, email:?string, phone:?string, participant:Participant}>  $owners
     * @param  array<string,mixed>  $row
     * @return array{participant: ?Participant, flag: ?string}
     */
    private function matchOwner(array $owners, array $row): array
    {
        $nameKey = mb_strtolower($row['name']);
        $sameNameConflict = false;

        foreach ($owners as $owner) {
            if ($owner['name_key'] !== $nameKey) {
                continue;
            }
            if ($this->contactConflicts($owner, $row)) {
                $sameNameConflict = true;

                continue;
            }

            return ['participant' => $owner['participant'], 'flag' => null];
        }

        $sharedEmail = false;
        if ($row['email'] !== null) {
            foreach ($owners as $owner) {
                if ($owner['name_key'] !== $nameKey && $owner['email'] !== null
                    && strcasecmp($owner['email'], $row['email']) === 0) {
                    $sharedEmail = true;

                    break;
                }
            }
        }

        $flag = match (true) {
            $sameNameConflict => 'ambiguous: same name, different contact',
            $sharedEmail => 'shared email, not merged',
            default => null,
        };

        return ['participant' => null, 'flag' => $flag];
    }

    /** @param array{email:?string, phone:?string} $owner */
    private function contactConflicts(array $owner, array $row): bool
    {
        if ($owner['email'] !== null && $row['email'] !== null && strcasecmp($owner['email'], $row['email']) !== 0) {
            return true;
        }

        return $owner['phone'] !== null && $row['phone'] !== null && $owner['phone'] !== $row['phone'];
    }

    /**
     * @param  list<array{name_key:string, email:?string, phone:?string, participant:Participant}>  $owners
     * @param  array<string,mixed>  $row
     */
    private function rememberOwner(array &$owners, array $row, Participant $participant): void
    {
        $user = $participant->relationLoaded('user') ? $participant->user : $participant->user()->first();

        $owners[] = [
            'name_key' => mb_strtolower($row['name']),
            'email' => $user->email ?? $row['email'],
            'phone' => $user->phone ?? $row['phone'],
            'participant' => $participant,
        ];
    }
}
