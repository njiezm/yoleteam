{{--
    Configuration form (edit or create). Expects:
    $action, $method ('POST'|'PUT'), $formKey (old-input marker), $bag (MessageBag), $configuration (?BoatConfiguration), $submit (label), $id (form id).
--}}
@php
    $old = fn (string $key, mixed $default = null) => old('_form') === $formKey ? old($key, $default) : $default;
    $sails = (int) $old('sail_count', $configuration?->sail_count ?? 2);
    $defaults = \App\Services\BoatLayoutGenerator::defaults($sails);
    $crewCount = fn (string $field) => $old($field, $configuration?->{$field} ?? $defaults[$field]);
    $locked = $configuration && $configuration->crew_plans_count > 0;
@endphp
<form method="POST" action="{{ $action }}" id="{{ $id }}">
    @csrf
    @method($method)
    <input type="hidden" name="_form" value="{{ $formKey }}">
    <div class="grid sm:grid-cols-3 gap-4">
        <div>
            <label class="label" for="{{ $id }}-name">Nom *</label>
            <input id="{{ $id }}-name" name="name" value="{{ $old('name', $configuration?->name) }}" required maxlength="255" placeholder="2 voiles" @class(['input', 'input-error' => $bag->has('name')])>
            @include('boats._error', ['bag' => $bag, 'key' => 'name'])
        </div>
        <div>
            <span class="label">Nombre de voiles</span>
            <div class="seg w-full">
                @foreach ([1, 2] as $count)
                    <label class="flex-1 justify-center"><input type="radio" name="sail_count" value="{{ $count }}" class="sr-only" @checked($sails === $count)>{{ $count }}</label>
                @endforeach
            </div>
            @include('boats._error', ['bag' => $bag, 'key' => 'sail_count'])
        </div>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-4">
        @foreach ([
            'bwa_count' => ['Bwa dressés', 1, 12, 'Tous au vent'],
            'ecoute_count' => ['Écoutes', 1, 4, $sails >= 2 ? 'Petite + grande voile' : 'Une voile'],
            'cordes_count' => ['Cordes', 0, 2, 'À l’avant (2 voiles)'],
            'pagaie_count' => ['Pagaies', 0, 3, 'Sans compter le patron'],
        ] as $field => [$label, $min, $max, $hint])
            <div>
                <label class="label" for="{{ $id }}-{{ $field }}">{{ $label }}</label>
                <input id="{{ $id }}-{{ $field }}" name="{{ $field }}" type="number" min="{{ $min }}" max="{{ $max }}" inputmode="numeric"
                       value="{{ $crewCount($field) }}" placeholder="{{ $defaults[$field] }}" @class(['input', 'input-error' => $bag->has($field)])>
                <p class="text-[11px] muted mt-1">{{ $hint }}</p>
                @include('boats._error', ['bag' => $bag, 'key' => $field])
            </div>
        @endforeach
    </div>
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <label class="flex items-center gap-2 text-sm font-semibold">
            <input type="hidden" name="is_default" value="0">
            @if ($configuration?->is_default)
                <input type="hidden" name="is_default" value="1">
                <input type="checkbox" checked disabled class="w-4 h-4 accent-navy-900">Configuration par défaut
            @else
                <input type="checkbox" name="is_default" value="1" class="w-4 h-4 accent-navy-900" @checked($old('is_default', false))>Configuration par défaut
            @endif
        </label>
        <button class="btn-primary btn-sm hidden lg:inline-flex">{{ $submit }}</button>
    </div>
    <p class="text-xs muted mt-3">
        @if ($locked)
            <x-icon name="lock" class="w-3.5 h-3.5 inline -mt-0.5" /> Utilisée par {{ $configuration->crew_plans_count }} plan(s) d’équipage : la composition de l’équipage ne peut plus changer.
        @elseif ($configuration)
            Changer la voilure ou le nombre de postes régénère automatiquement le plan de la yole. Fonds / écopeurs : réglés à chaque sortie dans le plan d’équipage.
        @else
            Habituellement : misaine = 9 bwa, 2 écoutes, 2 pagaies ; 2 voiles = 8 bwa, 2 cordes, 4 écoutes, 2 pagaies (+ le patron). Fonds / écopeurs : réglés à chaque sortie.
        @endif
    </p>
</form>
