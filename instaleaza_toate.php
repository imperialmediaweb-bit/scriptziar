<?php
/**
 * Instalează fix-ul pe TOATE site-urile prin DirectAdmin API.
 * Rulează PE SERVER (localhost), nu de pe PC.
 *
 * 1. Uploadează pe un site prin File Manager
 * 2. Accesează în browser: https://site.ro/instaleaza_toate.php
 * 3. Gata! Șterge fișierul după.
 */

set_time_limit(300);

// Credențiale reseller DirectAdmin
$da_host = "https://localhost:2222";
$da_user = "sellsite";
$da_pass = "UiyeTD(5O54v[8";

$plugin_code = '<?php
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
echo "=== Instalare Fix Poze Duplicate pe TOATE site-urile ===\n\n";

// 1. Ia lista de useri
$users = da_get("CMD_API_SHOW_USERS", $da_host, $da_user, $da_pass);
if (empty($users)) {
    die("Nu am gasit useri. Verifica credentialele.\n");
}
echo "Gasiti " . count($users) . " useri.\n\n";

$ok = 0;
$fail = 0;

foreach ($users as $usr) {
    // 2. Ia domeniul principal al userului
    $config = da_get_raw("CMD_API_SHOW_USER_CONFIG&user=" . urlencode($usr), $da_host, $da_user, $da_pass);
    $domain = "";
    foreach (explode("&", $config) as $part) {
        if (strpos($part, "domain=") === 0) {
            $domain = urldecode(substr($part, 7));
            break;
        }
    }

    if (empty($domain)) continue;

    // 3. Scrie fisierul direct pe disk (de pe server avem acces la /home/user/...)
    $mu_dir = "/home/{$usr}/domains/{$domain}/public_html/wp-content/mu-plugins";
    $wp_config = "/home/{$usr}/domains/{$domain}/public_html/wp-config.php";

    // Verifica daca e WordPress
    if (!file_exists($wp_config)) {
        continue;
    }

    // Creaza mu-plugins
    if (!is_dir($mu_dir)) {
        @mkdir($mu_dir, 0755, true);
    }

    // Incearca sa scrie direct
    $written = @file_put_contents($mu_dir . "/fix-poze-duplicate.php", $plugin_code);

    if ($written !== false) {
        echo "  ✓ {$domain}\n";
        $ok++;
        continue;
    }

    // Daca nu merge direct, incearca prin DirectAdmin API (login-as)
    $boundary = "----" . md5(time());
    $file_content = "--{$boundary}\r\n";
    $file_content .= "Content-Disposition: form-data; name=\"action\"\r\n\r\nsave\r\n";
    $file_content .= "--{$boundary}\r\n";
    $file_content .= "Content-Disposition: form-data; name=\"path\"\r\n\r\n/domains/{$domain}/public_html/wp-content/mu-plugins/fix-poze-duplicate.php\r\n";
    $file_content .= "--{$boundary}\r\n";
    $file_content .= "Content-Disposition: form-data; name=\"text\"\r\n\r\n{$plugin_code}\r\n";
    $file_content .= "--{$boundary}--\r\n";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "{$da_host}/CMD_FILE_MANAGER",
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $file_content,
        CURLOPT_HTTPHEADER => ["Content-Type: multipart/form-data; boundary={$boundary}"],
        CURLOPT_USERPWD => "{$da_user}|{$usr}:{$da_pass}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    // Verifica daca a mers
    if (file_exists($mu_dir . "/fix-poze-duplicate.php")) {
        echo "  ✓ {$domain} (API)\n";
        $ok++;
    } else {
        // Ultima incercare: upload fisier
        $ch = curl_init();
        $post = [
            "action" => "upload",
            "path" => "/domains/{$domain}/public_html/wp-content/mu-plugins",
            "file" => new CURLFile(
                __DIR__ . "/wp-content/mu-plugins/fix-poze-duplicate.php",
                "application/x-php",
                "fix-poze-duplicate.php"
            ),
        ];

        // Creeaza fisier temporar pentru upload
        $tmp = tempnam(sys_get_temp_dir(), "fix");
        file_put_contents($tmp, $plugin_code);

        $post["file"] = new CURLFile($tmp, "application/x-php", "fix-poze-duplicate.php");

        curl_setopt_array($ch, [
            CURLOPT_URL => "{$da_host}/CMD_FILE_MANAGER",
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post,
            CURLOPT_USERPWD => "{$da_user}|{$usr}:{$da_pass}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 15,
        ]);
        curl_exec($ch);
        curl_close($ch);
        @unlink($tmp);

        if (file_exists($mu_dir . "/fix-poze-duplicate.php")) {
            echo "  ✓ {$domain} (upload)\n";
            $ok++;
        } else {
            echo "  ✗ {$domain}\n";
            $fail++;
        }
    }
    flush();
}

echo "\n=== Gata! {$ok} instalate, {$fail} erori. ===\n";
echo "\n⚠ STERGE ACEST FISIER DIN FILE MANAGER!\n";
echo "</pre>";


function da_get($cmd, $host, $user, $pass) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "{$host}/{$cmd}",
        CURLOPT_USERPWD => "{$user}:{$pass}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 30,
    ]);
    $result = curl_exec($ch);
    curl_close($ch);

    $items = [];
    foreach (explode("&", $result) as $part) {
        if (strpos($part, "=") !== false) {
            list($k, $v) = explode("=", $part, 2);
            if (strpos($k, "list") !== false) {
                $items[] = urldecode($v);
            }
        }
    }
    return $items;
}

function da_get_raw($cmd, $host, $user, $pass) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "{$host}/{$cmd}",
        CURLOPT_USERPWD => "{$user}:{$pass}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 30,
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}
