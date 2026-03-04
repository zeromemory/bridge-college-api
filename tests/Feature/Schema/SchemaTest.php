<?php

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ApplicationEducation;
use App\Models\ApplicationExtra;
use App\Models\ApplicationPersonalDetail;
use App\Models\Branch;
use App\Models\FeeChallan;
use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

it('creates all required tables via migrations', function () {
    // RefreshDatabase already runs migrations before each test
    expect(Schema::hasTable('users'))->toBeTrue()
        ->and(Schema::hasTable('programs'))->toBeTrue()
        ->and(Schema::hasTable('branches'))->toBeTrue()
        ->and(Schema::hasTable('applications'))->toBeTrue()
        ->and(Schema::hasTable('application_personal_details'))->toBeTrue()
        ->and(Schema::hasTable('application_education'))->toBeTrue()
        ->and(Schema::hasTable('application_documents'))->toBeTrue()
        ->and(Schema::hasTable('application_extras'))->toBeTrue()
        ->and(Schema::hasTable('fee_challans'))->toBeTrue();
});

it('creates program with factory', function () {
    $program = Program::factory()->create();

    expect($program)->toBeInstanceOf(Program::class)
        ->and($program->name)->not->toBeEmpty()
        ->and($program->slug)->not->toBeEmpty()
        ->and($program->level)->toBeIn(['ssc', 'hssc', 'short_course'])
        ->and($program->is_active)->toBeTrue();
});

it('creates branch with factory', function () {
    $branch = Branch::factory()->create();

    expect($branch)->toBeInstanceOf(Branch::class)
        ->and($branch->name)->not->toBeEmpty()
        ->and($branch->address)->not->toBeEmpty()
        ->and($branch->city)->not->toBeEmpty()
        ->and($branch->phones)->toBeArray()
        ->and($branch->is_active)->toBeTrue();
});

it('creates application with all relations', function () {
    $user = User::factory()->create();
    $program = Program::factory()->create();
    $branch = Branch::factory()->create();

    $application = Application::factory()->create([
        'user_id' => $user->id,
        'program_id' => $program->id,
        'branch_id' => $branch->id,
    ]);

    $personalDetail = ApplicationPersonalDetail::factory()->create([
        'application_id' => $application->id,
    ]);

    $education = ApplicationEducation::factory()->create([
        'application_id' => $application->id,
    ]);

    $document = ApplicationDocument::factory()->create([
        'application_id' => $application->id,
    ]);

    $extras = ApplicationExtra::factory()->create([
        'application_id' => $application->id,
    ]);

    $challan = FeeChallan::factory()->create([
        'application_id' => $application->id,
    ]);

    $application->refresh();

    expect($application->personalDetail)->toBeInstanceOf(ApplicationPersonalDetail::class)
        ->and($application->education)->toHaveCount(1)
        ->and($application->documents)->toHaveCount(1)
        ->and($application->extras)->toBeInstanceOf(ApplicationExtra::class)
        ->and($application->challans)->toHaveCount(1)
        ->and($application->user->id)->toBe($user->id)
        ->and($application->program->id)->toBe($program->id)
        ->and($application->branch->id)->toBe($branch->id);
});

it('generates unique application numbers', function () {
    $app1 = Application::factory()->create();
    $app2 = Application::factory()->create();

    expect($app1->application_number)->not->toBe($app2->application_number)
        ->and($app1->application_number)->toStartWith('BCI-2026-')
        ->and($app2->application_number)->toStartWith('BCI-2026-');
});

it('enforces unique application_number constraint', function () {
    $app = Application::factory()->create();

    Application::factory()->create([
        'application_number' => $app->application_number,
    ]);
})->throws(\Illuminate\Database\QueryException::class);

it('enforces unique program slug constraint', function () {
    $program = Program::factory()->create(['slug' => 'test-slug']);

    Program::factory()->create(['slug' => 'test-slug']);
})->throws(\Illuminate\Database\QueryException::class);

it('enforces unique application_id on personal details', function () {
    $application = Application::factory()->create();

    ApplicationPersonalDetail::factory()->create(['application_id' => $application->id]);
    ApplicationPersonalDetail::factory()->create(['application_id' => $application->id]);
})->throws(\Illuminate\Database\QueryException::class);

it('enforces unique application_id on extras', function () {
    $application = Application::factory()->create();

    ApplicationExtra::factory()->create(['application_id' => $application->id]);
    ApplicationExtra::factory()->create(['application_id' => $application->id]);
})->throws(\Illuminate\Database\QueryException::class);

it('enforces unique challan_number constraint', function () {
    $challan = FeeChallan::factory()->create();

    FeeChallan::factory()->create([
        'challan_number' => $challan->challan_number,
    ]);
})->throws(\Illuminate\Database\QueryException::class);

it('tests user has many applications relationship', function () {
    $user = User::factory()->create();
    $program = Program::factory()->create();
    $branch = Branch::factory()->create();

    Application::factory()->count(3)->create([
        'user_id' => $user->id,
        'program_id' => $program->id,
        'branch_id' => $branch->id,
    ]);

    expect($user->applications)->toHaveCount(3);
});

it('tests program has many applications relationship', function () {
    $program = Program::factory()->create();
    $branch = Branch::factory()->create();

    Application::factory()->count(2)->create([
        'program_id' => $program->id,
        'branch_id' => $branch->id,
    ]);

    expect($program->applications)->toHaveCount(2);
});

it('tests branch has many applications relationship', function () {
    $branch = Branch::factory()->create();
    $program = Program::factory()->create();

    Application::factory()->count(2)->create([
        'branch_id' => $branch->id,
        'program_id' => $program->id,
    ]);

    expect($branch->applications)->toHaveCount(2);
});

it('tests user has many challans through applications', function () {
    $user = User::factory()->create();
    $program = Program::factory()->create();
    $branch = Branch::factory()->create();

    $application = Application::factory()->create([
        'user_id' => $user->id,
        'program_id' => $program->id,
        'branch_id' => $branch->id,
    ]);

    FeeChallan::factory()->count(2)->create([
        'application_id' => $application->id,
    ]);

    expect($user->challans)->toHaveCount(2);
});

it('tests application reviewer relationship', function () {
    $admin = User::factory()->admin()->create();

    $application = Application::factory()->accepted()->create([
        'reviewed_by' => $admin->id,
    ]);

    expect($application->reviewer->id)->toBe($admin->id)
        ->and($application->reviewer->role)->toBe('admin');
});

it('allows multiple education records per application', function () {
    $application = Application::factory()->create();

    ApplicationEducation::factory()->count(3)->create([
        'application_id' => $application->id,
    ]);

    expect($application->education)->toHaveCount(3);
});

it('allows multiple documents per application', function () {
    $application = Application::factory()->create();

    ApplicationDocument::factory()->count(5)->create([
        'application_id' => $application->id,
    ]);

    expect($application->documents)->toHaveCount(5);
});

it('casts branch phones as array', function () {
    $branch = Branch::factory()->create([
        'phones' => ['042-36831098', '042-36851619'],
    ]);

    $branch->refresh();

    expect($branch->phones)->toBeArray()
        ->and($branch->phones)->toHaveCount(2);
});

it('casts fee challan amount as decimal', function () {
    $challan = FeeChallan::factory()->create(['amount' => 15000.50]);

    $challan->refresh();

    expect($challan->amount)->toBe('15000.50');
});

it('casts personal detail dates correctly', function () {
    $detail = ApplicationPersonalDetail::factory()->create([
        'date_of_birth' => '2005-06-15',
        'cnic_issuance_date' => '2023-01-10',
    ]);

    $detail->refresh();

    expect($detail->date_of_birth)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($detail->cnic_issuance_date)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($detail->same_address)->toBeBool();
});

it('cascades delete from application to related records', function () {
    $application = Application::factory()->create();

    ApplicationPersonalDetail::factory()->create(['application_id' => $application->id]);
    ApplicationEducation::factory()->count(2)->create(['application_id' => $application->id]);
    ApplicationDocument::factory()->count(2)->create(['application_id' => $application->id]);
    ApplicationExtra::factory()->create(['application_id' => $application->id]);
    FeeChallan::factory()->create(['application_id' => $application->id]);

    $appId = $application->id;
    $application->delete();

    expect(ApplicationPersonalDetail::where('application_id', $appId)->count())->toBe(0)
        ->and(ApplicationEducation::where('application_id', $appId)->count())->toBe(0)
        ->and(ApplicationDocument::where('application_id', $appId)->count())->toBe(0)
        ->and(ApplicationExtra::where('application_id', $appId)->count())->toBe(0)
        ->and(FeeChallan::where('application_id', $appId)->count())->toBe(0);
});
