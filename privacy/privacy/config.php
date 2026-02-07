<?php
// config.php

// 1. DATI DATABASE (Li trovi nel wp-config.php se non li ricordi)
$host = 'localhost';
$db   = 'm35_warroom';
$user = 'm35_warroom_user';
$pass = 'TrUlY~!g5v2l3kna';

// 2. LA TUA CHIAVE GEMINI (AI)
$gemini_api_key = 'xxxxxxxxxxxxxxx';

// Connessione DB
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) { die("Connessione fallita: " . $conn->connect_error); }
?>
