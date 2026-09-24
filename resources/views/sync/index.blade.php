<x-layouts.app title="Synchronisation" crumb="Mode hors ligne">
    <x-slot:actions>
        <button type="button" data-sync-now class="btn-primary btn-sm"><x-icon name="refresh" class="w-4 h-4" />Synchroniser</button>
    </x-slot:actions>
    <x-slot:sticky>
        <button type="button" data-sync-now class="btn-primary w-full h-12"><x-icon name="refresh" class="w-4 h-4" />Synchroniser</button>
    </x-slot:sticky>

    <div data-sync-page>
        <div class="grid gap-5 lg:grid-cols-3">
            <div class="card p-5 lg:col-span-2" data-sync-status-card>
                <div class="flex items-center gap-4">
                    <span class="w-14 h-14 shrink-0 rounded-2xl grid place-items-center bg-emerald-500 text-white" data-sync-status-icon><x-icon name="cloud-check" class="w-7 h-7" /></span>
                    <div class="flex-1 min-w-0">
                        <p class="text-lg font-extrabold" data-sync-status-title>En ligne</p>
                        <p class="text-sm muted" data-sync-status-text>Vérification des données enregistrées sur cet appareil…</p>
                    </div>
                </div>
            </div>
            <div class="card p-5">
                <p class="font-bold mb-3">Disponible hors ligne</p>
                <div class="space-y-1 text-sm" data-offline-pages><p class="muted">—</p></div>
                <p class="text-[11px] muted mt-3">L’appel et les plans d’équipage des sorties à venir sont gardés sur l’appareil quand vous ouvrez le tableau de bord avec du réseau.</p>
            </div>
        </div>

        <div class="card mt-5 overflow-hidden">
            <div class="p-5 pb-3">
                <x-section-title title="File d’attente de cet appareil"><span class="chip bg-slate-100 text-slate-700" data-sync-queue-count>0</span></x-section-title>
            </div>
            <div class="divide-y divide-slate-100" data-sync-queue>
                <p class="px-5 pb-5 text-sm muted">Aucune modification en attente.</p>
            </div>
        </div>

        @foreach ($conflicts as $operation)
            @php
                $details = $operation->conflict_details ?? [];
                $serverUser = $context['users']->get($details['server_user_id'] ?? 0);
            @endphp
            <div class="card p-5 mt-5 border-red-200">
                <div class="flex items-start gap-3">
                    <span class="w-10 h-10 shrink-0 rounded-xl bg-red-100 text-red-600 grid place-items-center"><x-icon name="alert" /></span>
                    <div class="flex-1 min-w-0">
                        <p class="font-extrabold">Conflit à résoudre</p>
                        <p class="text-sm muted">Modifié hors ligne sur un appareil, alors qu’une version plus récente existait déjà sur le serveur.</p>
                    </div>
                </div>
                <div class="grid sm:grid-cols-2 gap-3 mt-4">
                    <form method="POST" action="{{ route('sync.resolve', $operation) }}" class="rounded-2xl border-2 border-navy-900 p-4 flex flex-col">
                        @csrf
                        <input type="hidden" name="keep" value="device">
                        <p class="text-[11px] font-bold uppercase muted">Version de l’appareil · {{ $operation->client_updated_at->translatedFormat('j M H:i') }}</p>
                        <div class="mt-2 flex-1">@include('sync._operation')</div>
                        <p class="text-xs muted mt-1">par {{ $operation->user?->name ?? '—' }}</p>
                        <button class="btn-primary btn-sm w-full mt-4">Garder cette version</button>
                    </form>
                    <form method="POST" action="{{ route('sync.resolve', $operation) }}" class="rounded-2xl border border-slate-200 p-4 flex flex-col">
                        @csrf
                        <input type="hidden" name="keep" value="server">
                        <p class="text-[11px] font-bold uppercase muted">Version serveur · {{ isset($details['server_updated_at']) ? \Illuminate\Support\Carbon::parse($details['server_updated_at'])->timezone(config('app.timezone'))->translatedFormat('j M H:i') : '—' }}</p>
                        <div class="mt-2 flex-1">
                            @if ($operation->entity === 'attendance')
                                <p class="text-sm font-semibold">{{ ($context['statusLabel'])($details['server_status'] ?? null) }}</p>
                                <p class="text-xs muted">{{ $serverUser ? 'pointé par '.$serverUser->name : '' }}</p>
                            @else
                                @php($plan = $context['plans']->get($operation->entity_uuid))
                                <p class="text-sm font-semibold">Version {{ $details['server_version'] ?? '?' }} · {{ ($details['server_status'] ?? '') === 'valide' ? 'validée' : 'brouillon' }}</p>
                                <p class="text-xs muted">{{ $plan ? $plan->assignments_count.' poste(s) pourvu(s) actuellement' : '' }}</p>
                            @endif
                        </div>
                        <button class="btn-ghost btn-sm w-full mt-4">Garder la version serveur</button>
                    </form>
                </div>
            </div>
        @endforeach

        <div class="card mt-5 overflow-hidden">
            <div class="p-5 pb-3"><x-section-title title="Dernières synchronisations" /></div>
            <div class="divide-y divide-slate-100">
                @forelse ($recent as $operation)
                    <div class="px-5 py-3 flex items-center gap-3">
                        <span @class([
                            'w-9 h-9 shrink-0 rounded-xl grid place-items-center',
                            'bg-emerald-100 text-emerald-700' => $operation->status === \App\Enums\SyncStatus::Applied,
                            'bg-slate-100 text-slate-500' => $operation->status === \App\Enums\SyncStatus::Rejected,
                        ])><x-icon :name="$operation->status === \App\Enums\SyncStatus::Applied ? 'check' : 'x'" class="w-4 h-4" /></span>
                        <div class="flex-1 min-w-0">@include('sync._operation')</div>
                        <div class="text-right shrink-0">
                            <p class="text-xs font-semibold">{{ $operation->status->label() }}</p>
                            <p class="text-[11px] muted">{{ $operation->user?->name }} · {{ $operation->created_at->translatedFormat('j M H:i') }}</p>
                        </div>
                    </div>
                @empty
                    <p class="px-5 pb-5 text-sm muted">Aucune modification hors ligne n’a encore été synchronisée.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-layouts.app>
