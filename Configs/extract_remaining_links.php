<?php

// Konfiguracja
$inputFile = __DIR__ . '/AM_Desc_updated.csv';
$outputFile = __DIR__ . '/AM_Desc_remaining_links.csv';
$s3BaseUrl = 'https://pub.am-robots.pl/media/am-robots/';

// Otwórz plik wejściowy
if (($handle = fopen($inputFile, "r")) !== FALSE) {
    $outputHandle = fopen($outputFile, "w");
    // Nagłówek pliku wynikowego
    fputcsv($outputHandle, ['sku', 'link'], ";");

    // Pomiń nagłówek pliku wejściowego
    fgetcsv($handle, 0, ";");

    echo "Rozpoczynam ekstrakcję pozostałych linków...\n";
    $count = 0;

    while (($data = fgetcsv($handle, 0, ";")) !== FALSE) {
        $sku = $data[0];
        $shortDesc = $data[1];
        $desc = $data[2];

        $content = $shortDesc . ' ' . $desc;

        // Znajdź wszystkie linki w atrybutach href i src
        if (preg_match_all('/(href|src)=["\']([^"\']+)["\']/i', $content, $matches)) {
            $links = array_unique($matches[2]);

            foreach ($links as $link) {
                // Ignoruj linki prowadzące już do S3
                if (strpos($link, $s3BaseUrl) !== false) {
                    continue;
                }

                // Ignoruj puste linki lub kotwice
                if (empty($link) || $link === '#' || strpos($link, '#') === 0) {
                    continue;
                }

                // Zapisz znaleziony link
                fputcsv($outputHandle, [$sku, $link], ";");
                $count++;
            }
        }

        // Opcjonalnie: szukanie linków w tekście (nie w atrybutach), jeśli to konieczne,
        // ale zazwyczaj w HTML są w atrybutach.
    }

    fclose($handle);
    fclose($outputHandle);
    echo "Zakończono. Znaleziono $count linków. Wyniki w AM_Desc_remaining_links.csv\n";
} else {
    echo "Nie można otworzyć pliku AM_Desc_updated.csv\n";
}
