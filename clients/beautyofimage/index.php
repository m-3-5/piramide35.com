<?php
// M 3.5 CLIENT CONNECTOR
// Versione: 1.1 (Path Fix)

// 1. CALCOLO PERCORSO ASSOLUTO
// Siamo in: /clients/beautyofimage
// Dobbiamo salire di 2 livelli per tornare alla Root
$root_path = dirname(__DIR__, 2); 
$core_file = $root_path . '/m35_core/db_manager.php';

// Debug di sicurezza (Se vedi questo messaggio, il percorso è sbagliato)
if (!file_exists($core_file)) {
    die("❌ ERRORE CRITICO: Non trovo il file Core.<br>Sto cercando qui: <strong>" . $core_file . "</strong><br>Controlla se la cartella m35_core esiste nella root.");
}

// 2. CARICAMENTO CERVELLO
require_once $core_file;

// 3. RECUPERO DATI DAL DB TENANT
try {
    $db = M35Database::getTenant(); // Si connette a m35_beauty_tenant
    
    // Leggiamo la configurazione
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_config");
    $config = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $config[$row['setting_key']] = $row['setting_value'];
    }

    // Fallback dati
    $title = $config['scanned_title'] ?? 'Sito in Costruzione';
    $desc = $config['scanned_desc'] ?? 'Stiamo lavorando per voi.';
    $primary_color = $config['brand_primary_color'] ?? '#000000';

} catch (Exception $e) {
    die("Errore di connessione al Database Tenant: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?></title>
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            margin: 0; 
            padding: 0; 
            background: #f4f4f4;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            color: #333;
        }
        .card {
            background: white;
            padding: 50px;
            border-radius: 12px;
            box-shadow: 0 15px 3