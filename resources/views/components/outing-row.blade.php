@props(['outing'])
<a href="{{ route('outings.show', $outing) }}" class="flex items-center gap-4 py-3 group">
    <div class="w-14 shrink-0 text-center rounded-xl py-1.5 {{ $outing->isToday() ? 'bg-sun-400 text-navy-950' : 'bg-navy-50 text-navy-800' }}">
        <p class="text-[10px] font-bold uppercase">{{ rtrim($outing->date->translatedFormat('D'), '.') }}</p>
        <p class="text-lg font-extrabold leading-none">{{ $outing->date->format('j') }}</p>
        <p class="text-[10px] font-semibold">{{ $outing->date->translatedFormat('M') }}</p>
    </div>
    <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2 flex-wrap">
            <p class="font-bold truncate group-hover:text-navy-600">{{ $outing->title }}</p>
            <span class="chip {{ match ($outing->type) { \App\Enums\OutingType::Regate => 'bg-sun-100 text-amber-800', \App\Enums\OutingType::Tdy => 'bg-sky-100 text-sky-800', default => 'bg-slate-100 text-slate-700' } }}">{{ $outing->type->label() }}</span>
        </div>
        <p class="text-xs muted mt-0.5 flex items-center gap-3 flex-wrap">
            @if ($outing->time_range)
                <span class="inline-flex items-center gap-1"><x-icon name="clock" class="w-3.5 h-3.5" />{{ $outing->time_range }}</span>
            @endif
            @if ($outing->location)
                <span class="inline-flex items-center gap-1"><x-icon name="pin" class="w-3.5 h-3.5" />{{ $outing->location }}</span>
            @endif
            @if ($outing->relationLoaded('crewPlans') && $outing->crewPlans->isNotEmpty())
                <span class="inline-flex items-center gap-1"><x-icon name="boat" class="w-3.5 h-3.5" />{{ $outing->crewPlans->map(fn ($plan) => $plan->boat->name)->join(', ') }}</span>
            @endif
        </p>
    </div>
    <x-outing-status :status="$outing->status" class="hidden sm:inline-flex" />
    <x-icon name="right" class="w-4 h-4 text-slate-400" />
</a>
