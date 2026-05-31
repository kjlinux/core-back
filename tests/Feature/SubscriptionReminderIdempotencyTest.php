<?php

use App\Mail\SubscriptionExpiringReminderMail;
use App\Models\Company;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionReminderSent;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/*
 * Idempotence des rappels d'expiration : la table subscription_reminders_sent
 * (contrainte unique company_id + days_left + sent_on) garantit qu'une double
 * execution accidentelle de la commande le meme jour ne renvoie pas de mail.
 *
 * Les migrations applicatives sont specifiques a PostgreSQL et incompatibles avec le
 * sqlite :memory: des tests : on batit le schema minimal a la main, comme
 * tests/Feature/SubscriptionFeatureGatingTest.php.
 */

beforeEach(function () {
    Schema::create('companies', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->string('name')->nullable();
        $t->string('email')->nullable();
        $t->string('subscription')->default(SubscriptionPlan::FREEMIUM);
        $t->timestamp('subscription_starts_at')->nullable();
        $t->timestamp('subscription_expires_at')->nullable();
        $t->boolean('subscription_next_period_paid')->default(false);
        $t->timestamp('subscription_next_expires_at')->nullable();
        $t->timestamps();
    });

    Schema::create('subscription_reminders_sent', function (Blueprint $t) {
        $t->id();
        $t->uuid('company_id');
        $t->unsignedTinyInteger('days_left');
        $t->date('sent_on');
        $t->timestamps();
        $t->unique(['company_id', 'days_left', 'sent_on']);
    });
});

afterEach(function () {
    Schema::dropIfExists('subscription_reminders_sent');
    Schema::dropIfExists('companies');
});

function expiringCompany(int $days, string $email = 'acme@example.com'): Company
{
    return Company::create([
        'name' => 'ACME',
        'email' => $email,
        'subscription' => SubscriptionPlan::GARANTIE,
        'subscription_starts_at' => now()->subWeeks(3),
        'subscription_expires_at' => now()->addDays($days)->setTime(10, 0),
    ]);
}

it('envoie un rappel J-7 puis ne le renvoie pas lors d\'une seconde execution le meme jour', function () {
    Mail::fake();
    expiringCompany(7);

    $this->artisan('subscriptions:send-reminders')->assertSuccessful();
    Mail::assertQueued(SubscriptionExpiringReminderMail::class, 1);
    expect(SubscriptionReminderSent::count())->toBe(1);

    // Seconde execution accidentelle le meme jour : aucun nouvel envoi.
    $this->artisan('subscriptions:send-reminders')->assertSuccessful();
    Mail::assertQueued(SubscriptionExpiringReminderMail::class, 1);
    expect(SubscriptionReminderSent::count())->toBe(1);
});

it('n\'envoie rien pour une compagnie freemium ou sans email', function () {
    Mail::fake();

    Company::create([
        'name' => 'Freemium',
        'email' => 'free@example.com',
        'subscription' => SubscriptionPlan::FREEMIUM,
        'subscription_expires_at' => now()->addDays(7)->setTime(10, 0),
    ]);
    expiringCompany(7, email: '');

    $this->artisan('subscriptions:send-reminders')->assertSuccessful();

    Mail::assertNothingQueued();
    expect(SubscriptionReminderSent::count())->toBe(0);
});

it('envoie un rappel distinct a chaque palier J-7 / J-3 / J-1', function () {
    Mail::fake();
    expiringCompany(7, email: 'j7@example.com');
    expiringCompany(3, email: 'j3@example.com');
    expiringCompany(1, email: 'j1@example.com');

    $this->artisan('subscriptions:send-reminders')->assertSuccessful();

    Mail::assertQueued(SubscriptionExpiringReminderMail::class, 3);
    expect(SubscriptionReminderSent::count())->toBe(3);
});
