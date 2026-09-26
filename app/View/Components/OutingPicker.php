<?php

namespace App\View\Components;

use App\Enums\OutingStatus;
use App\Models\Outing;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;

/**
 * Outing selector of the Présences and Plan d'équipage pages (today's outing by default). Each option links to
 * the outing's own page, so that pages cached for offline use stay reachable one by one.
 */
class OutingPicker extends Component
{
    /** @var Collection<string, Collection<int, array{url: string, label: string, selected: bool}>> */
    public Collection $groups;

    /**
     * @param  'attendance'|'plans'  $target
     */
    public function __construct(public ?Outing $current = null, public string $target = 'attendance')
    {
        $outings = Outing::query()
            ->forAssociation(Auth::user()->association_id)
            ->where('status', '!=', OutingStatus::Annulee)
            ->whereBetween('date', [today()->subDays(60), today()->addDays(60)])
            ->with('crewPlans:id,outing_id')
            ->orderByDesc('date')
            ->orderByDesc('start_time')
            ->get();

        $option = fn (Outing $outing) => [
            'url' => $this->url($outing),
            'label' => ($outing->isToday() ? 'Aujourd’hui · ' : ucfirst($outing->date->translatedFormat('D j M')).' · ').$outing->title,
            'selected' => $outing->is($current),
        ];

        $this->groups = collect([
            'À venir' => $outings->filter(fn (Outing $outing) => $outing->date->isAfter(today()))->reverse()->map($option)->values(),
            'Aujourd’hui et passées' => $outings->filter(fn (Outing $outing) => ! $outing->date->isAfter(today()))->map($option)->values(),
        ])->filter->isNotEmpty();
    }

    private function url(Outing $outing): string
    {
        if ($this->target === 'attendance') {
            return route('attendance.edit', $outing);
        }

        return $outing->crewPlans->count() === 1
            ? route('crew-plans.edit', [$outing, $outing->crewPlans->first()])
            : route('outings.show', $outing);
    }

    public function render(): View|Closure|string
    {
        return <<<'BLADE'
            <label {{ $attributes->merge(['class' => 'relative block']) }}>
                <span class="sr-only">Choisir la sortie</span>
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"><x-icon name="calendar" class="w-4 h-4" /></span>
                <select class="input pl-9 font-semibold" data-outing-picker onchange="if (this.value) location.href = this.value">
                    @foreach ($groups as $label => $options)
                        <optgroup label="{{ $label }}">
                            @foreach ($options as $option)
                                <option value="{{ $option['url'] }}" @selected($option['selected'])>{{ $option['label'] }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </label>
            BLADE;
    }
}
