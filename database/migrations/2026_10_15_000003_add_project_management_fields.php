<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('currency', 10)->nullable()->after('budget');
        });

        Schema::table('project_activity_events', function (Blueprint $table) {
            $table->foreignId('actor_id')->nullable()->after('project_id')->constrained('users')->nullOnDelete();
            $table->string('action', 40)->nullable()->after('event_type');
        });
    }

    public function down(): void
    {
        Schema::table('project_activity_events', function (Blueprint $table) {
            $table->dropForeign(['actor_id']);
            $table->dropColumn(['actor_id', 'action']);
        });

        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn('currency'));
    }
};
