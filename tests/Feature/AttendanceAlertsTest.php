<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\OutingType;
use App\Models\Member;
use App\Models\Outing;
use App\Models\User;
use App\Services\AttendanceAlerts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceAlertsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->signInPatron();
    }

    /**
     * Trainings from oldest to newest with the given statuses of $member (null = not recorded while another
     * member was recorded, i.e. the appel was done).
     *
     * @param  list<AttendanceStatus|null>  $statuses
     */
    private function trainings(Member $member, array $statuses, OutingType $type = OutingType::Entrainement): void
    {
        $witness = Member::factory()->for($this->user->association)->create(['created_at' => now()->subYear()]);

        foreach ($statuses as $index => $status) {
            $outing = Outing::factory()->for($this->user->association)->create([
                'type' => $type,
                'date' => today()->subDays(count($statuses) - $index),
            ]);
            $outing->attendances()->create(['member_id' => $witness->id, 'status' => AttendanceStatus::Present]);
            if ($status) {
                $outing->attendances()->create(['member_id' => $member->id, 'status' => $status]);
            }
        }
    }

    private function member(): Member
    {
        return Member::factory()->for($this->user->association)->create(['first_name' => 'Kévin', 'created_at' => now()->subYear()]);
    }

    public function test_three_trainings_missed_in_a_row_raise_an_alert(): void
    {
        $member = $this->member();
        $this->trainings($member, [AttendanceStatus::Present, AttendanceStatus::Absent, null, AttendanceStatus::Absent]);

        $alerts = app(AttendanceAlerts::class)->for($this->user->association_id)->where('member.id', $member->id);

        $this->assertSame(['consecutive'], $alerts->pluck('type')->all());
        $this->assertSame('3 entraînements manqués d’affilée', $alerts->first()['title']);
    }

    public function test_an_excused_training_is_neutral(): void
    {
        $member = $this->member();
        $this->trainings($member, [AttendanceStatus::Present, AttendanceStatus::Absent, AttendanceStatus::Excuse, AttendanceStatus::Absent]);

        $this->assertEmpty(app(AttendanceAlerts::class)->for($this->user->association_id)->where('member.id', $member->id));
    }

    public function test_less_than_60_percent_on_the_last_ten_trainings_is_irregular(): void
    {
        $member = $this->member();
        $p = AttendanceStatus::Present;
        $a = AttendanceStatus::Absent;
        $this->trainings($member, [$p, $a, $p, $a, $p, $a, $a, $p, $a, $p]);

        $alerts = app(AttendanceAlerts::class)->for($this->user->association_id)->where('member.id', $member->id);

        $this->assertSame(['irregular'], $alerts->pluck('type')->all());
        $this->assertStringContainsString('5 présence(s) sur les 10 derniers entraînements (50 %)', $alerts->first()['detail']);
    }

    public function test_not_enough_trainings_or_other_outing_types_do_not_count(): void
    {
        $member = $this->member();
        $this->trainings($member, [AttendanceStatus::Present, AttendanceStatus::Absent, AttendanceStatus::Absent, AttendanceStatus::Absent], OutingType::Regate);
        $this->trainings($member, [AttendanceStatus::Present, AttendanceStatus::Absent]);

        $this->assertEmpty(app(AttendanceAlerts::class)->for($this->user->association_id)->where('member.id', $member->id));
    }

    public function test_alerts_and_their_rules_are_shown(): void
    {
        $member = $this->member();
        $this->trainings($member, [AttendanceStatus::Absent, AttendanceStatus::Absent, AttendanceStatus::Absent]);

        $this->get(route('dashboard'))->assertOk()->assertSee('Alertes de présence')->assertSee($member->full_name);
        $this->get(route('attendance.stats'))->assertOk()
            ->assertSee('Comment sont calculées les alertes ?')
            ->assertSee($member->full_name);
        $this->get('/historique')->assertRedirect('/presences/statistiques');
    }
}
