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

    # Logujemy zawsze do pliku
    echo "[$DATE] [MAIL SENT] Subject: $subject | Body: $body"

    # Próba wysyłki maila przez PHP (najbardziej niezawodne w środowisku Magento)
    if command -v php >/dev/null 2>&1; then
        php -r "
            \$to = '$ADMIN_EMAIL';
            \$subject = '$subject';
            \$message = '$body';
            \$headers = 'From: root@$HOSTNAME' . \"\r\n\" .
                       'Reply-To: root@$HOSTNAME' . \"\r\n\" .
                       'X-Mailer: PHP/' . phpversion();

            if(mail(\$to, \$subject, \$message, \$headers)) {
                echo 'Mail sent successfully.';
            } else {
                echo 'Mail sending failed. Check sendmail/postfix configuration.';
            }
        " >/dev/null 2>&1
    else
        echo "[$DATE] [ERROR] PHP not found, cannot send email."
    fi
}

check_and_restart() {
    local service="$1"
    systemctl is-active --quiet "$service"
    if [ $? -ne 0 ]; then
        echo "[$DATE] UWAGA: Usługa $service nie działa. Próba restartu..."
        systemctl restart "$service"
        echo "Czekam 30 sekund..."
        sleep 30

        systemctl is-active --quiet "$service"
        if [ $? -eq 0 ]; then
            send_email "[INFO] $HOSTNAME: $service zrestartowany" "Usługa $service nie działała ($DATE). System automatycznie ją zrestartował i teraz jest OK."
        else
            send_email "[ALERT] $HOSTNAME: $service LEZY!" "Usługa $service nie działa ($DATE). Próba automatycznego restartu NIE POWIODŁA SIĘ. Wymagana interwencja."
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
        send_email "[INFO] $HOSTNAME: Supervisord uruchomiony" "Supervisord został uruchomiony ponownie ($DATE)."
    else
        send_email "[ALERT] $HOSTNAME: Supervisord LEZY!" "Nie udalo sie uruchomic supervisord ($DATE)."
    fi
fi
