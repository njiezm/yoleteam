{{-- Bottom-of-page delete block with a confirmation prompt. --}}
@props(['action', 'label', 'confirm', 'hint' => null])
<form method="POST" action="{{ $action }}" data-confirm="{{ $confirm }}" onsubmit="return confirm(this.dataset.confirm)" {{ $attributes->merge(['class' => 'no-print mt-8']) }}>
    @csrf
    @method('DELETE')
    <div class="card p-5 flex flex-wrap items-center gap-3 border-red-200">
        <div class="flex-1 min-w-0">
            <p class="font-bold">{{ $label }}</p>
            @if ($hint)
                <p class="text-xs muted">{{ $hint }}</p>
            @endif
        </div>
        {{ $slot }}
        <button class="btn-danger btn-sm"><x-icon name="trash" class="w-4 h-4" />Supprimer</button>
    </div>
</form>
