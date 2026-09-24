<?php

namespace App\Http\Controllers;

use App\Models\Boat;
use App\Models\BoatConfiguration;
use App\Services\BoatLayoutGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The boat page holds several configuration forms: each one validates into its own error bag.
 */
class BoatConfigurationController extends Controller
{
    public function __construct(private readonly BoatLayoutGenerator $generator) {}

    public function store(Request $request, Boat $boat): RedirectResponse
    {
        $data = $this->validated($request, 'newConfiguration');

        $configuration = DB::transaction(function () use ($request, $boat, $data) {
            $isDefault = $request->boolean('is_default') || ! $boat->configurations()->where('is_default', true)->exists();

            if ($isDefault) {
                $boat->configurations()->update(['is_default' => false]);
            }

            $configuration = $boat->configurations()->create([...$data, 'is_default' => $isDefault]);
            $this->generator->generate($configuration);

            return $configuration;
        });

        return redirect()->route('boats.show', [$boat, 'configuration' => $configuration])
            ->with('status', 'Configuration créée');
    }

    public function update(Request $request, Boat $boat, BoatConfiguration $configuration): RedirectResponse
    {
        $data = $this->validated($request, 'configuration');

        // Compare effective counts: an empty count means the usual value of the rig.
        $current = [...BoatLayoutGenerator::defaults($configuration->sail_count), ...array_filter($configuration->only(['bwa_count', 'cordes_count', 'ecoute_count', 'pagaie_count']), fn ($value) => $value !== null)];
        $layoutChanged = $data['sail_count'] !== $configuration->sail_count
            || collect(['bwa_count', 'cordes_count', 'ecoute_count', 'pagaie_count'])->contains(fn (string $field) => $data[$field] !== $current[$field]);

        if ($layoutChanged && ($plans = $configuration->crewPlans()->withTrashed()->count()) > 0) {
            throw ValidationException::withMessages([
                'bwa_count' => "Configuration utilisée par {$plans} plan(s) d’équipage : pour changer l’équipage, créez plutôt une nouvelle configuration.",
            ])->errorBag('configuration');
        }

        DB::transaction(function () use ($request, $boat, $configuration, $data, $layoutChanged) {
            // The default configuration stays default until another one takes over.
            $isDefault = $configuration->is_default || $request->boolean('is_default');

            if ($isDefault && ! $configuration->is_default) {
                $boat->configurations()->whereKeyNot($configuration->id)->update(['is_default' => false]);
            }

            $configuration->update([...$data, 'is_default' => $isDefault]);

            if ($layoutChanged) {
                $this->generator->generate($configuration);
            }
        });

        return redirect()->route('boats.show', [$boat, 'configuration' => $configuration])
            ->with('status', $layoutChanged ? 'Configuration enregistrée · postes régénérés' : 'Configuration enregistrée');
    }

    public function destroy(Boat $boat, BoatConfiguration $configuration): RedirectResponse
    {
        $plans = $configuration->crewPlans()->withTrashed()->count();

        $error = match (true) {
            $plans > 0 => "Configuration utilisée par {$plans} plan(s) d’équipage : elle ne peut pas être supprimée.",
            $boat->configurations()->count() <= 1 => 'Une yole doit garder au moins une configuration.',
            default => null,
        };

        if ($error) {
            return redirect()->route('boats.show', [$boat, 'configuration' => $configuration])
                ->withErrors(['delete' => $error], 'configuration');
        }

        DB::transaction(function () use ($boat, $configuration) {
            $configuration->delete();

            if ($configuration->is_default) {
                $boat->configurations()->orderBy('name')->first()?->update(['is_default' => true]);
            }
        });

        return redirect()->route('boats.show', $boat)->with('status', 'Configuration supprimée');
    }

    /**
     * Crew counts left empty take the usual values of the rig (BoatLayoutGenerator::defaults()).
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, string $bag): array
    {
        $data = $request->validateWithBag($bag, [
            'name' => ['required', 'string', 'max:255'],
            'sail_count' => ['required', 'integer', 'in:1,2'],
            'bwa_count' => ['nullable', 'integer', 'between:1,12'],
            'cordes_count' => ['nullable', 'integer', 'between:0,2'],
            'ecoute_count' => ['nullable', 'integer', 'between:1,4'],
            'pagaie_count' => ['nullable', 'integer', 'between:0,3'],
            'is_default' => ['boolean'],
        ]);

        $defaults = BoatLayoutGenerator::defaults((int) $data['sail_count']);

        return [
            ...$data,
            'sail_count' => (int) $data['sail_count'],
            ...collect($defaults)->map(fn (int $default, string $field) => isset($data[$field]) ? (int) $data[$field] : $default)->all(),
        ];
    }
}
