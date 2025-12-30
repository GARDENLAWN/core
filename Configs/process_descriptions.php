<?php

// Ustawienia
$jsonFile = __DIR__ . '/automow_prepared_description_data.json';
$outputJsonFile = __DIR__ . '/automow_prepared_description_data_processed.json';
$downloadDir = __DIR__ . '/downloaded_files/';
$s3BaseUrl = 'https://pub.am-robots.pl/media/am-robots/';

// Rozszerzenia plików, których szukamy
$allowedExtensions = [
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
    'zip', 'rar', '7z',
    'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
    'mp4', 'avi', 'mov'
];

// Utwórz katalog na pobrane pliki, jeśli nie istnieje
if (!file_exists($downloadDir)) {
    if (!mkdir($downloadDir, 0777, true) && !is_dir($downloadDir)) {
        die(sprintf('Nie można utworzyć katalogu "%s"', $downloadDir));
    }
}

// Wczytaj dane JSON
if (!file_exists($jsonFile)) {
    die("Plik JSON nie istnieje: $jsonFile");
}

$jsonData = file_get_contents($jsonFile);
$products = json_decode($jsonData, true);

if (!$products) {
    die("Błąd dekodowania pliku JSON.");
}

echo "Rozpoczynam przetwarzanie " . count($products) . " produktów...\n";

foreach ($products as &$product) {
    if (empty($product['description'])) {
        continue;
    }

    $description = $product['description'];
    $modified = false;

    // Używamy DOMDocument do parsowania HTML
    $dom = new DOMDocument();

    // Wyłączamy błędy libxml dla niepoprawnego HTML
    libxml_use_internal_errors(true);

    // Hack na kodowanie UTF-8
    // Dodajemy wrapper, aby upewnić się, że fragmenty HTML są poprawnie parsowane
    $dom->loadHTML('<?xml encoding="utf-8" ?><div>' . $description . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    // Szukamy wszystkich tagów 'a'
    $links = $dom->getElementsByTagName('a');

    // Zbieramy linki do tablicy, aby móc modyfikować DOM w pętli bez problemów
    $linksToProcess = [];
    foreach ($links as $link) {
        $linksToProcess[] = $link;
    }

    foreach ($linksToProcess as $link) {
        $href = $link->getAttribute('href');

        if (empty($href)) {
            continue;
        }

        // Parsuj URL
        $parsedUrl = parse_url($href);
        if (!isset($parsedUrl['path'])) {
            continue;
        }

        $pathInfo = pathinfo($parsedUrl['path']);

        // Sprawdź czy link prowadzi do pliku o szukanym rozszerzeniu
        if (isset($pathInfo['extension']) && in_array(strtolower($pathInfo['extension']), $allowedExtensions)) {

            $filename = $pathInfo['basename'];
            // Dekoduj nazwę pliku (np. spacje %20)
            $filename = urldecode($filename);

            // Oczyszczanie nazwy pliku z dziwnych znaków, które mogą powodować problemy
            $cleanFilename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

            $localPath = $downloadDir . $cleanFilename;

            echo "Znaleziono plik: $filename ($href)\n";

            // Sprawdź czy plik już istnieje, aby nie pobierać go ponownie
            if (file_exists($localPath)) {
                 echo " -> Plik już istnieje: $localPath\n";
                 $fileDownloaded = true;
            } else {
                // Pobierz plik
                $fileContent = @file_get_contents($href);

                if ($fileContent !== false) {
                    file_put_contents($localPath, $fileContent);
                    echo " -> Pobrano do: $localPath\n";
                    $fileDownloaded = true;
                } else {
                    echo " -> BŁĄD: Nie udało się pobrać pliku.\n";
                    $fileDownloaded = false;
                }
            }

            if ($fileDownloaded) {
                // Zaktualizuj link w HTML
                $newUrl = $s3BaseUrl . $cleanFilename;
                $link->setAttribute('href', $newUrl);
                // Opcjonalnie: ustaw target="_blank" dla plików
                $link->setAttribute('target', '_blank');

                $modified = true;
            }
        }
    }

    if ($modified) {
        // Zapisz zmodyfikowany HTML
        // Pobieramy zawartość div-a, którego dodaliśmy jako wrapper
        $container = $dom->getElementsByTagName('div')->item(0);
        $newDescription = '';
        foreach ($container->childNodes as $child) {
            $newDescription .= $dom->saveHTML($child);
        }

        $product['description'] = $newDescription;
    }
}

// Zapisz nowy plik JSON
$newJsonData = json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (file_put_contents($outputJsonFile, $newJsonData)) {
    echo "\nSukces! Przetworzone dane zapisano w: $outputJsonFile\n";
    echo "Pobrane pliki znajdują się w: $downloadDir\n";
} else {
    echo "\nBłąd podczas zapisywania pliku JSON.\n";
}
