<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->environment(['local', 'testing'])) {
            Project::upsert([
                ['platform' => 'mock', 'external_project_id' => 'demo-001', 'title' => 'طراحی رابط کاربری فروشگاه آنلاین', 'description' => 'طراحی یک رابط کاربری فارسی و واکنش‌گرا برای فروشگاه آزمایشی.', 'budget' => '۱۰ تا ۱۵ میلیون تومان', 'deadline' => null, 'required_skills' => json_encode(['Figma', 'UI/UX', 'Tailwind']), 'discovered_at' => now(), 'availability_status' => 'available', 'decision_status' => 'new', 'approved_at' => null, 'rejected_at' => null, 'is_demo' => true, 'created_at' => now(), 'updated_at' => now()],
                ['platform' => 'mock', 'external_project_id' => 'demo-002', 'title' => 'توسعه API برای اپلیکیشن موبایل', 'description' => 'پیاده‌سازی چند endpoint امن برای نمونه آموزشی اپلیکیشن.', 'budget' => null, 'deadline' => null, 'required_skills' => json_encode(['Laravel', 'REST API', 'MySQL']), 'discovered_at' => now()->subHour(), 'availability_status' => 'available', 'decision_status' => 'approved', 'approved_at' => now()->subMinutes(30), 'rejected_at' => null, 'is_demo' => true, 'created_at' => now(), 'updated_at' => now()],
                ['platform' => 'mock', 'external_project_id' => 'demo-003', 'title' => 'بهینه‌سازی محتوای وب‌سایت', 'description' => 'بازبینی و بهبود محتوای یک وب‌سایت نمونه.', 'budget' => 'نامشخص', 'deadline' => null, 'required_skills' => json_encode(['تولید محتوا', 'SEO']), 'discovered_at' => now()->subHours(2), 'availability_status' => 'available', 'decision_status' => 'rejected', 'approved_at' => null, 'rejected_at' => now()->subHour(), 'is_demo' => true, 'created_at' => now(), 'updated_at' => now()],
            ], ['platform', 'external_project_id'], ['title', 'description', 'budget', 'deadline', 'required_skills', 'discovered_at', 'availability_status', 'decision_status', 'approved_at', 'rejected_at', 'is_demo', 'updated_at']);
        }
    }
}
