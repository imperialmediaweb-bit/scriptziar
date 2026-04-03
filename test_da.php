<?php
echo "<pre>";

// Test 1: DirectAdmin API conectare
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "https://localhost:2222/CMD_API_SHOW_USERS",
    CURLOPT_USERPWD => "sellsite:UiyeTD(5O54v[8",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_TIMEOUT => 10,
]);
$r1 = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

echo "Test 1 - localhost:2222:\n";
echo "Eroare curl: " . ($err ?: "nicio eroare") . "\n";
echo "Raspuns (primele 300 car): " . substr($r1, 0, 300) . "\n\n";

// Test 2: cu 127.0.0.1
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "https://127.0.0.1:2222/CMD_API_SHOW_USERS",
    CURLOPT_USERPWD => "sellsite:UiyeTD(5O54v[8",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_TIMEOUT => 10,
]);
$r2 = curl_exec($ch);
$err2 = curl_error($ch);
curl_close($ch);

echo "Test 2 - 127.0.0.1:2222:\n";
echo "Eroare curl: " . ($err2 ?: "nicio eroare") . "\n";
echo "Raspuns (primele 300 car): " . substr($r2, 0, 300) . "\n\n";

// Test 3: cu HTTP (nu HTTPS)
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "http://localhost:2222/CMD_API_SHOW_USERS",
    CURLOPT_USERPWD => "sellsite:UiyeTD(5O54v[8",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_FOLLOWLOCATION => true,
]);
$r3 = curl_exec($ch);
$err3 = curl_error($ch);
curl_close($ch);

echo "Test 3 - http://localhost:2222:\n";
echo "Eroare curl: " . ($err3 ?: "nicio eroare") . "\n";
echo "Raspuns (primele 300 car): " . substr($r3, 0, 300) . "\n\n";

// Test 4: cu hostname-ul real
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "https://web7.gazduire.net:2222/CMD_API_SHOW_USERS",
    CURLOPT_USERPWD => "sellsite:UiyeTD(5O54v[8",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_TIMEOUT => 10,
]);
$r4 = curl_exec($ch);
$err4 = curl_error($ch);
curl_close($ch);

echo "Test 4 - web7.gazduire.net:2222:\n";
echo "Eroare curl: " . ($err4 ?: "nicio eroare") . "\n";
echo "Raspuns (primele 300 car): " . substr($r4, 0, 300) . "\n\n";

// Test 5: Calea pe disc
echo "Test 5 - Cale curenta: " . __DIR__ . "\n";
echo "User curent PHP: " . get_current_user() . "\n";
echo "wp-config exista: " . (file_exists(__DIR__ . "/wp-config.php") ? "DA" : "NU") . "\n";

echo "\n⚠ STERGE ACEST FISIER!\n";
echo "</pre>";
