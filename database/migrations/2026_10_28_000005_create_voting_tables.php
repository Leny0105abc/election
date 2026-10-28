<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Voting tables.
 *
 * election_voters = WHO may vote and who has voted (identity, never choices).
 * anonymous_ballots + ballot_selections = WHAT was voted (choices, never identity).
 *
 * Nothing links the two halves: no foreign key, no timestamp, no sequential id.
 * Ballot and selection ids are random UUIDv4 values, so their order in the
 * InnoDB clustered index does not follow submission order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('election_voters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            // Snapshot taken when the voter roll is generated, so later edits to
            // a student's grade or section never change how a ballot is judged.
            $table->unsignedTinyInteger('grade_level');
            $table->string('section', 60);
            $table->boolean('has_voted')->default(false);
            // Rounded down to the minute (enough for turnout charts).
            $table->timestamp('voted_at')->nullable();
            $table->timestamps();

            // One participation record per student per election.
            $table->unique(['election_id', 'student_id']);
            $table->index(['election_id', 'has_voted']);
            $table->index(['election_id', 'grade_level']);
        });

        Schema::create('anonymous_ballots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('election_id')->constrained()->restrictOnDelete();
            // Intentionally no voter id and no timestamps.
        });

        Schema::create('ballot_selections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('anonymous_ballot_id')->constrained('anonymous_ballots')->cascadeOnDelete();
            $table->foreignId('election_id')->constrained()->restrictOnDelete();
            $table->foreignId('election_position_id')->constrained()->restrictOnDelete();
            $table->foreignId('candidate_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('is_abstain')->default(false);

            // A ballot cannot select the same candidate twice.
            $table->unique(['anonymous_ballot_id', 'election_position_id', 'candidate_id'], 'ballot_selection_unique');
            $table->index(['election_position_id', 'candidate_id']);
        });

        // Frozen official tallies, written once when an election is finalized.
        Schema::create('election_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->foreignId('election_position_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('is_abstain_row')->default(false);
            $table->unsignedInteger('votes');
            $table->decimal('percentage', 6, 2)->default(0);
            $table->unsignedSmallInteger('rank')->nullable();
            $table->boolean('is_winner')->default(false);
            $table->boolean('is_tied')->default(false);
            $table->timestamps();

            $table->unique(['election_position_id', 'candidate_id'], 'result_row_unique');
        });

        // Election committee decisions for ties that affect who wins.
        Schema::create('tie_resolutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->foreignId('election_position_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('winning_candidate_ids');
            $table->text('method_and_notes');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tie_resolutions');
        Schema::dropIfExists('election_results');
        Schema::dropIfExists('ballot_selections');
        Schema::dropIfExists('anonymous_ballots');
        Schema::dropIfExists('election_voters');
    }
};
