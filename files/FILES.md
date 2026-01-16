# Dokumentacja Plików Infrastruktury (GardenLawn Core)

Ten katalog zawiera pliki konfiguracyjne serwera oraz skrypty niezbędne do optymalnego i stabilnego działania sklepu Magento 2.4.8.

## 1. crontab
*   **Opis:** Gotowa konfiguracja harmonogramu zadań (Cron) dla użytkownika systemowego (np. `nginx` lub `ec2-user`).
*   **Funkcja:** Uruchamia zadania cykliczne Magento z podziałem na grupy (`default`, `index`, `gardenlawn_*`, `inpostpay`), co zapobiega blokowaniu się zadań.
*   **Instalacja:**
    ```bash
    # Edycja crontaba użytkownika obsługującego Magento (np. ec2-user lub nginx)
    # Używamy edytora nano dla wygody
    sudo EDITOR=nano crontab -u nginx -e   # Jeśli pliki należą do nginx
    # LUB
    EDITOR=nano crontab -e                 # Jeśli pliki należą do obecnego użytkownika (np. ec2-user)
    
    # Wklej zawartość pliku 'crontab' na końcu
    ```

## 2. magento-consumer.conf
*   **Opis:** Plik konfiguracyjny dla narzędzia **Supervisor** (Supervisord).
*   **Funkcja:** Zarządza procesami działającymi w tle (Consumers), które obsługują kolejki RabbitMQ. Utrzymuje procesy przy życiu i restartuje je w razie awarii.
*   **Obsługiwane procesy:** Inventory (MSI), Export, Media Gallery (S3/Synchronizacja), Async Operations, Mass Actions, InPost Pay.
*   **Instalacja:**
    ```bash
    # Kopiowanie konfiguracji
    sudo cp magento-consumer.conf /etc/supervisord.d/magento-consumer.ini
    
    # Dostosowanie użytkownika (jeśli pliki należą do nginx lub ec2-user)
    # Domyślnie w pliku jest 'root'. Zmień na właściwego:
    sudo sed -i 's/user=root/user=nginx/g' /etc/supervisord.d/magento-consumer.ini
    # LUB
    sudo sed -i 's/user=root/user=ec2-user/g' /etc/supervisord.d/magento-consumer.ini

    # Przeładowanie Supervisora
    sudo supervisorctl reread
    sudo supervisorctl update
    ```

## 3. php-min.ini (Zalecany)
*   **Opis:** "Lekki" plik konfiguracyjny PHP zawierający tylko kluczowe optymalizacje dla Magento (tzw. delta). Nie zawiera domyślnych ustawień systemowych, tylko nadpisuje te wymagane.
*   **Kluczowe zmiany:**
    *   `memory_limit = 3G` (Bezpieczny limit dla serwera 8GB RAM)
    *   `opcache` (Włączony i skonfigurowany pod >60k plików)
    *   `realpath_cache` (Zwiększony do 10M)
    *   `max_input_vars = 10000`
*   **Instalacja:**
    ```bash
    sudo cp php-min.ini /etc/php.d/99-magento.ini
    sudo systemctl restart php-fpm
    ```

## 4. php.ini
*   **Opis:** Pełny, kompletny plik konfiguracyjny PHP.
*   **Funkcja:** Zawiera całą konfigurację PHP wraz z optymalizacjami. Może służyć jako referencja lub do całkowitego zastąpienia systemowego pliku `/etc/php.ini`.
*   **Instalacja:** Używać tylko w specyficznych przypadkach, gdy `php-min.ini` nie wystarcza.

## 5. monitor_services.sh
*   **Opis:** Skrypt Bash do automatycznego monitorowania i naprawy usług systemowych (Self-Healing).
*   **Funkcja:**
    1. Sprawdza status usług: `php-fpm`, `mariadb`, `nginx`, `redis6`, `opensearch`, `varnish`, `crond`, `supervisord`.
    2. W razie awarii próbuje zrestartować usługę.
    3. Czeka 30 sekund na pełne uruchomienie (ważne dla OpenSearch).
    4. Wysyła powiadomienie mailowe do administratora o sukcesie lub porażce restartu (używając PHP mail).
*   **Instalacja:**
    ```bash
    # 1. Skopiuj skrypt w bezpieczne miejsce
    sudo mkdir -p /root/scripts
    sudo cp monitor_services.sh /root/scripts/
    sudo chmod +x /root/scripts/monitor_services.sh
    
    # 2. Dodaj do crontaba użytkownika ROOT (wymagane do restartu usług)
    sudo EDITOR=nano crontab -e
    
    # 3. Wklej linię uruchamiającą co 15 minut:
    # */15 * * * * /root/scripts/monitor_services.sh >> /var/log/monitor_services.log 2>&1
    ```
