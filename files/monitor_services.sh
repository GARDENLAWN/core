#!/bin/bash

# Konfiguracja
ADMIN_EMAIL="marcin.piechota@gardenlawn.pl"
SERVICES=("php-fpm" "mariadb" "nginx" "redis6" "opensearch" "varnish" "crond")
SUPERVISOR_CMD="/usr/local/bin/supervisord"
SUPERVISOR_CONF="/etc/supervisord.conf"

# Ustalanie ścieżek
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
# Zakładamy, że send_email.php jest w ../scripts/ względem tego pliku
PHP_MAILER_SCRIPT="$SCRIPT_DIR/../scripts/send_email.php"

HOSTNAME=$(hostname)
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

    # Budujemy pełny HTML
    local html_body="
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; }
            .container { max-width: 650px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
            .header { background: #2c3e50; color: #ffffff; padding: 20px; text-align: center; }
            .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
            .header p { margin: 5px 0 0; opacity: 0.8; font-size: 14px; }
            .content { padding: 20px; }
            .status-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            .footer { background: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #888; border-top: 1px solid #eee; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>$title</h1>
                <p>$HOSTNAME | $DATE</p>
            </div>
            <div class='content'>
                <p style='color: #555; margin-bottom: 20px;'>Poniżej znajduje się raport stanu usług systemowych:</p>
                <table class='status-table'>
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
            <div class='footer'>
                Automatyczny raport z serwera $HOSTNAME
            </div>
        </div>
    </body>
    </html>
    "

    # Logujemy wysyłkę
    echo "[$DATE] [MAIL SENT] Subject: $subject"

    if [ -f "$PHP_MAILER_SCRIPT" ]; then
        # Uruchamiamy skrypt PHP, przekazując argumenty
        # Uwaga: Przekazujemy HTML jako argument, więc musimy uważać na długość.
        # Dla bezpieczeństwa można by zapisać do pliku tymczasowego, ale spróbujmy tak.

        php "$PHP_MAILER_SCRIPT" "$ADMIN_EMAIL" "$subject" "$html_body"

        if [ $? -eq 0 ]; then
             echo "[$DATE] Mail sent successfully via Magento."
        else
             echo "[$DATE] [ERROR] Failed to send mail via Magento script."
        fi
    else
        echo "[$DATE] [ERROR] PHP Mailer script not found at: $PHP_MAILER_SCRIPT"
        # Fallback do starej metody mail()
        # ... (można dodać fallback, ale jeśli mail() nie działa, to bez sensu)
    fi
}

check_and_restart() {
    local service="$1"
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
            # W przypadku restartu wysyłamy maila natychmiast (ale z tabelą dotychczasowych sprawdzeń)
            send_email "[INFO] $HOSTNAME: $service zrestartowany" "Usługa została przywrócona"
        else
            add_html_row "$service" "FAILED" "Restart nieudany. Wymagana interwencja!"
            send_email "[ALERT] $HOSTNAME: $service LEZY!" "KRYTYCZNA AWARIA USŁUGI"
        fi
    else
        add_html_row "$service" "ACTIVE" "Działa poprawnie."
    fi
}

# 1. Sprawdzamy standardowe usługi systemd
for SERVICE in "${SERVICES[@]}"; do
    check_and_restart "$SERVICE"
done

# 2. Sprawdzamy Supervisora
if ! pgrep -x "supervisord" > /dev/null; then
    ALL_OK=0
    echo "[$DATE] UWAGA: Supervisord nie działa. Próba uruchomienia..."

    if systemctl list-unit-files | grep -q supervisord.service; then
        systemctl start supervisord
    else
        if [ -f "$SUPERVISOR_CMD" ]; then
            $SUPERVISOR_CMD -c $SUPERVISOR_CONF
        else
            supervisord -c $SUPERVISOR_CONF
        fi
    fi

    sleep 10

    if pgrep -x "supervisord" > /dev/null; then
        add_html_row "supervisord" "RESTARTED" "Proces uruchomiony ponownie."
        send_email "[INFO] $HOSTNAME: Supervisord uruchomiony" "Supervisord przywrócony"
    else
        add_html_row "supervisord" "FAILED" "Nie udało się uruchomić procesu."
        send_email "[ALERT] $HOSTNAME: Supervisord LEZY!" "AWARIA SUPERVISORD"
    fi
else
    add_html_row "supervisord" "ACTIVE" "Proces działa."
fi

# 3. Raport dzienny (tylko jeśli wszystko OK i flaga ustawiona)
if [ "$DAILY_REPORT" -eq 1 ] && [ "$ALL_OK" -eq 1 ]; then
    send_email "[OK] $HOSTNAME: Raport dzienny usług" "Wszystkie systemy sprawne"
fi
