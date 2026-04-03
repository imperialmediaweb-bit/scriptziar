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

foreach ($users as $usr) {
    $cfg = da_get("/CMD_API_SHOW_USER_CONFIG?user=" . urlencode($usr));
    $domain = "";
    if (preg_match('/(?:^|&)domain=([^&]+)/', $cfg, $dm)) {
        $domain = urldecode($dm[1]);
    }
    if (empty($domain)) continue;

    $mu = "/home/{$usr}/domains/{$domain}/public_html/wp-content/mu-plugins";
    $file = "{$mu}/fix-poze-duplicate.php";

    // Comanda pe O SINGURA linie, fara newline, fara caractere speciale
    $cmd = "php -r \"@mkdir('{$mu}',0755,true);file_put_contents('{$file}','<?php add_action(chr(34).chr(119).chr(112).chr(95).chr(104).chr(101).chr(97).chr(100).chr(34),function(){echo chr(60).chr(115).chr(116).chr(121).chr(108).chr(101).chr(62).chr(105).chr(109).chr(103).chr(43).chr(105).chr(109).chr(103).chr(91).chr(100).chr(97).chr(116).chr(97).chr(45).chr(101).chr(105).chr(111).chr(61).chr(34).chr(108).chr(34).chr(93).chr(123).chr(100).chr(105).chr(115).chr(112).chr(108).chr(97).chr(121).chr(58).chr(110).chr(111).chr(110).chr(101).chr(33).chr(105).chr(109).chr(112).chr(111).chr(114).chr(116).chr(97).chr(110).chr(116).chr(125).chr(60).chr(47).chr(115).chr(116).chr(121).chr(108).chr(101).chr(62);});');\"";

    // Asta e prea complicat. Hai mai simplu: cron ruleaza php care creeaza fisierul
    // Folosim base64 ca sa evitam probleme cu escape
    $php_content = '<?php add_action("wp_head", function() { echo "<style>img+img[data-eio]{display:none!important}</style>"; });';
    $b64 = base64_encode($php_content);
    $cmd = "php -r \"@mkdir('{$mu}',0755,true);file_put_contents('{$file}',base64_decode('{$b64}'));\"";

    $result = da_post("/CMD_API_CRON_JOBS", [
        "action" => "create",
        "minute" => "0",
        "hour" => "0",
        "dayofmonth" => "1",
        "month" => "1",
        "dayofweek" => "*",
        "command" => $cmd,
    ], "{$da_user}|{$usr}");

    if (strpos($result, "error=0") !== false) {
        echo "  ✓ {$domain}\n";
        $ok++;
    } else {
        // Decoded error
        $err = urldecode($result);
        echo "  ✗ {$domain}: " . substr($err, 0, 80) . "\n";
        $fail++;
    }
    flush();
    ob_flush();
}

echo "\n=== {$ok} cron jobs create, {$fail} erori ===\n";
if ($ok > 0) {
    echo "\nAcum apasa linkul de mai jos pentru a rula cron-urile ACUM:\n";
    echo "<a href='?run=1'>▶ RULEAZA ACUM</a>\n";
}
echo "\nDupa instalare:\n";
echo "<a href='?cleanup=1'>STERGE CRON-URILE</a>\n";
echo "\n⚠ STERGE SI ACEST FISIER!\n";
echo "</pre>";

// Ruleaza cron-urile acum
if (isset($_GET['run'])) {
    echo "<pre>=== Rulare cron-uri ===\n\n";
    $r = da_get("/CMD_API_SHOW_USERS");
    preg_match_all('/list\[\]=([^&]+)/', $r, $m);
    foreach ($m[1] as $usr) {
        $usr = urldecode($usr);
        da_post("/CMD_API_CRON_JOBS", ["action" => "force"], "{$da_user}|{$usr}");
        echo "  {$usr}: fortat\n";
        flush();
    }
    echo "\nGata! Verifica acum pe site-uri.\n";
    echo "<a href='?cleanup=1'>STERGE CRON-URILE</a>\n</pre>";
    exit;
}

// Cleanup
if (isset($_GET['cleanup'])) {
    echo "<pre>=== Stergere cron-uri ===\n\n";
    $r = da_get("/CMD_API_SHOW_USERS");
    preg_match_all('/list\[\]=([^&]+)/', $r, $m);
    foreach ($m[1] as $usr) {
        $usr = urldecode($usr);
        $crons = da_get("/CMD_API_CRON_JOBS", "{$da_user}|{$usr}");
        if (preg_match_all('/(\d+)=[^&]*mu-plugins/', $crons, $ids)) {
            foreach ($ids[1] as $id) {
                da_post("/CMD_API_CRON_JOBS", ["action" => "delete", "select0" => $id], "{$da_user}|{$usr}");
                echo "  {$usr}: sters #{$id}\n";
            }
        }
        flush();
    }
    echo "\nGata!\n</pre>";
    exit;
}

function da_get($path, $auth = null) {
    global $da_host, $da_user, $da_pass;
    $ch = curl_init($da_host . $path);
    curl_setopt_array($ch, [CURLOPT_USERPWD => ($auth ?: $da_user) . ":" . $da_pass, CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false, CURLOPT_TIMEOUT => 30]);
    $r = curl_exec($ch); curl_close($ch); return $r ?: "";
}
function da_post($path, $data, $auth = null) {
    global $da_host, $da_user, $da_pass;
    $ch = curl_init($da_host . $path);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($data), CURLOPT_USERPWD => ($auth ?: $da_user) . ":" . $da_pass, CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false, CURLOPT_TIMEOUT => 30]);
    $r = curl_exec($ch); curl_close($ch); return $r ?: "";
}
