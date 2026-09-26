{{-- Crew plan of the outing page, like the offline page: no drawing, the next action first. Expects $plan, $outing. --}}
@php
    $total = $plan->configuration->positions->count();
    $filled = $plan->assignments->count();
    $url = $plan->isValidated() ? route('crew-plans.show', [$outing, $plan]) : route('crew-plans.edit', [$outing, $plan]);
@endphp
<div class="card p-4 flex flex-wrap items-center gap-3" data-plan-card>
    <span class="w-11 h-11 shrink-0 rounded-xl grid place-items-center text-white font-extrabold" style="background: {{ $plan->boat->color() }}">{{ $filled }}</span>
    <div class="flex-1 min-w-[10rem]">
        <div class="flex items-center gap-2 flex-wrap"><p class="font-bold">{{ $plan->boat->name }}</p><x-plan-status :plan="$plan" /></div>
        <p class="text-xs muted">{{ $filled }}/{{ $total }} postes · {{ $plan->configuration->name }}</p>
        @if ($filled)
            <div class="flex -space-x-2 mt-2">
                @foreach ($plan->assignments->take(9) as $assignment)
                    <x-avatar :member="$assignment->member" size="w-7 h-7 text-[10px]" class="ring-2 ring-white" />
                @endforeach
                @if ($filled > 9)
                    <span class="w-7 h-7 rounded-full bg-slate-100 ring-2 ring-white grid place-items-center text-[10px] font-bold">+{{ $filled - 9 }}</span>
                @endif
            </div>
        @endif
    </div>
    <a href="{{ $url }}" @class(['btn-sm', 'btn-ghost' => $plan->isValidated(), 'btn-primary' => ! $plan->isValidated()])>
        {{ $plan->isValidated() ? 'Voir le plan' : ($filled ? 'Continuer' : 'Composer l’équipage') }}
    </a>
</div>
