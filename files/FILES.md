# Dokumentacja Plików Infrastruktury (GardenLawn Core)

Ten katalog zawiera pliki konfiguracyjne serwera oraz skrypty niezbędne do optymalnego i stabilnego działania sklepu Magento 2.4.8.

## 1. crontab
*   **Opis:** Gotowa konfiguracja harmonogramu zadań (Cron) dla użytkownika systemowego (np. `nginx` lub `ec2-user`).
*   **Funkcja:** Uruchamia zadania cykliczne Magento z podziałem na grupy (`default`, `index`, `gardenlawn_*`, `inpostpay`), co zapobiega blokowaniu się zadań.
*   **Instalacja:**
    ```bash
    crontab -e
    # Wklej zawartość tego pliku
    ```

## 2. magento-consumer.conf
*   **Opis:** Plik konfiguracyjny dla narzędzia **Supervisor** (Supervisord).
*   **Funkcja:** Zarządza procesami działającymi w tle (Consumers), które obsługują kolejki RabbitMQ. Utrzymuje procesy przy życiu i restartuje je w razie awarii.
*   **Obsługiwane procesy:** Inventory (MSI), Export, Media Gallery (S3/Synchronizacja), Async Operations, Mass Actions.
*   **Instalacja:**
    ```bash
    sudo cp magento-consumer.conf /etc/supervisord.d/magento-consumer.ini
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
    4. Wysyła powiadomienie mailowe do administratora o sukcesie lub porażce restartu.
*   **Instalacja:**
    Dodać do crontaba użytkownika `root`:
    ```bash
    sudo crontab -e
    # */15 * * * * /ścieżka/do/monitor_services.sh
    ```
