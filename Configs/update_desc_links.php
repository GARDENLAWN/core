<?php

// Konfiguracja
$linksCsvFile = __DIR__ . '/AM_Desc_links.csv';
$sourceCsvFile = __DIR__ . '/AM_Desc.csv';
$outputCsvFile = __DIR__ . '/AM_Desc_updated.csv';
$s3BaseUrl = 'https://pub.am-robots.pl/media/am-robots/';
$baseUrl = 'https://am-robots.com';

// Mapa zamian: Stary Link => Nowy Link
$replacements = [];

// 1. Budowanie mapy zamian na podstawie AM_Desc_links.csv
if (($handle = fopen($linksCsvFile, "r")) !== FALSE) {
    fgetcsv($handle, 0, ";"); // Pomiń nagłówek

    while (($data = fgetcsv($handle, 0, ";")) !== FALSE) {
        $link = trim($data[1]);

        if (empty($link)) {
            continue;
        }

        // Ta sama logika filtrowania co w download_assets.php
        if (stripos($link, 'youtube.com/embed') !== false ||
            stripos($link, 'mailto:') !== false ||
            stripos($link, 'flippingbook.com') !== false ||
            (stripos($link, '/shop/') !== false && stripos($link, '.') === false) ||
            (stripos($link, '/shopold/') !== false && stripos($link, '.') === false)
        ) {
            continue;
        }

        // Obsługa linków relatywnych dla wyciągnięcia nazwy pliku
        $downloadUrl = $link;
        if (strpos($link, '/') === 0) {
            $downloadUrl = $baseUrl . $link;
        }

        $pathInfo = pathinfo(parse_url($downloadUrl, PHP_URL_PATH));

        if (!isset($pathInfo['extension'])) {
             continue;
        }

        $filename = $pathInfo['basename'];
        $newLink = $s3BaseUrl . $filename;

        // Dodaj do mapy zamian
        // Kluczem jest oryginalny link z CSV (czyli to co jest w tekście)
        $replacements[$link] = $newLink;
    }
    fclose($handle);
}

// Sortuj zamiany od najdłuższych kluczy, aby uniknąć problemów z podciągami
uksort($replacements, function($a, $b) {
    return strlen($b) - strlen($a);
});

echo "Liczba linków do podmienienia: " . count($replacements) . "\n";

// 2. Przetwarzanie AM_Desc.csv i podmiana linków
if (($inputHandle = fopen($sourceCsvFile, "r")) !== FALSE) {
    $outputHandle = fopen($outputCsvFile, "w");

    // Nagłówek
    $header = fgetcsv($inputHandle, 0, ";");
    fputcsv($outputHandle, $header, ";");

    $count = 0;
    while (($row = fgetcsv($inputHandle, 0, ";")) !== FALSE) {
        // row[0] = sku, row[1] = short_description, row[2] = description

        $shortDesc = $row[1];
        $desc = $row[2];

        // Podmiana w short_description
        $shortDesc = str_replace(array_keys($replacements), array_values($replacements), $shortDesc);

        // Podmiana w description
        $desc = str_replace(array_keys($replacements), array_values($replacements), $desc);

        $row[1] = $shortDesc;
        $row[2] = $desc;

        fputcsv($outputHandle, $row, ";");
        $count++;
    }

    fclose($inputHandle);
    fclose($outputHandle);

    echo "Zakończono. Przetworzono $count wierszy. Wynik w AM_Desc_updated.csv\n";
} else {
    echo "Nie można otworzyć pliku źródłowego CSV.\n";
}
