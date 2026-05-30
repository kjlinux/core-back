@component('mail::message')
# Votre abonnement a expiré

Bonjour {{ $company->name }},

Votre abonnement **{{ ucfirst($previousPlan) }}** TangaFlow a expiré et votre compte est repassé au plan **Freemium**.

Les fonctionnalités suivantes ne sont plus accessibles : paie automatisée, rapports RH, mises à jour firmware OTA, support dédié.

@component('mail::button', ['url' => rtrim(config('app.frontend_url') ?? config('app.url'), '/').'/abonnement'])
Réactiver mon abonnement
@endcomponent

Vos données sont conservées : un renouvellement les rend immédiatement disponibles.

À bientôt,
**L'équipe Tangaflow**
@endcomponent
