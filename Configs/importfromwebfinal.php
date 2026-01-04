<?php

// --- KONFIGURACJA ---
$inputFile = 'AMRobotsAll_full_desc.csv'; // Plik z opisami HTML
$outputFile = 'AMRobotsAll_ready_for_import.csv'; // Plik gotowy
$youtubeFile = 'youtube_links.txt'; // Plik na linki YT
$delimiter = ';';

// S3 Base URL
$s3BaseUrl = 'https://pub.am-robots.pl/media/am-robots/';

if (!file_exists($inputFile)) {
    die("Błąd: Plik wejściowy $inputFile nie istnieje.\n");
}

$inputHandle = fopen($inputFile, 'r');
$outputHandle = fopen($outputFile, 'w');
$ytHandle = fopen($youtubeFile, 'w');

echo "Rozpoczynam przetwarzanie HTML (S3, YouTube, Tailwind)...\n";

$rowNumber = 0;

while (($data = fgetcsv($inputHandle, 0, $delimiter)) !== false) {

    // 1. Nagłówek
    if ($rowNumber === 0) {
        fputcsv($outputHandle, $data, $delimiter);
        $rowNumber++;
        continue;
    }

    $sku = $data[1] ?? 'UNKNOWN';

    // Pobierz opisy (zakładamy, że są na końcu - indeksy mogą się różnić w zależności od poprzedniego pliku)
    // Zazwyczaj w poprzednim skrypcie: ... | short_desc | desc
    // Policzmy indeksy dynamicznie lub przyjmijmy ostatnie dwa
    $count = count($data);
    $idxShort = $count - 2;
    $idxLong = $count - 1;

    $shortHtml = $data[$idxShort] ?? '';
    $longHtml = $data[$idxLong] ?? '';

    // Przetwarzanie
    $newShort = processHtmlContent($shortHtml, $sku, $s3BaseUrl, $ytHandle);
    $newLong = processHtmlContent($longHtml, $sku, $s3BaseUrl, $ytHandle);

    // Zapisz z powrotem
    $data[$idxShort] = $newShort;
    $data[$idxLong] = $newLong;

    fputcsv($outputHandle, $data, $delimiter);
    $rowNumber++;

    if ($rowNumber % 50 == 0) echo "Przetworzono $rowNumber wierszy...\n";
}

fclose($inputHandle);
fclose($outputHandle);
fclose($ytHandle);

echo "\nGotowe! Wynik: $outputFile\nLinki YouTube: $youtubeFile\n";


// --- GŁÓWNA FUNKCJA PRZETWARZAJĄCA ---

function processHtmlContent($html, $sku, $s3Url, $ytFileHandle) {
    if (empty(trim($html))) return '';

    // Tłumienie błędów HTML5
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();

    // Hack na UTF-8: dodajemy meta charset, bo loadHTML domyślnie woli ISO-8859-1
    $htmlWithCharset = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $html;
    $dom->loadHTML($htmlWithCharset, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    $xpath = new DOMXPath($dom);

    // --- 1. OBRAZKI I PLIKI (S3) ---

    // A) Obrazki <img>
    $images = $dom->getElementsByTagName('img');
    // Iteracja wsteczna nie jest konieczna przy zmianie atrybutów, ale bezpieczniejsza
    for ($i = $images->length - 1; $i >= 0; $i--) {
        $img = $images->item($i);
        $src = $img->getAttribute('src');
        if ($src) {
            $filename = basename(parse_url($src, PHP_URL_PATH));
            // Czasem w URL są parametry po ? - basename to utnie jeśli użyjemy parse_url
            // Jeśli src jest base64, pomijamy lub decydujemy co robić (tu pomijamy)
            if (strpos($src, 'data:image') === false) {
                $img->setAttribute('src', $s3Url . $filename);
                // Usuń stare atrybuty (punkt 4)
                $img->removeAttribute('width');
                $img->removeAttribute('height');
                $img->removeAttribute('style');
                $img->removeAttribute('srcset'); // często psuje responsywność po zmianie domeny
                // Dodaj klasy Tailwind (punkt 4)
                addClass($img, "max-w-full h-auto my-4 rounded-lg shadow-sm");
            }
        }
    }

    // B) Linki do plików <a>
    $links = $dom->getElementsByTagName('a');
    $fileExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'jpg', 'jpeg', 'png', 'webp', 'gif'];

    // Używamy tablicy do usunięcia, żeby nie modyfikować DOM podczas iteracji
    $nodesToRemove = [];
    $linksToUnwrap = [];

    foreach ($links as $link) {
        $href = $link->getAttribute('href');
        $ext = strtolower(pathinfo(parse_url($href, PHP_URL_PATH), PATHINFO_EXTENSION));

        // --- 2. YOUTUBE (wykrywanie w linkach tekstowych) ---
        if (strpos($href, 'youtube.com') !== false || strpos($href, 'youtu.be') !== false) {
            fwrite($ytFileHandle, "$sku - $href\n");
            $nodesToRemove[] = $link;
            continue;
        }

        // Czy to plik/obrazek?
        if (in_array($ext, $fileExtensions)) {
            // Podmień na S3
            $filename = basename(parse_url($href, PHP_URL_PATH));
            $link->setAttribute('href', $s3Url . $filename);
            $link->setAttribute('target', '_blank'); // Dobra praktyka dla plików
        } else {
            // --- 3. LINKI ZEWNĘTRZNE (Unwrap) ---
            // Jeśli to nie plik, to usuwamy link, zostawiając tekst
            $text = trim($link->textContent);
            // Jeśli tekst to generyczne "kliknij", usuwamy cały węzeł
            if (preg_match('/^(kliknij|zobacz|więcej|tutaj|here|click)/i', $text)) {
                $nodesToRemove[] = $link;
            } else {
                $linksToUnwrap[] = $link;
            }
        }
    }

    // Wykrywanie iframe YouTube
    $iframes = $dom->getElementsByTagName('iframe');
    foreach ($iframes as $iframe) {
        $src = $iframe->getAttribute('src');
        if (strpos($src, 'youtube') !== false || strpos($src, 'youtu.be') !== false) {
            fwrite($ytFileHandle, "$sku - IFRAME: $src\n");
            $nodesToRemove[] = $iframe;
        }
    }

    // Wykonaj usuwanie węzłów
    foreach ($nodesToRemove as $node) {
        $node->parentNode->removeChild($node);
    }

    // Wykonaj unwrap (zamiana <a>tekst</a> na tekst)
    foreach ($linksToUnwrap as $link) {
        $textNode = $dom->createTextNode($link->textContent);
        $link->parentNode->replaceChild($textNode, $link);
    }

    // --- 4. STYLIZACJA TAILWIND ---

    // Typografia: <b>, <strong> -> span.font-bold
    $boldTags = ['b', 'strong'];
    foreach ($boldTags as $tagName) {
        $nodes = $dom->getElementsByTagName($tagName);
        // Iteracja wsteczna, bo zmieniamy typ węzła
        for ($i = $nodes->length - 1; $i >= 0; $i--) {
            $node = $nodes->item($i);
            $span = $dom->createElement('span');
            $span->setAttribute('class', 'font-bold');
            while ($node->childNodes->length > 0) {
                $span->appendChild($node->childNodes->item(0));
            }
            $node->parentNode->replaceChild($span, $node);
        }
    }

    // Listy
    $uls = $dom->getElementsByTagName('ul');
    foreach ($uls as $ul) addClass($ul, "list-disc pl-5 mb-4 space-y-1");

    $ols = $dom->getElementsByTagName('ol');
    foreach ($ols as $ol) addClass($ol, "list-decimal pl-5 mb-4 space-y-1");

    // Nagłówki
    for ($h = 1; $h <= 6; $h++) {
        $headers = $dom->getElementsByTagName("h$h");
        foreach ($headers as $header) addClass($header, "text-lg font-semibold mt-4 mb-2");
    }

    // Tabele (wrapowanie w div)
    $tables = $dom->getElementsByTagName('table');
    // Iterujemy wstecz, bo modyfikujemy strukturę rodziców
    for ($i = $tables->length - 1; $i >= 0; $i--) {
        $table = $tables->item($i);
        addClass($table, "w-full text-sm text-left");

        // Stwórz wrapper
        $wrapper = $dom->createElement('div');
        $wrapper->setAttribute('class', 'overflow-x-auto my-4');

        // Podmień w drzewie
        $table->parentNode->replaceChild($wrapper, $table);
        $wrapper->appendChild($table);
    }

    // Usuwanie pustych tagów (sprzątanie)
    $cleanTags = ['p', 'span', 'div'];
    foreach ($cleanTags as $tag) {
        $nodes = $dom->getElementsByTagName($tag);
        for ($i = $nodes->length - 1; $i >= 0; $i--) {
            $node = $nodes->item($i);
            // Sprawdź czy pusty (bez tekstu i bez dzieci typu img)
            if (trim($node->textContent) === '' && $node->getElementsByTagName('img')->length === 0 && $node->getElementsByTagName('iframe')->length === 0) {
                $node->parentNode->removeChild($node);
            }
        }
    }

    // Wrapper PROSE (zamykamy wszystko w jednym divie na końcu, ale jako string)
    // DOMDocument->saveHTML() zwróci treść body.
    // Musimy usunąć dodany na początku <meta charset>.

    $bodyContent = '';
    // Pobieramy dzieci body (bo loadHTML tworzy strukturę html->body)
    $body = $dom->getElementsByTagName('body')->item(0);
    if ($body) {
        foreach ($body->childNodes as $child) {
            $bodyContent .= $dom->saveHTML($child);
        }
    } else {
        // Fallback jeśli html był bardzo prosty
        $bodyContent = $dom->saveHTML();
    }

    // Owiń w kontener Hyva/Tailwind
    $finalHtml = '<div class="prose prose-sm max-w-none text-gray-700">' . $bodyContent . '</div>';

    return $finalHtml;
}

// Funkcja pomocnicza do dodawania klas (nie nadpisuje istniejących, tylko dopisuje)
function addClass($node, $classes) {
    if (!$node) return;
    $current = $node->getAttribute('class');
    $node->setAttribute('class', trim($current . ' ' . $classes));
}
?>
