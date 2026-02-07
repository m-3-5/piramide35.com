<?php
// CORREZIONE PERCORSI: Usiamo __DIR__ per essere sicuri al 100%
// __DIR__ è la cartella corrente (/m35_core/modules/scanner)
// dirname(__DIR__, 2) risale di 2 livelli (/m35_core)

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/db_manager.php';

// Inizializzazione
$message = "";
$scan_results = [];

// LOGICA PHP (Se viene premuto il pulsante)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target_url = $_POST['url'];
    
    // 1. Validazione URL
    if (filter_var($target_url, FILTER_VALIDATE_URL)) {
        
        // 2. Simuliamo un browser (per non essere bloccati)
        $context = stream_context_create([
            "http" => ["header" => "User-Agent: M35_Bot/1.0\r\n"]
        ]);
        
        // 3. Scarichiamo l'HTML
        $html = @file_get_contents($target_url, false, $context);
        
        if ($html) {
            // 4. Analisi Semplice (Regex)
            // Titolo
            preg_match("/<title>(.*)<\/title>/i", $html, $matches);
            $site_title = $matches[1] ?? 'Non trovato';
            
            // Meta Description
            $meta_desc = 'Non trovata';
            $tags = get_meta_tags($target_url);
            if(isset($tags['description'])) $meta_desc = $tags['description'];

            // Cerchiamo colori (Codici HEX)
            preg_match_all("/#[a-f0-9]{6}/i", $html, $color_matches);
            $colors_found = array_unique($color_matches[0]);
            $primary_color = $colors_found[0] ?? '#000000'; // Prendiamo il primo come ipotesi

            // 5. SALVATAGGIO NEL DATABASE CLIENTE (Beauty)
            try {
                $db = M35Database::getTenant(); // Connette a m35_beauty_tenant
                
                // Salviamo Titolo
                $stmt = $db->prepare("INSERT INTO system_config (setting_key, setting_value) VALUES ('scanned_title', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$site_title, $site_title]);
                
                // Salviamo Descrizione
                $stmt = $db->prepare("INSERT INTO system_config (setting_key, setting_value) VALUES ('scanned_desc', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$meta_desc, $meta_desc]);
                
                // Salviamo Colore Primario
                $stmt = $db->prepare("INSERT INTO system_config (setting_key, setting_value) VALUES ('brand_primary_color', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$primary_color, $primary_color]);

                $message = "✅ Scansione completata e dati salvati nel DB Tenant!";
                $scan_results = [
                    'Titolo' => $site_title,
                    'Descrizione' => $meta_desc,
                    'Colore Rilevato' => $primary_color
                ];

            } catch (Exception $e) {
                $message = "❌ Errore Database: " . $e->getMessage();
            }

        } else {
            $message = "❌ Impossibile leggere il sito (potrebbe essere protetto).";
        }
    } else {
        $message = "⚠️ Inserisci un URL valido (es. https://beautyofimage.com)";
    }
}

// INCLUDE INTERFACCIA (Corretto il percorso)
include dirname(__DIR__, 2) . '/layout_header.php';
?>

<div class="page-header">
    <h1>IA Scanner</h1>
    <div class="subtitle">Modulo di ingestione dati v1.0</div>
</div>

<div class="card">
    <form method="POST">
        <label style="display:block; margin-bottom:10px; color:var(--text-muted);">URL del sito da clonare/analizzare:</label>
        <div style="display:flex; gap:10px;">
            <input type="url" name="url" placeholder="https://www.vecchiosito.it" required>
            <button type="submit" class="btn-action">AVVIA SCANSIONE</button>
        </div>
    </form>
</div>

<?php if (!empty($message)): ?>
    <div style="margin-bottom: 20px; color: <?php echo strpos($message, '✅') !== false ? '#10b981' : '#ef4444'; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<?php if (!empty($scan_results)): ?>
    <div class="card">
        <h3>Dati Estratti e Salvati:</h3>
        <div class="scan-result">
            <?php foreach ($scan_results as $key => $val): ?>
                <p><strong><?php echo $key; ?>:</strong> <?php echo htmlspecialchars($val); ?></p>
            <?php endforeach; ?>
            
            <div style="margin-top:15px; display:flex; align-items:center; gap:10px;">
                Colore salvato: 
                <div style="width:30px; height:30px; background-color:<?php echo $scan_results['Colore Rilevato']; ?>; border:1px solid #fff;"></div>
            </div>
        </div>
        <p style="margin-top:15px; font-size:0.9rem;">
            <em>Questi dati sono ora nella tabella <strong>system_config</strong> del database <strong>m35_beauty_tenant</strong>.</em>
        </p>
    </div>
<?php endif; ?>

<?php 
// INCLUDE FOOTER (Corretto il percorso)
include dirname(__DIR__, 2) . '/layout_footer.php'; 
?>