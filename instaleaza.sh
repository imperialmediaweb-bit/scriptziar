#!/bin/bash
# Instalează fix-ul pe TOATE site-urile WordPress de pe server.
# Rulează o singură dată prin SSH.
#
# Utilizare:
#   1. Copiază fix-poze-duplicate.php pe server
#   2. Rulează: bash instaleaza.sh
#
# Sau dintr-o singură comandă SSH:
#   scp fix-poze-duplicate.php user@server:/tmp/ && ssh user@server 'bash -s' < instaleaza.sh

COUNT=0

for wpconfig in $(find /home/*/domains/*/public_html -maxdepth 1 -name "wp-config.php" 2>/dev/null); do
    WP_DIR=$(dirname "$wpconfig")
    MU_DIR="$WP_DIR/wp-content/mu-plugins"
    SITE=$(echo "$WP_DIR" | grep -oP 'domains/\K[^/]+')

    mkdir -p "$MU_DIR"
    cp /tmp/fix-poze-duplicate.php "$MU_DIR/fix-poze-duplicate.php"
    COUNT=$((COUNT + 1))

    echo "  ✓ $SITE"
done

echo ""
echo "Gata! Instalat pe $COUNT site-uri."
