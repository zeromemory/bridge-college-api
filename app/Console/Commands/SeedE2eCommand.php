<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\ApplicationEducation;
use App\Models\ApplicationExtra;
use App\Models\ApplicationPersonalDetail;
use App\Models\Branch;
use App\Models\Program;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

class SeedE2eCommand extends Command
{
    protected $signature = 'e2e:seed';

    protected $description = 'Reset the database and seed it with known E2E test data (LOCAL ONLY — blocked in production)';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('⛔ e2e:seed is BLOCKED in production. It wipes the entire database.');
            $this->error('Use "php artisan db:seed-admin" to safely create admin user.');

            return self::FAILURE;
        }

        $this->info('Running migrate:fresh --seed...');
        Artisan::call('migrate:fresh', [
            '--seed' => true,
            '--force' => true,
        ]);
        $this->info('Database migrated and seeded with programs + branches.');

        // Create verified student user
        $this->info('Creating E2E student user...');
        $student = User::create([
            'name' => 'E2E Test Student',
            'cnic' => '35202-1111111-1',
            'email' => 'e2e-student@test.com',
            'mobile' => '03001234567',
            'nationality' => 'pakistani',
            'password' => Hash::make('Test1234!'),
            'role' => 'student',
            'is_active' => true,
        ]);
        $student->forceFill(['email_verified_at' => now()])->save();
        $this->info("Student created: {$student->email} (ID: {$student->id})");

        // Create verified admin user
        $this->info('Creating E2E admin user...');
        $admin = User::create([
            'name' => 'E2E Admin',
            'cnic' => '35202-9999999-1',
            'email' => 'e2e-admin@test.com',
            'password' => Hash::make('Bci@Admin#2026!'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $this->info("Admin created: {$admin->email} (ID: {$admin->id})");

        // Create a submitted application for admin tests
        $this->info('Creating submitted application...');
        $program = Program::first();
        $branch = Branch::first();

        $application = Application::create([
            'user_id' => $student->id,
            'program_id' => $program->id,
            'branch_id' => $branch->id,
            'application_number' => 'BCI-2026-00001',
            'status' => 'submitted',
            'study_mode' => 'at_home',
            'city' => 'Islamabad',
            'submitted_at' => now(),
        ]);
        $this->info("Application created: {$application->application_number} (ID: {$application->id})");

        // Personal details
        ApplicationPersonalDetail::create([
            'application_id' => $application->id,
            'father_name' => 'Test Father',
            'father_cnic' => '35202-2222222-1',
            'father_phone' => '03009876543',
            'gender' => 'male',
            'date_of_birth' => '2000-01-15',
            'nationality' => 'Pakistani',
            'religion' => 'Islam',
            'postal_address' => '123 Test Street, Islamabad',
            'permanent_address' => '123 Test Street, Islamabad',
            'same_address' => true,
        ]);
        $this->info('Personal details created.');

        // Education record
        ApplicationEducation::create([
            'application_id' => $application->id,
            'qualification' => 'Matriculation',
            'board_university' => 'Federal Board',
            'exam_year' => 2024,
            'total_marks' => 1100,
            'obtained_marks' => 920,
            'sort_order' => 0,
        ]);
        $this->info('Education record created.');

        // Extra information
        ApplicationExtra::create([
            'application_id' => $application->id,
            'study_from' => 'within_pakistan',
            'prior_computer_knowledge' => true,
            'has_computer' => true,
            'internet_type' => 'fiber',
        ]);
        $this->info('Extra information created.');

        // Create a second submitted application for reject test
        $this->info('Creating second submitted application...');
        $student2 = User::create([
            'name' => 'E2E Second Student',
            'cnic' => '35202-3333333-1',
            'email' => 'e2e-student2@test.com',
            'mobile' => '03007654321',
            'nationality' => 'pakistani',
            'password' => Hash::make('Test1234!'),
            'role' => 'student',
            'is_active' => true,
        ]);
        $student2->forceFill(['email_verified_at' => now()])->save();

        $app2 = Application::create([
            'user_id' => $student2->id,
            'program_id' => $program->id,
            'branch_id' => $branch->id,
            'application_number' => 'BCI-2026-00002',
            'status' => 'submitted',
            'study_mode' => 'virtual_campus',
            'city' => 'Lahore',
            'submitted_at' => now(),
        ]);

        ApplicationPersonalDetail::create([
            'application_id' => $app2->id,
            'father_name' => 'Second Father',
            'father_cnic' => '35202-4444444-1',
            'father_phone' => '03005555555',
            'gender' => 'female',
            'date_of_birth' => '2001-06-20',
            'nationality' => 'Pakistani',
            'religion' => 'Islam',
            'postal_address' => '456 Test Avenue, Lahore',
            'permanent_address' => '456 Test Avenue, Lahore',
            'same_address' => true,
        ]);

        ApplicationEducation::create([
            'application_id' => $app2->id,
            'qualification' => 'Intermediate',
            'board_university' => 'Lahore Board',
            'exam_year' => 2023,
            'total_marks' => 1100,
            'obtained_marks' => 850,
            'sort_order' => 0,
        ]);

        ApplicationExtra::create([
            'application_id' => $app2->id,
            'study_from' => 'within_pakistan',
            'prior_computer_knowledge' => false,
            'has_computer' => false,
            'internet_type' => '3g4g',
        ]);
        $this->info("Second application created: {$app2->application_number}");

        $this->newLine();
        $this->info('E2E seed complete!');
        $this->table(
            ['Role', 'Email', 'Password'],
            [
                ['Student', 'e2e-student@test.com', 'Test1234!'],
                ['Admin', 'e2e-admin@test.com', 'Bci@Admin#2026!'],
            ]
        );

        return self::SUCCESS;
    }
}
