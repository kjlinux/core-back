@component('mail::message')
# Paiement confirmé

Bonjour {{ $payment->company->name }},

Nous confirmons la réception de votre paiement.

**Montant :** {{ number_format($payment->amount_xof, 0, ',', ' ') }} FCFA
**Plan :** {{ ucfirst($payment->to_plan) }}
@if($payment->is_prorata)
**Type :** Prorata d'upgrade
@endif
@if($payment->period_end)
**Période couverte :** jusqu'au {{ \Illuminate\Support\Carbon::parse($payment->period_end)->format('d/m/Y') }}
@endif

@component('mail::button', ['url' => rtrim(config('app.frontend_url') ?? config('app.url'), '/').'/abonnement'])
Voir mon abonnement
@endcomponent

Merci de votre confiance,
**L'équipe Tangaflow**
@endcomponent
