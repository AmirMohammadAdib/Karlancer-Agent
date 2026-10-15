<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matching_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->json('breakdown');
            $table->string('source_hash', 64);
            $table->timestamp('scored_at');
            $table->timestamps();
            $table->unique(['matching_profile_id', 'project_id']);
            $table->index(['matching_profile_id', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_matches');
    }
};
