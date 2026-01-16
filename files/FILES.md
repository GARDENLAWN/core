# Dokumentacja Wdrożeniowa (GardenLawn Core)

Poniższe instrukcje zakładają, że moduł jest zainstalowany przez Composer w katalogu `vendor`.
**Wszystkie polecenia wykonuj z głównego katalogu Magento** (np. `/var/www/html/magento`).

```bash
cd /var/www/html/magento
```

## 1. crontab (Harmonogram Zadań)
*   **Opis:** Konfiguracja grup Crona (`default`, `index`, `gardenlawn`, `inpostpay`).
*   **Instalacja:**
    1. Wyświetl zawartość pliku źródłowego:
       ```bash
       cat vendor/gardenlawn/core/files/crontab
       ```
    2. Otwórz edytor crontaba dla użytkownika obsługującego Magento (np. `nginx` lub `ec2-user`):
       ```bash
       # Jeśli pliki należą do nginx:
       sudo EDITOR=nano crontab -u nginx -e
       
       # Jeśli pliki należą do obecnego użytkownika (np. ec2-user):
       EDITOR=nano crontab -e
       ```
    3. **Wklej** wyświetloną wcześniej zawartość na końcu pliku.

## 2. magento-consumer.conf (Supervisor / RabbitMQ)
*   **Opis:** Konfiguracja procesów w tle (Consumers).
*   **Instalacja:**
    ```bash
    # 1. Kopiowanie konfiguracji z vendora do systemu
    sudo cp vendor/gardenlawn/core/files/magento-consumer.conf /etc/supervisord.d/magento-consumer.ini
    
    # 2. Dostosowanie użytkownika (Wymagane!)
    # Sprawdź kto jest właścicielem plików Magento (ls -la var/log).
    
    # Jeśli właścicielem jest 'nginx':
    sudo sed -i 's/user=root/user=nginx/g' /etc/supervisord.d/magento-consumer.ini
    
    # Jeśli właścicielem jest 'ec2-user':
    sudo sed -i 's/user=root/user=ec2-user/g' /etc/supervisord.d/magento-consumer.ini

    # 3. Przeładowanie Supervisora
    sudo supervisorctl reread
    sudo supervisorctl update
    ```

## 3. php-min.ini (Optymalizacja PHP)
*   **Opis:** Kluczowe ustawienia PHP (Memory Limit 3G, Opcache, Realpath).
*   **Instalacja:**
    ```bash
    # Kopiowanie pliku konfiguracyjnego
    sudo cp vendor/gardenlawn/core/files/php-min.ini /etc/php.d/99-magento.ini
    
    # Restart PHP-FPM (aby zmiany weszły w życie)
    sudo systemctl restart php-fpm
    
    # Weryfikacja
    php -i | grep memory_limit
    ```

## 4. monitor_services.sh (Self-Healing / Monitoring)
*   **Opis:** Skrypt restartujący usługi (PHP, Nginx, Varnish, OpenSearch) w razie awarii.
*   **Instalacja:**
    ```bash
    # 1. Utwórz katalog i skopiuj skrypt
    sudo mkdir -p /root/scripts
    sudo cp vendor/gardenlawn/core/files/monitor_services.sh /root/scripts/
    
    # 2. Nadaj uprawnienia wykonywania
    sudo chmod +x /root/scripts/monitor_services.sh
    
    # 3. Dodaj do crontaba użytkownika ROOT (wymagane do restartu usług)
    sudo EDITOR=nano crontab -e
    
    # 4. Wklej poniższą linię na końcu pliku:
    # */15 * * * * /root/scripts/monitor_services.sh >> /var/log/monitor_services.log 2>&1
    ```

## 5. php.ini (Pełny config - Opcjonalnie)
*   **Opis:** Pełny plik konfiguracyjny PHP. Używać tylko w razie potrzeby zastąpienia całego `/etc/php.ini`.
*   **Lokalizacja:** `vendor/gardenlawn/core/files/php.ini`
