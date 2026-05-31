<?php

namespace App\Traits;

use App\Support\AvatarGenerator;

/**
 * Affecte automatiquement un avatar abstrait DiceBear à la création du modèle
 * lorsqu'aucun avatar n'est fourni. Couvre tous les points de création (contrôleurs,
 * seeders, commandes) sans duplication. N'écrase jamais un avatar déjà renseigné.
 */
trait HasDefaultAvatar
{
    protected static function bootHasDefaultAvatar(): void
    {
        static::creating(function ($model) {
            if (empty($model->avatar)) {
                $model->avatar = AvatarGenerator::forSeed($model->avatarSeed());
            }
        });
    }

    /**
     * Seed utilisé pour générer l'avatar. L'email garantit l'unicité et aligne
     * l'avatar d'un employé sur celui de son User lié (même email = même image).
     * Retombe sur le nom complet, puis laisse AvatarGenerator gérer le cas vide.
     */
    public function avatarSeed(): ?string
    {
        if (! empty($this->email)) {
            return $this->email;
        }

        $fullName = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));

        return $fullName !== '' ? $fullName : null;
    }
}
