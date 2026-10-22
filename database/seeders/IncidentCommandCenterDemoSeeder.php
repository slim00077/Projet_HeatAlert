<?php

namespace Database\Seeders;

use App\Enums\ResponseStage;
use App\Models\Neighborhood;
use App\Models\Residence;
use App\Models\ResidentReport;
use App\Models\ResponseCase;
use App\Models\ResponseConfirmation;
use App\Models\ResponseTask;
use App\Models\ResponseUpdate;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Fictional demo data for the Incident Command Center, the AI Incident Constellation and the AI Incident Lens:
 * residences, residents' reports (with related and recurring problems) and response cases at every workflow
 * stage, with tasks, a timeline of updates and resident confirmations.
 *
 *   php artisan db:seed --class=IncidentCommandCenterDemoSeeder
 *
 * Safe to run several times and on a database that already holds data:
 * - Demo records are identified by stable keys (emails, residence name + owner, report title + residence,
 *   case title + residence, task title + case), so reruns never duplicate them. A rerun re-anchors the demo
 *   dates to today and resets the demo reports and cases to their seeded state; nothing outside the demo
 *   records is touched.
 * - Existing users are never modified: a demo account is only created when its email is not taken yet.
 * - Demo accounts use the development password "password" and are verified, so they can log in directly.
 *
 * Dates are relative to the day the seeder runs ("age" = days since the report, so the last 90 days are covered).
 */
class IncidentCommandCenterDemoSeeder extends Seeder
{
    private const PASSWORD = 'password';

    /**
     * key => [name, email, neighborhood name used when that neighborhood exists]
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private const MANAGERS = [
        'nadia' => ['Nadia Ferchichi', 'demo.manager.nadia@heatalert.test', 'La Marsa'],
        'walid' => ['Walid Ben Youssef', 'demo.manager.walid@heatalert.test', 'Ariana'],
        'ines' => ['Ines Mahjoub', 'demo.manager.ines@heatalert.test', 'Centre Ville'],
    ];

    /**
     * @var array<string, array{0: string, 1: string}>
     */
    private const RESIDENTS = [
        'amel' => ['Amel Jemni', 'demo.resident.amel@heatalert.test'],
        'bilel' => ['Bilel Ouertani', 'demo.resident.bilel@heatalert.test'],
        'chiraz' => ['Chiraz Hadded', 'demo.resident.chiraz@heatalert.test'],
        'dorra' => ['Dorra Khelifi', 'demo.resident.dorra@heatalert.test'],
        'elyes' => ['Elyes Sassi', 'demo.resident.elyes@heatalert.test'],
        'fatma' => ['Fatma Zouari', 'demo.resident.fatma@heatalert.test'],
        'hedi' => ['Hedi Belhadj', 'demo.resident.hedi@heatalert.test'],
        'imen' => ['Imen Rekik', 'demo.resident.imen@heatalert.test'],
        'jawher' => ['Jawher Triki', 'demo.resident.jawher@heatalert.test'],
        'khouloud' => ['Khouloud Mrad', 'demo.resident.khouloud@heatalert.test'],
        'lotfi' => ['Lotfi Chaabane', 'demo.resident.lotfi@heatalert.test'],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $managers = $this->seedUsers(self::MANAGERS, User::ROLE_MANAGER);
            $residents = $this->seedUsers(self::RESIDENTS, User::ROLE_RESIDENT);

            foreach ($this->residences() as $key => $definition) {
                $residence = $this->seedResidence($definition, $managers[$definition['manager']]);
                $residence->members()->syncWithoutDetaching(array_map(fn (string $member) => $residents[$member]->id, $definition['members']));

                foreach ($this->reports()[$key] ?? [] as $report) {
                    $this->seedReport($residence, $residents[$report['author']], $report);
                }

                foreach ($this->cases()[$key] ?? [] as $case) {
                    $this->seedCase($residence, $managers[$definition['manager']], $residents, $case);
                }
            }

            $this->seedExistingManagers();
            $this->seedTrackerResidents();
        });

        $this->command?->info('Incident Command Center demo data ready (5 residences, 3 managers, 11 residents, response cases at every stage).');
        $this->command?->line('Demo logins (password "password"): demo.manager.nadia@heatalert.test, demo.manager.walid@heatalert.test, demo.manager.ines@heatalert.test, demo.resident.amel@heatalert.test, demo.resident.dorra@heatalert.test, demo.resident.jawher@heatalert.test ...');
    }

    /**
     * Gives the real (non-demo) managers something to work with: in each approved residence they manage that already
     * has residents, adds a few reports from those residents and two response cases, leaving one report awaiting triage.
     * Accounts and residences are never modified; only new reports, cases and their records are added.
     */
    private function seedExistingManagers(): void
    {
        $managers = User::where('role', User::ROLE_MANAGER)->where('email', 'not like', 'demo.%')->get();

        foreach ($managers as $manager) {
            foreach ($manager->residences()->approved()->with('members')->get() as $residence) {
                if ($residence->members->isEmpty()) {
                    continue;
                }

                $people = ['m0' => $residence->members[0], 'm1' => $residence->members[1] ?? $residence->members[0]];

                foreach ($this->managedReports() as $report) {
                    $this->seedReport($residence, $people[$report['author']], $report);
                }

                foreach ($this->managedCases() as $case) {
                    $this->seedCase($residence, $manager, $people, $case);
                }
            }
        }
    }

    /**
     * Real resident accounts whose own reports get response cases, so their Response Tracker has something to show.
     * Their residences have no manager, so the cases are run by the first administrator.
     *
     * @var list<string>
     */
    private const TRACKER_RESIDENTS = ['slim.aziz@hotmail.com'];

    private function seedTrackerResidents(): void
    {
        $staff = User::where('role', User::ROLE_ADMIN)->orderBy('id')->first();

        if ($staff === null) {
            return;
        }

        foreach (User::whereIn('email', self::TRACKER_RESIDENTS)->where('role', User::ROLE_RESIDENT)->get() as $resident) {
            foreach ($this->trackerCases() as $residenceName => $case) {
                $residence = Residence::where('name', $residenceName)->where('user_id', $resident->id)->first();

                if ($residence !== null && $residence->residentReports()->whereIn('title', $case['reports'])->where('user_id', $resident->id)->count() === count($case['reports'])) {
                    $this->seedCase($residence, $staff, [], $case);
                }
            }
        }
    }

    /**
     * Cases for the reports of the Response Tracker accounts, by residence name. One report is left without a case.
     *
     * @return array<string, array<string, mixed>>
     */
    private function trackerCases(): array
    {
        $job = fn (string $title, string $description, ?string $crew, int $progress, int $due, ?int $doneAgo = null) => [
            'title' => $title, 'description' => $description, 'crew' => $crew, 'progress' => $progress, 'due' => $due, 'done_ago' => $doneAgo,
        ];

        return [
            'Residence Slim - Centre' => [
                'title' => 'Cooling and water pressure at Residence Slim - Centre',
                'summary' => 'Two comfort issues reported in the same building during the heat wave: weak air conditioning and low water pressure in the afternoon.',
                'stage' => 'action', 'priority' => 'high', 'created' => 6, 'stage_since' => 2, 'target' => 7,
                'reports' => ['Air conditioning not cooling', 'Water pressure drop in afternoon'],
                'tasks' => [
                    $job('Diagnose the air conditioning unit', 'Technician visit with a written diagnosis.', 'CoolFix HVAC', 100, -2, 2),
                    $job('Replace the AC filter and recharge the unit', 'Parts ordered, installation planned this week.', 'CoolFix HVAC', 50, 3),
                    $job('Check the water pump pressure', 'Measure pressure at peak hours and adjust the booster pump.', 'Plumber (Mr. Gharbi)', 0, 5),
                ],
                'updates' => [
                    [6, ResponseUpdate::KIND_CREATED, true, 'Response case opened for 2 related report(s).'],
                    [6, ResponseUpdate::KIND_STAGE, true, 'Status moved from Reported to Under Investigation. A technician will look at both problems.'],
                    [4, ResponseUpdate::KIND_NOTE, false, 'Pump pressure looks normal in the morning; need a reading at 5 pm.'],
                    [2, ResponseUpdate::KIND_STAGE, true, 'Status moved from Under Investigation to Action in Progress. The AC fault is confirmed and a filter and recharge are planned.'],
                    [1, ResponseUpdate::KIND_NOTE, true, 'The technician will come back on Thursday morning to finish the AC repair. We will measure the water pressure at 5 pm the same day.'],
                ],
            ],
            'Villa Aziz' => [
                'title' => 'Heat-peak power cut and resident welfare',
                'summary' => 'A power cut during the hottest hours and a resident who felt unwell. The power was restored and checks were done; waiting for confirmation.',
                'stage' => 'verification', 'priority' => 'critical', 'created' => 9, 'stage_since' => 1, 'target' => 2,
                'reports' => ['Power cut during heat peak', 'Elderly resident feeling unwell'],
                'tasks' => [
                    $job('Restore power and check the breaker', 'Electrician visit the same day.', 'Electrician (Elec-Plus)', 100, -8, 8),
                    $job('Check on the resident who felt unwell', 'Door-to-door visit and advice on cooling and hydration.', null, 100, -7, 7),
                    $job('Share heat-wave safety advice with the household', 'Leaflet and cool spots list.', null, 100, -3, 3),
                ],
                'updates' => [
                    [9, ResponseUpdate::KIND_CREATED, true, 'Response case opened for 2 related report(s).'],
                    [8, ResponseUpdate::KIND_STAGE, true, 'Status moved from Reported to Action in Progress. An electrician is on the way and we are checking on the resident.'],
                    [7, ResponseUpdate::KIND_NOTE, true, 'Power was restored the same evening. The resident was visited and is feeling better; we shared heat-wave advice.'],
                    [1, ResponseUpdate::KIND_STAGE, true, 'Status moved from Action in Progress to Awaiting Verification. Everything is done on our side: please tell us whether the problem still appears.'],
                ],
            ],
            'Residence Le Lac Tower' => [
                'title' => 'Elevator out of service',
                'summary' => 'The elevator has been out of service for several days. The contractor is investigating the fault.',
                'stage' => 'investigating', 'priority' => 'medium', 'created' => 4, 'stage_since' => 3, 'target' => 6,
                'reports' => ['Elevator out of service'],
                'tasks' => [
                    $job('Contractor diagnosis of the elevator fault', 'Written report and quote for the repair.', 'Elevator contractor', 25, 2),
                    $job('Post a notice and arrange assistance for residents who need it', 'Temporary measure while the elevator is out.', null, 100, -2, 2),
                ],
                'updates' => [
                    [4, ResponseUpdate::KIND_CREATED, true, 'Response case opened for 1 related report(s).'],
                    [3, ResponseUpdate::KIND_STAGE, true, 'Status moved from Reported to Under Investigation. The elevator contractor was called and will inspect the cabin and controller.'],
                    [2, ResponseUpdate::KIND_NOTE, true, 'A notice is posted at the entrance, and we can help residents who need assistance while the elevator is out.'],
                ],
            ],
        ];
    }

    /**
     * Reports added to the residences of real managers. The last open report is deliberately left out of every case.
     *
     * @return list<array<string, mixed>>
     */
    private function managedReports(): array
    {
        $report = fn (string $author, string $category, string $title, string $description, string $status, int $age, ?int $resolvedAfter = null, ?string $notes = null) => [
            'author' => $author, 'category' => $category, 'title' => $title, 'description' => $description,
            'status' => $status, 'age' => $age, 'resolved_after' => $resolvedAfter, 'notes' => $notes,
        ];

        return [
            $report('m0', 'cooling', 'Air conditioning down in the entrance hall', 'The AC in the entrance hall has stopped. It is very hot inside and elderly residents struggle to wait there.', 'Pending', 12),
            $report('m1', 'cooling', 'Hall is unbearably hot in the afternoon', 'The shared hall gets extremely hot because the cooling is broken. Nobody can stay there after noon.', 'In Progress', 8),
            $report('m0', 'cooling', 'No cold air near the lift lobby', 'There is no cold air near the lift lobby; the vents only blow warm air.', 'Pending', 3),
            $report('m1', 'power', 'Repeated power cuts in the evening', 'Every evening around 7 pm the power drops on several floors for about twenty minutes.', 'Pending', 20),
            $report('m0', 'power', 'Lights go out on the stairs again', 'The stair lights flickered and went out this evening, the same way as last week.', 'Pending', 9),
            $report('m1', 'water', 'Leak under the staircase', 'Water is dripping under the main staircase and the floor stays wet.', 'Pending', 16),
            $report('m0', 'elevator', 'Elevator stuck during the heat', 'The elevator stopped between two floors with people inside.', 'Resolved', 30, 3,
                'The elevator contractor replaced the door sensor and reset the controller. The inspection log was signed on site.'),
        ];
    }

    /**
     * Two cases for the reports above: one in progress with an overdue task, one awaiting resident verification.
     *
     * @return list<array<string, mixed>>
     */
    private function managedCases(): array
    {
        $job = fn (string $title, string $description, ?string $crew, int $progress, int $due, ?int $doneAgo = null) => [
            'title' => $title, 'description' => $description, 'crew' => $crew, 'progress' => $progress, 'due' => $due, 'done_ago' => $doneAgo,
        ];

        return [
            [
                'title' => 'Entrance hall cooling failure',
                'summary' => 'Three residents describe the same loss of cooling around the entrance hall and the lift lobby during the heat wave.',
                'stage' => 'action', 'priority' => 'critical', 'created' => 9, 'stage_since' => 3, 'target' => 5,
                'reports' => ['Air conditioning down in the entrance hall', 'Hall is unbearably hot in the afternoon', 'No cold air near the lift lobby'],
                'ai_linked' => ['No cold air near the lift lobby'],
                'tasks' => [
                    $job('Inspect the hall cooling units', 'Contractor visit with a written diagnosis.', 'CoolFix HVAC', 100, -4, 4),
                    $job('Replace the faulty compressor', 'Order the part and schedule the installation.', 'CoolFix HVAC', 60, 3),
                    $job('Open a temporary cooling point', 'Two portable units and opening hours posted at the entrance.', null, 0, -2),
                ],
                'updates' => [
                    [9, ResponseUpdate::KIND_CREATED, true, 'Response case opened for 2 related report(s).'],
                    [9, ResponseUpdate::KIND_STAGE, true, 'Status moved from Reported to Under Investigation. A technician will check the units.'],
                    [6, ResponseUpdate::KIND_NOTE, false, 'Compressor quote received; checking a second supplier before approval.'],
                    [5, ResponseUpdate::KIND_LINK, false, 'Linked 1 related report suggested by the AI Incident Constellation: No cold air near the lift lobby.'],
                    [3, ResponseUpdate::KIND_STAGE, true, 'Status moved from Under Investigation to Action in Progress. The compressor is confirmed faulty and is being replaced.'],
                    [1, ResponseUpdate::KIND_NOTE, true, 'Replacement part ordered, delivery expected this week. A temporary cooling point will open in the hall meanwhile.'],
                ],
            ],
            [
                'title' => 'Evening power cuts',
                'summary' => 'Power drops in the evening on several floors. The electrician rebalanced the load; waiting for residents to confirm.',
                'stage' => 'verification', 'priority' => 'high', 'created' => 18, 'stage_since' => 2, 'target' => 3,
                'reports' => ['Repeated power cuts in the evening', 'Lights go out on the stairs again'],
                'tasks' => [
                    $job('Ask the utility for the outage schedule', 'Written request, answer shared with residents.', null, 100, -12, 11),
                    $job('Rebalance the electrical load', 'Electrician work on the distribution board.', 'Electrician (Elec-Plus)', 100, -4, 3),
                ],
                'updates' => [
                    [18, ResponseUpdate::KIND_CREATED, true, 'Response case opened for 2 related report(s).'],
                    [17, ResponseUpdate::KIND_STAGE, true, 'Status moved from Reported to Under Investigation. We asked the utility for its outage schedule.'],
                    [8, ResponseUpdate::KIND_STAGE, true, 'Status moved from Under Investigation to Action in Progress. The electrician will rebalance the load.'],
                    [2, ResponseUpdate::KIND_STAGE, true, 'Status moved from Action in Progress to Awaiting Verification. No cut this week; please tell us if it still happens.'],
                ],
                'confirmations' => [['m0', true, 'Nothing since Monday.']],
            ],
        ];
    }

    /**
     * Creates the demo accounts that do not exist yet; existing accounts are returned untouched.
     *
     * @param  array<string, array<int, string>>  $definitions
     * @return array<string, User>
     */
    private function seedUsers(array $definitions, string $role): array
    {
        $users = [];

        foreach ($definitions as $key => $definition) {
            $user = User::firstOrNew(['email' => $definition[1]]);

            if (! $user->exists) {
                $user->forceFill([
                    'name' => $definition[0],
                    'password' => self::PASSWORD,
                    'role' => $role,
                    'neighborhood_id' => $role === User::ROLE_MANAGER ? Neighborhood::where('name', $definition[2])->value('id') : null,
                    'email_verified_at' => now(),
                ])->save();
            } elseif ($user->role !== $role) {
                $this->command?->warn("{$definition[1]} already exists with the role {$user->role}; it was left untouched.");
            }

            $users[$key] = $user;
        }

        return $users;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function seedResidence(array $definition, User $manager): Residence
    {
        $residence = Residence::firstOrNew(['user_id' => $manager->id, 'name' => $definition['name']]);

        if (! $residence->exists) {
            $created = now()->subDays(100);

            $residence->forceFill([
                'address' => $definition['address'],
                'neighborhood' => $definition['neighborhood'],
                'description' => $definition['description'],
                'status' => Residence::STATUS_APPROVED,
                'reviewed_by' => User::where('role', User::ROLE_ADMIN)->value('id'),
                'reviewed_at' => $created,
                'created_at' => $created,
                'updated_at' => $created,
            ])->save();
        }

        return $residence;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function seedReport(Residence $residence, User $author, array $data): void
    {
        $reportedAt = today()->subDays($data['age']);
        $resolvedAt = $data['resolved_after'] !== null ? $reportedAt->copy()->addDays($data['resolved_after'])->setTime(10, 0) : null;

        ResidentReport::firstOrNew(['residence_id' => $residence->id, 'title' => $data['title']])->forceFill([
            'user_id' => $author->id,
            'category' => $data['category'],
            'description' => $data['description'],
            'status' => $resolvedAt !== null ? ResidentReport::STATUS_RESOLVED : $data['status'],
            'resolution_notes' => $data['notes'],
            'resolved_at' => $resolvedAt,
            'reported_at' => $reportedAt,
            'created_at' => $reportedAt->copy()->setTime(9, 0),
            'updated_at' => $resolvedAt ?? $reportedAt->copy()->setTime(9, 0),
        ])->save();
    }

    /**
     * Seeds one response case with its linked reports, tasks, timeline and resident confirmations.
     * Models are saved quietly so the historic dates and states below are kept as written.
     *
     * @param  array<string, User>  $residents
     * @param  array<string, mixed>  $data
     */
    private function seedCase(Residence $residence, User $manager, array $residents, array $data): void
    {
        $stage = ResponseStage::from($data['stage']);
        $created = Carbon::now()->subDays($data['created'])->setTime(9, 30);
        $resolved = $stage === ResponseStage::Resolved ? Carbon::now()->subDays($data['resolved_ago'])->setTime(16, 0) : null;

        $case = ResponseCase::firstOrNew(['residence_id' => $residence->id, 'title' => $data['title']]);
        $case->forceFill([
            'summary' => $data['summary'],
            'stage' => $stage,
            'priority' => $data['priority'],
            'target_date' => today()->addDays($data['target']),
            'created_by' => $manager->id,
            'stage_changed_at' => Carbon::now()->subDays($data['stage_since'])->setTime(11, 0),
            'resolved_at' => $resolved,
            'created_at' => $created,
            'updated_at' => $created,
        ])->saveQuietly();

        $reports = ResidentReport::where('residence_id', $residence->id)->whereIn('title', $data['reports'])->get();
        $case->reports()->syncWithoutDetaching($reports->mapWithKeys(fn (ResidentReport $report) => [
            $report->id => ['linked_by' => $manager->id, 'source' => in_array($report->title, $data['ai_linked'] ?? [], true) ? 'constellation' : 'manual'],
        ])->all());

        foreach ($data['tasks'] as $task) {
            $done = $task['progress'] >= 100;

            ResponseTask::firstOrNew(['response_case_id' => $case->id, 'title' => $task['title']])->forceFill([
                'description' => $task['description'],
                'assigned_to' => $task['crew'] === null ? $manager->id : null,
                'assignee_label' => $task['crew'],
                'status' => $done ? ResponseTask::STATUS_DONE : ($task['progress'] > 0 ? ResponseTask::STATUS_IN_PROGRESS : ResponseTask::STATUS_TODO),
                'progress' => $task['progress'],
                'due_date' => today()->addDays($task['due']),
                'created_by' => $manager->id,
                'completed_at' => $done ? Carbon::now()->subDays($task['done_ago'] ?? 1)->setTime(15, 0) : null,
                'created_at' => $created,
                'updated_at' => $created,
            ])->saveQuietly();
        }

        foreach ($data['updates'] as [$ago, $kind, $public, $body]) {
            $at = Carbon::now()->subDays($ago)->setTime(10, 0);

            ResponseUpdate::firstOrNew(['response_case_id' => $case->id, 'body' => $body])->forceFill([
                'user_id' => $manager->id,
                'kind' => $kind,
                'is_public' => $public,
                'published_at' => $public ? $at : null,
                'created_at' => $at,
                'updated_at' => $at,
            ])->saveQuietly();
        }

        foreach ($data['confirmations'] ?? [] as [$author, $isResolved, $comment]) {
            $report = $reports->firstWhere('user_id', $residents[$author]->id);

            if ($report !== null) {
                ResponseConfirmation::updateOrCreate(
                    ['response_case_id' => $case->id, 'resident_report_id' => $report->id],
                    ['user_id' => $residents[$author]->id, 'is_resolved' => $isResolved, 'comment' => $comment],
                );
            }
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function residences(): array
    {
        return [
            'jasmins' => [
                'manager' => 'nadia', 'members' => ['amel', 'bilel', 'chiraz'],
                'name' => 'Résidence Les Jasmins du Lac', 'address' => '14 Rue du Lac Léman', 'neighborhood' => 'La Marsa',
                'description' => 'Eight-floor building with a large lobby and a shared hall used daily by many elderly residents.',
            ],
            'amal' => [
                'manager' => 'nadia', 'members' => ['dorra', 'elyes', 'hedi'],
                'name' => 'Résidence El Amal', 'address' => '5 Avenue de la République', 'neighborhood' => 'La Marsa',
                'description' => 'Well-maintained four-floor building with an active residents committee.',
            ],
            'carthage' => [
                'manager' => 'walid', 'members' => ['fatma', 'hedi', 'imen'],
                'name' => 'Résidence Cap Carthage', 'address' => '22 Rue Hannibal', 'neighborhood' => 'Ariana',
                'description' => 'Mixed-use complex where evening power cuts are frequent during heat waves.',
            ],
            'oliviers' => [
                'manager' => 'walid', 'members' => ['jawher', 'khouloud', 'lotfi'],
                'name' => 'Résidence Les Oliviers Sud', 'address' => '71 Avenue des Oliviers', 'neighborhood' => 'Ariana',
                'description' => 'Older building (1980s) with ageing plumbing and no cooled common areas.',
            ],
            'beb-bhar' => [
                'manager' => 'ines', 'members' => ['imen', 'lotfi'],
                'name' => 'Résidence Beb Bhar', 'address' => '3 Rue Beb Bhar', 'neighborhood' => 'Centre Ville',
                'description' => 'Newly onboarded residence: no incident reported yet.',
            ],
        ];
    }

    /**
     * Report definitions per residence. "age" = days since the report; "resolved_after" = days it took to resolve (null = still open).
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function reports(): array
    {
        $open = fn (string $author, ?string $category, string $title, string $description, string $status, int $age) => [
            'author' => $author, 'category' => $category, 'title' => $title, 'description' => $description,
            'status' => $status, 'age' => $age, 'resolved_after' => null, 'notes' => null,
        ];
        $done = fn (string $author, ?string $category, string $title, string $description, int $age, int $after, ?string $notes) => [
            'author' => $author, 'category' => $category, 'title' => $title, 'description' => $description,
            'status' => ResidentReport::STATUS_RESOLVED, 'age' => $age, 'resolved_after' => $after, 'notes' => $notes,
        ];

        return [
            // Weak residence: three differently worded cooling reports still open, overdue items, mostly unrelated others.
            'jasmins' => [
                $open('amel', 'cooling', 'Lobby air conditioning has stopped', 'The AC in the lobby has stopped working. It has been over 30 degrees inside since Monday and elderly residents are struggling.', 'Pending', 34),
                $open('bilel', 'cooling', 'Common room is unbearably hot', 'The common room becomes extremely hot because the cooling is broken. Nobody can stay there in the afternoon.', 'In Progress', 21),
                $open('chiraz', 'cooling', 'No cold air in the shared hall', 'There is no cold air in the shared hall, and residents cannot stay there comfortably. The vents only blow warm air.', 'Pending', 9),
                $open('amel', 'elevator', 'Elevator call button on the 3rd floor is damaged', 'The call button on the 3rd floor is cracked and sometimes does not respond, so people wait a long time.', 'Pending', 41),
                $open('bilel', 'water', 'Water dripping from a pipe near the parking ramp', 'A pipe near the basement parking ramp is leaking and the floor is wet and slippery.', 'In Progress', 16),
                $open('chiraz', 'ventilation', 'Stairwell fan is very noisy', 'The ventilation fan in staircase B makes a loud rattling noise at night.', 'Pending', 5),
                $open('bilel', 'safety', 'Shade cloth above the courtyard is torn', 'Part of the shade cloth above the courtyard fell down, leaving the seating area in full sun.', 'Pending', 12),
                $done('amel', 'cooling', 'Lobby air conditioning blowing warm air', 'In July the lobby AC only blew warm air for several days.', 78, 6,
                    'Technician recharged the refrigerant and replaced the faulty thermostat on the lobby unit. Filters were cleaned. Lobby temperature was back to 24°C and a check one week later confirmed it held.'),
                $done('bilel', 'power', 'Power cut on the 2nd floor corridor', 'Corridor lights and sockets on the 2nd floor lost power twice in one afternoon.', 60, 3,
                    'The circuit breaker was tripping from overload caused by portable fans plugged into one circuit. The electrician rebalanced the load across two circuits and labelled the panel. Residents were asked to avoid daisy-chained extension cords.'),
                $done('chiraz', 'other', 'Rooftop water tank water too warm', 'Tap water on the top floors is lukewarm even in the morning.', 52, 8, null),
                $done('amel', 'building', 'Stairwell window cannot be opened', 'The window in the stairwell is stuck, so the stairs are very stuffy.', 31, 5,
                    'Maintenance lubricated the hinges and replaced two broken window stays so the stairwell can be ventilated again.'),
            ],

            // Strong residence: mostly resolved quickly, nothing overdue.
            'amal' => [
                $done('dorra', 'cooling', 'Gym air conditioner is leaking water', 'Water is dripping from the gym AC onto the floor.', 66, 4,
                    'The condensate drain line was clogged. The technician cleared it and replaced the small condensate pump. No leak was seen during the following two weeks.'),
                $done('elyes', 'power', 'Lobby lights out during a power outage', 'The whole lobby went dark for an hour and the emergency light did not come on.', 58, 1,
                    'A neighbourhood-wide outage was confirmed by the utility. The emergency lighting battery was found weak and replaced; a monthly generator and lighting test was scheduled.'),
                $done('hedi', 'elevator', 'Elevator stuck between floors', 'The elevator stopped between the 2nd and 3rd floors with someone inside.', 45, 2,
                    'The elevator company replaced the door sensor and reset the controller. The inspection log was signed on site.'),
                $done('dorra', 'water', 'Low water pressure on the 5th floor', 'Showers on the top floor barely run in the evening.', 38, 7,
                    'The pressure valve was readjusted and the booster pump serviced. Pressure was measured at the normal level on all floors.'),
                $done('elyes', null, 'Courtyard bench is broken', 'One of the courtyard benches has a broken slat.', 27, 6, null),
                $done('hedi', 'ventilation', 'Mailroom ventilation grille rattles', 'The grille in the mailroom rattles whenever the fan runs.', 20, 3,
                    'Loose fixing screws were tightened and the grille was cleaned.'),
                $done('hedi', 'cooling', 'Hallway AC drips on the 4th floor', 'The hallway AC on the 4th floor drips onto the carpet.', 10, 2,
                    'The drain pan was emptied and the drain slope corrected.'),
                $open('elyes', 'building', 'Entrance door closer is too slow', 'The entrance door stays open for almost a minute after use.', 'In Progress', 6),
                $open('dorra', 'safety', 'Terrace shade net is torn', 'The shade net on the terrace has a large tear.', 'Pending', 3),
            ],

            // Medium residence: recurring power cuts still open.
            'carthage' => [
                $open('fatma', 'power', 'Afternoon power cuts on the upper floors', 'Every afternoon around 4 pm the power drops on floors 4 to 6 for about twenty minutes.', 'Pending', 30),
                $open('hedi', 'power', 'Electricity trips again around 4 pm', 'The electricity tripped again this afternoon, the same way as last week.', 'In Progress', 18),
                $open('imen', 'power', 'Fridge shut off after another power cut', 'Another power cut spoiled the food in my fridge. This keeps happening in the afternoon.', 'Pending', 7),
                $open('imen', 'cooling', 'Lobby air conditioning cools poorly', 'The lobby is not getting cold anymore, even with the AC on its lowest setting.', 'In Progress', 12),
                $done('fatma', 'building', 'Garage door remote not working', 'None of the garage remotes open the door anymore.', 44, 9,
                    'The receiver battery and antenna were replaced and all remotes were re-paired.'),
                $done('hedi', 'elevator', 'Elevator makes a grinding noise', 'A grinding noise comes from the elevator shaft when it moves.', 36, 12,
                    'The guide rollers were lubricated and one worn roller was replaced by the elevator contractor. The noise was gone after test runs.'),
                $done('fatma', 'safety', 'Roof access door left unlocked', 'The door to the roof has been unlocked for days.', 25, 2,
                    'The lock was replaced and the key policy was reminded to all residents.'),
                $done('imen', null, 'Bin room smells strongly', 'The bin room smell reaches the ground-floor flats.', 15, 5, null),
            ],

            // Weakest residence: old building, repeated water leaks and several overdue items.
            'oliviers' => [
                $open('jawher', 'building', 'Crack in the 2nd floor corridor ceiling', 'A long crack has opened in the ceiling of the 2nd floor corridor and it is growing.', 'Pending', 47),
                $open('khouloud', 'water', 'Water leak in the basement storage', 'Water is coming through the wall of the basement storage rooms.', 'Pending', 39),
                $open('lotfi', 'water', 'Pipe leaking again near the boiler room', 'The pipe near the boiler room is leaking again after the last repair.', 'In Progress', 24),
                $open('jawher', 'water', 'Damp patch on the 3rd floor bathroom ceiling', 'A damp patch is spreading on the bathroom ceiling, probably from the flat above.', 'Pending', 11),
                $open('khouloud', 'ventilation', 'No ventilation in the first floor corridor', 'The corridor has no airflow at all and gets extremely hot in the afternoon.', 'Pending', 29),
                $open('lotfi', 'cooling', 'No cooling in the common area', 'The ground floor common area has no air conditioning and is unusable during heat waves.', 'Pending', 19),
                $open('khouloud', 'safety', 'Stair handrail is loose', 'The handrail between the 1st and 2nd floors moves when you hold it.', 'Pending', 8),
                $done('jawher', 'power', 'Power socket sparks in the laundry room', 'A socket in the laundry room produced sparks when a machine was plugged in.', 62, 5,
                    'The electrician replaced the damaged socket and tested the circuit. The laundry room socket is now protected by a residual-current breaker.'),
                $done('lotfi', 'elevator', 'Elevator out of service for two days', 'The elevator has not worked since Tuesday.', 33, 4,
                    'The controller board was replaced by the contractor under the maintenance contract.'),
            ],

            // 'beb-bhar' intentionally has no report: it demonstrates the empty state.
        ];
    }

    /**
     * Response cases per residence. created/stage_since/resolved_ago = days ago; target = days from today;
     * task due = days from today (negative = overdue); updates = [days ago, kind, visible to residents, text].
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function cases(): array
    {
        $created = ResponseUpdate::KIND_CREATED;
        $stage = ResponseUpdate::KIND_STAGE;
        $note = ResponseUpdate::KIND_NOTE;
        $task = ResponseUpdate::KIND_TASK;
        $link = ResponseUpdate::KIND_LINK;
        $confirmation = ResponseUpdate::KIND_CONFIRMATION;

        $job = fn (string $title, string $description, ?string $crew, int $progress, int $due, ?int $doneAgo = null) => [
            'title' => $title, 'description' => $description, 'crew' => $crew, 'progress' => $progress, 'due' => $due, 'done_ago' => $doneAgo,
        ];

        return [
            'jasmins' => [
                [
                    'title' => 'Lobby and shared hall cooling failure',
                    'summary' => 'Three residents describe the same loss of cooling in the lobby, the common room and the shared hall. Elderly residents are exposed during the heat wave.',
                    'stage' => 'action', 'priority' => 'critical', 'created' => 10, 'stage_since' => 4, 'target' => 6,
                    'reports' => ['Lobby air conditioning has stopped', 'Common room is unbearably hot', 'No cold air in the shared hall'],
                    'ai_linked' => ['No cold air in the shared hall'],
                    'tasks' => [
                        $job('Inspect the lobby and hall AC units', 'Contractor visit with a written diagnosis.', 'CoolFix HVAC', 100, -5, 5),
                        $job('Replace the lobby compressor', 'Order the part and schedule the installation.', 'CoolFix HVAC', 60, 3),
                        $job('Open a temporary cooling point in the shared hall', 'Two portable units and opening hours posted at the entrance.', null, 0, -2),
                        $job('Post the cool spots map and emergency numbers in the lobby', 'A3 poster from the Cool Spots page.', null, 0, 5),
                    ],
                    'updates' => [
                        [10, $created, true, 'Response case opened for 3 related report(s).'],
                        [10, $stage, true, 'Status moved from Reported to Under Investigation. A technician will check all three units.'],
                        [8, $note, false, 'Supplier quote for the compressor is high; asking a second contractor before approving.'],
                        [7, $link, false, 'Linked 1 related report suggested by the AI Incident Constellation: No cold air in the shared hall.'],
                        [4, $stage, true, 'Status moved from Under Investigation to Action in Progress. The compressor is confirmed faulty and is being replaced.'],
                        [4, $task, false, 'Task completed: Inspect the lobby and hall AC units.'],
                        [2, $note, true, 'Replacement part ordered, delivery expected within the week. A temporary cooling point will be open in the hall meanwhile.'],
                    ],
                ],
                [
                    'title' => 'Parking ramp pipe leak',
                    'summary' => 'A leaking pipe makes the basement ramp wet and slippery.',
                    'stage' => 'investigating', 'priority' => 'high', 'created' => 6, 'stage_since' => 5, 'target' => 8,
                    'reports' => ['Water dripping from a pipe near the parking ramp'],
                    'tasks' => [
                        $job('Plumber inspection of the ramp pipe', 'Locate the leak and quote the repair.', 'Plumber (Mr. Gharbi)', 30, 2),
                        $job('Put warning signs on the wet ramp', 'Temporary safety measure.', null, 100, -4, 5),
                    ],
                    'updates' => [
                        [6, $created, true, 'Response case opened for 1 related report(s).'],
                        [5, $stage, true, 'Status moved from Reported to Under Investigation. A plumber is booked this week.'],
                    ],
                ],
                [
                    'title' => 'Stairwell window repair',
                    'summary' => 'Stuck stairwell window that kept the stairs stuffy.',
                    'stage' => 'resolved', 'priority' => 'low', 'created' => 36, 'stage_since' => 31, 'resolved_ago' => 31, 'target' => -30,
                    'reports' => ['Stairwell window cannot be opened'],
                    'tasks' => [$job('Lubricate hinges and replace window stays', 'Maintenance visit.', null, 100, -32, 32)],
                    'updates' => [
                        [36, $created, true, 'Response case opened for 1 related report(s).'],
                        [33, $stage, true, 'Status moved from Reported to Action in Progress. Maintenance is scheduled.'],
                        [32, $stage, true, 'Status moved from Action in Progress to Awaiting Verification. The window opens again; please confirm.'],
                        [31, $confirmation, false, 'The resident of the report confirmed the issue appears resolved.'],
                        [31, $stage, true, 'Status moved from Awaiting Verification to Resolved.'],
                    ],
                    'confirmations' => [['amel', true, 'The window opens and closes normally.']],
                ],
            ],
            'carthage' => [
                [
                    'title' => 'Recurring afternoon power cuts',
                    'summary' => 'The power drops around 4 pm on the upper floors, several times a week. Work with the utility and the electrician is done; waiting for the residents to confirm.',
                    'stage' => 'verification', 'priority' => 'high', 'created' => 28, 'stage_since' => 3, 'target' => 2,
                    'reports' => ['Afternoon power cuts on the upper floors', 'Electricity trips again around 4 pm', 'Fridge shut off after another power cut'],
                    'ai_linked' => ['Fridge shut off after another power cut'],
                    'tasks' => [
                        $job('Request the power-cut schedule from the utility', 'Written request, answer shared with residents.', null, 100, -20, 18),
                        $job('Rebalance the floor 4-6 electrical load', 'Electrician work on the distribution board.', 'Electrician (Elec-Plus)', 100, -6, 5),
                        $job('Fit emergency lights on the stairwells', 'Battery lights on every landing.', null, 100, -3, 3),
                    ],
                    'updates' => [
                        [28, $created, true, 'Response case opened for 2 related report(s).'],
                        [27, $stage, true, 'Status moved from Reported to Under Investigation. We asked the utility for its outage schedule.'],
                        [14, $stage, true, 'Status moved from Under Investigation to Action in Progress. The electrician will rebalance the load on floors 4 to 6.'],
                        [10, $link, false, 'Linked 1 related report suggested by the AI Incident Constellation: Fridge shut off after another power cut.'],
                        [3, $stage, true, 'Status moved from Action in Progress to Awaiting Verification. No cut was recorded this week; please tell us if it still happens.'],
                        [2, $confirmation, false, 'The resident of the report confirmed the issue appears resolved.'],
                        [1, $confirmation, false, 'The resident of the report says the issue still appears. "Another cut yesterday around 4:15 pm."'],
                    ],
                    'confirmations' => [['fatma', true, 'Nothing since Monday.'], ['imen', false, 'Another cut yesterday around 4:15 pm.']],
                ],
                [
                    'title' => 'Lobby cooling performance',
                    'summary' => 'The lobby no longer gets cold even on the lowest setting.',
                    'stage' => 'reported', 'priority' => 'medium', 'created' => 2, 'stage_since' => 2, 'target' => 12,
                    'reports' => ['Lobby air conditioning cools poorly'],
                    'tasks' => [$job('Ask the contractor for a lobby unit check', 'Filters, refrigerant and thermostat.', 'CoolFix HVAC', 0, 4)],
                    'updates' => [[2, $created, true, 'Response case opened for 1 related report(s).']],
                ],
            ],
            'oliviers' => [
                [
                    'title' => 'Corridor ceiling crack and basement water ingress',
                    'summary' => 'A growing ceiling crack on the 2nd floor and water coming through the basement wall may share a structural cause. Engineer opinion needed.',
                    'stage' => 'investigating', 'priority' => 'critical', 'created' => 15, 'stage_since' => 12, 'target' => -1,
                    'reports' => ['Crack in the 2nd floor corridor ceiling', 'Water leak in the basement storage'],
                    'tasks' => [
                        $job('Structural survey of the corridor ceiling', 'Written opinion from an engineer.', 'Structural engineer', 20, -8),
                        $job('Inspect the basement wall for water ingress', 'Moisture readings and photos.', null, 0, -3),
                    ],
                    'updates' => [
                        [15, $created, true, 'Response case opened for 2 related report(s).'],
                        [12, $stage, true, 'Status moved from Reported to Under Investigation. An engineer will survey the building.'],
                        [9, $note, false, 'Engineer unavailable until next week; asking a second firm.'],
                    ],
                ],
            ],
        ];
    }
}
