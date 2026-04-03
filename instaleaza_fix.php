<?php
/**
 * Instalează fix-ul pentru poze duplicate pe TOATE site-urile de pe server.
 * Uploadează pe UN SINGUR site și accesează în browser.
 */

$plugin = '<?php
/*
Plugin Name: Fix Poze Duplicate
Version: 4.0
*/
add_action("wp_head", function() {
    echo \'<style>
        img[data-eio-rwidth] + img[data-eio="l"],
        img.lazyautosizes + img[data-eio="l"],
        img.lazyloaded + img[data-eio="l"],
        img.lazyload + img:not(.lazyload):not(.lazyautosizes):not(.lazyloaded),
        img + img[data-eio="l"] {
            display: none !important;
        }
    </style>\';
});
';

echo "<pre>";
echo "=== Instalare Fix Poze Duplicate ===\n\n";

$count = 0;

// Cauta TOATE instalările WordPress pe server
$patterns = [
    '/home/*/domains/*/public_html/wp-config.php',
];

$configs = [];
foreach ($patterns as $p) {
    $found = glob($p);
    if ($found) $configs = array_merge($configs, $found);
}

if (empty($configs)) {
    echo "Nu am gasit site-uri WordPress.\n";
    echo "Cale curenta: " . __DIR__ . "\n";
} else {
    echo "Gasite " . count($configs) . " site-uri WordPress.\n\n";

    foreach ($configs as $wpconfig) {
        $wp_dir = dirname($wpconfig);
        $mu_dir = $wp_dir . '/wp-content/mu-plugins';

        if (preg_match('#domains/([^/]+)/#', $wp_dir, $m)) {
            $site = $m[1];
        } else {
            $site = basename($wp_dir);
        }

        if (!is_dir($mu_dir)) {
            @mkdir($mu_dir, 0755, true);
        }

        $result = @file_put_contents($mu_dir . '/fix-poze-duplicate.php', $plugin);

        if ($result !== false) {
            echo "  ✓ $site\n";
            $count++;
        } else {
            echo "  ✗ $site (nu am permisiuni)\n";
        }
    }
}

echo "\n=== Gata! $count site-uri fixate. ===\n";
echo "\n⚠ STERGE ACEST FISIER DIN FILE MANAGER!\n";
echo "</pre>";
