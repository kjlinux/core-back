@component('mail::message')
# Paiement confirme

Bonjour {{ $payment->company->name }},

Nous confirmons la reception de votre paiement.

**Montant :** {{ number_format($payment->amount_xof, 0, ',', ' ') }} FCFA
**Plan :** {{ ucfirst($payment->to_plan) }}
@if($payment->is_prorata)
**Type :** Prorata d'upgrade
@endif
@if($payment->period_end)
**Periode couverte :** jusqu'au {{ \Illuminate\Support\Carbon::parse($payment->period_end)->format('d/m/Y') }}
@endif

@component('mail::button', ['url' => rtrim(config('app.frontend_url') ?? config('app.url'), '/').'/abonnement'])
Voir mon abonnement
@endcomponent

Merci de votre confiance,
**L'equipe TANGA GROUP**
@endcomponent
