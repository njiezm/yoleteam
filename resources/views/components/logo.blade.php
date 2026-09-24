@props(['association' => null, 'dark' => true])
<div class="flex items-center gap-2.5">
    <img src="/icons/icon.svg?v=2" alt="" class="w-9 h-9 rounded-xl" width="36" height="36">
    <div class="leading-tight">
        <p @class(['font-extrabold tracking-tight', 'text-white' => $dark, 'text-navy-950' => ! $dark])>YoleTeam</p>
        @if ($association)
            <p @class(['text-[11px] font-medium', 'text-navy-200' => $dark, 'muted' => ! $dark])>{{ $association->name }}</p>
        @endif
    </div>
</div>
