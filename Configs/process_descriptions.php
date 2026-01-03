<?php
/**
 * Skrypt przetwarzający opisy produktów dla Hyvä Theme / Magento 2
 * Uruchomienie: php process_descriptions.php
 */

use DOMDocument;
use DOMXPath;

// Konfiguracja ścieżek
$dir = __DIR__;
$inputFile = $dir . '/produkty_opisy.json';
$outputFile = $dir . '/produkty_opisy_processed.json';
$youtubeLogFile = $dir . '/youtube_links.txt';

// Konfiguracja URL
$s3BaseUrl = 'https://pub.am-robots.pl/media/am-robots/';

// Rozszerzenia traktowane jako pliki do pobrania/zasoby
$fileExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar'];

// Frazy do całkowitego usunięcia linku (zamiast unwrap)
$removeLinkPhrases = ['kliknij tutaj', 'zobacz więcej', 'tutaj', 'więcej', 'czytaj dalej', 'zobacz'];

// Inicjalizacja
if (!file_exists($inputFile)) {
    die("Błąd: Nie znaleziono pliku $inputFile\n");
}

$jsonData = file_get_contents($inputFile);
$products = json_decode($jsonData, true);

if (!$products) {
    die("Błąd: Nie udało się sparsować JSON.\n");
}

$youtubeLinksLog = [];
$processedCount = 0;

// Główna pętla
foreach ($products as &$product) {
    $sku = $product['sku'] ?? 'UNKNOWN_SKU';

    if (isset($product['description'])) {
        $product['description'] = processHtml($product['description'], $sku, $youtubeLinksLog, $s3BaseUrl, $fileExtensions, $removeLinkPhrases);
    }

    if (isset($product['short_description'])) {
        $product['short_description'] = processHtml($product['short_description'], $sku, $youtubeLinksLog, $s3BaseUrl, $fileExtensions, $removeLinkPhrases);
    }

    $processedCount++;
    if ($processedCount % 100 === 0) {
        echo "Przetworzono $processedCount produktów...\n";
    }
}

// Zapis wyników
file_put_contents($outputFile, json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

// Zapis logów YouTube
$ytContent = "";
foreach ($youtubeLinksLog as $entry) {
    $ytContent .= "{$entry['sku']} - {$entry['link']}\n";
}
file_put_contents($youtubeLogFile, $ytContent);

echo "Zakończono!\n";
echo "Przetworzony JSON: $outputFile\n";
echo "Linki YouTube: $youtubeLogFile\n";

/**
 * Główna funkcja przetwarzająca HTML
 */
function processHtml($html, $sku, &$youtubeLinksLog, $s3BaseUrl, $fileExtensions, $removeLinkPhrases) {
    if (empty(trim($html))) {
        return '';
    }

    // Obsługa błędów HTML
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();

    // Hack na UTF-8: mb_convert_encoding zamiast dodawania meta tagów, które potem trzeba wycinać
    $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');

    // Opcje ładowania, aby nie dodawał doctype/html/body automatycznie (choć saveHTML i tak może dodać, obsłużymy to)
    // Używamy flagi 0 zamiast stałych, jeśli wersja PHP jest starsza, ale tutaj zakładamy standardowe środowisko M2
    $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    $xpath = new DOMXPath($dom);

    // 1. OBRAZKI (IMG) - S3 i Style
    $images = $dom->getElementsByTagName('img');
    // Iteracja wsteczna nie jest konieczna przy modyfikacji atrybutów, ale bezpieczna
    for ($i = $images->length - 1; $i >= 0; $i--) {
        $img = $images->item($i);
        $src = $img->getAttribute('src');

        // Podmiana SRC na S3
        if ($src) {
            $filename = basename($src);
            // Usuwamy ewentualne parametry URL po rozszerzeniu
            $filename = explode('?', $filename)[0];
            $img->setAttribute('src', $s3BaseUrl . $filename);
        }

        // Usuwanie atrybutów
        $img->removeAttribute('width');
        $img->removeAttribute('height');
        $img->removeAttribute('style');

        // Dodawanie klas Tailwind
        $img->setAttribute('class', 'max-w-full h-auto my-4 rounded-lg shadow-sm');
    }

    // 2. LINKI (A) - Pliki, YouTube, Zewnętrzne
    $links = $dom->getElementsByTagName('a');
    // Iteracja wsteczna, bo możemy usuwać węzły
    for ($i = $links->length - 1; $i >= 0; $i--) {
        $link = $links->item($i);
        $href = $link->getAttribute('href');
        $text = trim($link->textContent);

        // A) YouTube Links
        if (preg_match('/(youtube\.com|youtu\.be)/i', $href)) {
            $youtubeLinksLog[] = ['sku' => $sku, 'link' => $href];
            // Usuwamy węzeł całkowicie
            $link->parentNode->removeChild($link);
            continue;
        }

        // B) Pliki i Obrazki w linkach
        $ext = strtolower(pathinfo(parse_url($href, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (in_array($ext, $fileExtensions)) {
            $filename = basename($href);
            $filename = explode('?', $filename)[0];
            $link->setAttribute('href', $s3BaseUrl . $filename);
            continue; // To był plik, zostawiamy tag <a> (zaktualizowany)
        }

        // C) Linki zewnętrzne (czyszczenie)
        // Sprawdzamy czy tekst jest "generyczny"
        $isGenericText = false;
        foreach ($removeLinkPhrases as $phrase) {
            if (mb_strtolower($text) === $phrase) {
                $isGenericText = true;
                break;
            }
        }

        if ($isGenericText) {
            // Usuwamy cały tag
            $link->parentNode->removeChild($link);
        } else {
            // Unwrap - zostawiamy tekst, usuwamy tag <a>
            // Przenosimy dzieci linku przed link
            while ($link->hasChildNodes()) {
                $child = $link->firstChild;
                $link->parentNode->insertBefore($child, $link);
            }
            // Usuwamy pusty tag <a>
            $link->parentNode->removeChild($link);
        }
    }

    // 2b. IFRAME (YouTube)
    $iframes = $dom->getElementsByTagName('iframe');
    for ($i = $iframes->length - 1; $i >= 0; $i--) {
        $iframe = $iframes->item($i);
        $src = $iframe->getAttribute('src');
        if (preg_match('/(youtube\.com|youtu\.be)/i', $src)) {
            $youtubeLinksLog[] = ['sku' => $sku, 'link' => $src];
            $iframe->parentNode->removeChild($iframe);
        }
    }

    // 3. STYLIZACJA HYVÄ (Tailwind)

    // B / STRONG -> span.font-bold
    foreach (['b', 'strong'] as $tagName) {
        $nodes = $dom->getElementsByTagName($tagName);
        for ($i = $nodes->length - 1; $i >= 0; $i--) {
            $node = $nodes->item($i);
            $span = $dom->createElement('span');
            $span->setAttribute('class', 'font-bold');

            while ($node->hasChildNodes()) {
                $span->appendChild($node->firstChild);
            }
            $node->parentNode->replaceChild($span, $node);
        }
    }

    // Listy UL
    $uls = $dom->getElementsByTagName('ul');
    foreach ($uls as $ul) {
        $ul->setAttribute('class', 'list-disc pl-5 mb-4 space-y-1');
    }

    // Listy OL
    $ols = $dom->getElementsByTagName('ol');
    foreach ($ols as $ol) {
        $ol->setAttribute('class', 'list-decimal pl-5 mb-4 space-y-1');
    }

    // Nagłówki H1-H6
    for ($h = 1; $h <= 6; $h++) {
        $headers = $dom->getElementsByTagName("h$h");
        foreach ($headers as $header) {
            // Resetujemy style inline jeśli są
            $header->removeAttribute('style');
            $header->setAttribute('class', 'text-lg font-semibold mt-4 mb-2');
        }
    }

    // Tabele - Wrapper
    $tables = $dom->getElementsByTagName('table');
    for ($i = $tables->length - 1; $i >= 0; $i--) {
        $table = $tables->item($i);

        // Tworzymy wrapper
        $wrapper = $dom->createElement('div');
        $wrapper->setAttribute('class', 'overflow-x-auto');

        // Podmieniamy tabelę na wrapper w drzewie DOM
        $table->parentNode->replaceChild($wrapper, $table);

        // Dodajemy tabelę do wrappera
        $wrapper->appendChild($table);

        // Klasy dla tabeli
        $table->setAttribute('class', 'w-full text-sm text-left');
    }

    // 4. CZYSZCZENIE PUSTYCH TAGÓW
    // Używamy XPath do znalezienia pustych elementów (bez tekstu i bez dzieci)
    // Wykluczamy img, br, hr
    $emptyTags = $xpath->query('//*[not(node()) and not(self::img) and not(self::br) and not(self::hr)]');
    foreach ($emptyTags as $node) {
        $node->parentNode->removeChild($node);
    }

    // 5. WRAPPER GŁÓWNY (Prose)
    // Tworzymy nowy element główny i przenosimy tam całą zawartość body
    $wrapperDiv = $dom->createElement('div');
    $wrapperDiv->setAttribute('class', 'prose prose-sm max-w-none text-gray-700');

    // Przenosimy wszystkie dzieci body do wrappera
    // Uwaga: loadHTML tworzy strukturę html > body, nawet jeśli jej nie było w stringu wejściowym
    $body = $dom->getElementsByTagName('body')->item(0);
    if ($body) {
        while ($body->hasChildNodes()) {
            $wrapperDiv->appendChild($body->firstChild);
        }
    } else {
        // Fallback jeśli struktura jest inna (mało prawdopodobne przy loadHTML)
        while ($dom->hasChildNodes()) {
            $wrapperDiv->appendChild($dom->firstChild);
        }
    }

    // Tworzymy nowy dokument, żeby zapisać tylko wrapper
    $newDom = new DOMDocument();
    // Importujemy wrapper do nowego dokumentu
    $importedNode = $newDom->importNode($wrapperDiv, true);
    $newDom->appendChild($importedNode);

    // Zwracamy HTML bez deklaracji XML
    return $newDom->saveHTML($importedNode);
}
