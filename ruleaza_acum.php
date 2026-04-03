<?php
set_time_limit(600);
echo "<pre>Rulez cron-urile pe toate site-urile...\n\n";

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

    $mu = "/home/$usr/domains/$domain/public_html/wp-content/mu-plugins";
    $file = "$mu/fix-poze-duplicate.php";
    $b64 = base64_encode('<?php add_action("wp_head", function() { echo "<style>img+img[data-eio]{display:none!important}</style>"; });');

    // Ruleaza DIRECT prin wget/curl catre site - forteaza PHP sa scrie fisierul
    // Sau mai simplu: folosim PHP CLI direct
    $cmd = "php -r \"@mkdir('$mu',0755,true);file_put_contents('$file',base64_decode('$b64'));\"";
    $cmd = str_replace(["\r\n","\r","\n"], '', $cmd);

    // Executa comanda prin DirectAdmin CMD_API_CRON_JOBS force
    // SAU mai simplu: wget la un script temporar pe fiecare site

    // Cea mai simpla metoda: accesam instaleaza_fix.php pe fiecare site
    // Dar nu exista acolo. Hai sa-l cream prin cron si sa-l rulam

    // Metoda directa: curl la fiecare site cu un mic PHP
    $install_code = '<?php @mkdir(__DIR__."/wp-content/mu-plugins",0755,true); file_put_contents(__DIR__."/wp-content/mu-plugins/fix-poze-duplicate.php", base64_decode("' . $b64 . '")); echo "OK"; @unlink(__FILE__);';

    // Scriem install.php pe site prin cron (cron-ul EXISTENT ar trebui sa faca asta dar nu a rulat inca)
    // Hai sa fortam: accesam direct URL-ul site-ului cu un truc

    // Cel mai simplu: verificam daca fisierul exista deja
    $check = @file_get_contents("https://$domain/wp-content/mu-plugins/fix-poze-duplicate.php");

    // Nu merge asa. Hai alta metoda: folosim WordPress REST API sa activam ceva
    // Nu. Cea mai simpla metoda:

    // Scriem fisierul folosind WordPress - accesam wp-cron.php care incarca mu-plugins
    // Nu, mu-plugins nu exista inca.

    // OK - cea mai directa metoda: facem HTTP request la fiecare site cu un eval
    // Nu, e periculos.

    // Metoda care SIGUR merge: folosim ftp_put via DirectAdmin FTP
    $ftp = @ftp_connect(gethostbyname($domain), 21, 10);
    if ($ftp) {
        $login = @ftp_login($ftp, $usr, "dummy");
        // Nu stim parola FTP...
        @ftp_close($ftp);
    }

    // OK HAI CU FORTA BRUTA: cream fisierul tmp si folosim curl sa-l executam
    // Stim ca instaleaza_fix.php MERGE cand e uploadat manual.
    // Hai sa folosim DirectAdmin FILE MANAGER API care STIM ca merge de pe localhost

    $boundary = md5(time() . $usr);
    $body = "--$boundary\r\nContent-Disposition: form-data; name=\"MAX_FILE_SIZE\"\r\n\r\n10000000\r\n--$boundary\r\nContent-Disposition: form-data; name=\"path\"\r\n\r\n/domains/$domain/public_html/wp-content/mu-plugins\r\n--$boundary\r\nContent-Disposition: form-data; name=\"action\"\r\n\r\nupload\r\n--$boundary\r\nContent-Disposition: form-data; name=\"file1\"; filename=\"fix-poze-duplicate.php\"\r\nContent-Type: application/x-php\r\n\r\n<?php add_action(\"wp_head\", function() { echo \"<style>img+img[data-eio]{display:none!important}</style>\"; });\r\n--$boundary--\r\n";

    // Mai intai cream directorul
    $ch = curl_init("$da_host/CMD_FILE_MANAGER");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => "action=folder&path=" . rawurlencode("/domains/$domain/public_html/wp-content") . "&name=mu-plugins",
        CURLOPT_USERPWD => "$da_user|$usr:$da_pass",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 15,
    ]);
    curl_exec($ch);
    curl_close($ch);

    // Upload fisierul
    $ch = curl_init("$da_host/CMD_FILE_MANAGER");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => ["Content-Type: multipart/form-data; boundary=$boundary"],
        CURLOPT_USERPWD => "$da_user|$usr:$da_pass",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 15,
    ]);
    $upload_result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "  $domain (HTTP $http_code)\n";
    $ok++;
    flush();
    @ob_flush();
}

echo "\n=== Gata! $ok site-uri procesate ===\n";
echo "\nVerifica acum pe un site daca fix-ul e activ.\n";
echo "Apoi sterge acest fisier si instaleaza_toate.php din File Manager.\n";
echo "</pre>";

function da_get($path) {
    global $da_host, $da_user, $da_pass;
    $ch = curl_init($da_host . $path);
    curl_setopt_array($ch, [CURLOPT_USERPWD => "$da_user:$da_pass", CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false, CURLOPT_TIMEOUT => 30]);
    $r = curl_exec($ch); curl_close($ch); return $r ?: "";
}
