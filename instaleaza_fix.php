<?php
/**
 * Instalează fix-ul pentru poze duplicate pe TOATE site-urile WordPress de pe server.
 *
 * UTILIZARE:
 * 1. Uploadează acest fișier pe oricare site (ex: botosaniexpres.ro/instaleaza_fix.php)
 *    prin File Manager din DirectAdmin
 * 2. Accesează în browser: https://botosaniexpres.ro/instaleaza_fix.php
 * 3. Gata! Șterge fișierul după instalare.
 */

// Securitate: schimbă acest cod ca să nu ruleze oricine
$cod_secret = 'schimba-ma-123';

if (!isset($_GET['cod']) || $_GET['cod'] !== $cod_secret) {
    die('Adaugă ?cod=schimba-ma-123 în URL ca să rulezi instalarea.');
}

echo "<pre>";
echo "=== Instalare Fix Poze Duplicate ===\n\n";

// Conținutul pluginului
$plugin_content = '<?php
/**
 * Plugin Name: Fix Poze Duplicate
 * Description: Elimina automat pozele duplicate consecutive din articole
 * Version: 1.0
 */

add_filter("the_content", "fix_poze_duplicate", 20);

function fix_poze_duplicate($content) {
    // Elimina imaginile duplicate consecutive (aceeasi poza de 2 ori)
    $content = preg_replace_callback(
        \'#(<(?:p|figure|div)[^>]*>\s*<img\s[^>]*src=["\x27]([^"\x27]+)["\x27][^>]*>\s*</(?:p|figure|div)>)\s*(<(?:p|figure|div)[^>]*>\s*<img\s[^>]*src=["\x27]([^"\x27]+)["\x27][^>]*>\s*</(?:p|figure|div)>)#i\',
        function($matches) {
            if ($matches[2] === $matches[4]) {
                return $matches[1];
            }
            return $matches[0];
        },
        $content
    );

    // Acelasi lucru pentru img simplu fara wrapper
    $content = preg_replace_callback(
        \'#(<img\s[^>]*src=["\x27]([^"\x27]+)["\x27][^>]*>)\s*(<img\s[^>]*src=["\x27]([^"\x27]+)["\x27][^>]*>)#i\',
        function($matches) {
            if ($matches[2] === $matches[4]) {
                return $matches[1];
            }
            return $matches[0];
        },
        $content
    );

    return $content;
}
';

$count = 0;

// Caută toate instalările WordPress pe server
// DirectAdmin: /home/USER/domains/DOMAIN/public_html/
// Detectează automat calea pe baza locației acestui fișier
// Exemplu: dacă fișierul e în /domains/botosaniexpres.ro/public_html/
// atunci caută /domains/*/public_html/wp-config.php
$current_dir = __DIR__;
$base_path = '';

if (preg_match('#^(.*/domains)/[^/]+/public_html#', $current_dir, $m)) {
    $base_path = $m[1];
} elseif (preg_match('#^(/home/[^/]+)/domains/#', $current_dir, $m)) {
    $base_path = $m[1] . '/domains';
}

echo "Cale detectată: $base_path\n\n";

$configs = [];
$patterns = [
    "$base_path/*/public_html/wp-config.php",
    '/home/*/domains/*/public_html/wp-config.php',
    '/domains/*/public_html/wp-config.php',
    '/home/*/public_html/wp-config.php',
];

foreach ($patterns as $pattern) {
    $found = glob($pattern) ?: [];
    if (!empty($found)) {
        $configs = $found;
        echo "Pattern folosit: $pattern\n";
        echo "Site-uri găsite: " . count($configs) . "\n\n";
        break;
    }
}

if (empty($configs)) {
    echo "Nu am găsit instalări WordPress!\n";
    echo "Calea serverului tău poate fi diferită.\n";
    echo "Calea curentă a acestui fișier: " . __DIR__ . "\n";
} else {
    foreach ($configs as $wpconfig) {
        $wp_dir = dirname($wpconfig);
        $mu_dir = $wp_dir . '/wp-content/mu-plugins';

        // Extrage numele domeniului
        if (preg_match('#domains/([^/]+)/#', $wp_dir, $m)) {
            $site = $m[1];
        } else {
            $site = basename(dirname($wp_dir));
        }

        // Creează mu-plugins dacă nu există
        if (!is_dir($mu_dir)) {
            mkdir($mu_dir, 0755, true);
        }

        // Scrie pluginul
        $result = file_put_contents($mu_dir . '/fix-poze-duplicate.php', $plugin_content);

        if ($result !== false) {
            echo "  ✓ $site\n";
            $count++;
        } else {
            echo "  ✗ $site (nu am putut scrie - verifică permisiunile)\n";
        }
    }
}

echo "\n=== Gata! Fix instalat pe $count site-uri. ===\n";
echo "\n⚠ IMPORTANT: Șterge acest fișier (instaleaza_fix.php) din File Manager!\n";
echo "</pre>";
