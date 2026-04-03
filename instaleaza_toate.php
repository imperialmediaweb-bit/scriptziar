<?php
set_time_limit(600);

$da_host = "https://localhost:2222";
$da_user = "sellsite";
$da_pass = "UiyeTD(5O54v[8";

$plugin_code = '<?php
add_action("wp_head", function() {
    echo \'<style>img[data-eio-rwidth]+img[data-eio="l"],img.lazyautosizes+img[data-eio="l"],img.lazyloaded+img[data-eio="l"],img+img[data-eio="l"]{display:none!important}</style>\';
});';

// Escape pentru shell
$plugin_escaped = str_replace("'", "'\\''", $plugin_code);

echo "<pre>";
echo "=== Instalare Fix Poze Duplicate ===\n\n";

// Ia userii
$users = da_request("GET", "/CMD_API_SHOW_USERS", [], $da_host, $da_user, $da_pass);
$user_list = [];
foreach (explode("&", $users) as $part) {
    if (strpos($part, "=") !== false) {
        $val = urldecode(explode("=", $part, 2)[1]);
        if (!empty($val)) $user_list[] = $val;
    }
}

echo count($user_list) . " useri gasiti.\n\n";

$ok = 0;
$fail = 0;

foreach ($user_list as $usr) {
    // Ia domeniul
    $config = da_request("GET", "/CMD_API_SHOW_USER_CONFIG?user=" . urlencode($usr), [], $da_host, $da_user, $da_pass);
    $domain = "";
    foreach (explode("&", $config) as $part) {
        if (strpos($part, "domain=") === 0) {
            $domain = urldecode(substr($part, 7));
            break;
        }
    }
    if (empty($domain)) continue;

    $mu_path = "/home/{$usr}/domains/{$domain}/public_html/wp-content/mu-plugins";
    $file_path = "{$mu_path}/fix-poze-duplicate.php";

    // Creeaza un cron job care scrie fisierul (ruleaza ca userul respectiv)
    $cmd = "mkdir -p '{$mu_path}' && echo '{$plugin_escaped}' > '{$file_path}' && chmod 644 '{$file_path}'";

    // Adauga cron job
    $result = da_request("POST", "/CMD_API_CRON_JOBS", [
        "action" => "create",
        "minute" => "0",
        "hour" => "0",
        "dayofmonth" => "*",
        "month" => "*",
        "dayofweek" => "*",
        "command" => $cmd,
    ], $da_host, "{$da_user}|{$usr}", $da_pass);

    if (strpos($result, "error") === false || strpos($result, "error=0") !== false) {
        echo "  ✓ {$domain} (cron creat)\n";
        $ok++;
    } else {
        echo "  ✗ {$domain}: {$result}\n";
        $fail++;
    }
    flush();
}

echo "\n=== {$ok} cron jobs create. ===\n";
echo "\nCron-urile vor rula la miezul noptii si vor instala fix-ul.\n";
echo "Dupa ce se instaleaza, ruleaza CURATA pentru a sterge cron-urile.\n";
echo "\n<a href='?action=run_now'>▶ RULEAZA ACUM (nu astepta miezul noptii)</a>\n";
echo "<a href='?action=cleanup'>🗑 CURATA cron-urile dupa instalare</a>\n";
echo "</pre>";

// Ruleaza cron-urile imediat
if (isset($_GET['action']) && $_GET['action'] === 'run_now') {
    echo "<pre>\n=== Rulare imediata ===\n\n";
    foreach ($user_list as $usr) {
        $config = da_request("GET", "/CMD_API_SHOW_USER_CONFIG?user=" . urlencode($usr), [], $da_host, $da_user, $da_pass);
        $domain = "";
        foreach (explode("&", $config) as $part) {
            if (strpos($part, "domain=") === 0) {
                $domain = urldecode(substr($part, 7));
                break;
            }
        }
        if (empty($domain)) continue;

        $mu_path = "/home/{$usr}/domains/{$domain}/public_html/wp-content/mu-plugins";
        $file_path = "{$mu_path}/fix-poze-duplicate.php";

        // Executa direct prin DirectAdmin CMD_API_CMD_EXEC sau prin cron force
        $cmd = "mkdir -p '{$mu_path}' && echo '{$plugin_escaped}' > '{$file_path}' && chmod 644 '{$file_path}'";

        // Incearca executie prin PHP ca acel user - nu merge, dar incercam altfel
        // Forteaza rularea cron-ului
        $result = da_request("POST", "/CMD_CRON_JOBS", [
            "action" => "force",
        ], $da_host, "{$da_user}|{$usr}", $da_pass);

        echo "  {$domain}: trimis\n";
        flush();
    }
    echo "\nGata! Asteapta 1-2 minute si verifica.\n</pre>";
}

// Curata cron-urile
if (isset($_GET['action']) && $_GET['action'] === 'cleanup') {
    echo "<pre>\n=== Curatare cron-uri ===\n\n";
    foreach ($user_list as $usr) {
        // Ia lista de cron-uri
        $crons = da_request("GET", "/CMD_API_CRON_JOBS", [], $da_host, "{$da_user}|{$usr}", $da_pass);
        // Cauta si sterge cron-urile noastre
        preg_match_all('/(\d+)=.*?mu-plugins/i', $crons, $matches);
        foreach ($matches[1] as $id) {
            da_request("POST", "/CMD_API_CRON_JOBS", [
                "action" => "delete",
                "select0" => $id,
            ], $da_host, "{$da_user}|{$usr}", $da_pass);
        }
        echo "  {$usr}: curatat\n";
        flush();
    }
    echo "\nGata!\n</pre>";
}

function da_request($method, $path, $data, $host, $user, $pass) {
    $ch = curl_init();
    $url = $host . $path;
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERPWD => "{$user}:{$pass}",
        CURLOPT_TIMEOUT => 30,
    ];
    if ($method === "POST") {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($data);
    }
    $opts[CURLOPT_URL] = $url;
    curl_setopt_array($ch, $opts);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result ?: "";
}
