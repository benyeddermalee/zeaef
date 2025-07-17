<?php 
ini_set('display_errors', 1); 
error_reporting(E_ALL); 

// Read the contents of the two files
$part1 = file_get_contents('index1.txt'); 
$part2 = file_get_contents('index2.txt'); 

// Combine the parts. The urldecode() is likely not needed if you are just
// storing plain HTML, but we can leave it for now.
$fullText = urldecode($part1 . $part2); 

// --- THIS IS THE FIX ---
// Instead of trying to EXECUTE the text, just DISPLAY it.
echo $fullText; 
?>
