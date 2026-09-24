@props(['association' => null, 'dark' => true])
<div class="flex items-center gap-2.5">
    <span class="w-9 h-9 rounded-xl bg-sun-400 grid place-items-center text-navy-950"><x-icon name="boat" class="w-5 h-5" /></span>
    <div class="leading-tight">
        <p @class(['font-extrabold tracking-tight', 'text-white' => $dark, 'text-navy-950' => ! $dark])>YoleTeam</p>
        @if ($association)
            <p @class(['text-[11px] font-medium', 'text-navy-200' => $dark, 'muted' => ! $dark])>{{ $association->name }}</p>
        @endif
    </div>
</div>
