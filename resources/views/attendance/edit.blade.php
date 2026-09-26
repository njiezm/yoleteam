<x-layouts.app title="Présences" :crumb="ucfirst($outing->date->translatedFormat('D j M')).' · '.$outing->title" :back="route('outings.show', $outing)">
    <x-slot:actions>
        <a href="{{ route('attendance.stats') }}" class="btn-ghost btn-sm"><x-icon name="chart" class="w-4 h-4" />Statistiques</a>
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
    <div class="flex flex-col sm:flex-row gap-2 mb-4">
        <x-outing-picker :current="$outing" target="attendance" class="flex-1" />
        <a href="{{ route('attendance.stats') }}" class="btn-ghost lg:hidden"><x-icon name="chart" class="w-4 h-4" />Statistiques</a>
    </div>

    <div data-attendance data-url="{{ route('attendance.update', $outing) }}" data-outing-uuid="{{ $outing->uuid }}" data-outing-date="{{ $outing->date->toDateString() }}" data-outing-label="{{ $outing->title }} · {{ $outing->date->translatedFormat('j M') }}">
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
                @include('attendance._rows')
            </div>
        </form>
        <p class="text-xs muted text-center mt-4 flex items-center justify-center gap-1.5" data-save-state><x-icon name="check" class="w-3.5 h-3.5" />Chaque clic est enregistré immédiatement.</p>
    </div>
</x-layouts.app>
