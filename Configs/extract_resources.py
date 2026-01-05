import csv
import re
import os

# Ścieżki
input_file = '/var/www/html/magento/app/code/GardenLawn/Core/Configs/AM_Desc.csv'
files_output = '/var/www/html/magento/app/code/GardenLawn/Core/Configs/extracted_files_to_download.txt'
videos_output = '/var/www/html/magento/app/code/GardenLawn/Core/Configs/extracted_videos.csv'

# Zbiory danych
unique_files = set()
video_entries = []

# Domeny do uzupełnienia linków relatywnych (zakładam na podstawie treści)
base_domain = "https://am-robots.com"

# Regexy
# Szukamy plików w href (np. PDF, XLSX) oraz obrazków w src
# Wykluczamy linki, które nie są plikami (np. linki do podstron kategorii, chyba że mają rozszerzenie)
file_pattern = re.compile(r'(?:href|src)=["\']([^"\']+\.(?:pdf|xlsx|xls|doc|docx|jpg|jpeg|png|webp|gif))["\']', re.IGNORECASE)

# Specyficzny regex dla wp-content/uploads, który może nie mieć standardowego rozszerzenia w linku lub być ukryty
wp_content_pattern = re.compile(r'(?:href|src)=["\']([^"\']*\/wp-content\/uploads\/[^"\']*)["\']', re.IGNORECASE)

# Regex dla wideo (YouTube w iframe)
# Obsługuje data-src-cmplz (Complianz) oraz zwykłe src
video_pattern = re.compile(r'(?:data-src-cmplz|src)=["\'](https?:\/\/(?:www\.)?(?:youtube\.com\/embed\/|youtu\.be\/)[^"\']+)["\']', re.IGNORECASE)

try:
    with open(input_file, 'r', encoding='utf-8') as f:
        # CSV używa średnika jako separatora
        reader = csv.DictReader(f, delimiter=';')

        for row in reader:
            sku = row.get('external_sku', 'UNKNOWN')
            # Łączymy opis krótki i długi do przeszukania
            content = (row.get('short_description', '') + " " + row.get('description', ''))

            # 1. Ekstrakcja Wideo
            videos = video_pattern.findall(content)
            for video in videos:
                # Czyścimy URL z ewentualnych parametrów ?feature=oembed jeśli są zbędne, ale tu zostawimy oryginał
                video_entries.append({'sku': sku, 'url': video})

            # 2. Ekstrakcja Plików
            # Najpierw ogólne pliki z rozszerzeniami
            files_ext = file_pattern.findall(content)
            for file_url in files_ext:
                unique_files.add(file_url)

            # Potem wszystko co jest w wp-content/uploads (często obrazki placeholderów wideo)
            wp_files = wp_content_pattern.findall(content)
            for file_url in wp_files:
                unique_files.add(file_url)

    # Zapisywanie listy plików
    with open(files_output, 'w', encoding='utf-8') as f:
        f.write("# Lista plików do pobrania. Linki relatywne wymagają dodania domeny (np. https://am-robots.com)\n")
        for url in sorted(unique_files):
            # Jeśli link jest relatywny (zaczyna się od /), dodajemy domenę dla ułatwienia
            full_url = url
            if url.startswith('/'):
                full_url = base_domain + url
            elif not url.startswith('http'):
                # Ignorujemy dziwne fragmenty, ale zapisujemy
                pass

            # Filtrowanie śmieci (np. about:blank)
            if 'about:blank' not in full_url:
                f.write(full_url + '\n')

    # Zapisywanie listy wideo
    with open(videos_output, 'w', encoding='utf-8', newline='') as f:
        writer = csv.writer(f, delimiter=';')
        writer.writerow(['external_sku', 'video_url'])
        for entry in video_entries:
            writer.writerow([entry['sku'], entry['url']])

    print("Extraction complete.")

except Exception as e:
    print(f"Error: {e}")
