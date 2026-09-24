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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MemberController extends Controller
{
    public function index(Request $request, AttendanceStats $stats): View
    {
        $associationId = $request->user()->association_id;
        $search = trim((string) $request->query('q', ''));
        $level = MemberLevel::tryFrom((string) $request->query('level'));
        $status = in_array($request->query('status'), ['inactive', 'all'], true) ? $request->query('status') : 'active';
        $crewRoles = CrewRole::query()->orderBy('sort_order')->get();
        $role = $crewRoles->firstWhere('code', $request->query('role'));

        $members = Member::query()
            ->forAssociation($associationId)
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

        $roleCounts = $crewRoles->mapWithKeys(fn (CrewRole $crewRole) => [
            $crewRole->code => $members->filter(fn (Member $member) => $member->crewRoles->contains('id', $crewRole->id))->count(),
        ]);

        return view('members.index', [
            'members' => $role
                ? $members->filter(fn (Member $member) => $member->crewRoles->contains('id', $role->id))->values()
                : $members,
            'total' => $members->count(),
            'activeCount' => Member::query()->forAssociation($associationId)->active()->count(),
            'crewRoles' => $crewRoles,
            'roleCounts' => $roleCounts,
            'rates' => $stats->perMember($associationId, today()->startOfYear())->map(fn (array $row) => $row['rate']),
            'filters' => ['q' => $search, 'level' => $level?->value, 'status' => $status, 'role' => $role?->code],
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
}
