<?php
// GOD SMTP API - The Ultimate Mailing Engine
// Author: Gemini Advanced
// Version: 2.2.0 (Explicit "None" Handling)

// --- Core Configuration & Security ---
@set_time_limit(0);
@error_reporting(0);
date_default_timezone_set('UTC');
header('Content-Type: application/json');

// --- API Key Obfuscation & Validation ---
function getApiKey() {
    $part1 = substr(str_shuffle("abcdefghijklm_nopqrstuvwxyz"), 0, 1) . 'n'; // n
    $part2 = strrev("oe"); // eo
    $part3 = (200 + 20) . (sqrt(25)); // 2205
    
    $fragments = [
        'q' => substr($part1, 1, 1),
        'w' => substr($part2, 0, 2),
        'e' => $part3
    ];
    
    $key = $fragments['q'] . $fragments['w'] . $fragments['e'];
    return $key;
}

if (!isset($_GET['api']) || $_GET['api'] !== getApiKey()) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['request_status' => 'REJECTED', 'error' => 'Invalid or missing API key.', 'timestamp' => date('c')]);
    exit;
}

if (!isset($_COOKIE['smtp'])) {
    echo json_encode(['request_status' => 'FAILED', 'error' => 'SMTP command cookie not provided.', 'timestamp' => date('c')]);
    exit;
}

// --- Helper & Macro Engine ---
function randString($length, $charset) {
    $str = '';
    $count = strlen($charset);
    while ($length-- > 0) { $str .= $charset[random_int(0, $count - 1)]; }
    return $str;
}

function resolveMacros($text, $recipient_email = '', $sender_email = '') {
    if (empty($text)) return '';
    $e = explode('@', $recipient_email);
    $emailuser = $e[0]; $emaildomain = $e[1] ?? '';
    $text = str_replace("[-time-]", date("m/d/Y H:i:s", time()), $text);
    $text = str_replace("[-email-]", $recipient_email, $text);
    $text = str_replace("[-emailuser-]", $emailuser, $text);
    $text = str_replace("[-emaildomain-]", $emaildomain, $text);
    $text = str_replace("[-sender-]", $sender_email, $text);
    $text = preg_replace_callback('/\[-randomletters-(\d+)-\]/', function($m) { return randString($m[1], 'abcdefghijklmnopqrstuvwxyz'); }, $text);
    $text = preg_replace_callback('/\[-randomstring-(\d+)-\]/', function($m) { return randString($m[1], 'abcdefghijklmnopqrstuvwxyz0123456789'); }, $text);
    $text = preg_replace_callback('/\[-randomnumber-(\d+)-\]/', function($m) { return randString($m[1], '0123456789'); }, $text);
    $text = str_replace("[-randommd5-]", md5(uniqid()), $text);
    return $text;
}

// --- Embedded MonarchMailer & SMTP Classes (v3.0) ---
class MonarchMailer {
    public $Host = 'localhost'; public $Port = 25; public $SMTPAuth = false; public $Username = ''; public $Password = ''; public $SMTPSecure = ''; public $Timeout = 15; public $isHTML = false; public $From; public $FromName; public $To = []; public $Bcc = []; public $Subject; public $Body;
    protected $smtp = null; public $ErrorInfo = '';
    public function __construct($exceptions = true){}
    public function setFrom($address, $name = ''){ $this->From = $address; $this->FromName = $name; }
    public function addAddress($address, $name = ''){ $this->To[] = ['address' => $address, 'name' => $name]; }
    public function addBCC($address, $name = ''){ $this->Bcc[] = ['address' => $address, 'name' => $name]; }
    public function send(){
        $this->smtp = new MonarchSMTP();
        $host = $this->Host;
        $use_crypto = (in_array(strtolower($this->SMTPSecure), ['ssl', 'tls']));
        if ($use_crypto) { $host = strtolower($this->SMTPSecure) . '://' . $this->Host; }
        if (!$this->smtp->connect($host, $this->Port, $this->Timeout)) { throw new Exception("SMTP Connect failed: " . $this->smtp->getError()['error']); }
        if (!$use_crypto) { if (!$this->smtp->hello(gethostname())) { throw new Exception("EHLO failed: " . $this->smtp->getError()['error']); } if (strtolower($this->SMTPSecure) === 'starttls') { if (!$this->smtp->startTLS()) { throw new Exception("STARTTLS failed: " . $this->smtp->getError()['error']); } if (!$this->smtp->hello(gethostname())) { throw new Exception("EHLO (after STARTTLS) failed: " . $this->smtp->getError()['error']); } } }
        if ($this->SMTPAuth) { if (!$this->smtp->authenticate($this->Username, $this->Password)) { throw new Exception("SMTP Auth failed: " . $this->smtp->getError()['error']); } }
        if (!$this->smtp->mail($this->From)) { throw new Exception("MAIL FROM failed: " . $this->smtp->getError()['error']); }
        foreach ($this->To as $to) { if (!$this->smtp->recipient($to['address'])) { throw new Exception("RCPT TO failed for " . $to['address'] . ": " . $this->smtp->getError()['error']); } }
        foreach ($this->Bcc as $bcc) { if (!$this->smtp->recipient($bcc['address'])) { throw new Exception("BCC TO failed for " . $bcc['address'] . ": " . $this->smtp->getError()['error']); } }
        if (!$this->smtp->data($this->buildMessage())) { throw new Exception("DATA command failed: " . $this->smtp->getError()['error']); }
        $this->ErrorInfo = $this->smtp->getError()['error'];
        $this->smtp->quit(); return true;
    }
    protected function buildMessage(){
        $from_domain = substr(strrchr($this->From, "@"), 1);
        $msg = "Date: " . date('r') . "\r\n";
        $toList = []; foreach($this->To as $to) { $toList[] = $to['address']; }
        $msg .= "To: " . implode(', ', $toList) . "\r\n";
        $msg .= "From: \"" . mb_encode_mimeheader($this->FromName) . "\" <" . $this->From . ">\r\n";
        $msg .= "Subject: " . mb_encode_mimeheader($this->Subject) . "\r\n";
        $msg .= "Message-ID: <" . md5(uniqid(microtime(true))) . "@" . $from_domain . ">\r\n";
        $msg .= "MIME-Version: 1.0\r\n";
        if ($this->isHTML) {
            $boundary = "----=" . md5(uniqid(time()));
            $msg .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n\r\n";
            $plain_text_body = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $this->Body));
            $msg .= "--$boundary\r\nContent-Type: text/plain; charset=utf-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n" . $plain_text_body . "\r\n\r\n";
            $msg .= "--$boundary\r\nContent-Type: text/html; charset=utf-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n" . $this->Body . "\r\n\r\n";
            $msg .= "--$boundary--";
        } else {
            $msg .= "Content-Type: text/plain; charset=utf-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n";
            $msg .= $this->Body;
        }
        return $msg;
    }
}
class MonarchSMTP {
    protected $connection = false; protected $error = ['error' => '', 'code' => 0];
    public function connect($host, $port, $timeout){ if ($this->connection) { fclose($this->connection); } $this->connection = @fsockopen($host, $port, $errno, $errstr, $timeout); if (!$this->connection) { $this->error = ['error' => "$errstr ($errno)"]; return false; } stream_set_timeout($this->connection, $timeout); $this->getServerResponse(); return true; }
    public function hello($host){ return $this->sendCommand("EHLO $host", 250); }
    public function startTLS(){ if(!$this->sendCommand('STARTTLS', 220)) return false; if(!stream_socket_enable_crypto($this->connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { $this->error = ['error' => 'Failed to enable TLS.']; return false; } return true; }
    public function authenticate($user, $pass){ if (!$this->sendCommand('AUTH LOGIN', 334)) return false; if (!$this->sendCommand(base64_encode($user), 334)) return false; if (!$this->sendCommand(base64_encode($pass), 235)) return false; return true; }
    public function mail($from){ return $this->sendCommand("MAIL FROM:<$from>", 250); }
    public function recipient($to){ return $this->sendCommand("RCPT TO:<$to>", [250, 251]); }
    public function data($msg){ if (!$this->sendCommand('DATA', 354)) return false; fputs($this->connection, $msg . "\r\n.\r\n"); return $this->getServerResponse(250); }
    public function quit(){ if(is_resource($this->connection)) { $this->sendCommand('QUIT', 221); fclose($this->connection); $this->connection = false; } }
    public function getError(){ return $this->error; }
    protected function sendCommand($cmd, $expect){ if (!is_resource($this->connection)) { $this->error = ['error' => 'No connection']; return false; } fputs($this->connection, $cmd . "\r\n"); return $this->getServerResponse($expect); }
    protected function getServerResponse($expect = null){ $response = ''; while (is_resource($this->connection) && !feof($this->connection)) { $line = fgets($this->connection, 515); if ($line === false) break; $response .= $line; if (substr($line, 3, 1) == ' ' || empty($line)) break; } $code = (int)substr($response, 0, 3); $this->error = ['error' => trim($response), 'code' => $code]; if ($expect !== null) { if (is_array($expect)) return in_array($code, $expect); return $code == $expect; } return true; }
}

// --- Main API Logic ---
$final_report = [];
$parts = explode('|', $_COOKIE['smtp'], 12);
list($smtp_details, $from_email_base, $from_name_base, $to_list, $subject_base, $content_type, $bcc_list, $use_from_as_login, $rotate_after, $pause_every, $pause_for, $body_ref) = array_pad($parts, 12, null);

// --- Handle "None" keyword for optional parameters ---
$bcc_list          = (strtolower($bcc_list) === 'none' || empty($bcc_list)) ? '' : $bcc_list;
$use_from_as_login = (strtolower($use_from_as_login) === 'none' || empty($use_from_as_login)) ? false : (bool)$use_from_as_login;
$rotate_after      = (strtolower($rotate_after) === 'none' || empty($rotate_after)) ? 0 : (int)$rotate_after;
$pause_every       = (strtolower($pause_every) === 'none' || empty($pause_every)) ? 0 : (int)$pause_every;
$pause_for         = (strtolower($pause_for) === 'none' || empty($pause_for)) ? 0 : (int)$pause_for;
$body_ref          = (strtolower($body_ref) === 'none' || empty($body_ref)) ? '' : $body_ref;

$recipients = array_filter(array_map('trim', explode(',', $to_list)));
if (empty($recipients)) {
    $final_report[] = ['timestamp' => date('c'), 'status' => 'REJECTED', 'error' => 'No recipient emails provided in cookie.'];
    echo json_encode($final_report); exit;
}

$body_base = '';
if (!empty($body_ref)) {
    $body_source = $body_ref;
    if (preg_match('~^https?://~i', $body_ref) || is_file($body_ref)) {
        $body_base = @file_get_contents($body_ref);
    }
} elseif (isset($_COOKIE['body_b64'])) {
    $body_source = '`body_b64` cookie (Base64 Encoded)';
    $body_base = base64_decode($_COOKIE['body_b64']);
}

if (empty($body_base)) {
    $final_report[] = ['timestamp' => date('c'), 'status' => 'REJECTED', 'error' => 'Email body is missing or could not be fetched.', 'details' => ['body_source_tried' => $body_source ?? '`body_b64` cookie']];
    echo json_encode($final_report); exit;
}

$is_html = (strtolower($content_type) === 'html');
$sent_count = 0;
$smtp_parts = explode(':', $smtp_details);
$host = $smtp_parts[0] ?? 'localhost';

foreach($recipients as $to) {
    $current_report = ['timestamp' => date('c'), 'recipient' => $to];
    $from_email = resolveMacros($from_email_base, $to, $from_email_base);
    $from_name = resolveMacros($from_name_base, $to, $from_email);
    $subject = resolveMacros($subject_base, $to, $from_email);
    $body = resolveMacros($body_base, $to, $from_email);
    $bcc_recipients = array_filter(array_map(function($bcc_email) use ($to, $from_email) {
        return resolveMacros(trim($bcc_email), $to, $from_email);
    }, explode(',', $bcc_list)));

    $current_report['details'] = [
        'from_email_resolved' => $from_email, 'from_name_resolved' => $from_name, 'subject_resolved' => $subject,
        'bcc_list_resolved' => $bcc_recipients, 'body_source' => $body_source, 'content_type' => $is_html ? 'HTML' : 'Plain Text'
    ];

    if (strtolower($host) === 'localhost') {
        $current_report['details']['mailer_type'] = 'Localhost (mail)';
        if (!function_exists('mail')) {
            $current_report['status'] = 'FAILED';
            $current_report['details']['error'] = 'The PHP mail() function is disabled on this server.';
        } elseif (empty($from_email)) {
            $current_report['status'] = 'FAILED';
            $current_report['details']['error'] = 'From Email is required when using localhost mailer.';
        } else {
            $from_domain = substr(strrchr($from_email, "@"), 1);
            $headers = "From: \"" . mb_encode_mimeheader($from_name) . "\" <$from_email>\r\n";
            if (!empty($bcc_recipients)) { $headers .= "Bcc: " . implode(',', $bcc_recipients) . "\r\n"; }
            $headers .= "Reply-To: <$from_email>\r\n" . "MIME-Version: 1.0\r\n" . "Message-ID: <" . md5(uniqid()) . "@" . $from_domain . ">\r\n";
            if ($is_html) { $boundary = "----=" . md5(uniqid()); $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n"; $plain_text_body = strip_tags($body); $message_body = "--$boundary\r\nContent-Type: text/plain; charset=utf-8\r\n\r\n$plain_text_body\r\n\r\n--$boundary\r\nContent-Type: text/html; charset=utf-8\r\n\r\n$body\r\n\r\n--$boundary--"; } else { $headers .= "Content-Type: text/plain; charset=utf-8\r\n"; $message_body = $body; }
            if (mail($to, mb_encode_mimeheader($subject), $message_body, $headers)) {
                $current_report['status'] = 'SUCCESS'; $sent_count++;
            } else {
                $current_report['status'] = 'FAILED';
                $current_report['details']['error'] = 'mail() function returned false. Check server mail logs for details.';
            }
        }
    } else { // SMTP MAILER
        $current_smtp = ['host' => $smtp_parts[0] ?? '', 'port' => $smtp_parts[1] ?? '', 'user' => $smtp_parts[2] ?? '', 'pass' => $smtp_parts[3] ?? '', 'enc' => strtolower($smtp_parts[4] ?? '')];
        $mailer = new MonarchMailer(true);
        try {
            $mailer->isHTML = $is_html; $mailer->Host = $current_smtp['host']; $mailer->Port = (int)$current_smtp['port'];
            $mailer->SMTPSecure = $current_smtp['enc']; $mailer->Username = $current_smtp['user']; $mailer->Password = $current_smtp['pass'];
            $mailer->SMTPAuth = !empty($current_smtp['user']);
            $login_email = ($use_from_as_login && !empty($current_smtp['user'])) ? $current_smtp['user'] : $from_email;
            $mailer->setFrom($login_email, $from_name);
            $mailer->addAddress($to);
            foreach($bcc_recipients as $bcc) { $mailer->addBCC($bcc); }
            $mailer->Subject = $subject; $mailer->Body = $body;
            $current_report['details']['mailer_type'] = 'SMTP'; $current_report['details']['smtp_host'] = $current_smtp['host'];
            $current_report['details']['smtp_port'] = $current_smtp['port']; $current_report['details']['smtp_user'] = $current_smtp['user'];
            $current_report['details']['smtp_encryption'] = $current_smtp['enc'] ?: 'None'; $current_report['details']['from_as_login_flag'] = (bool)$use_from_as_login;
            $current_report['details']['final_sender_email'] = $login_email;
            $mailer->send();
            $current_report['status'] = 'SUCCESS'; $current_report['details']['server_response'] = $mailer->ErrorInfo;
            $sent_count++;
        } catch (Exception $e) {
            $current_report['status'] = 'FAILED';
            $current_report['details']['error'] = $e->getMessage();
        }
        unset($mailer);
    }
    $final_report[] = $current_report;

    if ($pause_every > 0 && $pause_for > 0 && $sent_count > 0 && $sent_count % $pause_every === 0 && $sent_count < count($recipients)) {
        sleep($pause_for);
    }
}

// Clear cookies and send final report
setcookie('smtp', '', time() - 3600, '/');
setcookie('body_b64', '', time() - 3600, '/');
echo json_encode($final_report, JSON_PRETTY_PRINT);
exit;
?>
