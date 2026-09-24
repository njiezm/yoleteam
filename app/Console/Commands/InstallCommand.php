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
 * Production setup without demo data: crew roles, the association and its first admin account.
 */
#[Signature('yoleteam:installer
    {--association= : Nom de l’association}
    {--ville= : Commune}
    {--nom= : Nom du premier administrateur}
    {--email= : E-mail du premier administrateur}
    {--password= : Mot de passe (sinon demandé)}')]
#[Description('Prépare une installation réelle : postes d’équipage, association et premier compte bureau')]
class InstallCommand extends Command
{
    public function handle(): int
    {
        $this->call('db:seed', ['--class' => CrewRoleSeeder::class, '--force' => true]);

        $data = [
            'association' => $this->option('association') ?? text('Nom de l’association', default: 'Association des Yoles Rondes de la Baie des Mulets', required: true),
            'ville' => $this->option('ville') ?? text('Commune', default: 'Le Vauclin'),
            'nom' => $this->option('nom') ?? text('Nom du premier administrateur (bureau)', required: true),
            'email' => $this->option('email') ?? text('Son adresse e-mail', required: true),
            'password' => $this->option('password') ?? password('Son mot de passe (8 caractères minimum)', required: true),
        ];

        $validator = Validator::make($data, [
            'association' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($data) {
            $association = Association::firstOrCreate(
                ['slug' => Str::slug($data['association'])],
                ['name' => $data['association'], 'city' => $data['ville'], 'settings' => ['timezone' => config('app.timezone'), 'locale' => 'fr']],
            );

            return User::create([
                'association_id' => $association->id,
                'name' => $data['nom'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => UserRole::Admin,
                'email_verified_at' => now(),
            ]);
        });

        $this->info("Compte bureau créé pour {$user->email} ({$user->association->name}).");
        $this->line('Connectez-vous, puis ajoutez les yoles, les membres et les patrons depuis l’application.');

        return self::SUCCESS;
    }
}
