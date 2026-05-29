<?php

return [
    // Minutes sans signal avant de marquer un capteur hors ligne.
    'offline_threshold_minutes' => (int) env('DEVICE_OFFLINE_THRESHOLD_MINUTES', 5),

    // Paliers (en jours) d'escalade pour les capteurs hors ligne prolongé.
    // Utilisés par support:check-prolonged-offline pour la sévérité montante.
    'prolonged_offline_days' => [
        'medium' => (int) env('DEVICE_PROLONGED_OFFLINE_MEDIUM_DAYS', 2),
        'high' => (int) env('DEVICE_PROLONGED_OFFLINE_HIGH_DAYS', 7),
        'critical' => (int) env('DEVICE_PROLONGED_OFFLINE_CRITICAL_DAYS', 14),
    ],
];
