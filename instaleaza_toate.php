<?php
set_time_limit(600);

$da_host = "https://localhost:2222";
$da_user = "sellsite";
$da_pass = "UiyeTD(5O54v[8";

echo "<pre>";
echo "=== Instalare Fix Poze Duplicate ===\n\n";

$r = da_get("/CMD_API_SHOW_USERS");
preg_match_all('/list\[\]=([^&]+)/', $r, $m);
$users = array_map('urldecode', $m[1]);
echo count($users) . " useri.\n\n";

$ok = 0;
$fail = 0;
$first = true;

foreach ($users as $usr) {
    $cfg = da_get("/CMD_API_SHOW_USER_CONFIG?user=" . urlencode($usr));
    $domain = "";
    if (preg_match('/(?:^|&)domain=([^&]+)/', $cfg, $dm)) {
        $domain = urldecode($dm[1]);
    }
    if (empty($domain)) continue;

    $mu = "/home/$usr/domains/$domain/public_html/wp-content/mu-plugins";
    $file = "$mu/fix-poze-duplicate.php";
    $b64 = base64_encode('<?php add_action("wp_head", function() { echo "<style>img+img[data-eio]{display:none!important}</style>"; });');
    $cmd = "php -r \"@mkdir('$mu',0755,true);file_put_contents('$file',base64_decode('$b64'));\"";

    // Elimina ORICE newline din comanda
    $cmd = str_replace(["\r\n", "\r", "\n"], '', $cmd);

    // Debug: arata prima comanda
    if ($first) {
        echo "DEBUG comanda (primele 200 car):\n";
        echo substr($cmd, 0, 200) . "\n";
        echo "Lungime: " . strlen($cmd) . "\n";
        echo "Are newline: " . (strpos($cmd, "\n") !== false ? "DA" : "NU") . "\n\n";
        $first = false;
    }

    // Construieste POST data manual, fara http_build_query
    $post_data = "action=create&minute=0&hour=0&dayofmonth=*&month=*&dayofweek=*&command=" . rawurlencode($cmd);

    $ch = curl_init($da_host . "/CMD_API_CRON_JOBS");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post_data,
        CURLOPT_USERPWD => "$da_user|$usr:$da_pass",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 30,
    ]);
    $result = curl_exec($ch);
    curl_close($ch);

    if (strpos($result, "error=0") !== false) {
        echo "  ✓ $domain\n";
        $ok++;
    } else {
        echo "  ✗ $domain: " . substr(urldecode($result), 0, 80) . "\n";
        $fail++;
    }
    flush();
    @ob_flush();
}

echo "\n=== $ok ok, $fail erori ===\n";
echo "<a href='?cleanup=1'>STERGE CRON-URILE</a>\n";
echo "</pre>";

if (isset($_GET['cleanup'])) {
    echo "<pre>=== Stergere ===\n";
    $r = da_get("/CMD_API_SHOW_USERS");
    preg_match_all('/list\[\]=([^&]+)/', $r, $m);
    foreach ($m[1] as $usr) {
        $usr = urldecode($usr);
        $crons = da_get("/CMD_API_CRON_JOBS", "$da_user|$usr");
        if (preg_match_all('/(\d+)=[^&]*mu-plugins/', $crons, $ids)) {
            foreach ($ids[1] as $id) {
                da_post("/CMD_API_CRON_JOBS", ["action" => "delete", "select0" => $id], "$da_user|$usr");
            }
        }
    }
    echo "Gata!\n</pre>";
    exit;
}

function da_get($path, $auth = null) {
    global $da_host, $da_user, $da_pass;
    $ch = curl_init($da_host . $path);
    curl_setopt_array($ch, [CURLOPT_USERPWD => ($auth ?: $da_user) . ":$da_pass", CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false, CURLOPT_TIMEOUT => 30]);
    $r = curl_exec($ch); curl_close($ch); return $r ?: "";
}
function da_post($path, $data, $auth = null) {
    global $da_host, $da_user, $da_pass;
    $ch = curl_init($da_host . $path);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($data), CURLOPT_USERPWD => ($auth ?: $da_user) . ":$da_pass", CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false, CURLOPT_TIMEOUT => 30]);
    $r = curl_exec($ch); curl_close($ch); return $r ?: "";
}
