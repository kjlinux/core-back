<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Destinataires supplementaires du digest quotidien
    |--------------------------------------------------------------------------
    |
    | Adresses qui recoivent le recapitulatif des logs terminaux (8h), en plus
    | des utilisateurs role=support_it. Plusieurs adresses possibles, separees
    | par des virgules dans DEVICE_LOGS_DIGEST_TO.
    |
    */
    'digest_extra_recipients' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('DEVICE_LOGS_DIGEST_TO', 'koffijude33@gmail.com'))
    ))),
];
