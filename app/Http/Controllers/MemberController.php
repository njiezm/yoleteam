<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\MemberLevel;
use App\Enums\OutingStatus;
use App\Enums\OutingType;
use App\Http\Requests\MemberRequest;
use App\Models\CrewAssignment;
use App\Models\CrewRole;
use App\Models\Member;
use App\Models\Outing;
use App\Services\AttendanceStats;
use App\Support\XlsxWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberController extends Controller
{
    public function index(Request $request, AttendanceStats $stats): View
    {
        $associationId = $request->user()->association_id;
        ['members' => $members, 'all' => $all, 'crewRoles' => $crewRoles, 'filters' => $filters] = $this->filteredMembers($request);

        $roleCounts = $crewRoles->mapWithKeys(fn (CrewRole $crewRole) => [
            $crewRole->code => $all->filter(fn (Member $member) => $member->crewRoles->contains('id', $crewRole->id))->count(),
        ]);

        return view('members.index', [
            'members' => $members,
            'total' => $all->count(),
            'activeCount' => Member::query()->forAssociation($associationId)->active()->count(),
            'crewRoles' => $crewRoles,
            'roleCounts' => $roleCounts,
            'rates' => $this->seasonRates($associationId, $stats),
            'filters' => $filters,
            'exportQuery' => array_filter($filters, fn ($value) => $value !== null && $value !== '' && $value !== 'active'),
        ]);
    }

    /**
     * Excel (.xlsx) export of the members list, with the same filters as the index.
     */
    public function export(Request $request, AttendanceStats $stats): StreamedResponse
    {
        ['members' => $members] = $this->filteredMembers($request);
        $rates = $this->seasonRates($request->user()->association_id, $stats);

        $writer = new XlsxWriter('Membres');
        $writer->setColumnWidths([20, 18, 14, 7, 15, 11, 11, 14, 42, 17, 30, 8, 19]);
        $writer->addRow([
            'Nom', 'Prénom', 'Surnom', 'Âge', 'Années de yole', 'Poids (kg)', 'Taille (cm)', 'Niveau',
            'Postes (préféré marqué ★)', 'Téléphone', 'E-mail', 'Actif', 'Présence saison (%)',
        ], bold: true);

        foreach ($members as $member) {
            $writer->addRow([
                $member->last_name,
                $member->first_name,
                $member->nickname,
                $member->age(),
                $member->yoleYears(),
                $member->weight_kg !== null ? (float) $member->weight_kg : null,
                $member->height_cm,
                $member->level?->label(),
                $this->rolesLabel($member),
                $member->phone,
                $member->email,
                $member->is_active ? 'Oui' : 'Non',
                $rates[$member->id] ?? null,
            ]);
        }

        $contents = $writer->toString();

        return response()->streamDownload(
            function () use ($contents): void {
                echo $contents;
            },
            'membres-'.today()->format('Y-m-d').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /**
     * Print-optimised members list (the browser saves it as PDF), with the same filters as the index.
     */
    public function print(Request $request, AttendanceStats $stats): View
    {
        ['members' => $members, 'crewRoles' => $crewRoles, 'filters' => $filters] = $this->filteredMembers($request);
        $level = MemberLevel::tryFrom((string) $filters['level']);
        $role = $crewRoles->firstWhere('code', $filters['role']);

        return view('members.print', [
            'members' => $members,
            'association' => $request->user()->association,
            'rates' => $this->seasonRates($request->user()->association_id, $stats),
            'filtersSummary' => array_values(array_filter([
                $filters['q'] !== '' ? 'Recherche « '.$filters['q'].' »' : null,
                $level ? 'Niveau : '.$level->label() : null,
                $role ? 'Poste : '.$role->label : null,
                ['active' => 'Membres actifs', 'inactive' => 'Membres inactifs', 'all' => 'Tous les membres'][$filters['status']],
            ])),
            'rolesLabels' => $members->mapWithKeys(fn (Member $member) => [$member->id => $this->rolesLabel($member)]),
        ]);
    }

    public function show(Member $member): View
    {
        $member->load('crewRoles');
        $seasonStart = today()->startOfYear();

        $attendances = $member->attendances()
            ->select('attendances.*')
            ->join('outings', 'outings.id', '=', 'attendances.outing_id')
            ->whereNull('outings.deleted_at')
            ->where('outings.status', '!=', OutingStatus::Annulee->value)
            ->whereDate('outings.date', '<=', today())
            ->orderByDesc('outings.date')
            ->orderByDesc('outings.start_time')
            ->with('outing')
            ->get();

        $season = $attendances->filter(fn ($attendance) => $attendance->outing->date->gte($seasonStart));
        $seasonOnSite = $season->filter(fn ($attendance) => $attendance->status->isOnSite())->count();

        $streak = 0;
        foreach ($attendances as $attendance) {
            if (! $attendance->status->isOnSite()) {
                break;
            }
            $streak++;
        }

        $assignments = CrewAssignment::query()
            ->select('crew_assignments.*')
            ->join('crew_plans', 'crew_plans.id', '=', 'crew_assignments.crew_plan_id')
            ->join('outings', 'outings.id', '=', 'crew_plans.outing_id')
            ->where('crew_assignments.member_id', $member->id)
            ->whereNull('crew_plans.deleted_at')
            ->whereNull('outings.deleted_at')
            ->where('outings.status', '!=', OutingStatus::Annulee->value);

        $regattas = (clone $assignments)
            ->where('outings.type', OutingType::Regate->value)
            ->whereDate('outings.date', '>=', $seasonStart)
            ->distinct()
            ->count('outings.id');

        $recentAssignments = $assignments
            ->whereDate('outings.date', '<=', today())
            ->orderByDesc('outings.date')
            ->orderByDesc('outings.start_time')
            ->with(['crewPlan.outing', 'crewPlan.boat', 'position'])
            ->limit(8)
            ->get();

        return view('members.show', [
            'member' => $member,
            'seasonRate' => $season->isNotEmpty() ? (int) round($seasonOnSite / $season->count() * 100) : null,
            'seasonOnSite' => $seasonOnSite,
            'seasonOutings' => Outing::query()
                ->forAssociation($member->association_id)
                ->where('status', '!=', OutingStatus::Annulee)
                ->whereBetween('date', [$seasonStart, today()])
                ->count(),
            'seasonLate' => $season->where('status', AttendanceStatus::Retard)->count(),
            'regattas' => $regattas,
            'streak' => $streak,
            'lastAttendances' => $attendances->take(12)->reverse()->values(),
            'recentAssignments' => $recentAssignments,
        ]);
    }

    public function create(): View
    {
        $member = new Member(['is_active' => true, 'level' => MemberLevel::Debutant]);
        $member->setRelation('crewRoles', collect());

        return view('members.create', [
            'member' => $member,
            'crewRoles' => CrewRole::query()->orderBy('sort_order')->get(),
        ]);
    }

    public function store(MemberRequest $request): RedirectResponse
    {
        $member = DB::transaction(function () use ($request): Member {
            $member = Member::create([
                ...$request->memberAttributes(),
                'association_id' => $request->user()->association_id,
            ]);
            $member->crewRoles()->sync($request->crewRoles());

            return $member;
        });

        return redirect()->route('members.show', $member)->with('status', 'Membre enregistré');
    }

    public function edit(Member $member): View
    {
        $member->load('crewRoles');

        return view('members.edit', [
            'member' => $member,
            'crewRoles' => CrewRole::query()->orderBy('sort_order')->get(),
        ]);
    }

    public function update(MemberRequest $request, Member $member): RedirectResponse
    {
        DB::transaction(function () use ($request, $member): void {
            $member->update($request->memberAttributes());
            $member->crewRoles()->sync($request->crewRoles());
        });

        return redirect()->route('members.show', $member)->with('status', 'Membre enregistré');
    }

    public function destroy(Member $member): RedirectResponse
    {
        DB::transaction(function () use ($member) {
            // Free the member's seats in today's and upcoming crew plans; past plans keep their history.
            CrewAssignment::query()
                ->where('member_id', $member->id)
                ->whereHas('crewPlan.outing', fn ($query) => $query->whereDate('date', '>=', today()))
                ->get()
                ->each->delete();

            $member->delete();
        });

        return redirect()->route('members.index')->with('status', 'Membre supprimé');
    }

    /**
     * Members matching the index filters (q, level, status, role), plus the list before the role filter.
     *
     * @return array{members: Collection<int, Member>, all: Collection<int, Member>, crewRoles: Collection<int, CrewRole>, filters: array{q: string, level: ?string, status: string, role: ?string}}
     */
    private function filteredMembers(Request $request): array
    {
        $search = trim((string) $request->query('q', ''));
        $level = MemberLevel::tryFrom((string) $request->query('level'));
        $status = in_array($request->query('status'), ['inactive', 'all'], true) ? $request->query('status') : 'active';
        $crewRoles = CrewRole::query()->orderBy('sort_order')->get();
        $role = $crewRoles->firstWhere('code', $request->query('role'));

        $members = Member::query()
            ->forAssociation($request->user()->association_id)
            ->with('crewRoles')
            ->when($status === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->when($level, fn (Builder $query) => $query->where('level', $level))
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                $query->whereLike('first_name', "%{$search}%")
                    ->orWhereLike('last_name', "%{$search}%")
                    ->orWhereLike('nickname', "%{$search}%")
                    ->orWhereLike('phone', "%{$search}%");
            }))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return [
            'members' => $role
                ? $members->filter(fn (Member $member) => $member->crewRoles->contains('id', $role->id))->values()
                : $members,
            'all' => $members,
            'crewRoles' => $crewRoles,
            'filters' => ['q' => $search, 'level' => $level?->value, 'status' => $status, 'role' => $role?->code],
        ];
    }

    /**
     * Season attendance rate per member id.
     *
     * @return Collection<int, int|null>
     */
    private function seasonRates(int $associationId, AttendanceStats $stats): Collection
    {
        return $stats->perMember($associationId, today()->startOfYear())->map(fn (array $row) => $row['rate']);
    }

    /** "Bwa dressé ★, Écopeur": crew roles, preferred first and starred. */
    private function rolesLabel(Member $member): string
    {
        return $member->orderedCrewRoles()
            ->map(fn (CrewRole $role) => $role->label.($role->pivot->is_preferred ? ' ★' : ''))
            ->join(', ');
    }
}
