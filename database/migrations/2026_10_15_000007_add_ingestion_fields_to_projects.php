<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('projects', function (Blueprint $table) { $table->string('canonical_url_hash', 64)->nullable()->unique(); $table->decimal('budget_min', 18, 2)->nullable(); $table->decimal('budget_max', 18, 2)->nullable(); $table->timestamp('published_at')->nullable(); $table->timestamp('first_seen_at')->nullable(); $table->timestamp('last_seen_at')->nullable(); }); }
    public function down(): void { Schema::table('projects', function (Blueprint $table) { $table->dropUnique(['canonical_url_hash']); $table->dropColumn(['canonical_url_hash', 'budget_min', 'budget_max', 'published_at', 'first_seen_at', 'last_seen_at']); }); }
};
