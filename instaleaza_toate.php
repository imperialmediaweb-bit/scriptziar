<?php
set_time_limit(600);

$da_host = "https://localhost:2222";
$da_user = "sellsite";
$da_pass = "UiyeTD(5O54v[8";

$plugin_code = '<?php
add_action("wp_head", function() {
    echo \'<style>img[data-eio-rwidth]+img[data-eio="l"],img.lazyautosizes+img[data-eio="l"],img.lazyloaded+img[data-eio="l"],img+img[data-eio="l"]{display:none!important}</style>\';
});';

echo "<pre>";
echo "=== Instalare Fix Poze Duplicate ===\n\n";

// Ia userii
$r = da_get("/CMD_API_SHOW_USERS");
preg_match_all('/list\[\]=([^&]+)/', $r, $m);
$users = array_map('urldecode', $m[1]);
echo count($users) . " useri.\n\n";

$ok = 0;
$fail = 0;

foreach ($users as $usr) {
    // Ia domeniul
    $cfg = da_get("/CMD_API_SHOW_USER_CONFIG?user=" . urlencode($usr));
    $domain = "";
    if (preg_match('/(?:^|&)domain=([^&]+)/', $cfg, $dm)) {
        $domain = urldecode($dm[1]);
    }
    if (empty($domain)) {
        echo "  - {$usr}: niciun domeniu\n";
        continue;
    }

    // Creeaza cron job care scrie fisierul (ruleaza ca userul respectiv)
    $mu = "/home/{$usr}/domains/{$domain}/public_html/wp-content/mu-plugins";
    $esc = addcslashes($plugin_code, "'\\");
    $cmd = "mkdir -p {$mu} && printf '%s' '{$esc}' > {$mu}/fix-poze-duplicate.php";

    $result = da_post("/CMD_API_CRON_JOBS", [
        "action" => "create",
        "minute" => rand(0,59),
        "hour" => rand(0,23),
        "dayofmonth" => "*",
        "month" => "*",
        "dayofweek" => "*",
        "command" => $cmd,
    ], "{$da_user}|{$usr}");

    if (strpos($result, "error=0") !== false || strpos($result, "error") === false) {
        echo "  ✓ {$domain} (cron)\n";
        $ok++;
    } else {
        echo "  ✗ {$domain}: " . substr($result, 0, 100) . "\n";
        $fail++;
    }
    flush();
    ob_flush();
}

echo "\n=== {$ok} cron jobs create, {$fail} erori ===\n";
echo "\nCron-urile vor rula automat si vor instala fix-ul.\n";
echo "Dupa cateva ore, acceseaza linkul de mai jos pentru a sterge cron-urile:\n";
echo "<a href='?cleanup=1'>STERGE CRON-URILE (dupa ce fix-ul e instalat)</a>\n";
echo "\n⚠ STERGE SI ACEST FISIER DIN FILE MANAGER!\n";
echo "</pre>";

// Cleanup
if (isset($_GET['cleanup'])) {
    echo "<pre>\n=== Stergere cron-uri ===\n\n";
    $r = da_get("/CMD_API_SHOW_USERS");
    preg_match_all('/list\[\]=([^&]+)/', $r, $m);
    foreach ($m[1] as $usr) {
        $usr = urldecode($usr);
        $crons = da_get("/CMD_API_CRON_JOBS", "{$da_user}|{$usr}");
        // Gaseste ID-urile cron-urilor cu mu-plugins
        preg_match_all('/(\d+)=/', $crons, $ids);
        $lines = explode("&", $crons);
        foreach ($lines as $line) {
            if (strpos($line, "mu-plugins") !== false && preg_match('/^(\d+)=/', $line, $id)) {
                da_post("/CMD_API_CRON_JOBS", [
                    "action" => "delete",
                    "select0" => $id[1],
                ], "{$da_user}|{$usr}");
                echo "  {$usr}: sters cron #{$id[1]}\n";
            }
        }
        flush();
    }
    echo "\nGata!\n</pre>";
    exit;
}

function da_get($path, $auth_user = null) {
    global $da_host, $da_user, $da_pass;
    $ch = curl_init($da_host . $path);
    curl_setopt_array($ch, [
        CURLOPT_USERPWD => ($auth_user ?: $da_user) . ":" . $da_pass,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 30,
    ]);
    $r = curl_exec($ch);
    curl_close($ch);
    return $r ?: "";
}

function da_post($path, $data, $auth_user = null) {
    global $da_host, $da_user, $da_pass;
    $ch = curl_init($da_host . $path);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_USERPWD => ($auth_user ?: $da_user) . ":" . $da_pass,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 30,
    ]);
    $r = curl_exec($ch);
    curl_close($ch);
    return $r ?: "";
}
