<x-layouts.app title="Présences du jour" :crumb="ucfirst($outing->date->translatedFormat('D j M')).' · '.$outing->title" :back="route('outings.show', $outing)">
    <x-slot:actions>
        <button form="attendance-form" name="all_present" value="1" class="btn-ghost btn-sm" data-all-present><x-icon name="check" class="w-4 h-4" />Tous présents</button>
        <a href="{{ route('outings.show', $outing) }}" class="btn-primary btn-sm">Équipages <x-icon name="right" class="w-4 h-4" /></a>
    </x-slot:actions>
    <x-slot:sticky>
        <div class="flex gap-2">
            <button form="attendance-form" name="all_present" value="1" class="btn-ghost flex-1 h-12" data-all-present><x-icon name="check" class="w-4 h-4" />Tous présents</button>
            <a href="{{ route('outings.show', $outing) }}" class="btn-sun h-12">Équipages <x-icon name="right" class="w-4 h-4" /></a>
        </div>
    </x-slot:sticky>

    @php($statuses = \App\Enums\AttendanceStatus::cases())
    <div data-attendance data-url="{{ route('attendance.update', $outing) }}" data-outing-uuid="{{ $outing->uuid }}" data-outing-label="{{ $outing->title }} · {{ $outing->date->translatedFormat('j M') }}">
        <div class="card p-4 lg:p-5 flex flex-col lg:flex-row lg:items-center gap-4">
            <div class="flex items-center gap-3 flex-1 min-w-0">
                <span class="w-12 h-12 shrink-0 rounded-2xl bg-navy-900 text-sun-400 grid place-items-center"><x-icon name="calendar" /></span>
                <div class="min-w-0">
                    <p class="font-extrabold truncate">{{ $outing->title }}</p>
                    <p class="text-xs muted truncate">{{ collect([$outing->time_range, $outing->location, $outing->crewPlans->map(fn ($plan) => $plan->boat->name)->join(', '), $outing->conditionsSummary()])->filter()->join(' · ') }}</p>
                </div>
            </div>
            <div class="grid grid-cols-5 gap-2 lg:w-[520px]">
                @foreach ($statuses as $status)
                    <div class="rounded-xl p-2.5 text-center" style="background: {{ $status->background() }}; color: {{ $status->textColor() }}">
                        <p class="text-xl font-extrabold" data-count="{{ $status->value }}">{{ $counts[$status->value] }}</p>
                        <p class="text-[10px] font-bold uppercase tracking-wide">{{ $status->label() }}</p>
                    </div>
                @endforeach
                <div class="rounded-xl p-2.5 text-center bg-slate-100">
                    <p class="text-xl font-extrabold text-slate-600" data-count="none">{{ $members->count() - $attendances->count() }}</p>
                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">À pointer</p>
                </div>
            </div>
        </div>

        <label class="relative block mt-4">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"><x-icon name="search" class="w-4 h-4" /></span>
            <input type="search" data-search class="input pl-9" placeholder="Rechercher un membre…" aria-label="Rechercher un membre">
        </label>

        <form id="attendance-form" method="POST" action="{{ route('attendance.update', $outing) }}">
            @csrf
            @method('PUT')
            <div class="card mt-4 divide-y divide-slate-100 overflow-hidden">
                @forelse ($members as $member)
                    @php($current = $attendances->get($member->id)?->status)
                    <div @class(['flex items-center gap-2.5 lg:gap-3 px-3 lg:px-5 py-3', 'bg-amber-50/40' => ! $current])
                         data-member-row="{{ $member->id }}" data-label="{{ $member->short_name }}" data-name="{{ \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($member->full_name.' '.$member->nickname)) }}">
                        <x-avatar :member="$member" />
                        <div class="flex-1 min-w-0">
                            <p class="font-bold truncate"><span class="lg:hidden">{{ $member->short_name }}</span><span class="hidden lg:inline">{{ $member->full_name }}</span></p>
                            <p class="text-xs muted truncate">{{ $member->primaryCrewRole()?->label ?? 'Sans poste' }}@if ($member->weight_kg)<span class="hidden sm:inline"> · {{ (float) $member->weight_kg }} kg</span>@endif</p>
                        </div>
                        <div class="flex gap-1 lg:gap-1.5">
                            @foreach ($statuses as $status)
                                @php($on = $current === $status)
                                <button name="statuses[{{ $member->id }}]" value="{{ $on ? '' : $status->value }}"
                                        data-member="{{ $member->id }}" data-status="{{ $status->value }}" data-color="{{ $status->color() }}"
                                        aria-pressed="{{ $on ? 'true' : 'false' }}" title="{{ $status->label() }}"
                                        @class([
                                            'h-10 w-10 lg:w-auto lg:px-3 rounded-xl text-[13px] font-bold border transition cursor-pointer',
                                            'text-white border-transparent shadow-sm' => $on,
                                            'bg-white border-slate-200 text-slate-500 hover:border-slate-300' => ! $on,
                                        ])
                                        @style(["background: {$status->color()}" => $on])>
                                    <span class="lg:hidden">{{ mb_substr($status->label(), 0, 1) }}</span><span class="hidden lg:inline">{{ $status->label() }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="p-5 text-sm muted">Aucun membre actif. <a href="{{ route('members.index') }}" class="font-semibold text-navy-700">Gérer les membres</a></p>
                @endforelse
            </div>
        </form>
        <p class="text-xs muted text-center mt-4 flex items-center justify-center gap-1.5" data-save-state><x-icon name="check" class="w-3.5 h-3.5" />Chaque clic est enregistré immédiatement.</p>
    </div>
</x-layouts.app>
