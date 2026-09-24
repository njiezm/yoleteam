<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Enums\BwaPlacement;
use App\Enums\CrewPlanStatus;
use App\Enums\Gender;
use App\Enums\MemberCategory;
use App\Enums\MemberLevel;
use App\Enums\OutingStatus;
use App\Enums\OutingType;
use App\Enums\RaceType;
use App\Enums\UserRole;
use App\Models\Association;
use App\Models\Boat;
use App\Models\BoatConfiguration;
use App\Models\CrewPlan;
use App\Models\CrewRole;
use App\Models\Member;
use App\Models\Outing;
use App\Models\Race;
use App\Models\User;
use App\Services\BoatLayoutGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function __construct(private readonly BoatLayoutGenerator $layoutGenerator) {}

    public function run(): void
    {
        mt_srand(2026); // reproducible demo data

        $association = Association::create([
            'name' => 'Association des Yoles Rondes de la Baie des Mulets',
            'slug' => 'baie-des-mulets',
            'city' => 'Le Vauclin',
            'primary_color' => '#0B2545',
            'settings' => ['timezone' => 'America/Martinique', 'locale' => 'fr'],
        ]);

        $admin = User::create([
            'association_id' => $association->id,
            'name' => 'Bureau Baie des Mulets',
            'email' => 'admin@yoleteam.test',
            'password' => 'password',
            'role' => UserRole::Admin,
            'email_verified_at' => now(),
        ]);

        $patron = User::create([
            'association_id' => $association->id,
            'name' => 'Joël Bellance',
            'email' => 'patron@yoleteam.test',
            'phone' => '0696 12 34 56',
            'password' => 'password',
            'role' => UserRole::Patron,
            'email_verified_at' => now(),
        ]);

        $boats = $this->seedBoats($association);
        $members = $this->seedMembers($association);
        $outings = $this->seedOutings($association, $patron);

        $planOuting = $outings['plan'];
        $plan = $this->seedCrewPlan($planOuting, $boats->first(), $members, $patron);

        $this->seedAttendances($outings['past'], $members, $patron, $plan);
        $this->seedRace($association);
    }

    /** @return Collection<int, Boat> */
    private function seedBoats(Association $association): Collection
    {
        $boats = collect([
            ['name' => 'Prixe – Midea', 'sponsor' => 'Prixe · Midea', 'hull_color' => 'rouge', 'length_m' => 9.50, 'notes' => 'Yole de course de l’association (Tour des Yoles, championnat FYRM).'],
            ['name' => 'Yole école', 'sponsor' => null, 'hull_color' => 'bleu', 'length_m' => 9.20, 'notes' => 'Yole d’entraînement et d’initiation (données de démonstration).'],
        ])->map(fn (array $data) => $association->boats()->create($data));

        foreach ($boats as $boat) {
            foreach ([
                ['name' => '1 voile', 'sail_count' => 1, 'bwa_count' => 3, 'is_default' => false],
                ['name' => '2 voiles', 'sail_count' => 2, 'bwa_count' => 4, 'is_default' => true],
            ] as $config) {
                $this->layoutGenerator->generate($boat->configurations()->create($config));
            }
        }

        return $boats;
    }

    /** @return Collection<int, Member> */
    private function seedMembers(Association $association): Collection
    {
        $p = CrewRole::PATRON;
        $ap = CrewRole::AIDE_PATRON;
        $c1 = CrewRole::PREMIERE_CORDE;
        $c2 = CrewRole::DEUXIEME_CORDE;
        $ec = CrewRole::ECOUTE;
        $dr = CrewRole::DRESSEUR;
        $eco = CrewRole::ECOPEUR;

        // [prénom, nom, surnom, genre, poids, taille, niveau, année naissance, [rôle => préféré]]
        $rows = [
            ['Joël', 'Bellance', 'Ti Jo', 'm', 78.5, 176, 'expert', 1972, [$p => true, $ap => false]],
            ['Rodrigue', 'Sainte-Rose', null, 'm', 82.0, 180, 'expert', 1979, [$p => true, $ap => true]],
            ['Fabrice', 'Moutoussamy', 'Fafa', 'm', 74.0, 174, 'confirme', 1985, [$ap => true, $ec => false]],
            ['Frantz', 'Charlery', null, 'm', 80.5, 178, 'confirme', 1981, [$ap => true, $dr => false]],
            ['Didier', 'Marie-Sainte', 'Dédé', 'm', 71.0, 172, 'expert', 1968, [$ap => true, $p => false]],
            ['Ludovic', 'Pinel', 'Ludo', 'm', 62.5, 170, 'confirme', 1998, [$c1 => true, $c2 => false]],
            ['Teddy', 'Ursulet', null, 'm', 60.0, 168, 'intermediaire', 2004, [$c1 => true, $c2 => true]],
            ['Kévin', 'Louisy', null, 'm', 64.0, 175, 'intermediaire', 2002, [$c2 => true, $eco => false]],
            ['Loïc', 'Zobda', null, 'm', 66.5, 177, 'debutant', 2007, [$c2 => true]],
            ['Harry', 'Jean-Baptiste', 'Ari', 'm', 76.0, 181, 'confirme', 1988, [$ec => true, $ap => false]],
            ['Emmanuel', 'Désiré', 'Manu', 'm', 73.5, 179, 'intermediaire', 1994, [$ec => true, $dr => false]],
            ['Sandrine', 'Élisabeth', 'Sandy', 'f', 58.0, 165, 'confirme', 1990, [$eco => true, $c2 => false]],
            ['Murielle', 'Rosine', null, 'f', 55.5, 162, 'intermediaire', 1996, [$eco => true]],
            ['Stéphane', 'Larcher', 'Tiloup', 'm', 92.0, 188, 'expert', 1983, [$dr => true]],
            ['Mickaël', 'Nicolas', 'Mika', 'm', 88.5, 185, 'confirme', 1991, [$dr => true]],
            ['Yannick', 'Cyrille', null, 'm', 94.5, 190, 'expert', 1977, [$dr => true, $ap => false]],
            ['Gérard', 'Bélaise', 'Gégé', 'm', 90.0, 183, 'confirme', 1966, [$dr => true]],
            ['Jean-Marc', 'Grandjean', null, 'm', 86.0, 182, 'confirme', 1987, [$dr => true]],
            ['Wilfried', 'Hoton', 'Wil', 'm', 91.5, 187, 'intermediaire', 1993, [$dr => true]],
            ['Dorian', 'Lise', null, 'm', 84.0, 184, 'intermediaire', 2000, [$dr => true, $ec => false]],
            ['Marius', 'Fanfant', null, 'm', 87.0, 180, 'debutant', 2005, [$dr => true]],
            ['Alain', 'Petit-Frère', 'Ti Alain', 'm', 89.0, 179, 'expert', 1964, [$dr => true]],
            ['Nathalie', 'Jos', 'Nana', 'f', 68.0, 170, 'intermediaire', 1989, [$dr => true, $eco => false]],
            ['Lucien', 'Bellemare', 'Lulu', 'm', 85.5, 186, 'debutant', 2008, [$dr => true]],
        ];

        $roleIds = CrewRole::idsByCode();

        return collect($rows)->map(function (array $row) use ($association, $roleIds) {
            [$first, $last, $nick, $gender, $weight, $height, $level, $year, $roles] = $row;
            $birth = Carbon::create($year, mt_rand(1, 12), mt_rand(1, 28));
            $age = $birth->age;

            $member = $association->members()->create([
                'first_name' => $first,
                'last_name' => $last,
                'nickname' => $nick,
                'phone' => sprintf('0696 %02d %02d %02d', mt_rand(10, 99), mt_rand(10, 99), mt_rand(10, 99)),
                'email' => Str::slug("$first.$last", '.').'@example.mq',
                'birth_date' => $birth,
                'gender' => Gender::from($gender),
                'weight_kg' => $weight,
                'height_cm' => $height,
                'level' => MemberLevel::from($level),
                'category' => match (true) {
                    $age < 21 => MemberCategory::Jeune,
                    $age >= 45 => MemberCategory::Veteran,
                    default => MemberCategory::Senior,
                },
                'is_active' => true,
            ]);

            $member->crewRoles()->attach(
                collect($roles)->mapWithKeys(fn (bool $preferred, string $code) => [
                    $roleIds[$code] => ['is_preferred' => $preferred],
                ])->all()
            );

            return $member;
        });
    }

    /** @return array{past: Collection<int, Outing>, plan: Outing} */
    private function seedOutings(Association $association, User $creator): array
    {
        $specs = [
            [-12, OutingType::Entrainement, 'Entraînement du samedi', '06:00', '09:00', 'Baie des Mulets'],
            [-9, OutingType::Entrainement, 'Entraînement virements', '17:00', '19:00', 'Baie des Mulets'],
            [-5, OutingType::SortieLibre, 'Sortie jusqu’à la Pointe Faula', '07:00', '12:00', 'Pointe Faula'],
            [-2, OutingType::Entrainement, 'Entraînement vitesse au largue', '06:00', '09:00', 'Baie des Mulets'],
            [3, OutingType::Entrainement, 'Entraînement du samedi', '06:00', '09:00', 'Baie des Mulets'],
            [10, OutingType::Regate, 'Régate du Vauclin', '08:00', '13:00', 'Baie du Vauclin'],
        ];

        $outings = collect($specs)->map(function (array $s) use ($association, $creator) {
            [$offset, $type, $title, $start, $end, $location] = $s;
            $date = today()->addDays($offset);

            return $association->outings()->create([
                'type' => $type,
                'title' => $title,
                'date' => $date,
                'start_time' => $start,
                'end_time' => $end,
                'location' => $location,
                'status' => $offset < 0 ? OutingStatus::Terminee : OutingStatus::Planifiee,
                'created_by' => $creator->id,
            ]);
        });

        $past = $outings->filter(fn (Outing $o) => $o->date->isPast())->values();

        return ['past' => $past, 'plan' => $past->last()];
    }

    /**
     * Fully assigned & validated crew plan on the default "2 voiles" configuration.
     */
    private function seedCrewPlan(Outing $outing, Boat $boat, Collection $members, User $patron): CrewPlan
    {
        /** @var BoatConfiguration $configuration */
        $configuration = $boat->configurations()->where('is_default', true)->firstOrFail();

        $plan = $outing->crewPlans()->create([
            'boat_id' => $boat->id,
            'boat_configuration_id' => $configuration->id,
            'wind_direction' => 90,
            'wind_strength' => 16,
            'status' => CrewPlanStatus::Brouillon,
            'notes' => 'Alizé d\'est soutenu, 4 bwa dressés.',
            'created_by' => $patron->id,
        ]);

        $members->each->load('crewRoles');
        $used = collect();
        $placements = BwaPlacement::cases();

        foreach ($configuration->positions()->with('crewRole')->get() as $position) {
            $code = $position->crewRole->code;
            $available = $members->reject(fn (Member $m) => $used->contains($m->id));

            $member = $available->first(fn (Member $m) => $m->crewRoles->contains(fn ($r) => $r->code === $code && $r->pivot->is_preferred))
                ?? $available->first(fn (Member $m) => $m->crewRoles->contains('code', $code))
                ?? $available->first();

            $used->push($member->id);

            $plan->assignments()->create([
                'boat_position_id' => $position->id,
                'member_id' => $member->id,
                'bwa_placement' => $position->bwa_index !== null
                    ? $placements[($position->bwa_index - 1) % count($placements)]
                    : null,
            ]);
        }

        $plan->validate($patron);

        return $plan;
    }

    private function seedAttendances(Collection $pastOutings, Collection $members, User $recorder, CrewPlan $plan): void
    {
        $crewIds = $plan->assignments()->pluck('member_id');

        foreach ($pastOutings as $outing) {
            $start = Carbon::parse($outing->date->toDateString().' '.$outing->start_time);

            foreach ($members as $member) {
                $status = ($outing->id === $plan->outing_id && $crewIds->contains($member->id))
                    ? AttendanceStatus::Present
                    : $this->randomAttendanceStatus();

                $outing->attendances()->create([
                    'member_id' => $member->id,
                    'status' => $status,
                    'arrived_at' => match ($status) {
                        AttendanceStatus::Present => $start->copy()->subMinutes(mt_rand(0, 20)),
                        AttendanceStatus::Retard => $start->copy()->addMinutes(mt_rand(10, 40)),
                        default => null,
                    },
                    'comment' => match ($status) {
                        AttendanceStatus::Excuse => collect(['Travail', 'Raison familiale', 'Blessure à l\'épaule', 'En déplacement'])->random(),
                        AttendanceStatus::Retard => 'Embouteillages',
                        default => null,
                    },
                    'recorded_by' => $recorder->id,
                ]);
            }
        }
    }

    private function randomAttendanceStatus(): AttendanceStatus
    {
        $roll = mt_rand(1, 100);

        return match (true) {
            $roll <= 72 => AttendanceStatus::Present,
            $roll <= 82 => AttendanceStatus::Retard,
            $roll <= 92 => AttendanceStatus::Excuse,
            default => AttendanceStatus::Absent,
        };
    }

    /**
     * Tour des Yoles Rondes 2026 (40e édition, 26 juillet – 2 août) : parcours officiel annoncé par la fédération.
     * Les résultats ne sont pas inventés : ils sont à saisir dans l’application.
     */
    private function seedRace(Association $association): void
    {
        $race = Race::create([
            'association_id' => $association->id,
            'name' => 'Tour des Yoles Rondes 2026',
            'type' => RaceType::TourDesYoles,
            'season' => 2026,
            'start_date' => '2026-07-26',
            'end_date' => '2026-08-02',
            'location' => 'Tour de la Martinique (départ et arrivée à Sainte-Anne)',
            'notes' => '40e édition. Étapes baptisées du nom des patrons du premier Tour (1985).',
        ]);

        $stages = [
            [1, 'Étape Gabriel Mélidor', '2026-07-26', 'Sainte-Anne', 'Le Vauclin'],
            [2, 'Étape Frantz Férule', '2026-07-27', 'Le Vauclin', 'Le Robert'],
            [3, 'Étape Eugène Math', '2026-07-28', 'Le Robert', 'La Trinité'],
            [4, 'Étape François Lagin', '2026-07-29', 'La Trinité', 'Saint-Pierre'],
            [5, 'Étape Charles Exilie', '2026-07-30', 'Saint-Pierre', 'Fort-de-France'],
            [6, 'Étape Romain Lassource', '2026-07-31', 'Fort-de-France', 'Les Anses-d’Arlet'],
            [7, 'Étape Raoul Pancrate', '2026-08-01', 'Les Anses-d’Arlet', 'Rivière-Pilote'],
            [8, 'Étape Désiré Lamon', '2026-08-02', 'Rivière-Pilote', 'Sainte-Anne'],
        ];

        foreach ($stages as [$number, $name, $date, $from, $to]) {
            $race->stages()->create([
                'number' => $number,
                'name' => $name,
                'date' => $date,
                'start_location' => $from,
                'end_location' => $to,
            ]);
        }
    }
}
