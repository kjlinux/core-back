<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Génère un PDF de rapport à partir de la vue Blade générique.
 * Réutilisable par les endpoints d'export ET les jobs de rapports planifiés.
 */
class ReportPdfRenderer
{
    /**
     * @param  array<int,string>  $headers
     * @param  array<int,array<int,string|int|float|null>>  $rows
     * @param  array<int,array{label:string,value:string|int|float}>  $summary
     */
    public static function render(
        string $title,
        array $headers,
        array $rows,
        array $summary = [],
        string $subtitle = '',
    ): \Barryvdh\DomPDF\PDF {
        return Pdf::loadView('reports.generic', [
            'title' => $title,
            'subtitle' => $subtitle,
            'headers' => $headers,
            'rows' => $rows,
            'summary' => $summary,
        ])->setPaper('a4', 'portrait');
    }
}
