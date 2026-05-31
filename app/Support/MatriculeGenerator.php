<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Employee;

/**
 * Génère le matricule d'un employé au format {PREFIX}{NNN}.
 *
 * Le préfixe provient du champ `matricule_prefix` de l'entreprise (ou est dérivé
 * de son nom à défaut). Le numéro est strictement supérieur au plus grand suffixe
 * numérique déjà attribué pour ce préfixe : on se base sur le maximum existant et
 * non sur un simple comptage, afin de rester correct malgré les suppressions
 * (trous dans la numérotation) ou les listes paginées côté client.
 */
class MatriculeGenerator
{
    /**
     * Construit le prochain matricule disponible pour une entreprise.
     */
    public static function forCompany(Company $company): string
    {
        $prefix = self::prefixFor($company);

        return $prefix.str_pad((string) self::nextSuffix($prefix), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Préfixe du matricule : champ dédié de l'entreprise, sinon dérivé du nom.
     */
    public static function prefixFor(Company $company): string
    {
        $prefix = trim((string) $company->matricule_prefix);

        if ($prefix !== '') {
            return strtoupper($prefix);
        }

        return self::derivePrefix((string) $company->name);
    }

    /**
     * Dérive un préfixe depuis le nom de l'entreprise (mêmes règles que le front) :
     * initiales si plusieurs mots, sinon les premières lettres, en majuscules.
     */
    private static function derivePrefix(string $name): string
    {
        $words = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $raw = count($words) > 1
            ? implode('', array_map(static fn (string $word): string => $word[0], $words))
            : ($words[0] ?? '');

        $clean = strtoupper(preg_replace('/[^a-zA-Z]/', '', $raw) ?? '');

        return $clean !== '' ? substr($clean, 0, 5) : 'EMP';
    }

    /**
     * Plus grand suffixe numérique déjà utilisé pour ce préfixe, incrémenté de 1.
     * La recherche est globale car le matricule est unique au niveau de la table.
     */
    private static function nextSuffix(string $prefix): int
    {
        $max = 0;

        Employee::query()
            ->where('employee_number', 'LIKE', $prefix.'%')
            ->pluck('employee_number')
            ->each(function (string $number) use ($prefix, &$max): void {
                $suffix = substr($number, strlen($prefix));

                if ($suffix !== '' && ctype_digit($suffix)) {
                    $max = max($max, (int) $suffix);
                }
            });

        return $max + 1;
    }
}
