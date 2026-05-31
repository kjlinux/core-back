<?php

use App\Models\Company;
use App\Models\SubscriptionHistory;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

/*
 * Endpoints lecture seule de l'abonnement :
 *  - GET /subscriptions/quote : devis autoritatif (meme calcul que subscribe/upgrade), pour
 *    que le montant affiche corresponde exactement au montant debite ;
 *  - GET /subscriptions/events : historique complet des evenements (SubscriptionHistory),
 *    au-dela des seuls paiements.
 *
 * Migrations applicatives specifiques a PostgreSQL : schema minimal bati a la main, comme
 * tests/Feature/SubscriptionFeatureGatingTest.php.
 */

beforeEach(function () {
    Schema::create('users', function (Blueprint $t) {
        $t->id();
        $t->string('name')->nullable();
        $t->string('first_name')->nullable();
        $t->string('last_name')->nullable();
        $t->string('email')->unique();
        $t->string('phone')->nullable();
        $t->string('password');
        $t->string('role')->default('employe');
        $t->uuid('company_id')->nullable();
        $t->uuid('employee_id')->nullable();
        $t->string('avatar')->nullable();
        $t->boolean('is_active')->default(true);
        $t->timestamps();
    });

    Schema::create('companies', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->string('name')->nullable();
        $t->string('email')->nullable();
        $t->string('subscription')->default(SubscriptionPlan::FREEMIUM);
        $t->timestamp('subscription_starts_at')->nullable();
        $t->timestamp('subscription_expires_at')->nullable();
        $t->boolean('subscription_next_period_paid')->default(false);
        $t->timestamp('subscription_next_expires_at')->nullable();
        $t->timestamp('warranty_ends_at')->nullable();
        $t->boolean('warranty_auto_renew')->default(false);
        $t->boolean('is_active')->default(true);
        $t->timestamps();
    });

    Schema::create('subscription_plans', function (Blueprint $t) {
        $t->id();
        $t->string('code')->unique();
        $t->string('name');
        $t->integer('monthly_price_xof')->default(0);
        $t->json('features')->nullable();
        $t->boolean('requires_warranty')->default(false);
        $t->boolean('is_active')->default(true);
        $t->integer('sort_order')->default(0);
        $t->timestamps();
    });

    Schema::create('subscription_history', function (Blueprint $t) {
        $t->id();
        $t->uuid('company_id');
        $t->string('event');
        $t->string('from_plan')->nullable();
        $t->string('to_plan')->nullable();
        $t->unsignedBigInteger('actor_user_id')->nullable();
        $t->uuid('payment_id')->nullable();
        $t->text('notes')->nullable();
        $t->timestamp('created_at')->nullable();
    });

    (new SubscriptionPlanSeeder)->run();
});

afterEach(function () {
    Schema::dropIfExists('subscription_history');
    Schema::dropIfExists('subscription_plans');
    Schema::dropIfExists('companies');
    Schema::dropIfExists('users');
});

function quoteCompany(string $plan, bool $active = true): Company
{
    return Company::create([
        'name' => 'ACME',
        'email' => 'acme@example.com',
        'subscription' => $plan,
        'subscription_starts_at' => $plan === SubscriptionPlan::FREEMIUM ? null : now()->subDay(),
        'subscription_expires_at' => match (true) {
            $plan === SubscriptionPlan::FREEMIUM => null,
            $active => now()->addDays(30),
            default => now()->subDay(),
        },
    ]);
}

function actingAdminFor(Company $company): void
{
    Sanctum::actingAs(User::create([
        'first_name' => 'Admin',
        'last_name' => 'Test',
        'email' => 'admin@example.com',
        'password' => 'Secret123',
        'role' => 'admin_enterprise',
        'company_id' => $company->id,
    ]));
}

// --- quote -----------------------------------------------------------------

it('quote: upgrade garantie -> premium renvoie le prorata', function () {
    actingAdminFor(quoteCompany(SubscriptionPlan::GARANTIE));

    $this->getJson('/api/subscriptions/quote?plan_code=premium')
        ->assertOk()
        ->assertJsonPath('data.is_prorata', true)
        ->assertJsonPath('data.days_remaining', 30)
        ->assertJsonPath('data.amount_xof', 15000); // (30000 - 15000) * 30 / 30
});

it('quote: souscription neuve (freemium) renvoie le plein tarif', function () {
    actingAdminFor(quoteCompany(SubscriptionPlan::FREEMIUM));

    $this->getJson('/api/subscriptions/quote?plan_code=premium')
        ->assertOk()
        ->assertJsonPath('data.is_prorata', false)
        ->assertJsonPath('data.amount_xof', 30000);
});

it('quote: downgrade premium -> garantie est gratuit (applique en fin de cycle)', function () {
    actingAdminFor(quoteCompany(SubscriptionPlan::PREMIUM));

    $this->getJson('/api/subscriptions/quote?plan_code=garantie')
        ->assertOk()
        ->assertJsonPath('data.is_prorata', false)
        ->assertJsonPath('data.amount_xof', 0);
});

// --- events -----------------------------------------------------------------

it('events: renvoie l\'historique des changements de la compagnie, du plus recent au plus ancien', function () {
    $company = quoteCompany(SubscriptionPlan::GARANTIE);
    actingAdminFor($company);

    SubscriptionHistory::create([
        'company_id' => $company->id,
        'event' => SubscriptionHistory::EVENT_SUBSCRIBED,
        'from_plan' => 'freemium',
        'to_plan' => 'garantie',
        'created_at' => now()->subDays(2),
    ]);
    SubscriptionHistory::create([
        'company_id' => $company->id,
        'event' => SubscriptionHistory::EVENT_UPGRADED,
        'from_plan' => 'garantie',
        'to_plan' => 'premium',
        'created_at' => now(),
    ]);

    $this->getJson('/api/subscriptions/events')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.event', SubscriptionHistory::EVENT_UPGRADED)
        ->assertJsonPath('meta.total', 2);
});
