<?php

namespace Tests\Feature\Domain;

use App\Enums\EventStatus;
use App\Exceptions\VotingException;
use App\Services\LoginCodeService;
use App\Services\RegistrationService;
use App\Services\VotingLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsShow;
use Tests\TestCase;

class LifecycleAndRegistrationTest extends TestCase
{
    use BuildsShow, RefreshDatabase;

    public function test_lifecycle_moves_forward_only(): void
    {
        $event = $this->makeEvent();
        $admin = $this->makeAdmin();
        $service = app(VotingLifecycleService::class);

        $this->assertThrows(fn () => $service->close($event, $admin), VotingException::class);
        $service->open($event, $admin);
        $this->assertSame(EventStatus::VotingOpen, $event->fresh()->status);
        $this->assertThrows(fn () => $service->open($event, $admin), VotingException::class);
        $service->close($event, $admin);
        $this->assertSame(EventStatus::VotingClosed, $event->fresh()->status);
        $this->assertNotNull($event->fresh()->closed_tally_hash);
        $this->assertThrows(fn () => $service->open($event, $admin), VotingException::class);
    }

    public function test_close_stops_online_and_manual_voting_and_registration(): void
    {
        $event = $this->makeEvent(EventStatus::VotingOpen);
        $admin = $this->makeAdmin();
        $voter = $this->makeContestant($event, 1);
        app(VotingLifecycleService::class)->close($event, $admin);

        $this->assertThrows(fn () => $this->vote($voter, [$voter->cars[0]]), VotingException::class);
        $this->assertThrows(fn () => $this->vote($voter, [$voter->cars[0]], \App\Enums\VoteSource::Manual, $admin), VotingException::class);
        $this->assertThrows(fn () => app(RegistrationService::class)->createContestant($event->fresh(), ['name' => 'Late'], $admin), VotingException::class);
        $this->assertThrows(fn () => app(RegistrationService::class)->registerCar($voter, ['category_id' => $voter->cars[0]->category_id, 'description' => 'Late car'], $admin), VotingException::class);
    }

    public function test_registration_continues_while_voting_open_but_structural_changes_are_frozen(): void
    {
        $event = $this->makeEvent();
        $admin = $this->makeAdmin();
        $cat = $this->makeCategory(1, 'A');
        $other = $this->makeCategory(2, 'B');
        $p = $this->makeContestant($event, 1, $cat);
        $q = $this->makeContestant($event, 1, $cat);
        $this->forceStatus($event, EventStatus::VotingOpen);
        $registration = app(RegistrationService::class);

        [$late] = $registration->createContestant($event, ['name' => 'Late Arrival'], $admin);
        $car = $registration->registerCar($late, ['category_id' => $cat->id, 'description' => '1970 Chevelle SS'], $admin);
        $this->assertSame(5, $late->fresh()->allowance());

        $existing = $p->cars[0];
        $this->assertThrows(fn () => $registration->updateCar($existing, ['category_id' => $other->id], $admin), VotingException::class);
        $this->assertThrows(fn () => $registration->updateCar($existing, ['participant_id' => $q->id], $admin), VotingException::class);
        $this->assertThrows(fn () => $registration->deleteCar($existing, $admin), VotingException::class);

        $registration->updateCar($existing, ['description' => '1969 Camaro Z28'], $admin);
        $this->assertSame('1969 Camaro Z28', $existing->fresh()->description);
        $this->assertSame($cat->id, $car->category_id);
    }

    public function test_entry_numbers_auto_increment_and_collisions_fail(): void
    {
        $event = $this->makeEvent();
        $cat = $this->makeCategory(1, 'A');
        $p = $this->makeContestant($event, 2, $cat);
        $this->assertSame([1, 2], $p->cars->pluck('entry_number')->all());

        $registration = app(RegistrationService::class);
        $this->assertThrows(fn () => $registration->registerCar($p, ['category_id' => $cat->id, 'entry_number' => 2, 'description' => 'x'], null), VotingException::class);
        $car = $registration->registerCar($p, ['category_id' => $cat->id, 'entry_number' => 40, 'description' => 'x'], null);
        $this->assertSame(40, $car->entry_number);
        $this->assertSame(41, $registration->nextEntryNumber($event));
    }

    public function test_voter_number_is_lowest_car_and_stays_stable(): void
    {
        $event = $this->makeEvent();
        $cat = $this->makeCategory(1, 'A');
        $registration = app(RegistrationService::class);
        [$p] = $registration->createContestant($event, ['name' => 'Two Cars'], null);
        $this->assertNull($p->fresh()->voter_number);

        $registration->registerCar($p, ['category_id' => $cat->id, 'entry_number' => 12, 'description' => 'x'], null);
        $registration->registerCar($p, ['category_id' => $cat->id, 'entry_number' => 7, 'description' => 'y'], null);

        $this->assertSame(12, $p->fresh()->voter_number, 'Assigned at first car and kept stable');
        $this->assertSame(10, $p->fresh()->allowance(), 'One shared allowance for both cars');
        $this->assertSame(1, $p->user->participants()->count(), 'One account, not one per car');
    }

    public function test_login_codes_are_unique_random_and_rotation_invalidates_old_code(): void
    {
        $event = $this->makeEvent();
        $admin = $this->makeAdmin();
        $codes = app(LoginCodeService::class);
        [$p, $plain] = app(RegistrationService::class)->createContestant($event, ['name' => 'Coder'], $admin);

        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{10}$/', $plain);
        $this->assertStringNotContainsString($plain, $p->fresh()->login_code_encrypted);
        $this->assertSame($p->id, $codes->findParticipant(strtolower($codes->format($plain)), $event)->id);
        $this->assertSame($codes->format($plain), $codes->reveal($p->fresh()));

        $version = $p->fresh()->session_version;
        $codes->rotate($p, $admin);
        $this->assertNull($codes->findParticipant($plain, $event));
        $this->assertSame($version + 1, $p->fresh()->session_version);
        $this->assertNotNull($codes->findParticipant($codes->reveal($p->fresh()), $event));
        $this->assertDatabaseHas('audit_logs', ['action' => 'login_code.rotated']);
    }

    public function test_likely_matches_find_existing_person_by_name_email_or_phone(): void
    {
        $event = $this->makeEvent();
        $registration = app(RegistrationService::class);
        $registration->createContestant($event, ['name' => 'Pat Example', 'email' => 'pat@example.test', 'phone' => '(770) 555-0199'], null);

        $this->assertCount(1, $registration->likelyMatches($event, ['name' => 'Pat Example']));
        $this->assertCount(1, $registration->likelyMatches($event, ['name' => 'Jordan Example']));
        $this->assertCount(1, $registration->likelyMatches($event, ['email' => 'pat@example.test']));
        $this->assertCount(1, $registration->likelyMatches($event, ['phone' => '770-555-0199']));
        $this->assertCount(0, $registration->likelyMatches($event, ['name' => 'Nobody Here']));
    }
}
