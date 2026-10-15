<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 50)->default('karlancer');
            $table->string('external_project_id')->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('title');
            $table->text('description');
            $table->string('budget')->nullable();
            $table->dateTime('deadline')->nullable();
            $table->json('required_skills')->nullable();
            $table->timestamp('discovered_at');
            $table->string('availability_status', 30)->default('available');
            $table->string('decision_status', 30)->default('new');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->unique(['platform', 'external_project_id']);
            $table->index(['decision_status', 'availability_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
