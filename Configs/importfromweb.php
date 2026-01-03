<?php

// --- KONFIGURACJA ---
$inputFile = 'AMRobotsAll_updated_v3.csv'; // Plik wejściowy (z poprzedniego etapu)
$outputFile = 'AMRobotsAll_full_desc.csv'; // Plik wyjściowy
$delimiter = ';';
$debugFile = 'desc_error_log.html';

if (!file_exists($inputFile)) {
    die("Błąd: Plik $inputFile nie istnieje. Upewnij się, że podałeś dobrą nazwę pliku wejściowego.\n");
}

$inputHandle = fopen($inputFile, 'r');
$outputHandle = fopen($outputFile, 'w');

// Nagłówki udające przeglądarkę (Anti-bot)
$browserHeaders = [
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
    'Accept-Language: pl-PL,pl;q=0.9,en-US;q=0.8,en;q=0.7',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
];

echo "Rozpoczynam pobieranie opisów HTML...\n";

$rowNumber = 0;

while (($data = fgetcsv($inputHandle, 0, $delimiter)) !== false) {

    // 1. Obsługa nagłówka
    if ($rowNumber === 0) {
        // Dodajemy nazwy nowych kolumn
        $data[] = 'short_description';
        $data[] = 'description';
        fputcsv($outputHandle, $data, $delimiter);
        $rowNumber++;
        continue;
    }

    // 2. Pobranie URL z pierwszej kolumny (indeks 0)
    $url = isset($data[0]) ? trim($data[0]) : '';
    $sku = isset($data[1]) ? trim($data[1]) : 'nieznane SKU';

    // Inicjalizacja pustych zmiennych na opisy
    $shortDesc = '';
    $longDesc = '';

    // Sprawdzamy czy URL jest poprawny (nie jest pusty i nie jest "BRAK")
    if (!empty($url) && stripos($url, 'http') === 0) {

        // Pobieramy treść strony
        $html = fetchUrl($url, $browserHeaders);

        if ($html) {
            $parsed = extractDescriptions($html);
            $shortDesc = $parsed['short'];
            $longDesc = $parsed['long'];

            echo "[$rowNumber] SKU: $sku -> Pobrano opisy (Krótki: " . strlen($shortDesc) . " zn., Długi: " . strlen($longDesc) . " zn.)\n";
        } else {
            echo "[$rowNumber] SKU: $sku -> Błąd pobierania strony ($url)\n";
            // Opcjonalnie logowanie błędu
            // file_put_contents($debugFile, $html);
        }

    } else {
        echo "[$rowNumber] SKU: $sku -> Pominięto (Brak poprawnego URL)\n";
    }

    // 3. Dodanie danych do wiersza CSV
    // Ważne: HTML w CSV może zawierać znaki nowej linii, fputcsv obsłuży to cudzysłowami.
    $data[] = $shortDesc;
    $data[] = $longDesc;

    fputcsv($outputHandle, $data, $delimiter);

    // Losowe opóźnienie (anty-ban)
    if (!empty($url) && stripos($url, 'http') === 0) {
        $sleepTime = rand(1, 3);
        sleep($sleepTime);
    }

    $rowNumber++;
}

fclose($inputHandle);
fclose($outputHandle);

echo "\nZakończono! Wynik zapisano w pliku: $outputFile\n";


// --- FUNKCJE POMOCNICZE ---

function fetchUrl($url, $headers) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_ENCODING, ""); // Obsługa gzip
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    // Używamy tego samego pliku cookies co wcześniej (opcjonalnie)
    $cookieFile = sys_get_temp_dir() . '/cookie_amrobots.txt';
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);

    $content = curl_exec($ch);

    if (curl_errno($ch)) {
        return null;
    }

    curl_close($ch);
    return $content;
}

function extractDescriptions($html) {
    // Tłumienie błędów HTML5
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    // Wymuszenie UTF-8
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($dom);

    // 1. Krótki opis
    // Szukamy diva z klasą woocommerce-product-details__short-description
    $shortQuery = "//div[contains(@class, 'woocommerce-product-details__short-description')]";
    $shortNodes = $xpath->query($shortQuery);
    $shortHtml = '';

    if ($shortNodes->length > 0) {
        // Pobieramy zawartość WEWNĄTRZ diva (innerHTML)
        $shortHtml = getInnerHTML($shortNodes->item(0));
    }

    // 2. Długi opis
    // Szukamy diva z klasą woocommerce-Tabs-panel--description
    $longQuery = "//div[contains(@class, 'woocommerce-Tabs-panel--description')]";
    $longNodes = $xpath->query($longQuery);
    $longHtml = '';

    if ($longNodes->length > 0) {
        $longHtml = getInnerHTML($longNodes->item(0));
    }

    return [
        'short' => trim($shortHtml),
        'long'  => trim($longHtml)
    ];
}

/**
 * Helper do pobierania "Inner HTML" z węzła DOM.
 * DOMDocument nie ma właściwości .innerHTML, więc trzeba to złożyć ręcznie.
 */
function getInnerHTML($node) {
    $innerHTML = "";
    $children = $node->childNodes;

    foreach ($children as $child) {
        // saveHTML zapisuje tagi HTML dziecka
        $innerHTML .= $node->ownerDocument->saveHTML($child);
    }

    return $innerHTML;
}
?>
