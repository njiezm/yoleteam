<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\SyncStatus;
use App\Models\Association;
use App\Models\Attendance;
use App\Models\Boat;
use App\Models\CrewPlan;
use App\Models\Member;
use App\Models\Outing;
use App\Models\SyncOperation;
use App\Models\User;
use App\Services\BoatLayoutGenerator;
use Database\Seeders\CrewRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SyncTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Outing $outing;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->signInPatron();
        $this->outing = Outing::factory()->for($this->user->association)->create(['date' => today()]);
        $this->member = Member::factory()->for($this->user->association)->create();
    }

    /** @param  list<array<string, mixed>>  $operations */
    private function push(array $operations, ?string $sentAt = null): TestResponse
    {
        return $this->postJson(route('sync.store'), [
            'device_id' => 'phone-1',
            'sent_at' => $sentAt ?? now()->toIso8601String(),
            'operations' => $operations,
        ]);
    }

    /** @return array<string, mixed> */
    private function attendanceOperation(?string $status, ?string $at = null, ?Member $member = null, ?Outing $outing = null): array
    {
        return [
            'id' => (string) Str::uuid(),
            'entity' => 'attendance',
            'entity_uuid' => ($outing ?? $this->outing)->uuid,
            'payload' => ['member_id' => ($member ?? $this->member)->id, 'status' => $status],
            'client_updated_at' => $at ?? now()->subMinutes(5)->toIso8601String(),
        ];
    }

    private function crewPlan(): CrewPlan
    {
        $this->seed(CrewRoleSeeder::class);
        $boat = Boat::factory()->for($this->user->association)->create();
        $configuration = $boat->configurations()->create(['name' => '2 voiles', 'sail_count' => 2, 'bwa_count' => 4, 'is_default' => true]);
        app(BoatLayoutGenerator::class)->generate($configuration);

        return $this->outing->crewPlans()->create(['boat_id' => $boat->id, 'boat_configuration_id' => $configuration->id]);
    }

    public function test_offline_attendance_is_applied_with_the_device_time(): void
    {
        $at = now()->subMinutes(10)->startOfSecond();
        $operation = $this->attendanceOperation('retard', $at->toIso8601String());

        $this->push([$operation])->assertOk()->assertJsonPath('results.0.status', 'applied');

        $attendance = Attendance::sole();
        $this->assertSame(AttendanceStatus::Retard, $attendance->status);
        $this->assertTrue($attendance->updated_at->equalTo($at));
        $this->assertDatabaseHas('sync_operations', ['id' => $operation['id'], 'status' => 'applied', 'user_id' => $this->user->id, 'device_id' => 'phone-1']);
    }

    public function test_utc_device_timestamps_are_stored_in_the_application_timezone(): void
    {
        $at = now()->subMinutes(10)->startOfSecond();

        $this->push([$this->attendanceOperation('present', $at->copy()->utc()->toIso8601ZuluString())])->assertJsonPath('results.0.status', 'applied');

        $this->assertSame($at->format('Y-m-d H:i:s'), Attendance::sole()->getRawOriginal('updated_at'));
    }

    public function test_replaying_the_same_operation_is_idempotent(): void
    {
        $operation = $this->attendanceOperation('present');

        $this->push([$operation])->assertJsonPath('results.0.status', 'applied');
        Attendance::sole()->update(['status' => AttendanceStatus::Absent]);
        $this->push([$operation])->assertJsonPath('results.0.status', 'applied');

        $this->assertSame(AttendanceStatus::Absent, Attendance::sole()->status);
        $this->assertSame(1, SyncOperation::count());
    }

    public function test_a_newer_server_record_creates_a_conflict_instead_of_overwriting(): void
    {
        $this->outing->attendances()->create(['member_id' => $this->member->id, 'status' => AttendanceStatus::Excuse]);

        $this->push([$this->attendanceOperation('absent', now()->subHour()->toIso8601String())])
            ->assertJsonPath('results.0.status', 'conflict');

        $this->assertSame(AttendanceStatus::Excuse, Attendance::sole()->status);
        $this->assertSame('excuse', SyncOperation::sole()->conflict_details['server_status']);
    }

    public function test_same_value_on_the_server_is_not_a_conflict(): void
    {
        $this->outing->attendances()->create(['member_id' => $this->member->id, 'status' => AttendanceStatus::Present]);

        $this->push([$this->attendanceOperation('present', now()->subHour()->toIso8601String())])
            ->assertJsonPath('results.0.status', 'applied');
    }

    public function test_device_clock_offset_is_corrected(): void
    {
        // Device clock is 2 hours late: its "now" is two hours behind the server.
        $this->outing->attendances()->create(['member_id' => $this->member->id, 'status' => AttendanceStatus::Present]);
        $this->travel(10)->minutes();

        $deviceNow = now()->subHours(2);
        $this->push([$this->attendanceOperation('absent', $deviceNow->copy()->subMinute()->toIso8601String())], $deviceNow->toIso8601String())
            ->assertJsonPath('results.0.status', 'applied');

        $this->assertSame(AttendanceStatus::Absent, Attendance::sole()->status);
    }

    public function test_clearing_a_status_offline(): void
    {
        $this->outing->attendances()->create(['member_id' => $this->member->id, 'status' => AttendanceStatus::Present])
            ->forceFill(['updated_at' => now()->subDay()])->save();

        $this->push([$this->attendanceOperation(null)])->assertJsonPath('results.0.status', 'applied');

        $this->assertSame(0, Attendance::count());
        $this->assertSame(1, Attendance::withTrashed()->count());
    }

    public function test_operations_on_other_associations_or_invalid_data_are_rejected(): void
    {
        $foreignOuting = Outing::factory()->create();
        $foreignMember = Member::factory()->create();

        $this->push([
            $this->attendanceOperation('present', outing: $foreignOuting),
            $this->attendanceOperation('present', member: $foreignMember),
            $this->attendanceOperation('malade'),
        ])->assertOk()
            ->assertJsonPath('results.0.status', 'rejected')
            ->assertJsonPath('results.0.message', 'Sortie introuvable.')
            ->assertJsonPath('results.1.status', 'rejected')
            ->assertJsonPath('results.2.status', 'rejected');

        $this->assertSame(0, Attendance::count());
    }

    public function test_batch_validation(): void
    {
        $this->postJson(route('sync.store'), ['operations' => [['entity' => 'boat']]])
            ->assertJsonValidationErrors(['device_id', 'sent_at', 'operations.0.id', 'operations.0.entity', 'operations.0.entity_uuid']);
    }

    public function test_offline_crew_plan_state_is_applied(): void
    {
        $plan = $this->crewPlan();
        $patron = $plan->configuration->positions()->where('code', 'patron')->value('id');

        $this->push([[
            'id' => (string) Str::uuid(),
            'entity' => 'crew_plan',
            'entity_uuid' => $plan->uuid,
            'payload' => ['boat_configuration_id' => $plan->boat_configuration_id, 'wind_direction' => 90, 'wind_strength' => 12, 'assignments' => [['position_id' => $patron, 'member_id' => $this->member->id]]],
            'client_updated_at' => now()->addMinute()->toIso8601String(),
        ]])->assertJsonPath('results.0.status', 'applied');

        $this->assertSame($this->member->id, $plan->assignments()->value('member_id'));
        $this->assertSame(90, $plan->fresh()->wind_direction);
    }

    public function test_crew_plan_changed_on_the_server_since_goes_to_conflict_and_can_be_resolved(): void
    {
        $plan = $this->crewPlan();
        $patron = $plan->configuration->positions()->where('code', 'patron')->value('id');
        $operation = [
            'id' => (string) Str::uuid(),
            'entity' => 'crew_plan',
            'entity_uuid' => $plan->uuid,
            'payload' => ['boat_configuration_id' => $plan->boat_configuration_id, 'assignments' => [['position_id' => $patron, 'member_id' => $this->member->id]]],
            'client_updated_at' => now()->subHour()->toIso8601String(),
        ];

        $this->push([$operation])->assertJsonPath('results.0.status', 'conflict');
        $this->assertSame(0, $plan->assignments()->count());

        $this->get(route('sync.index'))->assertOk()->assertSee('Conflit à résoudre')->assertSee($plan->boat->name);

        $this->post(route('sync.resolve', $operation['id']), ['keep' => 'device'])->assertRedirect(route('sync.index'));
        $this->assertSame(SyncStatus::Applied, SyncOperation::sole()->status);
        $this->assertSame(1, $plan->assignments()->count());

        $this->post(route('sync.resolve', $operation['id']), ['keep' => 'server'])->assertStatus(409);
    }

    public function test_keeping_the_server_version(): void
    {
        $this->outing->attendances()->create(['member_id' => $this->member->id, 'status' => AttendanceStatus::Excuse]);
        $operation = $this->attendanceOperation('absent', now()->subHour()->toIso8601String());
        $this->push([$operation]);

        $this->post(route('sync.resolve', $operation['id']), ['keep' => 'server'])->assertRedirect();

        $this->assertSame(SyncStatus::Rejected, SyncOperation::sole()->status);
        $this->assertSame(AttendanceStatus::Excuse, Attendance::sole()->status);
    }

    public function test_conflicts_of_another_association_are_not_found(): void
    {
        $operation = $this->attendanceOperation('absent');
        $this->push([$operation]);

        $this->actingAs(User::factory()->for(Association::factory())->create());

        $this->post(route('sync.resolve', $operation['id']), ['keep' => 'device'])->assertNotFound();
        $this->get(route('sync.index'))->assertOk()->assertDontSee($this->member->short_name);
    }

    public function test_token_endpoint_and_guests(): void
    {
        $this->getJson(route('sync.token'))->assertOk()->assertJsonStructure(['token']);

        auth()->logout();
        $this->getJson(route('sync.token'))->assertUnauthorized();
        $this->postJson(route('sync.store'), [])->assertUnauthorized();
    }
}
