<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

/*
 * Les endpoints d'abonnement plantaient en 500 pour un utilisateur sans entreprise
 * (super_admin / support_it ont company_id = null) car ils dereferencaient
 * $user->company. Ils doivent desormais renvoyer 422 proprement, comme me()/quote().
 *
 * Migrations applicatives specifiques a PostgreSQL : on monte le schema minimal a la
 * main (cf. SupportImpersonationTest). Seule la table users est necessaire ici : le
 * garde-fou court-circuite avant toute requete sur les paiements/historique.
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
});

afterEach(function () {
    Schema::dropIfExists('users');
});

function noCompanyUser(string $role = 'super_admin'): User
{
    return User::create([
        'first_name' => 'No',
        'last_name' => 'Company',
        'email' => 'nc@example.com',
        'password' => 'Secret123',
        'role' => $role,
        'company_id' => null,
        'is_active' => true,
    ]);
}

it('subscribe renvoie 422 (et non 500) pour un utilisateur sans entreprise', function () {
    Sanctum::actingAs(noCompanyUser());

    $this->postJson('/api/subscriptions/subscribe', ['plan_code' => 'premium'])
        ->assertStatus(422);
});

it('history renvoie 422 pour un utilisateur sans entreprise', function () {
    Sanctum::actingAs(noCompanyUser());

    $this->getJson('/api/subscriptions/history')->assertStatus(422);
});

it('events renvoie 422 pour un utilisateur sans entreprise', function () {
    Sanctum::actingAs(noCompanyUser());

    $this->getJson('/api/subscriptions/events')->assertStatus(422);
});

it('pay-next-period renvoie 422 pour un utilisateur sans entreprise', function () {
    Sanctum::actingAs(noCompanyUser());

    $this->postJson('/api/subscriptions/pay-next-period')->assertStatus(422);
});
