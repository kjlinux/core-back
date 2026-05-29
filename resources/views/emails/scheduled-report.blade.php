<x-mail::message>
# {{ $reportTitle }}

Voici votre rapport planifié pour la période : **{{ $periodLabel }}**.

Le document est joint à cet email ({{ $attachmentName }}).

<x-mail::subcopy>
Vous recevez cet email car une planification de rapport a été configurée pour votre compte.
Pour la modifier ou la désactiver, rendez-vous dans Paramètres → Rapports planifiés.
</x-mail::subcopy>
</x-mail::message>
