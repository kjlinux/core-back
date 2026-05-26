<?php

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Supprime en cascade toutes les donnees liees aux compagnies marquees is_test=true.
 * Execution manuelle uniquement (PAS de Schedule), avec confirmation explicite.
 *
 * Usage : php artisan cleanup:test-data --force
 */
class CleanupTestDataCommand extends Command
{
    protected $signature = 'cleanup:test-data {--force : Skip confirmation} {--dry-run : Affiche ce qui serait supprime sans rien faire}';
    protected $description = 'Supprime les compagnies marquees is_test=true et toutes leurs donnees liees.';

    public function handle(): int
    {
        $companies = Company::where('is_test', true)->get();
        if ($companies->isEmpty()) {
            $this->info('Aucune compagnie de test a supprimer.');
            return self::SUCCESS;
        }

        $this->table(['ID', 'Nom', 'Email', 'Created'], $companies->map(fn ($c) => [
            $c->id, $c->name, $c->email, $c->created_at?->format('Y-m-d'),
        ])->all());

        if ($this->option('dry-run')) {
            $this->warn('Dry-run : aucune suppression effectuee.');
            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Confirmer la suppression definitive ?')) {
            $this->warn('Annule.');
            return self::SUCCESS;
        }

        $deleted = 0;
        DB::transaction(function () use ($companies, &$deleted) {
            foreach ($companies as $company) {
                $this->info("Suppression : {$company->name} ({$company->id})");
                // Les FK avec onDelete('cascade') s'occuperont des tables liees.
                $company->delete();
                $deleted++;
            }
        });

        $this->info(sprintf('Total : %d compagnie(s) de test supprimee(s).', $deleted));
        return self::SUCCESS;
    }
}
