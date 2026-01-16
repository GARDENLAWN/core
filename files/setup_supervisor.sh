#!/bin/bash

# Sprawdź czy uruchomiono jako root
if [ "$EUID" -ne 0 ]; then
  echo "Uruchom ten skrypt jako root (sudo)"
  exit 1
fi

echo "--- Konfiguracja Supervisora dla Magento ---"

# 1. Instalacja (jeśli brak)
if ! command -v supervisord &> /dev/null; then
    echo "Instalowanie supervisor..."
    dnf install -y supervisor
fi

# 2. Backup starej konfiguracji
if [ -f /etc/supervisord.conf ] && [ ! -L /etc/supervisord.conf ]; then
    echo "Tworzenie kopii zapasowej /etc/supervisord.conf -> /etc/supervisord.conf.bak"
    mv /etc/supervisord.conf /etc/supervisord.conf.bak
fi

# 3. Linkowanie nowej konfiguracji
echo "Linkowanie konfiguracji..."
ln -sf /var/www/html/magento/app/code/GardenLawn/Core/files/supervisord.conf /etc/supervisord.conf

# 4. Tworzenie katalogu na logi
mkdir -p /var/log/supervisor

# 5. Restart usługi
echo "Restartowanie usługi supervisord..."
sudo systemctl enable supervisord
sudo systemctl restart supervisord

# 6. Weryfikacja
echo "Sprawdzanie statusu..."
sleep 2
if [ -S /var/run/supervisor.sock ]; then
    echo "Sukces! Supervisor działa."
    supervisorctl status
else
    echo "Błąd: Socket nie został utworzony. Sprawdź logi: journalctl -u supervisord"
    # Próba uruchomienia bezpośredniego jeśli systemctl zawiódł (np. w niektórych kontenerach)
    supervisord -c /etc/supervisord.conf
fi
