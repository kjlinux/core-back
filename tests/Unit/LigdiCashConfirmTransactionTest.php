<?php

use App\Services\LigdiCashService;
use Illuminate\Support\Facades\Http;

/*
 * confirmTransaction() est la re-verification serveur-a-serveur appelee par le
 * callback de paiement : on ne fait pas confiance au statut du corps du callback
 * (potentiellement forge), on re-interroge la passerelle avec le token stocke.
 * Test pur (Http::fake), sans base de donnees.
 */

uses(Tests\TestCase::class);

it('renvoie completed quand la passerelle confirme le paiement', function () {
    Http::fake(['*' => Http::response(['response_code' => '00', 'status' => 'completed'], 200)]);

    expect((new LigdiCashService)->confirmTransaction('tok-123'))->toBe('completed');
});

it('renvoie failed quand la passerelle signale un echec', function () {
    Http::fake(['*' => Http::response(['status' => 'NOCOMPLETED'], 200)]);

    expect((new LigdiCashService)->confirmTransaction('tok-123'))->toBe('failed');
});

it('renvoie pending quand la passerelle signale un statut intermediaire', function () {
    Http::fake(['*' => Http::response(['status' => 'IN_PROGRESS'], 200)]);

    expect((new LigdiCashService)->confirmTransaction('tok-123'))->toBe('pending');
});

it('renvoie unknown quand la passerelle est injoignable (fail-open)', function () {
    Http::fake(['*' => Http::response('', 500)]);

    expect((new LigdiCashService)->confirmTransaction('tok-123'))->toBe('unknown');
});

it('renvoie unknown sans token, sans appel reseau', function () {
    Http::fake();

    expect((new LigdiCashService)->confirmTransaction(null))->toBe('unknown');

    Http::assertNothingSent();
});
