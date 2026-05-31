<?php

use App\Events\CardScanned;
use Illuminate\Broadcasting\PrivateChannel;

/*
 * Verrouille le cloisonnement : l'UID scanne ne doit etre diffuse que sur le canal
 * prive de l'entreprise du capteur, jamais sur un canal public ecoute par tous.
 */
it('diffuse le scan sur le canal prive scope par entreprise', function () {
    $event = new CardScanned('1A2B3C4D', 'device-uuid', 'company-uuid');

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe('private-cards.company-uuid')
        ->and($event->broadcastAs())->toBe('card.scanned')
        ->and($event->broadcastWith())->toBe(['uid' => '1A2B3C4D', 'deviceId' => 'device-uuid']);
});

it('isole deux entreprises sur des canaux distincts', function () {
    $a = (new CardScanned('AA', 'd1', 'company-A'))->broadcastOn()[0];
    $b = (new CardScanned('BB', 'd2', 'company-B'))->broadcastOn()[0];

    expect($a->name)->toBe('private-cards.company-A')
        ->and($b->name)->toBe('private-cards.company-B')
        ->and($a->name)->not->toBe($b->name);
});
