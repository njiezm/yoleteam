<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\OutingStatus;
use App\Enums\OutingType;
use App\Models\Association;
use App\Models\Attendance;
use App\Models\Member;
use App\Models\Outing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoryTest extends TestCase
{
    use RefreshDatabase;

    private Association $association;

    private Member $alice;

    private Member $bruno;

    private Outing $recent;

    private Outing $older;

    private Outing $regatta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-23 12:00:00');

        $this->association = Association::factory()->create();
        $this->alice = Member::factory()->for($this->association)->create(['first_name' => 'Alice', 'last_name' => 'Abel', 'phone' => '0696 11 22 33']);
        $this->bruno = Member::factory()->for($this->association)->create(['first_name' => 'Bruno', 'last_name' => 'Bazile', 'phone' => '0696 44 55 66']);
        Member::factory()->for($this->association)->inactive()->create(['first_name' => 'Ignace', 'last_name' => 'Inactif']);

        $this->recent = $this->outing('2026-09-19', 'Entraînement vent fort');
        $this->older = $this->outing('2026-09-10', 'Entraînement du matin');
        $this->regatta = $this->outing('2026-03-15', 'Régate du Robert', OutingType::Regate);

        $this->record($this->recent, $this->alice, AttendanceStatus::Present);
        $this->record($this->recent, $this->bruno, AttendanceStatus::Absent);
        $this->record($this->older, $this->alice, AttendanceStatus::Retard);
        $this->record($this->older, $this->bruno, AttendanceStatus::Excuse);
        $this->record($this->regatta, $this->alice, AttendanceStatus::Present);
        $this->record($this->regatta, $this->bruno, AttendanceStatus::Present);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('attendance.stats'))->assertRedirect(route('login'));
    }

    public function test_index_shows_kpis_grid_and_rankings_for_the_last_30_days(): void
    {
        $this->signInPatron($this->association);

        $this->get(route('attendance.stats'))
            ->assertOk()
            ->assertViewHas('overall', fn (array $overall) => $overall['rate'] === 50 && $overall['retard'] === 1 && $overall['absent'] === 1)
            ->assertViewHas('unexcusedMembers', 1)
            ->assertViewHas('gridOutings', fn ($outings) => $outings->pluck('id')->all() === [$this->older->id, $this->recent->id])
            ->assertSee('50 %')
            ->assertSee('1,0')
            ->assertSee('10/09')
            ->assertSee('19/09')
            ->assertDontSee('15/03')
            ->assertSee('Alice A.')
            ->assertSee('Bruno B.')
            ->assertDontSee('Ignace')
            ->assertSee('Les plus assidus')
            ->assertSee('À relancer')
            ->assertSee('tel:0696445566', false)
            ->assertSee(route('history.export', ['period' => '30']));
    }

    public function test_index_filters_by_season_and_outing_type(): void
    {
        $this->signInAdmin($this->association);

        $this->get(route('attendance.stats', ['period' => 'season']))
            ->assertOk()
            ->assertSee('15/03')
            ->assertViewHas('overall', fn (array $overall) => $overall['total'] === 6);

        $this->get(route('attendance.stats', ['period' => 'season', 'type' => 'regate']))
            ->assertOk()
            ->assertSee('15/03')
            ->assertDontSee('19/09')
            ->assertViewHas('overall', fn (array $overall) => $overall['rate'] === 100)
            ->assertSee(route('history.export', ['period' => 'season', 'type' => 'regate']));
    }

    public function test_index_renders_an_empty_state_without_recorded_outings(): void
    {
        $this->signInAdmin();

        $this->get(route('attendance.stats'))
            ->assertOk()
            ->assertSee('Aucun appel sur la période');
    }

    public function test_index_ignores_other_associations(): void
    {
        $this->signInAdmin();

        $this->get(route('attendance.stats', ['period' => 'season']))
            ->assertOk()
            ->assertDontSee('Alice A.')
            ->assertViewHas('outings', fn ($outings) => $outings->isEmpty());
    }

    public function test_export_streams_a_semicolon_csv_with_bom(): void
    {
        $this->signInPatron($this->association);

        $response = $this->get(route('history.export', ['period' => 'season']));

        $response->assertOk()
            ->assertDownload('presences-2026-09-23.csv')
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        $lines = array_map(fn ($line) => str_getcsv($line, ';'), explode("\n", trim(substr($content, 3))));

        $this->assertSame(['Membre', '15/03/2026 Régate du Robert', '10/09/2026 Entraînement du matin', '19/09/2026 Entraînement vent fort', 'Taux de présence'], $lines[0]);
        $this->assertSame(['Alice Abel', 'Présent', 'Retard', 'Présent', '100 %'], $lines[1]);
        $this->assertSame(['Bruno Bazile', 'Présent', 'Excusé', 'Absent', '33 %'], $lines[2]);
        $this->assertCount(3, $lines);
    }

    public function test_export_respects_the_type_filter(): void
    {
        $this->signInAdmin($this->association);

        $content = $this->get(route('history.export', ['period' => 'season', 'type' => 'regate']))->streamedContent();

        $this->assertStringContainsString('Régate du Robert', $content);
        $this->assertStringNotContainsString('Entraînement', $content);
    }

    private function outing(string $date, string $title, OutingType $type = OutingType::Entrainement): Outing
    {
        return Outing::factory()->for($this->association)->create([
            'date' => $date,
            'title' => $title,
            'type' => $type,
            'status' => OutingStatus::Terminee,
        ]);
    }

    private function record(Outing $outing, Member $member, AttendanceStatus $status): void
    {
        Attendance::create(['outing_id' => $outing->id, 'member_id' => $member->id, 'status' => $status]);
    }
}
