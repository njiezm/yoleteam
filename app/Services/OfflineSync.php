<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\CrewPlanStatus;
use App\Enums\SyncAction;
use App\Enums\SyncStatus;
use App\Http\Requests\UpdateCrewPlanRequest;
use App\Models\Attendance;
use App\Models\Boat;
use App\Models\CrewPlan;
use App\Models\Outing;
use App\Models\SyncOperation;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Replays operations recorded offline by a device (attendance and crew plans only).
 *
 * Idempotent: the client-generated operation id is the sync_operations primary key.
 * Last write wins on updated_at: device timestamps are corrected by the device clock offset and stored as the
 * record's updated_at; an operation older than the server record is kept as a conflict for a human to resolve.
 */
class OfflineSync
{
    public const ENTITIES = ['attendance', 'crew_plan', 'form'];

    /**
     * Forms that can be filled offline and replayed as-is through their normal route (same validation,
     * authorization and tenant scoping as online).
     */
    public const REPLAYABLE_ROUTES = [
        'outings.store', 'outings.update',
        'members.store', 'members.update',
        'races.stages.store', 'races.stages.results.update',
    ];

    public function __construct(
        private readonly AttendanceRecorder $recorder,
        private readonly CrewPlanSync $crewPlanSync,
    ) {}

    /**
     * @param  array{id: string, entity: string, entity_uuid: string, payload: array<string, mixed>, client_updated_at: string}  $data
     * @param  int  $clockOffset  seconds to add to device timestamps (server now - device now)
     */
    public function handle(User $user, string $deviceId, array $data, int $clockOffset = 0): SyncOperation
    {
        if ($existing = SyncOperation::query()->find($data['id'])) {
            return $existing;
        }

        // Browsers send UTC; timestamps are stored in the application timezone, so convert before saving or comparing.
        $clientAt = Carbon::parse($data['client_updated_at'])->setTimezone(config('app.timezone'))->addSeconds($clockOffset);

        $operation = new SyncOperation([
            'id' => $data['id'],
            'association_id' => $user->association_id,
            'user_id' => $user->id,
            'device_id' => $deviceId,
            'entity' => $data['entity'],
            'entity_uuid' => $data['entity_uuid'],
            'action' => SyncAction::Upsert,
            'payload' => $data['payload'],
            'client_updated_at' => $clientAt->isFuture() ? now() : $clientAt,
        ]);

        $this->run($operation, $user, force: false);

        return $operation;
    }

    /**
     * Resolves a conflict: apply the device version anyway, or keep the server version.
     */
    public function resolve(SyncOperation $operation, User $user, bool $keepDevice): SyncOperation
    {
        if ($keepDevice) {
            $this->run($operation, $user, force: true);
        } else {
            $operation->forceFill([
                'status' => SyncStatus::Rejected,
                'conflict_details' => [...($operation->conflict_details ?? []), 'resolution' => 'server', 'resolved_by' => $user->id],
            ])->save();
        }

        return $operation;
    }

    private function run(SyncOperation $operation, User $user, bool $force): void
    {
        try {
            DB::transaction(function () use ($operation, $user, $force) {
                $conflict = match ($operation->entity) {
                    'attendance' => $this->attendance($operation, $user, $force),
                    'crew_plan' => $this->crewPlan($operation, $user, $force),
                    'form' => $this->form($operation),
                };

                $operation->forceFill($conflict === null
                    ? ['status' => SyncStatus::Applied, 'applied_at' => now(), 'conflict_details' => $force ? ['resolution' => 'device', 'resolved_by' => $user->id] : null]
                    : ['status' => SyncStatus::Conflict, 'conflict_details' => $conflict]
                )->save();
            });
        } catch (ValidationException $exception) {
            $operation->forceFill([
                'status' => SyncStatus::Rejected,
                'conflict_details' => ['errors' => $exception->errors()],
            ])->save();
        }
    }

    /**
     * @return array<string, mixed>|null conflict details, or null once applied
     */
    private function attendance(SyncOperation $operation, User $user, bool $force): ?array
    {
        $outing = Outing::query()->forAssociation($user->association_id)->where('uuid', $operation->entity_uuid)->first()
            ?? throw ValidationException::withMessages(['entity_uuid' => 'Sortie introuvable.']);

        $data = Validator::make($operation->payload, [
            'member_id' => ['required', 'integer', Rule::exists('members', 'id')->where('association_id', $user->association_id)],
            'status' => ['nullable', Rule::enum(AttendanceStatus::class)],
        ])->validate();

        $status = isset($data['status']) ? AttendanceStatus::from($data['status']) : null;
        $current = Attendance::withTrashed()->where('outing_id', $outing->id)->where('member_id', $data['member_id'])->first();
        $currentStatus = $current && ! $current->trashed() ? $current->status : null;

        if (! $force && $current && $currentStatus !== $status && $this->isNewer($current->updated_at, $operation->client_updated_at)) {
            return [
                'server_status' => $currentStatus?->value,
                'server_updated_at' => $current->updated_at->toIso8601String(),
                'server_user_id' => $current->recorded_by,
            ];
        }

        $this->recorder->record($outing, (int) $data['member_id'], $status, $user->id, $force ? now() : $operation->client_updated_at);

        return null;
    }

    /**
     * @return array<string, mixed>|null conflict details, or null once applied
     */
    private function crewPlan(SyncOperation $operation, User $user, bool $force): ?array
    {
        $plan = CrewPlan::query()
            ->where('uuid', $operation->entity_uuid)
            ->whereHas('outing', fn ($query) => $query->forAssociation($user->association_id))
            ->first()
            ?? $this->createOfflinePlan($operation, $user)
            ?? throw ValidationException::withMessages(['entity_uuid' => 'Plan d’équipage introuvable.']);

        $data = Validator::make($operation->payload, UpdateCrewPlanRequest::rulesFor($plan, $user->association_id), UpdateCrewPlanRequest::messagesFor())
            ->after(fn ($validator) => UpdateCrewPlanRequest::checkPositions($validator))
            ->validate();

        if (! $force && $this->isNewer($plan->updated_at, $operation->client_updated_at)) {
            return [
                'server_version' => $plan->version,
                'server_status' => $plan->status->value,
                'server_updated_at' => $plan->updated_at->toIso8601String(),
            ];
        }

        $plan = $this->crewPlanSync->sync($plan, $data);
        $plan->forceFill(['updated_at' => $force ? now() : $operation->client_updated_at])->save();

        // "Valider" pressed offline: validate once the state is applied (a plan needs at least one seat).
        if (! empty($operation->payload['validate']) && $plan->assignments()->exists()) {
            $plan->validate($user);
        }

        return null;
    }

    /**
     * A crew plan created offline on the outing page: create it with the client uuid, or reuse the plan
     * someone created online meanwhile for the same outing and boat (then last write wins as usual).
     */
    private function createOfflinePlan(SyncOperation $operation, User $user): ?CrewPlan
    {
        $create = $operation->payload['create'] ?? null;
        if (! is_array($create)) {
            return null;
        }

        $outing = Outing::query()->forAssociation($user->association_id)->where('uuid', $create['outing_uuid'] ?? null)->first()
            ?? throw ValidationException::withMessages(['entity_uuid' => 'Sortie introuvable.']);
        $boat = Boat::query()->forAssociation($user->association_id)->where('is_active', true)->find($create['boat_id'] ?? null)
            ?? throw ValidationException::withMessages(['entity_uuid' => 'Yole introuvable ou indisponible.']);

        $existing = $outing->crewPlans()->where('boat_id', $boat->id)->first();
        if ($existing) {
            return $existing;
        }

        $configuration = $boat->configurations()->find($operation->payload['boat_configuration_id'] ?? null)
            ?? $boat->configurations()->where('is_default', true)->first()
            ?? $boat->configurations()->firstOrFail();

        // A soft-deleted plan still holds the (outing, boat) unique key: bring it back under the new uuid.
        $plan = CrewPlan::withTrashed()->firstOrNew(['outing_id' => $outing->id, 'boat_id' => $boat->id]);
        $plan->forceFill([
            'uuid' => $operation->entity_uuid,
            'boat_configuration_id' => $configuration->id,
            'status' => CrewPlanStatus::Brouillon,
            'created_by' => $plan->created_by ?? $user->id,
            'deleted_at' => null,
            // Older than any device change so the replayed state applies.
            'updated_at' => $operation->client_updated_at->copy()->subSecond(),
        ])->save();

        return $plan;
    }

    /**
     * Replays a form submitted offline through the HTTP kernel, as the signed-in user.
     */
    private function form(SyncOperation $operation): ?array
    {
        $payload = $operation->payload;
        $method = strtoupper((string) ($payload['method'] ?? 'POST'));
        $path = '/'.ltrim((string) parse_url((string) ($payload['url'] ?? ''), PHP_URL_PATH), '/');

        if (! in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            throw ValidationException::withMessages(['form' => 'Formulaire non pris en charge hors ligne.']);
        }

        try {
            $route = app('router')->getRoutes()->match(Request::create($path, $method));
        } catch (\Throwable) {
            throw ValidationException::withMessages(['form' => 'Adresse de formulaire inconnue.']);
        }

        if (! in_array($route->getName(), self::REPLAYABLE_ROUTES, true)) {
            throw ValidationException::withMessages(['form' => 'Formulaire non pris en charge hors ligne.']);
        }

        $current = app('request');
        $fields = collect($payload['fields'] ?? [])->except(['_token', '_method'])->all();
        // Only the host and client details are carried over: the sync call itself is JSON, the replay is a form post.
        $server = ['HTTP_HOST' => $current->getHttpHost(), 'HTTPS' => $current->isSecure() ? 'on' : 'off', 'REMOTE_ADDR' => $current->ip()];
        $replay = Request::create($path, $method, $fields, $current->cookies->all(), [], $server);
        $replay->headers->set('Accept', 'application/json');
        $replay->headers->set('X-Requested-With', 'XMLHttpRequest');
        if ($current->hasSession()) {
            $replay->headers->set('X-CSRF-TOKEN', $current->session()->token());
        }

        try {
            $response = app(Kernel::class)->handle($replay);
        } finally {
            app()->instance('request', $current);
        }

        if ($response->getStatusCode() === 422) {
            $errors = json_decode((string) $response->getContent(), true)['errors'] ?? ['form' => ['Données refusées.']];
            throw ValidationException::withMessages($errors);
        }

        if ($response->getStatusCode() >= 400) {
            throw ValidationException::withMessages(['form' => match ($response->getStatusCode()) {
                403 => 'Action non autorisée pour ce compte.',
                404 => 'L’élément n’existe plus.',
                419 => 'Session expirée : reconnectez-vous puis synchronisez.',
                default => 'Erreur '.$response->getStatusCode().' lors du rejeu.',
            }]);
        }

        return null;
    }

    private function isNewer(?CarbonInterface $server, CarbonInterface $device): bool
    {
        return $server !== null && $server->greaterThan($device);
    }
}
