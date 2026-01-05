<?php

// Konfiguracja
$csvFile = __DIR__ . '/AM_Desc_links.csv';
$downloadDir = __DIR__ . '/Download';
$baseUrl = 'https://am-robots.com';

// Upewnij się, że katalog docelowy istnieje
if (!is_dir($downloadDir)) {
    mkdir($downloadDir, 0777, true);
}

// Otwórz plik CSV
if (($handle = fopen($csvFile, "r")) !== FALSE) {
    // Pomiń nagłówek
    fgetcsv($handle, 0, ";");

    echo "Rozpoczynam pobieranie plików...\n";

    while (($data = fgetcsv($handle, 0, ";")) !== FALSE) {
        $sku = $data[0];
        $link = trim($data[1]);

        // Pomiń puste linki
        if (empty($link)) {
            continue;
        }

        // Filtrowanie - pobieramy tylko pliki (obrazki, pdf, dokumenty)
        // Ignorujemy linki do stron, youtube embed, mailto
        if (stripos($link, 'youtube.com/embed') !== false ||
            stripos($link, 'mailto:') !== false ||
            stripos($link, 'flippingbook.com') !== false ||
            (stripos($link, '/shop/') !== false && stripos($link, '.') === false) || // Linki do produktów/kategorii bez rozszerzenia
            (stripos($link, '/shopold/') !== false && stripos($link, '.') === false)
        ) {
            echo "Pomijam: $link\n";
            continue;
        }

        // Obsługa linków relatywnych
        $downloadUrl = $link;
        if (strpos($link, '/') === 0) {
            $downloadUrl = $baseUrl . $link;
        }

        // Wyciągnij nazwę pliku
        $pathInfo = pathinfo(parse_url($downloadUrl, PHP_URL_PATH));

        // Jeśli brak rozszerzenia, pomiń (chyba że to specyficzny przypadek)
        if (!isset($pathInfo['extension'])) {
             echo "Pomijam (brak rozszerzenia): $link\n";
             continue;
        }

        $filename = $pathInfo['basename'];
        $destination = $downloadDir . '/' . $filename;

        // Pobieranie pliku
        echo "Pobieranie: $downloadUrl -> $filename\n";

        $content = @file_get_contents($downloadUrl);

        if ($content !== FALSE) {
            file_put_contents($destination, $content);
            echo "Zapisano.\n";
        } else {
            echo "BŁĄD: Nie udało się pobrać $downloadUrl\n";
        }
    }
    fclose($handle);
    echo "Zakończono.\n";
} else {
    echo "Nie można otworzyć pliku CSV.\n";
}
