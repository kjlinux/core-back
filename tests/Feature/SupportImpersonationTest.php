<?php

use App\Models\Company;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

/*
 * Les migrations applicatives sont specifiques a PostgreSQL et incompatibles avec le
 * sqlite :memory: des tests. On batit donc le schema minimal a la main (cf.
 * SupportTicketApiTest). Ici l'impersonation emet un vrai token Sanctum : la table
 * personal_access_tokens (avec expires_at) est donc indispensable.
 *
 * Objectif : verrouiller la prise de controle (SupportController::impersonate*) :
 *  - support_it / super_admin peuvent prendre le controle d'un compte d'entreprise ;
 *  - refus sur un super_admin et sur un compte sans entreprise ;
 *  - cloisonnement : un admin_enterprise ne peut pas appeler ces routes.
 */
beforeEach(function () {
    Schema::create('users', function (Blueprint $t) {
        $t->id();
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
        $t->string('phone')->nullable();
        $t->string('email')->nullable();
        $t->boolean('is_active')->default(true);
        $t->string('subscription')->default(SubscriptionPlan::FREEMIUM);
        $t->timestamp('subscription_starts_at')->nullable();
        $t->timestamp('subscription_expires_at')->nullable();
        $t->boolean('subscription_next_period_paid')->default(false);
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

    Schema::create('personal_access_tokens', function (Blueprint $t) {
        $t->id();
        $t->morphs('tokenable');
        $t->string('name');
        $t->string('token', 64)->unique();
        $t->text('abilities')->nullable();
        $t->timestamp('last_used_at')->nullable();
        $t->timestamp('expires_at')->nullable();
        $t->timestamps();
    });

    (new SubscriptionPlanSeeder)->run();
});

afterEach(function () {
    Schema::dropIfExists('personal_access_tokens');
    Schema::dropIfExists('subscription_plans');
    Schema::dropIfExists('companies');
    Schema::dropIfExists('users');
});

// --- Helpers ----------------------------------------------------------------

function impCompany(string $name = 'ACME'): Company
{
    return Company::create([
        'name' => $name,
        'phone' => '0102030405',
        'email' => strtolower($name).'@example.com',
        'is_active' => true,
    ]);
}

function impUser(string $role, ?string $companyId = null, string $email = 'u@example.com', bool $active = true): User
{
    return User::create([
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => $email,
        'phone' => '0708091011',
        'password' => 'Secret123',
        'role' => $role,
        'company_id' => $companyId,
        'is_active' => $active,
    ]);
}

// --- Prise de controle par entreprise ---------------------------------------

it('permet au support_it de prendre le controle de l\'admin d\'une entreprise', function () {
    $company = impCompany();
    $admin = impUser('admin_enterprise', $company->id, 'admin@example.com');

    Sanctum::actingAs(impUser('support_it', null, 'sit@example.com'));
    $response = $this->postJson("/api/support/companies/{$company->id}/impersonate");

    $response->assertSuccessful();
    $response->assertJsonPath('data.user.role', 'admin_enterprise');
    $response->assertJsonPath('data.user.id', (string) $admin->id);
    $response->assertJsonPath('data.user.companyId', $company->id);
    expect($response->json('data.accessToken'))->toBeString()->not->toBeEmpty();
    expect($response->json('data.impersonator.name'))->toBeString();
});

it('refuse la prise de controle si l\'entreprise n\'a aucun admin actif', function () {
    $company = impCompany();
    impUser('admin_enterprise', $company->id, 'inactif@example.com', active: false);
    impUser('manager', $company->id, 'manager@example.com');

    Sanctum::actingAs(impUser('support_it', null, 'sit@example.com'));
    $this->postJson("/api/support/companies/{$company->id}/impersonate")->assertStatus(422);
});

// --- Prise de controle par utilisateur --------------------------------------

it('permet de prendre le controle d\'un utilisateur precis', function () {
    $company = impCompany();
    $manager = impUser('manager', $company->id, 'manager@example.com');

    Sanctum::actingAs(impUser('super_admin', null, 'sa@example.com'));
    $response = $this->postJson("/api/support/users/{$manager->id}/impersonate");

    $response->assertSuccessful();
    $response->assertJsonPath('data.user.id', (string) $manager->id);
    $response->assertJsonPath('data.user.role', 'manager');
});

it('refuse de prendre le controle d\'un super_admin', function () {
    $target = impUser('super_admin', null, 'other-sa@example.com');

    Sanctum::actingAs(impUser('support_it', null, 'sit@example.com'));
    $this->postJson("/api/support/users/{$target->id}/impersonate")->assertStatus(403);
});

it('refuse de prendre le controle d\'un utilisateur sans entreprise', function () {
    $target = impUser('technicien', null, 'tech@example.com');

    Sanctum::actingAs(impUser('support_it', null, 'sit@example.com'));
    $this->postJson("/api/support/users/{$target->id}/impersonate")->assertStatus(422);
});

// --- Cloisonnement des roles ------------------------------------------------

it('interdit a un admin_enterprise d\'utiliser la prise de controle', function () {
    $company = impCompany();
    Sanctum::actingAs(impUser('admin_enterprise', $company->id, 'admin@example.com'));

    $this->postJson("/api/support/companies/{$company->id}/impersonate")->assertStatus(403);
});
