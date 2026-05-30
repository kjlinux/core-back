<?php

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/*
 * Les migrations applicatives sont specifiques a PostgreSQL et incompatibles avec le
 * sqlite :memory: des tests (RefreshDatabase est desactive dans Pest.php). On batit donc
 * le schema minimal a la main, comme tests/Feature/ScheduleApiTest.php.
 *
 * Objectif : verrouiller le contrat de bout en bout de /parametres/profile pour tous les
 * roles — mise a jour du profil (avec synchronisation de l'Employee lie) et changement de
 * mot de passe (PUT /api/auth/profile et PUT /api/auth/password, ProfileController).
 *
 * Note : `users.id` est un bigint auto-incremente (pas d'UUID), alors que `employees.id`
 * est un UUID genere par le trait HasUuid sur l'evenement `creating`.
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

    Schema::create('employees', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('company_id')->nullable();
        $t->string('first_name')->nullable();
        $t->string('last_name')->nullable();
        $t->string('email')->nullable();
        $t->string('phone')->nullable();
        $t->string('avatar')->nullable();
        $t->boolean('is_active')->default(true);
        $t->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('employees');
    Schema::dropIfExists('users');
});

// --- Helpers ----------------------------------------------------------------

/**
 * Cree un utilisateur persiste (necessaire car le controleur fait $user->update())
 * puis l'authentifie via Sanctum.
 *
 * @param  array<string, mixed>  $attrs
 */
function profileActingUser(array $attrs = []): User
{
    $user = User::create(array_merge([
        'name' => 'Jean Dupont',
        'first_name' => 'Jean',
        'last_name' => 'Dupont',
        'email' => 'jean@example.com',
        'phone' => '0102030405',
        'password' => 'OldPass123',
        'role' => 'admin_enterprise',
        'is_active' => true,
    ], $attrs));

    Sanctum::actingAs($user);

    return $user;
}

// --- Mise a jour du profil --------------------------------------------------

it('met a jour prenom, nom et telephone et renvoie un UserResource camelCase', function () {
    $user = profileActingUser();

    $response = $this->putJson('/api/auth/profile', [
        'first_name' => 'Marc',
        'last_name' => 'Martin',
        'phone' => '0708091011',
    ]);

    $response->assertSuccessful();
    $response->assertJsonPath('data.firstName', 'Marc');
    $response->assertJsonPath('data.lastName', 'Martin');
    $response->assertJsonPath('data.phone', '0708091011');

    $user->refresh();
    expect($user->first_name)->toBe('Marc');
    expect($user->last_name)->toBe('Martin');
    expect($user->phone)->toBe('0708091011');
    expect($user->name)->toBe('Marc Martin');
});

it('synchronise l\'Employee lie lors de la mise a jour du profil', function () {
    $companyId = (string) Str::uuid();

    $employee = Employee::create([
        'company_id' => $companyId,
        'first_name' => 'Jean',
        'last_name' => 'Dupont',
        'email' => 'jean@example.com',
        'phone' => '0102030405',
        'is_active' => true,
    ]);

    // company_id identique a celui de l'employe pour passer le global scope BelongsToCompany.
    profileActingUser([
        'role' => 'employe',
        'company_id' => $companyId,
        'employee_id' => $employee->id,
    ]);

    $response = $this->putJson('/api/auth/profile', [
        'first_name' => 'Marc',
        'last_name' => 'Martin',
        'email' => 'marc@example.com',
        'phone' => '0708091011',
    ]);

    $response->assertSuccessful();

    $employee = Employee::withoutGlobalScopes()->find($employee->id);
    expect($employee->first_name)->toBe('Marc');
    expect($employee->last_name)->toBe('Martin');
    expect($employee->email)->toBe('marc@example.com');
    expect($employee->phone)->toBe('0708091011');
});

it('met a jour le profil sans erreur quand aucun Employee n\'est lie', function () {
    $user = profileActingUser([
        'role' => 'admin_enterprise',
        'employee_id' => null,
    ]);

    $response = $this->putJson('/api/auth/profile', [
        'first_name' => 'Marc',
        'last_name' => 'Martin',
    ]);

    $response->assertSuccessful();
    expect($user->fresh()->first_name)->toBe('Marc');
});

it('rejette un email deja utilise par un autre utilisateur', function () {
    User::create([
        'name' => 'Autre Personne',
        'first_name' => 'Autre',
        'last_name' => 'Personne',
        'email' => 'taken@example.com',
        'password' => 'OtherPass123',
        'role' => 'manager',
        'is_active' => true,
    ]);

    profileActingUser();

    $response = $this->putJson('/api/auth/profile', [
        'email' => 'taken@example.com',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('email');
});

it('ne touche pas au nom lors d\'une mise a jour du seul telephone', function () {
    $user = profileActingUser();

    $response = $this->putJson('/api/auth/profile', [
        'phone' => '0708091011',
    ]);

    $response->assertSuccessful();

    $user->refresh();
    expect($user->phone)->toBe('0708091011');
    expect($user->first_name)->toBe('Jean');
    expect($user->last_name)->toBe('Dupont');
    expect($user->name)->toBe('Jean Dupont');
});

it('refuse un prenom ou nom vide', function () {
    profileActingUser();

    $response = $this->putJson('/api/auth/profile', [
        'first_name' => '',
        'last_name' => 'Martin',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('first_name');
});

it('met a jour le profil pour tous les roles', function (string $role) {
    $user = profileActingUser([
        'role' => $role,
        'employee_id' => null,
    ]);

    $response = $this->putJson('/api/auth/profile', [
        'first_name' => 'Marc',
        'last_name' => 'Martin',
        'phone' => '0708091011',
    ]);

    $response->assertSuccessful();
    expect($user->fresh()->first_name)->toBe('Marc');
})->with([
    'super_admin',
    'admin_enterprise',
    'manager',
    'technicien',
    'employe',
    'support_it',
]);

// --- Changement de mot de passe ---------------------------------------------

it('change le mot de passe avec le mot de passe actuel correct', function () {
    $user = profileActingUser();

    $response = $this->putJson('/api/auth/password', [
        'current_password' => 'OldPass123',
        'new_password' => 'NewPass123',
        'new_password_confirmation' => 'NewPass123',
    ]);

    $response->assertSuccessful();
    expect(Hash::check('NewPass123', $user->fresh()->password))->toBeTrue();
});

it('rejette le changement de mot de passe si le mot de passe actuel est incorrect', function () {
    $user = profileActingUser();

    $response = $this->putJson('/api/auth/password', [
        'current_password' => 'WrongPass123',
        'new_password' => 'NewPass123',
        'new_password_confirmation' => 'NewPass123',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('message', 'Mot de passe actuel incorrect');
    expect(Hash::check('OldPass123', $user->fresh()->password))->toBeTrue();
});

it('rejette un nouveau mot de passe qui ne respecte pas les regles', function (array $payload) {
    profileActingUser();

    $response = $this->putJson('/api/auth/password', array_merge([
        'current_password' => 'OldPass123',
    ], $payload));

    $response->assertStatus(422);
})->with([
    'sans majuscule' => [['new_password' => 'newpass123', 'new_password_confirmation' => 'newpass123']],
    'sans minuscule' => [['new_password' => 'NEWPASS123', 'new_password_confirmation' => 'NEWPASS123']],
    'sans chiffre' => [['new_password' => 'NewPassword', 'new_password_confirmation' => 'NewPassword']],
    'trop court' => [['new_password' => 'New1', 'new_password_confirmation' => 'New1']],
    'non confirme' => [['new_password' => 'NewPass123', 'new_password_confirmation' => 'Different123']],
]);
