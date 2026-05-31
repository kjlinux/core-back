<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\User;
use App\Support\AvatarGenerator;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BackfillAvatarsCommand extends Command
{
    protected $signature = 'avatars:backfill
                            {--dry-run : Affiche le nombre de comptes concernés sans rien écrire}';

    protected $description = 'Affecte un avatar abstrait DiceBear aux comptes (users et employés) qui n\'en ont pas encore';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Mode dry-run : aucune écriture en base.');
        }

        $users = $this->backfill(User::query(), $dryRun);
        $this->info("Users mis à jour : {$users}");

        // withoutGlobalScopes() : on traite TOUS les employés, indépendamment du
        // scope d'entreprise (sans effet en console mais explicite ici).
        $employees = $this->backfill(Employee::query()->withoutGlobalScopes(), $dryRun);
        $this->info("Employés mis à jour : {$employees}");

        $total = $users + $employees;
        $this->info($dryRun
            ? "Total concerné : {$total} compte(s)."
            : "Terminé. {$total} compte(s) ont reçu un avatar.");

        return self::SUCCESS;
    }

    /**
     * Affecte un avatar à tous les enregistrements sans avatar pour la requête donnée.
     * Retourne le nombre de comptes traités.
     */
    private function backfill(Builder $query, bool $dryRun): int
    {
        $query->where(function (Builder $q) {
            $q->whereNull('avatar')->orWhere('avatar', '');
        });

        if ($dryRun) {
            return $query->count();
        }

        $count = 0;

        $query->chunkById(200, function ($models) use (&$count) {
            $models->each(function (Model $model) use (&$count) {
                $model->avatar = AvatarGenerator::forSeed($model->avatarSeed());
                $model->save();
                $count++;
            });
        });

        return $count;
    }
}
