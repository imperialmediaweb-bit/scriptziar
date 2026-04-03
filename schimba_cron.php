<?php
set_time_limit(300);
echo "<pre>Sterg cron-urile vechi si creez altele noi care ruleaza la 18:40...\n\n";

$da_host = "https://localhost:2222";
$da_user = "sellsite";
$da_pass = "UiyeTD(5O54v[8";

$r = da_get("/CMD_API_SHOW_USERS");
preg_match_all('/list\[\]=([^&]+)/', $r, $m);
$users = array_map('urldecode', $m[1]);

$ok = 0;
foreach ($users as $usr) {
    $cfg = da_get("/CMD_API_SHOW_USER_CONFIG?user=" . urlencode($usr));
    $domain = "";
    if (preg_match('/(?:^|&)domain=([^&]+)/', $cfg, $dm)) {
        $domain = urldecode($dm[1]);
    }
    if (empty($domain)) continue;

    // Sterge cron-urile vechi
    $crons = da_get("/CMD_API_CRON_JOBS", "$da_user|$usr");
    if (preg_match_all('/(\d+)=[^&]*mu-plugins/', $crons, $ids)) {
        foreach ($ids[1] as $id) {
            da_post("/CMD_API_CRON_JOBS", ["action" => "delete", "select0" => $id], "$da_user|$usr");
        }
    }

    // Creeaza cron nou la 18:40
    $mu = "/home/$usr/domains/$domain/public_html/wp-content/mu-plugins";
    $file = "$mu/fix-poze-duplicate.php";
    $b64 = base64_encode('<?php add_action("wp_head", function() { echo "<style>img+img[data-eio]{display:none!important}</style>"; });');
    $cmd = "php -r \"@mkdir('$mu',0755,true);file_put_contents('$file',base64_decode('$b64'));\"";
    $cmd = str_replace(["\r\n","\r","\n"], '', $cmd);

    $post = "action=create&minute=40&hour=18&dayofmonth=*&month=*&dayofweek=*&command=" . rawurlencode($cmd);
    $ch = curl_init("$da_host/CMD_API_CRON_JOBS");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post,
        CURLOPT_USERPWD => "$da_user|$usr:$da_pass",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 15,
    ]);
    $result = curl_exec($ch);
    curl_close($ch);

    if (strpos($result, "error=0") !== false) {
        echo "  ✓ $domain\n";
        $ok++;
    } else {
        echo "  ✗ $domain\n";
    }
    flush(); @ob_flush();
}

echo "\n=== $ok cron-uri setate la 18:40 ===\n";
echo "\nAsteapta pana la 18:41, apoi verifica pe un site.\n";
echo "Dupa, deschide: instaleaza_toate.php?cleanup=1 pentru a sterge cron-urile.\n";
echo "</pre>";

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
