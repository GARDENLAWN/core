<?php

// --- KONFIGURACJA ---
$inputFile = 'AMRobotsAll.csv';
$outputFile = 'AMRobotsAll_updated_v3.csv';
$delimiter = ';';
$debugFile = 'error_log.html'; // Plik do podglądu błędu

if (!file_exists($inputFile)) {
    die("Błąd: Plik $inputFile nie istnieje.\n");
}

$inputHandle = fopen($inputFile, 'r');
$outputHandle = fopen($outputFile, 'w');

// Nagłówki udające prawdziwą przeglądarkę Chrome
$browserHeaders = [
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
    'Accept-Language: pl-PL,pl;q=0.9,en-US;q=0.8,en;q=0.7',
    'Cache-Control: max-age=0',
    'Connection: keep-alive',
    'Upgrade-Insecure-Requests: 1',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
];

echo "Rozpoczynam przetwarzanie w trybie 'Stealth' (losowe opóźnienia)...\n";

$rowNumber = 0;
$foundCount = 0;

while (($data = fgetcsv($inputHandle, 0, $delimiter)) !== false) {
    // Przepisanie nagłówka
    if ($rowNumber === 0) {
        fputcsv($outputHandle, $data, $delimiter);
        $rowNumber++;
        continue;
    }

    $sku = isset($data[1]) ? trim($data[1]) : '';

    if (!empty($sku)) {
        $searchUrl = "https://am-robots.com/pl/?s=" . urlencode($sku);

        // Wykonanie zapytania
        $response = fetchUrlComplex($searchUrl, $browserHeaders);
        $html = $response['content'];
        $finalUrl = $response['url']; // URL po ewentualnym przekierowaniu

        $result = null;

        if ($html) {
            // SPRAWDZENIE 1: Czy nastąpiło przekierowanie bezpośrednio do produktu?
            // (WooCommerce czasem przekierowuje, jeśli jest tylko 1 wynik)
            if (strpos($finalUrl, '/shop/') !== false || strpos($finalUrl, '/product/') !== false) {
                // Jesteśmy na karcie produktu, szukamy H1
                $result = extractFromProductPage($html, $finalUrl);
                if ($result) echo "[$rowNumber] SKU: $sku -> PRZEKIEROWANIE (Produkt: {$result['name']})\n";
            }

            // SPRAWDZENIE 2: Jeśli nie, szukamy na liście wyników
            if (!$result) {
                $result = extractFromSearchList($html);
                if ($result) echo "[$rowNumber] SKU: $sku -> LISTA (Produkt: {$result['name']})\n";
            }
        }

        // Zapis danych
        if ($result) {
            $data[0] = $result['url'];
            $data[2] = $result['name'];
            $foundCount++;
        } else {
            // Logowanie błędu tylko dla pierwszego nieudanego przypadku, żeby nie zaśmiecać dysku
            if (file_exists($debugFile) == false) {
                file_put_contents($debugFile, $html);
                echo "   !!! ZAPISANO PLIK DIAGNOSTYCZNY: $debugFile (Sprawdź go w przeglądarce) !!!\n";
            }

            $data[0] = 'BRAK';
            echo "[$rowNumber] SKU: $sku -> BRAK\n";
        }
    } else {
        $data[0] = 'BRAK';
    }

    fputcsv($outputHandle, $data, $delimiter);

    // Losowe opóźnienie 2-4 sekundy (bardziej ludzkie zachowanie)
    $sleepTime = rand(2, 4);
    sleep($sleepTime);

    $rowNumber++;
}

fclose($inputHandle);
fclose($outputHandle);
echo "\nZakończono. Znaleziono: $foundCount. Wyniki w: $outputFile\n";

// --- FUNKCJE ---

function fetchUrlComplex($url, $headers) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Podążaj za przekierowaniami
    curl_setopt($ch, CURLOPT_ENCODING, ""); // Obsługa kompresji gzip
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    // Cookie jar (ciasteczka), aby utrzymać sesję "człowieka"
    $cookieFile = sys_get_temp_dir() . '/cookie_amrobots.txt';
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);

    $content = curl_exec($ch);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

    curl_close($ch);
    return ['content' => $content, 'url' => $finalUrl];
}

// Funkcja 1: Parsowanie listy wyników (Twoje obecne podejście)
function extractFromSearchList($html) {
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($dom);

    $queryLink = "(//li[contains(@class, 'product')]//a[contains(@class, 'woocommerce-LoopProduct-link')])[1]";
    $linkNodes = $xpath->query($queryLink);

    if ($linkNodes->length > 0) {
        $linkNode = $linkNodes->item(0);
        $url = $linkNode->getAttribute('href');

        $queryName = ".//h2[contains(@class, 'woocommerce-loop-product__title')]";
        $nameNodes = $xpath->query($queryName, $linkNode);
        $name = ($nameNodes->length > 0) ? trim($nameNodes->item(0)->nodeValue) : '';

        return ['url' => $url, 'name' => $name];
    }
    return null;
}

// Funkcja 2: Parsowanie pojedynczej strony produktu (gdy nastąpi redirect)
function extractFromProductPage($html, $url) {
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($dom);

    // Szukamy głównego tytułu produktu H1
    $queryTitle = "//h1[contains(@class, 'product_title')]";
    $nodes = $xpath->query($queryTitle);

    if ($nodes->length > 0) {
        $name = trim($nodes->item(0)->nodeValue);
        return ['url' => $url, 'name' => $name];
    }
    return null;
}
?>
