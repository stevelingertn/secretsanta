<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Core car show schema. Every history-bearing foreign key uses RESTRICT so ballots,
 * votes, tiebreaks and awards can never be removed by a cascading delete.
 * Composite (id, event_id) keys make cross-event references impossible at the DB level.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('year');
            $table->string('location')->nullable();
            $table->date('show_date')->nullable();
            $table->text('details')->nullable();
            $table->boolean('is_test')->default(false);
            $table->boolean('is_active')->default(false);
            $table->string('status', 20)->default('setup');
            $table->unsignedTinyInteger('votes_per_car')->default(5);
            $table->timestamp('voting_opened_at')->nullable();
            $table->timestamp('voting_closed_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->char('closed_tally_hash', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            // IDs come from carClasses.csv and are preserved, so no auto-increment reliance.
            $table->unsignedBigInteger('id')->primary();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        Schema::create('participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('voter_number')->nullable();
            $table->text('login_code_encrypted');
            $table->char('login_code_hash', 64)->unique();
            $table->unsignedInteger('session_version')->default(1);
            $table->unsignedInteger('allowance_override')->nullable();
            $table->timestamp('code_rotated_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
            $table->unique(['event_id', 'voter_number']);
            $table->unique(['id', 'event_id']);
        });

        Schema::create('cars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('participant_id');
            $table->unsignedBigInteger('category_id');
            $table->unsignedInteger('entry_number');
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('make', 60)->nullable();
            $table->string('model', 80)->nullable();
            $table->string('description');
            $table->string('photo_path')->nullable();
            $table->string('thumb_path')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'entry_number']);
            $table->unique(['id', 'event_id']);
            $table->index(['event_id', 'category_id']);
            $table->foreign(['participant_id', 'event_id'])->references(['id', 'event_id'])->on('participants')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('category_id')->references('id')->on('categories')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('ballot_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('participant_id');
            $table->string('source', 10);
            $table->foreignId('entered_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->uuid('idempotency_key');
            $table->unsignedInteger('vote_count');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['event_id', 'idempotency_key']);
            $table->unique(['id', 'event_id']);
            $table->foreign(['participant_id', 'event_id'])->references(['id', 'event_id'])->on('participants')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('participant_id');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('car_id');
            $table->unsignedBigInteger('ballot_submission_id');
            $table->string('source', 10);
            $table->foreignId('entered_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            // One contestant vote per car for the whole show, across online and paper.
            $table->unique(['event_id', 'participant_id', 'car_id']);
            $table->index(['event_id', 'car_id']);
            $table->foreign(['participant_id', 'event_id'])->references(['id', 'event_id'])->on('participants')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['car_id', 'event_id'])->references(['id', 'event_id'])->on('cars')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['ballot_submission_id', 'event_id'])->references(['id', 'event_id'])->on('ballot_submissions')->restrictOnDelete()->restrictOnUpdate();
        });

        // System tiebreaker votes: award-scoped, never mixed with contestant votes.
        Schema::create('award_tiebreaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->string('scope_key', 40);
            $table->unsignedBigInteger('category_id')->nullable();
            $table->json('candidates');
            $table->unsignedInteger('tied_votes');
            $table->unsignedBigInteger('chosen_car_id');
            $table->unsignedTinyInteger('system_votes')->default(1);
            $table->char('tally_hash', 64);
            $table->foreignId('resolved_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['event_id', 'scope_key']);
            $table->foreign('category_id')->references('id')->on('categories')->restrictOnDelete();
            $table->foreign(['chosen_car_id', 'event_id'])->references(['id', 'event_id'])->on('cars')->restrictOnDelete()->restrictOnUpdate();
        });

        // Immutable snapshot written once at finalize.
        Schema::create('awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->string('scope_key', 40);
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedInteger('position');
            $table->string('outcome', 20);
            $table->unsignedBigInteger('car_id')->nullable();
            $table->unsignedInteger('contestant_votes')->default(0);
            $table->unsignedInteger('system_votes')->default(0);
            $table->unsignedInteger('entry_number')->nullable();
            $table->string('vehicle')->nullable();
            $table->string('owner_name')->nullable();
            $table->string('category_name', 100)->nullable();
            $table->string('explanation')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['event_id', 'scope_key']);
            $table->foreign('category_id')->references('id')->on('categories')->restrictOnDelete();
            $table->foreign(['car_id', 'event_id'])->references(['id', 'event_id'])->on('cars')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action', 60);
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['event_id', 'action']);
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'awards', 'award_tiebreaks', 'votes', 'ballot_submissions', 'cars', 'participants', 'categories', 'events'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
