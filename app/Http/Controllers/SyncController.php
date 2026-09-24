<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\SyncStatus;
use App\Models\CrewPlan;
use App\Models\Member;
use App\Models\Outing;
use App\Models\SyncOperation;
use App\Models\User;
use App\Services\OfflineSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SyncController extends Controller
{
    public function index(Request $request): View
    {
        $associationId = $request->user()->association_id;

        $conflicts = SyncOperation::query()->forAssociation($associationId)
            ->where('status', SyncStatus::Conflict)
            ->with('user')
            ->oldest('client_updated_at')
            ->get();

        $recent = SyncOperation::query()->forAssociation($associationId)
            ->where('status', '!=', SyncStatus::Conflict)
            ->with('user')
            ->latest()
            ->limit(15)
            ->get();

        return view('sync.index', [
            'conflicts' => $conflicts,
            'recent' => $recent,
            'context' => $this->context($conflicts->concat($recent), $associationId),
        ]);
    }

    /** Fresh CSRF token: pages served from the offline cache may carry an outdated one. */
    public function token(Request $request): JsonResponse
    {
        return response()->json(['token' => $request->session()->token()]);
    }

    public function store(Request $request, OfflineSync $sync): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:100'],
            'sent_at' => ['required', 'date'],
            'operations' => ['required', 'array', 'max:500'],
            'operations.*.id' => ['required', 'uuid'],
            'operations.*.entity' => ['required', Rule::in(OfflineSync::ENTITIES)],
            'operations.*.entity_uuid' => ['required', 'uuid'],
            'operations.*.payload' => ['required', 'array'],
            'operations.*.client_updated_at' => ['required', 'date'],
        ]);

        // Correct the device clock: server time minus device time when the batch was sent (a few seconds is just latency).
        $offset = (int) round(Carbon::parse($data['sent_at'])->diffInSeconds(now(), false));
        $offset = abs($offset) < 5 ? 0 : $offset;

        $results = collect($data['operations'])->map(function (array $operation) use ($request, $sync, $data, $offset) {
            $result = $sync->handle($request->user(), $data['device_id'], $operation, $offset);

            return [
                'id' => $result->id,
                'status' => $result->status->value,
                'message' => collect($result->conflict_details['errors'] ?? [])->flatten()->first(),
            ];
        });

        return response()->json(['results' => $results, 'synced_at' => now()->toIso8601String()]);
    }

    public function resolve(Request $request, SyncOperation $syncOperation, OfflineSync $sync): RedirectResponse
    {
        abort_unless($syncOperation->status === SyncStatus::Conflict, 409);

        $choice = $request->validate(['keep' => ['required', Rule::in(['device', 'server'])]])['keep'];
        $sync->resolve($syncOperation, $request->user(), $choice === 'device');

        $message = match ($syncOperation->status) {
            SyncStatus::Applied => 'Votre version a été appliquée',
            SyncStatus::Rejected => $choice === 'server' ? 'La version serveur est conservée' : 'Version refusée : '.collect($syncOperation->conflict_details['errors'] ?? [])->flatten()->first(),
            default => 'Conflit mis à jour',
        };

        return redirect()->route('sync.index')->with('status', $message);
    }

    /**
     * Records referenced by the operations, for human-readable labels.
     *
     * @param  Collection<int, SyncOperation>  $operations
     * @return array{outings: Collection<string, Outing>, plans: Collection<string, CrewPlan>, members: Collection<int, Member>, users: Collection<int, User>, statusLabel: \Closure(?string): string}
     */
    private function context(Collection $operations, int $associationId): array
    {
        $byEntity = $operations->groupBy('entity');

        return [
            'outings' => Outing::query()->forAssociation($associationId)
                ->whereIn('uuid', $byEntity->get('attendance', collect())->pluck('entity_uuid'))
                ->get()->keyBy('uuid'),
            'plans' => CrewPlan::query()
                ->whereIn('uuid', $byEntity->get('crew_plan', collect())->pluck('entity_uuid'))
                ->whereHas('outing', fn ($query) => $query->forAssociation($associationId))
                ->with(['boat', 'outing', 'validator'])
                ->withCount('assignments')
                ->get()->keyBy('uuid'),
            'members' => Member::withTrashed()->forAssociation($associationId)->with('crewRoles')
                ->whereIn('id', $byEntity->get('attendance', collect())->pluck('payload.member_id')->filter())
                ->get()->keyBy('id'),
            'users' => User::query()->where('association_id', $associationId)->get()->keyBy('id'),
            'statusLabel' => fn (?string $value) => $value ? AttendanceStatus::from($value)->label() : 'Non pointé',
        ];
    }
}
