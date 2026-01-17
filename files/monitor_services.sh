#!/bin/bash

# Konfiguracja
ADMIN_EMAIL="marcin.piechota@gardenlawn.pl"
# Dodano supervisor do listy usług systemd
SERVICES=("php-fpm" "mariadb" "nginx" "redis6" "opensearch" "varnish" "crond" "rabbitmq-server" "supervisor")

# Ustalanie ścieżek
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
PHP_MAILER_SCRIPT="$SCRIPT_DIR/../scripts/send_email.php"

# Bezpieczne ustalanie hostname
if command -v hostname &> /dev/null; then
    HOSTNAME=$(hostname)
elif [ -f /etc/hostname ]; then
    HOSTNAME=$(cat /etc/hostname)
else
    HOSTNAME="unknown-host"
fi

DATE=$(date '+%Y-%m-%d %H:%M:%S')
DAILY_REPORT=0
ALL_OK=1

# Zmienna do przechowywania wierszy tabeli HTML
HTML_ROWS=""

# Sprawdzamy argumenty
for arg in "$@"; do
    if [ "$arg" == "--daily-report" ]; then
        DAILY_REPORT=1
    fi
done

if [ "$EUID" -ne 0 ]; then
  echo "Ten skrypt musi byc uruchomiony jako root"
  exit 1
fi

# Funkcja pomocnicza do dodawania wiersza HTML
add_html_row() {
    local service_name="$1"
    local status="$2"
    local message="$3"
    local color=""
    local bg_color=""

    case "$status" in
        "ACTIVE")
            color="#155724"
            bg_color="#d4edda"
            ;;
        "RESTARTED")
            color="#856404"
            bg_color="#fff3cd"
            ;;
        "FAILED")
            color="#721c24"
            bg_color="#f8d7da"
            ;;
        "MISSING")
            color="#6c757d"
            bg_color="#e2e3e5"
            ;;
    esac

    HTML_ROWS+="<tr>
        <td style='padding: 12px 15px; border-bottom: 1px solid #eee; color: #333;'><strong>$service_name</strong></td>
        <td style='padding: 12px 15px; border-bottom: 1px solid #eee;'>
            <span style='background-color: $bg_color; color: $color; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold;'>$status</span>
        </td>
        <td style='padding: 12px 15px; border-bottom: 1px solid #eee; color: #666; font-size: 13px;'>$message</td>
    </tr>"
}

send_email() {
    local subject="$1"
    local title="$2"

    # Generujemy tylko treść (kontener), bez <html>/<body>, bo to doda send_email.php (z header/footer)
    local html_body="
        <div style='font-family: \"Segoe UI\", Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f6f9; padding: 20px;'>
            <div style='max-width: 650px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
                <div style='background: #2c3e50; color: #ffffff; padding: 20px; text-align: center;'>
                    <h1 style='margin: 0; font-size: 24px; font-weight: 600;'>$title</h1>
                    <p style='margin: 5px 0 0; opacity: 0.8; font-size: 14px;'>$HOSTNAME | $DATE</p>
                </div>
                <div style='padding: 20px;'>
                    <p style='color: #555; margin-bottom: 20px;'>Poniżej znajduje się raport stanu usług systemowych:</p>
                    <table style='width: 100%; border-collapse: collapse; margin-top: 10px;'>
                        <thead>
                            <tr style='background-color: #f8f9fa; text-align: left;'>
                                <th style='padding: 10px 15px; color: #555; font-weight: 600; border-bottom: 2px solid #eee;'>Usługa</th>
                                <th style='padding: 10px 15px; color: #555; font-weight: 600; border-bottom: 2px solid #eee;'>Status</th>
                                <th style='padding: 10px 15px; color: #555; font-weight: 600; border-bottom: 2px solid #eee;'>Info</th>
                            </tr>
                        </thead>
                        <tbody>
                            $HTML_ROWS
                        </tbody>
                    </table>
                </div>
                <div style='background: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #888; border-top: 1px solid #eee;'>
                    Automatyczny raport z serwera $HOSTNAME
                </div>
            </div>
        </div>
    "

    # Logujemy wysyłkę
    echo "[$DATE] [MAIL SENT] Subject: $subject"

    if [ -f "$PHP_MAILER_SCRIPT" ]; then
        php "$PHP_MAILER_SCRIPT" "$ADMIN_EMAIL" "$subject" "$html_body"

        if [ $? -eq 0 ]; then
             echo "[$DATE] Mail sent successfully via Magento."
        else
             echo "[$DATE] [ERROR] Failed to send mail via Magento script."
        fi
    else
        echo "[$DATE] [ERROR] PHP Mailer script not found at: $PHP_MAILER_SCRIPT"
    fi
}

check_and_restart() {
    local service="$1"
    # Sprawdzamy czy usługa w ogóle istnieje w systemie
    if systemctl list-unit-files | grep -q "^$service.service"; then
        systemctl is-active --quiet "$service"
        if [ $? -ne 0 ]; then
            ALL_OK=0
            echo "[$DATE] UWAGA: Usługa $service nie działa. Próba restartu..."
            systemctl restart "$service"
            echo "Czekam 30 sekund..."
            sleep 30

            systemctl is-active --quiet "$service"
            if [ $? -eq 0 ]; then
                add_html_row "$service" "RESTARTED" "Automatyczny restart udany."
                send_email "[INFO] $HOSTNAME: $service zrestartowany" "Usługa została przywrócona"
            else
                add_html_row "$service" "FAILED" "Restart nieudany. Wymagana interwencja!"
                send_email "[ALERT] $HOSTNAME: $service LEZY!" "KRYTYCZNA AWARIA USŁUGI"
            fi
        else
            add_html_row "$service" "ACTIVE" "Działa poprawnie."
        fi
    else
        # Usługa nie istnieje w systemie - logujemy jako MISSING w raporcie dziennym
        if [ "$DAILY_REPORT" -eq 1 ]; then
             add_html_row "$service" "MISSING" "Usługa nie zainstalowana w systemie."
        fi
    fi
}

# 1. Sprawdzamy standardowe usługi systemd
for SERVICE in "${SERVICES[@]}"; do
    check_and_restart "$SERVICE"
done

# 3. Raport dzienny (tylko jeśli wszystko OK i flaga ustawiona)
if [ "$DAILY_REPORT" -eq 1 ] && [ "$ALL_OK" -eq 1 ]; then
    send_email "[OK] $HOSTNAME: Raport dzienny usług" "Wszystkie systemy sprawne"
fi
