@component('mail::message')
# Votre abonnement a expire

Bonjour {{ $company->name }},

Votre abonnement **{{ ucfirst($previousPlan) }}** TangaFlow a expire et votre compte est repasse au plan **Freemium**.

Les fonctionnalites suivantes ne sont plus accessibles : paie automatisee, rapports RH, mises a jour firmware OTA, support dedie.

@component('mail::button', ['url' => rtrim(config('app.frontend_url') ?? config('app.url'), '/').'/abonnement'])
Reactiver mon abonnement
@endcomponent

Vos donnees sont conservees : un renouvellement les rend immediatement disponibles.

A bientot,
**L'equipe TANGA GROUP**
@endcomponent
