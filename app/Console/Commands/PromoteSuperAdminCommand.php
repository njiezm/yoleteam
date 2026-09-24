<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('yoleteam:super-admin {email : E-mail du compte existant à promouvoir}')]
#[Description('Promeut un compte existant en super admin (accès à toutes les associations)')]
class PromoteSuperAdminCommand extends Command
{
    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error("Aucun compte n’utilise l’adresse {$this->argument('email')}.");

            return self::FAILURE;
        }

        $user->update(['role' => UserRole::SuperAdmin]);

        $this->info("{$user->name} ({$user->email}) est maintenant super admin.");

        return self::SUCCESS;
    }
}
