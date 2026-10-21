<?php

use App\Contracts\AiTextGenerator;
use App\Enums\ResponseStage;
use App\Models\Residence;
use App\Models\ResidentReport;
use App\Models\ResponseCase;
use App\Models\ResponseConfirmation;
use App\Models\ResponseTask;
use App\Models\ResponseUpdate;
use App\Models\User;
use Database\Seeders\IncidentCommandCenterDemoSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

function demoResidence(string $name): Residence
{
    return Residence::where('name', $name)->firstOrFail();
}

function demoUser(string $key): User
{
    return User::where('email', "demo.{$key}@heatalert.test")->firstOrFail();
}

beforeEach(function () {
    Cache::flush();

    // No AI provider configured: the demo must work with rule-based and keyword links only.
    app()->instance(AiTextGenerator::class, new class implements AiTextGenerator
    {
        public function isAvailable(): bool
        {
            return false;
        }

        public function generate(string $system, string $prompt, int $maxTokens = 1200): ?string
        {
            return null;
        }
    });

    $this->admin = User::factory()->admin()->create();
    $this->seed(IncidentCommandCenterDemoSeeder::class);
});

it('creates a realistic dataset with response cases at several workflow stages', function () {
    expect(Residence::count())->toBe(5)
        ->and(User::where('role', User::ROLE_MANAGER)->count())->toBe(3)
        ->and(User::where('role', User::ROLE_RESIDENT)->count())->toBe(11)
        ->and(ResidentReport::count())->toBeGreaterThanOrEqual(30)
        ->and(ResidentReport::min('reported_at'))->toBeGreaterThanOrEqual(today()->subDays(90)->toDateString())
        ->and(ResponseCase::pluck('stage')->map->value->unique()->sort()->values()->all())->toBe(['action', 'investigating', 'reported', 'resolved', 'verification'])
        ->and(ResponseTask::whereNotNull('completed_at')->count())->toBeGreaterThan(0)
        ->and(ResponseUpdate::where('is_public', false)->count())->toBeGreaterThan(0)
        ->and(ResponseConfirmation::where('is_resolved', false)->count())->toBe(1);

    $case = ResponseCase::where('title', 'Lobby and shared hall cooling failure')->sole();
    expect($case->reports)->toHaveCount(3)
        ->and($case->tasks->filter->isOverdue())->toHaveCount(1)
        ->and($case->stage)->toBe(ResponseStage::Action)
        ->and($case->reports->firstWhere('title', 'No cold air in the shared hall')->pivot->source)->toBe('constellation');
});

it('can be rerun without duplicating anything or touching existing accounts', function () {
    $existing = demoUser('resident.amel');
    $existing->forceFill(['name' => 'A Real Person', 'password' => 'my-own-password'])->save();
    $password = $existing->fresh()->password;
    $counts = fn () => [
        User::count(), Residence::count(), ResidentReport::count(), ResponseCase::count(), ResponseTask::count(),
        ResponseUpdate::count(), ResponseConfirmation::count(), DB::table('response_case_report')->count(), DB::table('residence_user')->count(),
    ];

    $this->seed(IncidentCommandCenterDemoSeeder::class);
    $first = $counts();
    $this->seed(IncidentCommandCenterDemoSeeder::class);

    expect($counts())->toBe($first)
        ->and($existing->fresh())->name->toBe('A Real Person')->password->toBe($password);
});

it('shows each manager only the cases of their own residences', function () {
    $this->actingAs(demoUser('manager.nadia'))->get(route('command-center.index'))
        ->assertOk()
        ->assertSee('Lobby and shared hall cooling failure')
        ->assertSee('Parking ramp pipe leak')
        ->assertDontSee('Recurring afternoon power cuts')
        ->assertDontSee('Corridor ceiling crack and basement water ingress');

    $case = ResponseCase::where('title', 'Recurring afternoon power cuts')->sole();
    $this->actingAs(demoUser('manager.nadia'))->get(route('response-cases.show', $case))->assertForbidden();
    $this->actingAs(demoUser('manager.walid'))->get(route('response-cases.show', $case))->assertOk()->assertSee('Awaiting Verification');
});

it('gives a resident their own timeline with only the published updates', function () {
    $amel = demoUser('resident.amel');

    $this->actingAs($amel)->get(route('resident-reports.index'))
        ->assertOk()
        ->assertSee('Live tracking of your reports')
        ->assertSee('Lobby air conditioning has stopped')
        ->assertDontSee('Common room is unbearably hot');

    $report = ResidentReport::where('title', 'Lobby air conditioning has stopped')->sole();

    $this->actingAs($amel)->get(route('resident-reports.show', $report))
        ->assertOk()
        ->assertSee('The compressor is confirmed faulty')
        ->assertDontSee('Supplier quote for the compressor is high');
});

it('asks the resident to verify a case that awaits verification, once only for their own report', function () {
    $imen = demoUser('resident.imen');
    $fatma = demoUser('resident.fatma');
    $report = ResidentReport::where('title', 'Fridge shut off after another power cut')->sole();

    $this->actingAs($imen)->get(route('resident-reports.show', $report))->assertSee('You answered: the issue still appears.');
    $this->actingAs($fatma)->post(route('resident-reports.confirmation', $report), ['is_resolved' => 1])->assertForbidden();
});

it('builds the constellation from the demo reports and links the differently worded cooling problems', function () {
    $nadia = demoUser('manager.nadia');
    $jasmins = demoResidence('Résidence Les Jasmins du Lac');

    $graph = $this->actingAs($nadia)->getJson(route('constellation.graph', ['residence_id' => $jasmins->id, 'days' => 365]))
        ->assertOk()
        ->assertJsonPath('semantic.available', false);

    $cooling = ResidentReport::where('residence_id', $jasmins->id)->where('category', 'cooling')->whereNull('resolved_at')->pluck('id')->all();
    $linked = collect($graph->json('edges'))->filter(fn ($edge) => in_array($edge['source'], $cooling, true) && in_array($edge['target'], $cooling, true));

    expect($linked)->not->toBeEmpty()->and($linked->every(fn ($edge) => $edge['keyword'] || $edge['pattern']))->toBeTrue();

    // Nothing from another manager's residence leaks into the graph.
    expect($graph->getContent())->not->toContain('Oliviers')->not->toContain('Afternoon power cuts');
});

it('summarizes response performance for the admin dashboard', function () {
    $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Incident Command Center')
        ->assertSee('Longest-running unresolved cases')
        ->assertSee('Corridor ceiling crack and basement water ingress');

    $this->actingAs($this->admin)->get(route('command-center.index'))
        ->assertOk()
        ->assertSee('Response performance')
        ->assertSee('Overdue tasks')
        ->assertSee('Pressure by residence');
});

it('also fills the residences of real managers, leaves a report awaiting triage and does not duplicate on rerun', function () {
    $manager = User::factory()->manager()->create(['email' => 'real.manager@heatalert.test']);
    $residence = Residence::factory()->create(['user_id' => $manager->id]);
    $residence->members()->attach(User::factory()->create()->id);

    $this->seed(IncidentCommandCenterDemoSeeder::class);
    $this->seed(IncidentCommandCenterDemoSeeder::class);

    expect(ResponseCase::where('residence_id', $residence->id)->pluck('title')->sort()->values()->all())->toBe(['Entrance hall cooling failure', 'Evening power cuts'])
        ->and(ResidentReport::where('residence_id', $residence->id)->count())->toBe(7);

    $this->actingAs($manager)->get(route('command-center.index'))
        ->assertOk()
        ->assertSee('Entrance hall cooling failure')
        ->assertSee('Leak under the staircase');

    // The admin sees these cases next to everyone else's.
    $this->actingAs($this->admin)->get(route('command-center.index'))->assertOk()->assertSee('Evening power cuts')->assertSee('Parking ramp pipe leak');
});

it('fills the Response Tracker of the configured resident, whose residences have no manager', function () {
    $resident = User::factory()->create(['email' => 'slim.aziz@hotmail.com']);

    foreach (['Residence Slim - Centre' => ['Air conditioning not cooling', 'Water pressure drop in afternoon'], 'Villa Aziz' => ['Power cut during heat peak', 'Elderly resident feeling unwell'], 'Residence Le Lac Tower' => ['Elevator out of service', 'Rooftop shade request']] as $name => $titles) {
        $residence = Residence::factory()->create(['user_id' => $resident->id, 'name' => $name]);

        foreach ($titles as $title) {
            ResidentReport::factory()->create(['residence_id' => $residence->id, 'user_id' => $resident->id, 'title' => $title, 'status' => 'Pending']);
        }
    }

    $this->seed(IncidentCommandCenterDemoSeeder::class);
    $this->seed(IncidentCommandCenterDemoSeeder::class);

    $this->actingAs($resident)->get(route('resident-reports.index'))
        ->assertOk()
        ->assertSee('Action in Progress')
        ->assertSee('Awaiting Verification')
        ->assertSee('Under Investigation')
        ->assertSee('Rooftop shade request')
        ->assertSee('Waiting for a response case');

    $report = ResidentReport::where('title', 'Power cut during heat peak')->sole();
    $this->actingAs($resident)->get(route('resident-reports.show', $report))
        ->assertOk()
        ->assertSee('Does the issue still appear?')
        ->assertSee('Power was restored the same evening');

    expect(ResponseCase::followedBy($resident)->count())->toBe(3);
});
