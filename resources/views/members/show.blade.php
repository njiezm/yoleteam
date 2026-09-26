<x-layouts.app :title="$member->full_name" crumb="Membres" :back="route('members.index')">
    @can('manage')
        <x-slot:actions>
            <a href="{{ route('members.edit', $member) }}" class="btn-ghost btn-sm"><x-icon name="edit" class="w-4 h-4" />Modifier</a>
        </x-slot:actions>
        <x-slot:sticky>
            <a href="{{ route('members.edit', $member) }}" class="btn-primary w-full h-12"><x-icon name="edit" class="w-4 h-4" />Modifier le membre</a>
        </x-slot:sticky>
    @endcan

    <div class="grid gap-5 lg:grid-cols-[340px_1fr]">
        <div class="space-y-5">
            <div class="card p-5 text-center">
                <div class="mx-auto w-fit"><x-avatar :member="$member" size="w-24 h-24 text-3xl" class="ring-4 ring-sun-400/40" /></div>
                <h2 class="mt-3 text-xl font-extrabold">{{ $member->full_name }}</h2>
                @if ($member->nickname)
                    <p class="muted text-sm">« {{ $member->nickname }} »</p>
                @endif
                @if ($member->birth_date || $member->yole_since_year !== null)
                    <p class="text-sm font-semibold text-slate-600 mt-1">{{ implode(' · ', array_filter([$member->formattedAge(), $member->formattedYoleYears()])) }}</p>
                @endif
                <div class="flex justify-center flex-wrap gap-1.5 mt-3">
                    <x-level-pill :level="$member->level" />
                    @if ($member->is_active)
                        <span class="chip bg-emerald-100 text-emerald-800">Actif</span>
                    @else
                        <span class="chip bg-slate-100 text-slate-500">Inactif</span>
                    @endif
                </div>
                <div class="grid grid-cols-3 gap-2 mt-5">
                    @foreach ([
                        ['Poids', $member->weight_kg !== null ? $member->formattedWeight().' kg' : '—'],
                        ['Taille', $member->height_cm ? $member->height_cm.' cm' : '—'],
                        ['Présence', $seasonRate !== null ? $seasonRate.'%' : '—'],
                    ] as [$label, $value])
                        <div class="rounded-xl bg-slate-50 p-2.5"><p class="text-[10px] font-bold uppercase muted">{{ $label }}</p><p class="font-extrabold">{{ $value }}</p></div>
                    @endforeach
                </div>
                @if ($member->phone || $member->email)
                    <div @class(['grid gap-2 mt-4', 'grid-cols-2' => $member->phone && $member->email])>
                        @if ($member->phone)
                            <a class="btn-ghost btn-sm" href="tel:{{ preg_replace('/[^0-9+]/', '', $member->phone) }}"><x-icon name="phone" class="w-4 h-4" />Appeler</a>
                        @endif
                        @if ($member->email)
                            <a class="btn-ghost btn-sm" href="mailto:{{ $member->email }}"><x-icon name="mail" class="w-4 h-4" />E-mail</a>
                        @endif
                    </div>
                @endif
            </div>

            <div class="card p-5">
                <x-section-title title="Postes maîtrisés" />
                <div class="space-y-2">
                    @forelse ($member->orderedCrewRoles() as $crewRole)
                        <div class="flex items-center gap-3 p-2.5 rounded-xl bg-slate-50">
                            <span class="w-3 h-3 rounded-full" style="background: {{ $crewRole->color }}"></span>
                            <span class="font-semibold flex-1">{{ $crewRole->label }}</span>
                            @if ($crewRole->pivot->is_preferred)
                                <span class="chip bg-sun-100 text-amber-800"><x-icon name="star" class="w-3 h-3 fill-current" />Préféré</span>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm muted">Aucun poste renseigné.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="space-y-5">
            <div class="grid grid-cols-2 xl:grid-cols-4 gap-3">
                <x-kpi label="Sorties (saison)" :value="$seasonOnSite" :sub="'sur '.$seasonOutings.' programmée'.($seasonOutings > 1 ? 's' : '')" icon="calendar" />
                <x-kpi label="Retards" :value="$seasonLate" sub="cette saison" icon="clock" tone="sun" />
                <x-kpi label="Régates" :value="$regattas" sub="en équipage cette saison" icon="trophy" tone="sky" />
                <x-kpi label="Série en cours" :value="$streak" sub="présences d’affilée" icon="check" tone="green" />
            </div>

            <div class="card p-5">
                <x-section-title :title="$lastAttendances->count() > 1 ? $lastAttendances->count().' dernières sorties' : 'Dernières sorties'">
                    <a href="{{ route('attendance.stats', ['period' => 'season']) }}" class="text-sm font-semibold text-navy-700">Historique complet</a>
                </x-section-title>
                @if ($lastAttendances->isEmpty())
                    <p class="text-sm muted">Pas encore d’appel enregistré pour ce membre.</p>
                @else
                    @php($icons = ['present' => '✓', 'retard' => '◷', 'excuse' => 'E', 'absent' => '✕'])
                    <div class="flex gap-1.5 flex-wrap">
                        @foreach ($lastAttendances as $attendance)
                            <span class="w-9 h-9 rounded-lg grid place-items-center text-xs font-bold text-white" style="background: {{ $attendance->status->color() }}" title="{{ $attendance->outing->date->translatedFormat('j M') }} · {{ $attendance->outing->title }} — {{ $attendance->status->label() }}">{{ $icons[$attendance->status->value] }}</span>
                        @endforeach
                    </div>
                    <div class="flex flex-wrap gap-4 mt-3 text-xs muted">
                        @foreach (\App\Enums\AttendanceStatus::cases() as $status)
                            <span class="flex items-center gap-1.5"><i class="w-2.5 h-2.5 rounded-sm" style="background: {{ $status->color() }}"></i>{{ $status->label() }}</span>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="card overflow-hidden">
                <div class="p-5 pb-0"><x-section-title title="Postes occupés récemment" /></div>
                @if ($recentAssignments->isEmpty())
                    <p class="text-sm muted px-5 pb-5">Aucun poste occupé pour l’instant.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-slate-50"><tr><th class="th">Date</th><th class="th">Sortie</th><th class="th">Yole</th><th class="th">Poste</th></tr></thead>
                            <tbody>
                                @foreach ($recentAssignments as $assignment)
                                    <tr>
                                        <td class="td whitespace-nowrap">{{ $assignment->crewPlan->outing->date->translatedFormat('j M') }}</td>
                                        <td class="td">{{ $assignment->crewPlan->outing->title }}</td>
                                        <td class="td">{{ $assignment->crewPlan->boat->name }}</td>
                                        <td class="td font-semibold">{{ $assignment->position->label }}@if ($assignment->bwa_placement) · {{ mb_strtolower($assignment->bwa_placement->label()) }}@endif</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            @if ($member->notes)
                <div class="card p-5">
                    <x-section-title title="Notes du patron" />
                    <p class="text-sm text-slate-600 whitespace-pre-line">{{ $member->notes }}</p>
                </div>
            @endif
        </div>
    </div>

    @can('manage')
        <x-delete-zone :action="route('members.destroy', $member)" label="Supprimer ce membre"
                       :confirm="'Supprimer '.$member->full_name.' ?'"
                       hint="Il n’apparaîtra plus dans l’appel ni dans les équipages. Pour une pause, décochez plutôt « Membre actif » dans sa fiche." />
    @endcan
</x-layouts.app>
