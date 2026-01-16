#!/bin/bash

# Konfiguracja
ADMIN_EMAIL="marcin.piechota@gardenlawn.pl" # Zmień na swój adres e-mail
SERVICES=("php-fpm" "mariadb" "nginx" "redis6" "opensearch" "varnish" "crond" "supervisord")

# Pobierz nazwę hosta dla jasności w mailu
HOSTNAME=$(hostname)
DATE=$(date '+%Y-%m-%d %H:%M:%S')

# Sprawdź czy skrypt jest uruchomiony jako root
if [ "$EUID" -ne 0 ]; then
  echo "Ten skrypt musi byc uruchomiony jako root"
  exit 1
fi

send_email() {
    local subject="$1"
    local body="$2"

    # Używamy PHP do wysłania maila (korzysta z konfiguracji sendmaila systemowego/PHP)
    php -r "mail('$ADMIN_EMAIL', '$subject', '$body', 'From: root@$HOSTNAME');"
}

for SERVICE in "${SERVICES[@]}"; do
    # Sprawdzamy status (is-active zwraca 0 jak dziala, inne jak nie dziala)
    systemctl is-active --quiet "$SERVICE"
    STATUS=$?

    if [ $STATUS -ne 0 ]; then
        # Usługa leży! Próbujemy wstania.
        echo "[$DATE] UWAGA: Usługa $SERVICE nie działa. Próba restartu..."

        systemctl restart "$SERVICE"

        # Czekamy na wstanie usługi (OpenSearch potrzebuje czasu)
        echo "Czekam 30 sekund na uruchomienie..."
        sleep 30

        # Sprawdzamy ponownie
        systemctl is-active --quiet "$SERVICE"
        RESTART_STATUS=$?

        if [ $RESTART_STATUS -eq 0 ]; then
            SUBJECT="[FIXED] $HOSTNAME: Usługa $SERVICE została zrestartowana"
            BODY="Usługa $SERVICE nie działała ($DATE). System automatycznie ją zrestartował i teraz jest OK."
        else
            SUBJECT="[CRITICAL] $HOSTNAME: AWARIA usługi $SERVICE - restart nieudany!"
            BODY="Usługa $SERVICE nie działa ($DATE). Próba automatycznego restartu NIE POWIODŁA SIĘ. Wymagana natychmiastowa interwencja."
        fi

        # Wysyłamy powiadomienie
        send_email "$SUBJECT" "$BODY"
    fi
done
