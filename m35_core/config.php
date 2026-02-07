<?php
// M 3.5 SYSTEM CONFIGURATION
// Security Level: HIGH

// 1. CREDENZIALI CORE (Il Cervello)
define('DB_CORE_HOST', 'localhost');
define('DB_CORE_NAME', 'm35_core');
define('DB_CORE_USER', 'm35_core_user');
define('DB_CORE_PASS', '3dj?D^Lb1dizJlu8');

// 2. CREDENZIALI BEAUTY (Il Primo Cliente)
// Nota: In futuro queste saranno recuperate dinamicamente dal DB Core, 
// ma per ora le fissiamo qui per far funzionare il test.
define('DB_BEAUTY_NAME', 'm35_beauty_tenant');
define('DB_BEAUTY_USER', 'm35_beauty_tenant_user');
define('DB_BEAUTY_PASS', 'NS4Kv~q6oeh5sx~f');

// 3. SETTINGS GENERALI
define('ROOT_PATH', $_SERVER['DOCUMENT_ROOT']);
define('CORE_PATH', ROOT_PATH . '/m35_core');
?>