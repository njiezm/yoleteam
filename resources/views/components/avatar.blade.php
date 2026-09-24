@props(['member', 'size' => 'w-10 h-10 text-sm'])
<span {{ $attributes->merge(['class' => "$size rounded-full grid place-items-center font-bold text-white shrink-0"]) }} style="background: {{ $member->color() }}">{{ $member->initials }}</span>
