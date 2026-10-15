<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matching_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('preferred_skills')->nullable();
            $table->json('positive_keywords')->nullable();
            $table->json('negative_keywords')->nullable();
            $table->decimal('min_budget', 18, 2)->nullable();
            $table->decimal('max_budget', 18, 2)->nullable();
            $table->string('preferred_currency', 10)->nullable();
            $table->unsignedTinyInteger('min_score')->default(0);
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matching_profiles');
    }
};
