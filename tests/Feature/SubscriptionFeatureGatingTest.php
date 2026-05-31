<?php

use App\Http\Middleware\RequireFeatureMiddleware;
use App\Models\Company;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

/*
 * Verrouillage des fonctionnalites par plan d'abonnement.
 *
 * Les migrations applicatives sont specifiques a PostgreSQL et incompatibles avec le
 * sqlite :memory: des tests (RefreshDatabase est desactive). On batit donc le schema
 * minimal a la main, comme tests/Feature/SupportTicketApiTest.php, puis on seed les
 * plans reels via SubscriptionPlanSeeder (source de verite de la grille tarifaire).
 *
 * On couvre :
 *  - Company::hasFeature() (lecture data-driven + degradation a l'expiration) ;
 *  - RequireFeatureMiddleware (bypass roles internes, 403 subscription_required) ;
 *  - le cablage reel d'une route gatee (POST /client/tickets -> feature:dedicated_support).
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
        $t->string('phone')->nullable();
        $t->string('subscription')->default(SubscriptionPlan::FREEMIUM);
        $t->timestamp('subscription_starts_at')->nullable();
        $t->timestamp('subscription_expires_at')->nullable();
        $t->boolean('subscription_next_period_paid')->default(false);
        $t->timestamp('subscription_next_expires_at')->nullable();
        $t->timestamp('warranty_starts_at')->nullable();
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

    Schema::create('support_tickets', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('company_id');
        $t->unsignedBigInteger('created_by_user_id');
        $t->string('subject');
        $t->text('message');
        $t->string('priority')->default('medium');
        $t->string('status')->default('open');
        $t->text('support_notes')->nullable();
        $t->unsignedBigInteger('resolved_by_user_id')->nullable();
        $t->timestamp('resolved_at')->nullable();
        $t->timestamps();
    });

    (new SubscriptionPlanSeeder)->run();
});

afterEach(function () {
    Schema::dropIfExists('support_tickets');
    Schema::dropIfExists('subscription_plans');
    Schema::dropIfExists('companies');
    Schema::dropIfExists('users');
});

// --- Helpers ----------------------------------------------------------------

function planCompany(string $plan = SubscriptionPlan::FREEMIUM, bool $active = true): Company
{
    $expires = match (true) {
        $plan === SubscriptionPlan::FREEMIUM => null,
        $active => now()->addMonth(),
        default => now()->subDay(),
    };

    return Company::create([
        'name' => 'ACME '.$plan.($active ? '' : '-expired'),
        'email' => $plan.($active ? '' : 'x').'@example.com',
        'subscription' => $plan,
        'subscription_starts_at' => $plan === SubscriptionPlan::FREEMIUM ? null : now()->subWeek(),
        'subscription_expires_at' => $expires,
    ]);
}

function planUser(string $role, ?string $companyId, string $email): User
{
    return User::create([
        'name' => 'Test',
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => $email,
        'phone' => '0102030405',
        'password' => 'Secret123',
        'role' => $role,
        'company_id' => $companyId,
        'is_active' => true,
    ]);
}

/**
 * Execute le middleware en isolation avec un $next qui renvoie 200.
 */
function runFeatureMiddleware(?User $user, string ...$features)
{
    $request = Request::create('/api/_probe', 'GET');
    $request->setUserResolver(fn () => $user);

    return (new RequireFeatureMiddleware)->handle(
        $request,
        fn () => response()->json(['passed' => true], 200),
        ...$features
    );
}

// --- Company::hasFeature (coeur data-driven) --------------------------------

it('freemium ne dispose que du dashboard et de l\'export brut', function () {
    $company = planCompany(SubscriptionPlan::FREEMIUM);

    expect($company->hasFeature(SubscriptionPlan::FEATURE_DASHBOARD))->toBeTrue();
    expect($company->hasFeature(SubscriptionPlan::FEATURE_EXPORT_RAW))->toBeTrue();
    expect($company->hasFeature(SubscriptionPlan::FEATURE_PAYROLL))->toBeFalse();
    expect($company->hasFeature(SubscriptionPlan::FEATURE_HR_REPORTS))->toBeFalse();
    expect($company->hasFeature(SubscriptionPlan::FEATURE_ADVANCED_ANALYTICS))->toBeFalse();
});

it('garantie actif debloque paie, rapports RH et support mais pas l\'analytics avance', function () {
    $company = planCompany(SubscriptionPlan::GARANTIE);

    expect($company->hasFeature(SubscriptionPlan::FEATURE_PAYROLL))->toBeTrue();
    expect($company->hasFeature(SubscriptionPlan::FEATURE_HR_REPORTS))->toBeTrue();
    expect($company->hasFeature(SubscriptionPlan::FEATURE_DEDICATED_SUPPORT))->toBeTrue();
    expect($company->hasFeature(SubscriptionPlan::FEATURE_FIRMWARE_UPDATES))->toBeTrue();
    expect($company->hasFeature(SubscriptionPlan::FEATURE_ADVANCED_ANALYTICS))->toBeFalse();
    expect($company->hasFeature(SubscriptionPlan::FEATURE_FIELD_VISITS))->toBeFalse();
});

it('premium actif debloque l\'analytics avance et les visites terrain', function () {
    $company = planCompany(SubscriptionPlan::PREMIUM);

    expect($company->hasFeature(SubscriptionPlan::FEATURE_ADVANCED_ANALYTICS))->toBeTrue();
    expect($company->hasFeature(SubscriptionPlan::FEATURE_FIELD_VISITS))->toBeTrue();
    expect($company->hasFeature(SubscriptionPlan::FEATURE_PAYROLL))->toBeTrue();
});

it('un plan paye expire retombe sur les fonctionnalites freemium', function () {
    $company = planCompany(SubscriptionPlan::GARANTIE, active: false);

    expect($company->hasFeature(SubscriptionPlan::FEATURE_DASHBOARD))->toBeTrue();
    expect($company->hasFeature(SubscriptionPlan::FEATURE_PAYROLL))->toBeFalse();
    expect($company->hasFeature(SubscriptionPlan::FEATURE_HR_REPORTS))->toBeFalse();
});

// --- RequireFeatureMiddleware ------------------------------------------------

it('renvoie 401 sans utilisateur', function () {
    expect(runFeatureMiddleware(null, 'payroll')->getStatusCode())->toBe(401);
});

it('laisse passer les roles internes (super_admin, support_it, technicien) sans abonnement', function () {
    foreach (['super_admin', 'support_it', 'technicien'] as $role) {
        $user = planUser($role, null, $role.'@example.com');
        expect(runFeatureMiddleware($user, 'advanced_analytics')->getStatusCode())->toBe(200);
    }
});

it('renvoie 403 si l\'utilisateur n\'a pas de compagnie', function () {
    $user = planUser('admin_enterprise', null, 'nocompany@example.com');
    $response = runFeatureMiddleware($user, 'payroll');

    expect($response->getStatusCode())->toBe(403);
});

it('laisse passer un admin_enterprise dont le plan inclut la feature', function () {
    $company = planCompany(SubscriptionPlan::GARANTIE);
    $user = planUser('admin_enterprise', $company->id, 'gar@example.com');

    expect(runFeatureMiddleware($user, 'payroll')->getStatusCode())->toBe(200);
});

it('bloque un freemium avec 403 subscription_required et les plans requis', function () {
    $company = planCompany(SubscriptionPlan::FREEMIUM);
    $user = planUser('admin_enterprise', $company->id, 'free@example.com');

    $response = runFeatureMiddleware($user, 'payroll');
    $payload = json_decode($response->getContent(), true);

    expect($response->getStatusCode())->toBe(403);
    expect($payload['error_code'])->toBe('subscription_required');
    expect($payload['current_plan'])->toBe(SubscriptionPlan::FREEMIUM);
    expect($payload['required_plans'])->toContain(SubscriptionPlan::GARANTIE);
    expect($payload['required_plans'])->toContain(SubscriptionPlan::PREMIUM);
});

it('bloque un garantie sur une feature premium-only (analytics avance)', function () {
    $company = planCompany(SubscriptionPlan::GARANTIE);
    $user = planUser('admin_enterprise', $company->id, 'gar2@example.com');

    $response = runFeatureMiddleware($user, 'advanced_analytics');
    $payload = json_decode($response->getContent(), true);

    expect($response->getStatusCode())->toBe(403);
    expect($payload['required_plans'])->toBe([SubscriptionPlan::PREMIUM]);
});

// --- Cablage reel d'une route gatee -----------------------------------------

it('un freemium ne peut pas ouvrir de ticket support (403 via la route)', function () {
    $company = planCompany(SubscriptionPlan::FREEMIUM);
    Sanctum::actingAs(planUser('admin_enterprise', $company->id, 'free-ticket@example.com'));

    $this->postJson('/api/client/tickets', [
        'subject' => 'Probleme capteur',
        'message' => 'Le capteur ne repond plus.',
    ])->assertStatus(403)->assertJsonPath('error_code', 'subscription_required');
});

it('un garantie actif peut ouvrir un ticket support (201 via la route)', function () {
    $company = planCompany(SubscriptionPlan::GARANTIE);
    Sanctum::actingAs(planUser('admin_enterprise', $company->id, 'gar-ticket@example.com'));

    $this->postJson('/api/client/tickets', [
        'subject' => 'Probleme capteur',
        'message' => 'Le capteur ne repond plus.',
    ])->assertStatus(201);
});
