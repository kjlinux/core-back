<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Helper de streaming CSV — évite de matérialiser tout le fichier en mémoire.
 * Ajoute le BOM UTF-8 pour qu'Excel reconnaisse l'encodage (accents).
 */
class CsvExporter
{
    /**
     * @param iterable<int,array<int,string|int|float|null>> $rows
     * @param array<int,string> $headers
     */
    public static function stream(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        $safeName = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $filename) ?: 'export.csv';

        return new StreamedResponse(function () use ($headers, $rows) {
            $out = fopen('php://output', 'wb');
            // BOM UTF-8 pour Excel.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ';');
            foreach ($rows as $row) {
                fputcsv($out, $row, ';');
            }
            fclose($out);
        }, 200, [
            'Content-Type'              => 'text/csv; charset=UTF-8',
            'Content-Disposition'       => "attachment; filename=\"{$safeName}\"",
            'X-Content-Type-Options'    => 'nosniff',
            'Cache-Control'             => 'no-store, max-age=0',
        ]);
    }
}
