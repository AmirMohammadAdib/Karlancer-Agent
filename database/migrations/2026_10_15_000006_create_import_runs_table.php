<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('import_runs', function (Blueprint $table) { $table->id(); $table->string('source', 50); $table->string('status', 20)->default('running'); $table->timestamp('started_at')->nullable(); $table->timestamp('finished_at')->nullable(); $table->unsignedInteger('projects_fetched')->default(0); $table->unsignedInteger('projects_created')->default(0); $table->unsignedInteger('projects_updated')->default(0); $table->unsignedInteger('projects_skipped')->default(0); $table->json('errors')->nullable(); $table->timestamps(); $table->index(['source', 'status']); }); }
    public function down(): void { Schema::dropIfExists('import_runs'); }
};
