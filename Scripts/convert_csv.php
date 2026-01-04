<?php

$inputFile = __DIR__ . '/../Configs/AM_Desc_Cleaned.csv';
$outputFile = __DIR__ . '/../Configs/AM_Desc_Hyva.csv';

if (!file_exists($inputFile)) {
    echo "Input file not found: $inputFile\n";
    exit(1);
}

$handle = fopen($inputFile, 'r');
$outHandle = fopen($outputFile, 'w');

if ($handle === false || $outHandle === false) {
    echo "Error opening files.\n";
    exit(1);
}

// Read header
$header = fgetcsv($handle, 0, ";", "\"");
fputcsv($outHandle, $header, ";", "\"");

while (($data = fgetcsv($handle, 0, ";", "\"")) !== false) {
    $sku = $data[0];
    $shortDesc = $data[1];
    $desc = $data[2];

    $shortDesc = styleHtml($shortDesc);
    $desc = styleHtml($desc);

    fputcsv($outHandle, [$sku, $shortDesc, $desc], ";", "\"");
}

fclose($handle);
fclose($outHandle);

echo "File generated: $outputFile\n";

function styleHtml($html) {
    // Basic cleanup
    $html = trim($html);
    if (empty($html) || $html === '<p>TODO</p>') {
        return $html;
    }

    // Replace headings
    $html = preg_replace('/<h2>(.*?)<\/h2>/i', '<h2 class="text-2xl font-bold mb-4 text-gray-900">$1</h2>', $html);
    $html = preg_replace('/<h3>(.*?)<\/h3>/i', '<h3 class="text-xl font-bold mb-3 text-gray-900">$1</h3>', $html);
    $html = preg_replace('/<h6>(.*?)<\/h6>/i', '<h6 class="text-lg font-bold mb-2 text-gray-900">$1</h6>', $html);

    // Replace paragraphs
    // We use a negative lookahead to avoid double wrapping if run multiple times or if already styled
    $html = preg_replace('/<p>(.*?)<\/p>/i', '<p class="mb-4 text-gray-700 leading-relaxed">$1</p>', $html);

    // Add class to p tags that might have attributes already but not our classes
    // This regex is a bit simplistic, assumes standard HTML structure
    $html = preg_replace_callback('/<p\s+([^>]+)>(.*?)<\/p>/i', function($matches) {
        $attrs = $matches[1];
        $content = $matches[2];
        if (strpos($attrs, 'class="') === false) {
            return '<p class="mb-4 text-gray-700 leading-relaxed" ' . $attrs . '>' . $content . '</p>';
        } else {
             // If class exists, append our classes (simplified)
             return str_replace('class="', 'class="mb-4 text-gray-700 leading-relaxed ', $matches[0]);
        }
    }, $html);


    // Replace lists
    $html = preg_replace('/<ul>/i', '<ul class="list-disc pl-5 mb-4 space-y-2 text-gray-700">', $html);
    $html = preg_replace('/<ul\s+([^>]+)>/i', '<ul class="list-disc pl-5 mb-4 space-y-2 text-gray-700" $1>', $html);

    // Replace tables
    // Wrap table in responsive div
    if (strpos($html, '<table') !== false && strpos($html, 'overflow-x-auto') === false) {
        $html = preg_replace('/<table(.*?)>/i', '<div class="overflow-x-auto mb-6"><table class="min-w-full divide-y divide-gray-200 border border-gray-200"$1>', $html);
        $html = str_replace('</table>', '</table></div>', $html);

        $html = preg_replace('/<td(.*?)>/i', '<td class="px-4 py-2 text-sm text-gray-700 border-r border-gray-200 last:border-r-0"$1>', $html);
        // Try to identify header rows if they are just trs with strong or th
        $html = preg_replace('/<th(.*?)>/i', '<th class="px-4 py-2 text-sm font-bold text-gray-900 bg-gray-50 border-r border-gray-200 last:border-r-0"$1>', $html);
        $html = preg_replace('/<tr(.*?)>/i', '<tr class="even:bg-gray-50 border-b border-gray-200 last:border-b-0"$1>', $html);
    }

    // Replace strong/b
    $html = preg_replace('/<strong>(.*?)<\/strong>/i', '<strong class="font-bold text-gray-900">$1</strong>', $html);
    $html = preg_replace('/<b>(.*?)<\/b>/i', '<strong class="font-bold text-gray-900">$1</strong>', $html);

    // Replace links
    $html = preg_replace('/<a\s+(.*?)>/i', '<a class="text-blue-600 hover:underline" $1>', $html);

    // Specific fix for "Korzyści:" pattern in paragraphs to convert to header/list if possible
    $html = preg_replace('/<p class="[^"]+">Korzyści:<\/p>/u', '<h3 class="text-lg font-semibold mb-2 text-gray-900">Korzyści:</h3>', $html);

    // Fix for "Specyfikacje:"
    $html = preg_replace('/<p class="[^"]+">Specyfikacje:(.*?)<\/p>/u', '<p class="mb-4 text-gray-700 leading-relaxed"><strong class="font-bold text-gray-900">Specyfikacje:</strong>$1</p>', $html);

    return $html;
}
