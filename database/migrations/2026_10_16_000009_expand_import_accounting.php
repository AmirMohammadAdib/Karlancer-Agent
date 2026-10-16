<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Real KarLancer slugs can make the canonical URL longer than 255 bytes.
        // Keep the complete URL and continue using the fixed-size hash for lookup.
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE projects MODIFY canonical_url TEXT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE projects ALTER COLUMN canonical_url TYPE TEXT');
        } else {
            // Laravel's SQLite grammar rebuilds the table for this supported change.
            Schema::table('projects', function (Blueprint $table): void {
                $table->text('canonical_url')->nullable()->change();
            });
        }

        Schema::table('import_runs', function (Blueprint $table): void {
            $table->unsignedInteger('projects_rejected')->default(0)->after('projects_skipped');
            $table->unsignedInteger('projects_failed')->default(0)->after('projects_rejected');
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->string('category')->nullable()->after('currency');
            $table->string('source_status')->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('import_runs', function (Blueprint $table): void {
            $table->dropColumn(['projects_rejected', 'projects_failed']);
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn(['category', 'source_status']);
        });
        // Do not narrow canonical_url on rollback; doing so could destroy real data.
    }
};
