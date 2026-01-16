#!/bin/bash

# Konfiguracja
ADMIN_EMAIL="marcin.piechota@gardenlawn.pl"
SERVICES=("php-fpm" "mariadb" "nginx" "redis6" "opensearch" "varnish" "crond")
# Supervisora traktujemy osobno, bo może nie być usługą systemd
SUPERVISOR_CMD="/usr/local/bin/supervisord"
SUPERVISOR_CONF="/etc/supervisord.conf"

HOSTNAME=$(hostname)
DATE=$(date '+%Y-%m-%d %H:%M:%S')

if [ "$EUID" -ne 0 ]; then
  echo "Ten skrypt musi byc uruchomiony jako root"
  exit 1
fi

send_email() {
    local subject="$1"
    local body="$2"
    # Sprawdzamy czy sendmail istnieje, zeby uniknac bledu sh
    if [ -f /usr/sbin/sendmail ] || [ -f /usr/bin/msmtp ]; then
        php -r "mail('$ADMIN_EMAIL', '$subject', '$body', 'From: root@$HOSTNAME');"
    else
        echo "[$DATE] BRAK SENDMAILA! Nie moge wyslac powiadomienia: $subject"
    fi
}

check_and_restart() {
    local service="$1"
    systemctl is-active --quiet "$service"
    if [ $? -ne 0 ]; then
        echo "[$DATE] UWAGA: Usługa $service nie działa. Próba restartu..."
        systemctl restart "$service"
        echo "Czekam 15 sekund..."
        sleep 15

        systemctl is-active --quiet "$service"
        if [ $? -eq 0 ]; then
            send_email "[FIXED] $HOSTNAME: $service zrestartowany" "Usługa $service wstala."
        else
            send_email "[CRITICAL] $HOSTNAME: $service LEZY!" "Restart $service nieudany."
        fi
    fi
}

# 1. Sprawdzamy standardowe usługi systemd
for SERVICE in "${SERVICES[@]}"; do
    check_and_restart "$SERVICE"
done

# 2. Sprawdzamy Supervisora (specjalna obsługa)
# Sprawdzamy czy proces dziala
if ! pgrep -x "supervisord" > /dev/null; then
    echo "[$DATE] UWAGA: Supervisord nie działa. Próba uruchomienia..."

    # Proba przez systemd (jesli istnieje)
    if systemctl list-unit-files | grep -q supervisord.service; then
        systemctl start supervisord
    else
        # Reczne uruchomienie
        if [ -f "$SUPERVISOR_CMD" ]; then
            $SUPERVISOR_CMD -c $SUPERVISOR_CONF
        else
            # Fallback: sprobuj znalezc w sciezce
            supervisord -c $SUPERVISOR_CONF
        fi
    fi

    sleep 10

    if pgrep -x "supervisord" > /dev/null; then
        send_email "[FIXED] $HOSTNAME: Supervisord uruchomiony" "Supervisord został uruchomiony ponownie."
    else
        send_email "[CRITICAL] $HOSTNAME: Supervisord LEZY!" "Nie udalo sie uruchomic supervisord."
    fi
fi
