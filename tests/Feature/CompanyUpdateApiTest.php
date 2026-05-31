<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

/*
 * Les migrations applicatives sont specifiques a PostgreSQL et incompatibles avec le
 * sqlite :memory: des tests (RefreshDatabase est desactive dans Pest.php). On batit donc
 * le schema minimal a la main, comme tests/Feature/SupportTicketApiTest.php.
 *
 * Objectif : verrouiller le contrat d'autorisation de PUT /api/companies/{id}
 * (page /parametres/entreprise) :
 *  - super_admin / technicien : modifient n'importe quelle entreprise ;
 *  - admin_enterprise         : modifie UNIQUEMENT sa propre entreprise ;
 *  - manager / employe        : aucun acces (bloques par le role middleware) ;
 *  - un admin_enterprise ne peut PAS s'auto-attribuer un plan (champ subscription verrouille).
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
        $t->string('logo')->nullable();
        $t->string('email')->nullable();
        $t->string('phone')->nullable();
        $t->string('address')->nullable();
        $t->string('matricule_prefix')->nullable();
        $t->boolean('is_active')->default(true);
        $t->string('subscription')->default('freemium');
        $t->boolean('warranty_auto_renew')->default(false);
        $t->timestamp('warranty_ends_at')->nullable();
        $t->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('companies');
    Schema::dropIfExists('users');
});

// --- Helpers ----------------------------------------------------------------

function settingsCompany(string $name = 'ACME', string $subscription = 'freemium'): Company
{
    return Company::create([
        'name' => $name,
        'email' => strtolower($name).'@example.com',
        'phone' => '0102030405',
        'address' => 'Zone A',
        'subscription' => $subscription,
    ]);
}

function settingsUser(string $role, ?string $companyId = null, string $email = 'u@example.com'): User
{
    return User::create([
        'name' => 'Test User',
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => $email,
        'phone' => '0708091011',
        'password' => 'Secret123',
        'role' => $role,
        'company_id' => $companyId,
        'is_active' => true,
    ]);
}

// --- super_admin / technicien : acces total ---------------------------------

it('permet a un super_admin de modifier n_importe quelle entreprise', function () {
    $company = settingsCompany();
    Sanctum::actingAs(settingsUser('super_admin', null, 'super@example.com'));

    $this->putJson("/api/companies/{$company->id}", ['name' => 'ACME renommee'])
        ->assertOk()
        ->assertJsonPath('data.name', 'ACME renommee');

    expect($company->fresh()->name)->toBe('ACME renommee');
});

it('permet a un technicien de modifier n_importe quelle entreprise', function () {
    $company = settingsCompany();
    Sanctum::actingAs(settingsUser('technicien', null, 'tech@example.com'));

    $this->putJson("/api/companies/{$company->id}", ['phone' => '0900000000'])
        ->assertOk()
        ->assertJsonPath('data.phone', '0900000000');
});

// --- admin_enterprise : scope a sa propre entreprise ------------------------

it('permet a un admin_enterprise de modifier SA propre entreprise', function () {
    $company = settingsCompany();
    Sanctum::actingAs(settingsUser('admin_enterprise', $company->id, 'admin@example.com'));

    $this->putJson("/api/companies/{$company->id}", [
        'name' => 'Nouveau nom',
        'email' => 'contact@acme.test',
        'phone' => '0123456789',
        'address' => 'Nouvelle adresse',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Nouveau nom')
        ->assertJsonPath('data.address', 'Nouvelle adresse');

    expect($company->fresh()->name)->toBe('Nouveau nom');
});

it('refuse a un admin_enterprise de modifier une AUTRE entreprise', function () {
    $own = settingsCompany('ACME');
    $other = settingsCompany('Autre', 'freemium');
    Sanctum::actingAs(settingsUser('admin_enterprise', $own->id, 'admin@example.com'));

    $this->putJson("/api/companies/{$other->id}", ['name' => 'Tentative'])
        ->assertStatus(403);

    expect($other->fresh()->name)->toBe('Autre');
});

// --- Verrouillage du plan (anti auto-upgrade) -------------------------------

it('ignore le champ subscription envoye par un admin_enterprise', function () {
    $company = settingsCompany('ACME', 'freemium');
    Sanctum::actingAs(settingsUser('admin_enterprise', $company->id, 'admin@example.com'));

    $this->putJson("/api/companies/{$company->id}", [
        'name' => 'ACME',
        'subscription' => 'premium',
    ])->assertOk();

    expect($company->fresh()->subscription)->toBe('freemium');
});

it('autorise un super_admin a changer le plan subscription', function () {
    $company = settingsCompany('ACME', 'freemium');
    Sanctum::actingAs(settingsUser('super_admin', null, 'super@example.com'));

    $this->putJson("/api/companies/{$company->id}", ['subscription' => 'premium'])
        ->assertOk()
        ->assertJsonPath('data.subscription', 'premium');

    expect($company->fresh()->subscription)->toBe('premium');
});

// --- Roles sans acces -------------------------------------------------------

it('refuse l_acces a un manager', function () {
    $company = settingsCompany();
    Sanctum::actingAs(settingsUser('manager', $company->id, 'manager@example.com'));

    $this->putJson("/api/companies/{$company->id}", ['name' => 'X'])
        ->assertStatus(403);
});

it('refuse l_acces a un employe', function () {
    $company = settingsCompany();
    Sanctum::actingAs(settingsUser('employe', $company->id, 'employe@example.com'));

    $this->putJson("/api/companies/{$company->id}", ['name' => 'X'])
        ->assertStatus(403);
});
