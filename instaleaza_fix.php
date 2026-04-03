<?php
/**
 * Pune fix-ul pe site-ul CURENT.
 * Uploadează pe fiecare site prin File Manager și accesează în browser.
 * După: sterge acest fisier.
 */

$mu_dir = __DIR__ . '/wp-content/mu-plugins';
if (!is_dir($mu_dir)) {
    mkdir($mu_dir, 0755, true);
}

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

$result = file_put_contents($mu_dir . '/fix-poze-duplicate.php', $plugin);

echo "<pre>";
if ($result !== false) {
    echo "✓ Fix instalat pe: " . $_SERVER['HTTP_HOST'] . "\n";
    echo "Fisier: " . $mu_dir . "/fix-poze-duplicate.php\n";
    echo "Bytes: " . $result . "\n";
} else {
    echo "✗ EROARE: Nu am putut scrie fisierul!\n";
    echo "Director: " . $mu_dir . "\n";
    echo "Exista: " . (is_dir($mu_dir) ? "DA" : "NU") . "\n";
}
echo "\n⚠ STERGE ACEST FISIER (instaleaza_fix.php) DUPA INSTALARE!\n";
echo "</pre>";
