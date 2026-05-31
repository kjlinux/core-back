<?php

use App\Models\Order;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/*
 * Durcissement du callback de paiement des commandes marketplace :
 * - la signature HMAC est OBLIGATOIRE (fail-closed) : un callback non signe est refuse ;
 * - on ne confirme une commande que si la passerelle re-interrogee (avec le token STOCKE)
 *   confirme le paiement ; un callback de succes contredit est ignore ;
 * - le montant/devise annonces doivent correspondre a la commande.
 *
 * Migrations applicatives specifiques a PostgreSQL : on batit le schema minimal a
 * la main, comme tests/Feature/SubscriptionFeatureGatingTest.php.
 */

beforeEach(function () {
    config(['ligdicash.api_secret' => 'test-secret']);

    Schema::create('orders', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->string('order_number')->nullable();
        $t->uuid('company_id')->nullable();
        $t->integer('subtotal')->default(0);
        $t->integer('delivery_fee')->default(0);
        $t->integer('total')->default(0);
        $t->string('currency')->default('XOF');
        $t->string('status')->default('pending');
        $t->string('payment_method')->nullable();
        $t->string('payment_status')->default('pending');
        $t->json('delivery_address')->nullable();
        $t->string('invoice_url')->nullable();
        $t->string('payment_token')->nullable();
        $t->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('orders');
});

function pendingOrder(): Order
{
    return Order::create([
        'order_number' => 'ORD-1',
        'subtotal' => 1000,
        'total' => 1000,
        'status' => 'pending',
        'payment_status' => 'pending',
        'payment_token' => 'tok-xyz',
    ]);
}

/**
 * Poste un callback signe avec la cle HMAC de test, sur le corps brut exact
 * (comme le fait la passerelle reelle), pour passer la verification fail-closed.
 *
 * @param  array<string,mixed>  $payload
 */
function postSignedCallback(array $payload)
{
    $raw = json_encode($payload);
    $sig = hash_hmac('sha256', $raw, config('ligdicash.api_secret'));

    return test()->call('POST', '/api/payment/callback', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_LIGDICASH_SIGNATURE' => $sig,
    ], $raw);
}

it('confirme la commande quand la passerelle confirme le paiement', function () {
    Http::fake(['*' => Http::response(['response_code' => '00', 'status' => 'completed'], 200)]);
    $order = pendingOrder();

    postSignedCallback([
        'token' => 'tok-xyz',
        'status' => 'completed',
        'custom_data' => ['type' => 'order', 'order_id' => $order->id],
    ])->assertOk();

    $order->refresh();
    expect($order->payment_status)->toBe('paid');
    expect($order->status)->toBe('confirmed');
});

it('ignore un callback de succes contredit par la passerelle', function () {
    Http::fake(['*' => Http::response(['status' => 'NOCOMPLETED'], 200)]);
    $order = pendingOrder();

    postSignedCallback([
        'token' => 'tok-xyz',
        'status' => 'completed',
        'custom_data' => ['type' => 'order', 'order_id' => $order->id],
    ])->assertOk()->assertJsonPath('status', 'ignored');

    $order->refresh();
    expect($order->payment_status)->toBe('pending');
    expect($order->status)->toBe('pending');
});

it('confirme quand meme si la passerelle est injoignable (fail-open avec log)', function () {
    Http::fake(['*' => Http::response('', 500)]);
    $order = pendingOrder();

    postSignedCallback([
        'token' => 'tok-xyz',
        'status' => 'completed',
        'custom_data' => ['type' => 'order', 'order_id' => $order->id],
    ])->assertOk();

    $order->refresh();
    expect($order->payment_status)->toBe('paid');
});

it('refuse un callback non signe (fail-closed)', function () {
    Http::fake();
    $order = pendingOrder();

    $this->postJson('/api/payment/callback', [
        'token' => 'tok-xyz',
        'status' => 'completed',
        'custom_data' => ['type' => 'order', 'order_id' => $order->id],
    ])->assertStatus(400);

    $order->refresh();
    expect($order->payment_status)->toBe('pending');
    Http::assertNothingSent();
});

it('ignore un callback dont le montant ne correspond pas a la commande', function () {
    Http::fake();
    $order = pendingOrder(); // total = 1000

    postSignedCallback([
        'token' => 'tok-xyz',
        'status' => 'completed',
        'amount' => 999,
        'custom_data' => ['type' => 'order', 'order_id' => $order->id],
    ])->assertOk()->assertJsonPath('status', 'ignored');

    $order->refresh();
    expect($order->payment_status)->toBe('pending');
    Http::assertNothingSent();
});
