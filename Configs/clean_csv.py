import csv
import re

input_file = '/var/www/html/magento/app/code/GardenLawn/Core/Configs/AM_Desc.csv'
output_file = '/var/www/html/magento/app/code/GardenLawn/Core/Configs/AM_Desc_Cleaned.csv'

# Regexy do czyszczenia
# 1. Usuwanie iframe (wideo) - usuwamy cały tag iframe
iframe_pattern = re.compile(r'<iframe[^>]*>.*?<\/iframe>', re.IGNORECASE | re.DOTALL)

# 2. Usuwanie obrazków i linków do plików (zostaną zastąpione w nowym systemie)
# Uwaga: Użytkownik prosił o wrzucenie linków do nowego pliku, więc tutaj je usuwamy z treści,
# aby opis był czysty. Ale musimy uważać, żeby nie usunąć linków wewnętrznych do produktów, jeśli są ważne.
# Na razie usuwamy tagi <img> oraz linki do plików binarnych.
img_pattern = re.compile(r'<img[^>]*>', re.IGNORECASE)
# Usuwamy linki do plików (pdf, xlsx itp) - cały tag <a>...</a>
file_link_pattern = re.compile(r'<a[^>]*href=["\'][^"\']+\.(?:pdf|xlsx|xls|doc|docx)["\'][^>]*>.*?<\/a>', re.IGNORECASE | re.DOTALL)

# 3. Czyszczenie śmieci z ChatGPT / Worda
# Usuwamy kontenery div z klasami typu 'flex', 'text-message' itp.
# Najprościej: usunąć specyficzne divy otaczające, ale zostawić treść w środku?
# W tym przypadku, patrząc na plik, śmieci z ChatGPT są w blokach:
# <div class="flex flex-grow ..."> ... <div class="markdown prose ..."> TREŚĆ </div> ... </div>
# Spróbujemy usunąć te wrappery, zostawiając to co w środku, lub po prostu usunąć klasy.
# Jednak w przypadku "TODO" i śmieci AI, często cała struktura jest zbędna.
# Dla bezpieczeństwa usuniemy znane klasy śmieciowe.

classes_to_remove = [
    'flex', 'flex-grow', 'flex-col', 'max-w-full', 'min-h-\[20px\]', 'text-message',
    'items-start', 'whitespace-pre-wrap', 'break-words', '\[\.text-message\+&\]:mt-5',
    'juice:w-full', 'juice:items-end', 'overflow-x-auto', 'gap-2', 'juice:empty:hidden',
    'juice:first:pt-\[3px\]', 'markdown', 'prose', 'w-full', 'dark:prose-invert', 'light',
    'x_MsoNormal', 'x_MsoListParagraph', 'MsoListParagraph'
]
# Regex do usuwania atrybutów class="..." zawierających te śmieci
# To uproszczone podejście: usuwamy cały atrybut class jeśli zawiera śmieci
class_attr_pattern = re.compile(r'\sclass=["\'][^"\']*(' + '|'.join(classes_to_remove) + r')[^"\']*["\']', re.IGNORECASE)

# Usuwanie atrybutów data-olk... i innych śmieciowych atrybutów
attr_garbage_pattern = re.compile(r'\s(data-olk-copy-source|data-message-author-role|data-message-id|dir)=["\'][^"\']*["\']', re.IGNORECASE)

# Usuwanie pustych paragrafów i divów, które mogły zostać po czyszczeniu
empty_tag_pattern = re.compile(r'<(p|div|span)[^>]*>(\s|&nbsp;)*<\/\1>', re.IGNORECASE)

# 4. Poprawa tabel
# Zamiana width="677" lub style="width: 677px" na style="width: 100%"
table_width_pattern = re.compile(r'(width=["\'])\d+(["\'])', re.IGNORECASE)
style_width_pattern = re.compile(r'(width:\s*)\d+px', re.IGNORECASE)

def clean_html(html_content):
    if not html_content or html_content.strip() == '<p>TODO</p>':
        return html_content # Zostawiamy TODO jak jest, lub można zamienić na pusty string

    # Usuwanie wideo
    html_content = iframe_pattern.sub('', html_content)

    # Usuwanie obrazków (zakładamy, że są w extracted_files)
    # html_content = img_pattern.sub('', html_content) # Decyzja: czy usuwać obrazki z treści?
    # Użytkownik napisał: "jakiekolwiek linki do jakichkolwiek plikow wrzuc do nowego pliku".
    # Zazwyczaj obrazki w opisie są osadzone. Jeśli je usuniemy, opis będzie pusty wizualnie.
    # Ale użytkownik chce je pobrać i wrzucić na S3.
    # Zostawię tagi <img> ale podmienię ścieżkę na nową S3 placeholder?
    # NIE. Użytkownik napisał "pobiore je i wszystkie wrzuce do mojego aws s3".
    # Więc w pliku CSV powinniśmy podmienić stare linki na nowe linki S3.
    # Nowa ścieżka: https://pub.am-robots.pl/media/am-robots/NAZWA_PLIKU

    def replace_link(match):
        # Pobieramy cały tag
        tag = match.group(0)
        # Szukamy URL
        url_match = re.search(r'(href|src)=["\']([^"\']+)["\']', tag)
        if url_match:
            attr = url_match.group(1)
            old_url = url_match.group(2)
            filename = old_url.split('/')[-1]
            new_url = f"https://pub.am-robots.pl/media/am-robots/{filename}"
            return tag.replace(old_url, new_url)
        return tag

    # Podmiana linków w img i a (do plików)
    html_content = re.sub(r'<(img|a)[^>]+(?:src|href)=["\'][^"\']+\.(?:pdf|xlsx|xls|doc|docx|jpg|jpeg|png|webp|gif)["\'][^>]*>', replace_link, html_content, flags=re.IGNORECASE)

    # Usuwanie śmieciowych klas i atrybutów
    html_content = class_attr_pattern.sub('', html_content)
    html_content = attr_garbage_pattern.sub('', html_content)

    # Usuwanie kontenerów div z ChatGPT (te co zostały bez klas lub puste)
    # To trudne regexem. Skupmy się na usunięciu konkretnych divów jeśli są puste lub mają tylko style

    # Poprawa tabel
    html_content = table_width_pattern.sub(r'\1 100%\2', html_content)
    html_content = style_width_pattern.sub(r'\1 100%', html_content)

    # Usuwanie pustych tagów (wielokrotnie, bo mogą być zagnieżdżone)
    for _ in range(3):
        html_content = empty_tag_pattern.sub('', html_content)

    return html_content

try:
    with open(input_file, 'r', encoding='utf-8') as f_in, \
         open(output_file, 'w', encoding='utf-8', newline='') as f_out:

        reader = csv.DictReader(f_in, delimiter=';')
        fieldnames = reader.fieldnames

        writer = csv.DictWriter(f_out, fieldnames=fieldnames, delimiter=';', quoting=csv.QUOTE_ALL)
        writer.writeheader()

        for row in reader:
            row['short_description'] = clean_html(row.get('short_description', ''))
            row['description'] = clean_html(row.get('description', ''))
            writer.writerow(row)

    print("Cleaning complete.")

except Exception as e:
    print(f"Error: {e}")
