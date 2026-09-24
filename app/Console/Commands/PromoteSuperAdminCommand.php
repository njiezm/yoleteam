<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Association;
use App\Models\User;
use Database\Seeders\CrewRoleSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * One command for the platform owner: creates the super admin account (and whatever the app needs to
 * run: crew roles, a first association) or promotes an existing account.
 */
#[Signature('yoleteam:super-admin
    {email : E-mail du compte super admin}
    {--nom= : Nom affiché (demandé si le compte est créé)}
    {--password= : Mot de passe (demandé, sans affichage, si le compte est créé)}
    {--association=Association des Yoles Rondes de la Baie des Mulets : Association créée s’il n’en existe aucune}
    {--ville=Le Vauclin : Commune de cette association}')]
#[Description('Crée (ou promeut) le compte super admin : une seule commande, en production comme en local')]
class PromoteSuperAdminCommand extends Command
{
    public function handle(): int
    {
        $this->callSilently('db:seed', ['--class' => CrewRoleSeeder::class, '--force' => true]);

        $email = Str::lower(trim($this->argument('email')));
        $user = User::query()->where('email', $email)->first();

        if ($user) {
            $user->forceFill(['role' => UserRole::SuperAdmin, 'disabled_at' => null])->save();
            $this->info("{$user->name} ({$user->email}) est maintenant super admin.");

            return self::SUCCESS;
        }

        $data = [
            'email' => $email,
            'nom' => $this->option('nom') ?? text('Nom affiché', required: true),
            'password' => $this->option('password') ?? password('Mot de passe (8 caractères minimum)', required: true),
        ];

        $validator = Validator::make($data, [
            'email' => ['required', 'email'],
            'nom' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($data) {
            $association = Association::query()->oldest('id')->first()
                ?? Association::create([
                    'name' => $this->option('association'),
                    'slug' => Str::slug($this->option('association')),
                    'city' => $this->option('ville'),
                    'settings' => ['timezone' => config('app.timezone'), 'locale' => 'fr'],
                ]);

            return User::create([
                'association_id' => $association->id,
                'name' => $data['nom'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => UserRole::SuperAdmin,
                'email_verified_at' => now(),
            ]);
        });

        $this->info("Compte super admin créé pour {$user->email} ({$user->association->name}).");
        $this->line('Connexion : '.route('login').' — panneau : '.route('super-admin.dashboard'));

        return self::SUCCESS;
    }
}
