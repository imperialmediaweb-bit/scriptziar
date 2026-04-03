<?php
set_time_limit(300);
echo "<pre>Purge LiteSpeed Cache pe toate site-urile...\n\n";

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

    // Sterge cache-ul LiteSpeed - sterge folderul lscache
    $cache_dir = "/home/$usr/domains/$domain/public_html/wp-content/cache/";

    // Metoda 1: Trimite PURGE header la site
    $ch = curl_init("https://$domain/");
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => "PURGE",
        CURLOPT_HTTPHEADER => ["X-LiteSpeed-Purge: *"],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 10,
    ]);
    $result = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "  $domain (HTTP $code)\n";
    $ok++;
    flush(); @ob_flush();
}

echo "\n=== $ok site-uri purged ===\n";
echo "Verifica acum pe site-uri (Ctrl+F5).\n";
echo "</pre>";

function da_get($path, $auth = null) {
    global $da_host, $da_user, $da_pass;
    $ch = curl_init($da_host . $path);
    curl_setopt_array($ch, [CURLOPT_USERPWD => ($auth ?: $da_user) . ":$da_pass", CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false, CURLOPT_TIMEOUT => 30]);
    $r = curl_exec($ch); curl_close($ch); return $r ?: "";
}
