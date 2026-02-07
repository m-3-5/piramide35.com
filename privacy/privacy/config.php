<?php
// config.php

// 1. DATI DATABASE (Li trovi nel wp-config.php se non li ricordi)
$host = 'xxxxxx';
$db   = 'xxxxxxxx';
$user = 'xxxxxxxx';
$pass = 'xxxxxxxxx';

// 2. LA TUA CHIAVE GEMINI (AI)
$gemini_api_key = 'xxxxxxxxxxxxxxx';

// Connessione DB
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) { die("Connessione fallita: " . $conn->connect_error); }
?>
