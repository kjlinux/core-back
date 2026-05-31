<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Construit une URL d'avatar abstrait généré par DiceBear (style « shapes »).
 * Le seed rend l'avatar stable et unique par compte : un même seed renvoie
 * toujours la même image, ce qui aligne l'avatar d'un employé et de son User lié.
 *
 * @see https://www.dicebear.com/styles/shapes/
 */
class AvatarGenerator
{
    private const BASE_URL = 'https://api.dicebear.com/9.x/shapes/svg';

    /**
     * Génère l'URL d'avatar pour un seed donné. Un seed vide retombe sur une
     * chaîne aléatoire pour éviter que tous les comptes sans seed partagent
     * la même image.
     */
    public static function forSeed(?string $seed = null): string
    {
        $seed = $seed !== null && trim($seed) !== '' ? trim($seed) : Str::random(16);

        return self::BASE_URL.'?seed='.urlencode($seed);
    }
}
