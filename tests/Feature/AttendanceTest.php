<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Member;
use App\Models\Outing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_appel_shortcut_redirects_to_the_current_outing(): void
    {
        $user = $this->signInPatron();
        $this->get(route('attendance.today'))->assertRedirect(route('outings.create'));

        $outing = Outing::factory()->for($user->association)->create(['date' => today()]);
        $this->get(route('attendance.today'))->assertRedirect(route('attendance.edit', $outing));
    }

    public function test_appel_page_lists_active_members(): void
    {
        $user = $this->signInPatron();
        $outing = Outing::factory()->for($user->association)->create();
        Member::factory()->for($user->association)->create(['first_name' => 'Ludovic']);
        Member::factory()->for($user->association)->inactive()->create(['first_name' => 'Parti']);

        $this->get(route('attendance.edit', $outing))->assertOk()->assertSee('Ludovic')->assertDontSee('Parti');
    }

    public function test_recording_changing_and_clearing_a_status(): void
    {
        $user = $this->signInPatron();
        $outing = Outing::factory()->for($user->association)->create(['date' => today()]);
        $member = Member::factory()->for($user->association)->create();

        $this->putJson(route('attendance.update', $outing), ['statuses' => [$member->id => 'retard']])
            ->assertOk()
            ->assertJsonPath('counts.retard', 1)
            ->assertJsonPath("statuses.{$member->id}", 'retard');

        $attendance = Attendance::sole();
        $this->assertSame(AttendanceStatus::Retard, $attendance->status);
        $this->assertNotNull($attendance->arrived_at);
        $this->assertSame($user->id, $attendance->recorded_by);

        $this->putJson(route('attendance.update', $outing), ['statuses' => [$member->id => '']])->assertJsonPath('counts.total', 0);
        $this->assertSoftDeleted($attendance);

        // Re-recording restores the same row (unique outing + member).
        $this->putJson(route('attendance.update', $outing), ['statuses' => [$member->id => 'absent']])->assertJsonPath('counts.absent', 1);
        $this->assertSame(1, Attendance::withTrashed()->count());
        $this->assertNull(Attendance::sole()->arrived_at);
    }

    public function test_all_present_only_fills_unrecorded_active_members(): void
    {
        $user = $this->signInPatron();
        $outing = Outing::factory()->for($user->association)->create();
        [$excused, $other] = Member::factory()->for($user->association)->count(2)->create();
        Member::factory()->for($user->association)->inactive()->create();
        $outing->attendances()->create(['member_id' => $excused->id, 'status' => AttendanceStatus::Excuse]);

        $this->put(route('attendance.update', $outing), ['all_present' => 1])->assertRedirect(route('attendance.edit', $outing));

        $this->assertSame(AttendanceStatus::Excuse, $outing->attendances()->where('member_id', $excused->id)->value('status'));
        $this->assertSame(AttendanceStatus::Present, $outing->attendances()->where('member_id', $other->id)->value('status'));
        $this->assertSame(2, $outing->attendances()->count());
    }

    public function test_validation_and_tenant_isolation(): void
    {
        $user = $this->signInPatron();
        $outing = Outing::factory()->for($user->association)->create();
        $member = Member::factory()->for($user->association)->create();
        $foreignMember = Member::factory()->create();

        $this->putJson(route('attendance.update', $outing), ['statuses' => [$member->id => 'malade']])->assertJsonValidationErrors('statuses.'.$member->id);
        $this->putJson(route('attendance.update', $outing), ['statuses' => [$foreignMember->id => 'present']])->assertJsonValidationErrors('statuses');
        $this->putJson(route('attendance.update', Outing::factory()->create()), ['statuses' => [$member->id => 'present']])->assertNotFound();
        $this->assertSame(0, Attendance::count());
    }
}
