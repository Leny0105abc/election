<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_year_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('type', 30)->default('sslg');
            $table->text('description')->nullable();
            $table->string('banner_path')->nullable();
            // Stored in UTC; displayed in Asia/Manila.
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('eligible_grades')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->text('tie_resolution_rule')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('results_published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Position catalogue (school-wide defaults reused across elections).
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->unsignedTinyInteger('max_winners')->default(1);
            $table->json('eligible_voter_grades')->nullable();
            $table->json('eligible_candidate_grades')->nullable();
            $table->boolean('allow_abstain')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Per-election copy of each position, so editing the catalogue
        // never changes an election that has already started.
        Schema::create('election_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->unsignedTinyInteger('max_winners')->default(1);
            $table->json('eligible_voter_grades')->nullable();
            $table->json('eligible_candidate_grades')->nullable();
            $table->boolean('allow_abstain')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['election_id', 'name']);
            $table->index(['election_id', 'display_order']);
        });

        Schema::create('party_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('acronym', 20)->nullable();
            $table->string('color', 7)->default('#64748b');
            $table->string('logo_path')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['election_id', 'name']);
        });

        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->foreignId('election_position_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('party_list_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ballot_name', 150)->nullable();
            $table->unsignedTinyInteger('grade_level');
            $table->string('photo_path')->nullable();
            $table->text('platform')->nullable();
            $table->text('biography')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->string('status_reason')->nullable();
            $table->timestamps();

            // A student may run for only one position per election.
            $table->unique(['election_id', 'student_id']);
            $table->index(['election_position_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidates');
        Schema::dropIfExists('party_lists');
        Schema::dropIfExists('election_positions');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('elections');
    }
};
