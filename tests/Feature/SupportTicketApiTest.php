<?php

use App\Models\Company;
use App\Models\SubscriptionPlan;
use App\Models\SupportTicket;
use App\Models\User;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

/*
 * Les migrations applicatives sont specifiques a PostgreSQL et incompatibles avec le
 * sqlite :memory: des tests (RefreshDatabase est desactive dans Pest.php). On batit donc
 * le schema minimal a la main, comme tests/Feature/ProfileApiTest.php.
 *
 * Objectif : verrouiller le contrat des plaintes (SupportTicketController) :
 *  - cote client (admin_enterprise / manager) : creation + liste de SES plaintes ;
 *  - cote support (support_it / super_admin)   : liste globale + traitement ;
 *  - cloisonnement des roles.
 *
 * Point de regression couvert : created_by_user_id / resolved_by_user_id sont des bigint
 * (users.id est un bigint auto-incremente), PAS des uuid. Une insertion d'un id entier dans
 * une colonne uuid plantait sous PostgreSQL.
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
        $t->string('phone')->nullable();
        $t->string('email')->nullable();
        $t->string('subscription')->default(SubscriptionPlan::FREEMIUM);
        $t->timestamp('subscription_starts_at')->nullable();
        $t->timestamp('subscription_expires_at')->nullable();
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

function ticketCompany(string $name = 'ACME'): Company
{
    // Le support dedie (ouverture de ticket) requiert un plan Garantie/Premium actif.
    return Company::create([
        'name' => $name,
        'phone' => '0102030405',
        'email' => strtolower($name).'@example.com',
        'subscription' => SubscriptionPlan::GARANTIE,
        'subscription_starts_at' => now()->subWeek(),
        'subscription_expires_at' => now()->addMonth(),
    ]);
}

function ticketUser(string $role, ?string $companyId = null, string $email = 'u@example.com'): User
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

// --- Cote client ------------------------------------------------------------

it('permet a un admin_enterprise de creer une plainte (regression uuid/bigint)', function () {
    $company = ticketCompany();
    $user = ticketUser('admin_enterprise', $company->id);
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/client/tickets', [
        'subject' => 'Capteur RFID en panne',
        'message' => 'Le capteur du site A ne repond plus depuis ce matin.',
        'priority' => 'high',
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('data.subject', 'Capteur RFID en panne');
    $response->assertJsonPath('data.status', 'open');
    $response->assertJsonPath('data.priority', 'high');

    $ticket = SupportTicket::first();
    expect($ticket)->not->toBeNull();
    expect($ticket->company_id)->toBe($company->id);
    expect((int) $ticket->created_by_user_id)->toBe($user->id);
});

it('applique la priorite medium par defaut', function () {
    $company = ticketCompany();
    Sanctum::actingAs(ticketUser('manager', $company->id));

    $this->postJson('/api/client/tickets', [
        'subject' => 'Question',
        'message' => 'Un souci mineur.',
    ])->assertStatus(201)->assertJsonPath('data.priority', 'medium');
});

it('refuse une plainte sans sujet ou message', function () {
    $company = ticketCompany();
    Sanctum::actingAs(ticketUser('admin_enterprise', $company->id));

    $this->postJson('/api/client/tickets', ['subject' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['subject', 'message']);
});

it('renvoie uniquement les plaintes de la compagnie de l\'utilisateur', function () {
    $companyA = ticketCompany('AAA');
    $companyB = ticketCompany('BBB');
    $userA = ticketUser('admin_enterprise', $companyA->id, 'a@example.com');
    $userB = ticketUser('admin_enterprise', $companyB->id, 'b@example.com');

    SupportTicket::create(['company_id' => $companyA->id, 'created_by_user_id' => $userA->id, 'subject' => 'A1', 'message' => 'x', 'priority' => 'low', 'status' => 'open']);
    SupportTicket::create(['company_id' => $companyB->id, 'created_by_user_id' => $userB->id, 'subject' => 'B1', 'message' => 'y', 'priority' => 'low', 'status' => 'open']);

    Sanctum::actingAs($userA);
    $response = $this->getJson('/api/client/tickets');
    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(1);
    $response->assertJsonPath('data.0.subject', 'A1');
});

// --- Cote support -----------------------------------------------------------

it('permet au support_it de voir toutes les plaintes avec compagnie et emetteur (camelCase)', function () {
    $company = ticketCompany('ACME');
    $client = ticketUser('admin_enterprise', $company->id, 'client@example.com');
    SupportTicket::create(['company_id' => $company->id, 'created_by_user_id' => $client->id, 'subject' => 'Probleme', 'message' => 'détails', 'priority' => 'high', 'status' => 'open']);

    Sanctum::actingAs(ticketUser('support_it', null, 'sit@example.com'));
    $response = $this->getJson('/api/support/tickets');

    $response->assertSuccessful();
    $response->assertJsonPath('data.0.subject', 'Probleme');
    $response->assertJsonPath('data.0.company.name', 'ACME');
    $response->assertJsonPath('data.0.createdBy.email', 'client@example.com');
    $response->assertJsonPath('data.0.supportNotes', null);
});

it('filtre les plaintes par statut cote support', function () {
    $company = ticketCompany();
    $client = ticketUser('admin_enterprise', $company->id, 'client@example.com');
    SupportTicket::create(['company_id' => $company->id, 'created_by_user_id' => $client->id, 'subject' => 'Ouverte', 'message' => 'x', 'priority' => 'low', 'status' => 'open']);
    SupportTicket::create(['company_id' => $company->id, 'created_by_user_id' => $client->id, 'subject' => 'Resolue', 'message' => 'y', 'priority' => 'low', 'status' => 'resolved']);

    Sanctum::actingAs(ticketUser('super_admin', null, 'sa@example.com'));
    $response = $this->getJson('/api/support/tickets?status=resolved');

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(1);
    $response->assertJsonPath('data.0.subject', 'Resolue');
});

it('permet au support de resoudre une plainte (statut + resolved_at + resolved_by)', function () {
    $company = ticketCompany();
    $client = ticketUser('admin_enterprise', $company->id, 'client@example.com');
    $ticket = SupportTicket::create(['company_id' => $company->id, 'created_by_user_id' => $client->id, 'subject' => 'A traiter', 'message' => 'x', 'priority' => 'medium', 'status' => 'open']);

    $support = ticketUser('support_it', null, 'sit@example.com');
    Sanctum::actingAs($support);

    $response = $this->patchJson("/api/support/tickets/{$ticket->id}", [
        'status' => 'resolved',
        'support_notes' => 'Capteur redemarre a distance.',
    ]);

    $response->assertSuccessful();
    $ticket->refresh();
    expect($ticket->status)->toBe('resolved');
    expect($ticket->resolved_at)->not->toBeNull();
    expect((int) $ticket->resolved_by_user_id)->toBe($support->id);
    expect($ticket->support_notes)->toBe('Capteur redemarre a distance.');
});

// --- Cloisonnement des roles -----------------------------------------------

it('interdit a un admin_enterprise l\'acces aux plaintes support', function () {
    $company = ticketCompany();
    Sanctum::actingAs(ticketUser('admin_enterprise', $company->id));

    $this->getJson('/api/support/tickets')->assertStatus(403);
});

it('interdit a un support_it de creer une plainte cliente', function () {
    Sanctum::actingAs(ticketUser('support_it', null, 'sit@example.com'));

    $this->postJson('/api/client/tickets', ['subject' => 'x', 'message' => 'y'])->assertStatus(403);
});
