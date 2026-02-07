<?php
include 'config.php';

// --- FUNZIONE PER LEGGERE I FILE WORD (.DOCX) ---
function readDocx($filename) {
    $content = '';
    $zip = new ZipArchive;
    if ($zip->open($filename) === TRUE) {
        if (($index = $zip->locateName('word/document.xml')) !== false) {
            $xml_data = $zip->getFromIndex($index);
            $dom = new DOMDocument;
            $dom->loadXML($xml_data, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
            $content = strip_tags($dom->saveXML());
        }
        $zip->close();
    }
    return $content;
}

// --- LOGICA DI ELABORAZIONE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $input_user = $_POST['prompt'] ?? '';
    
    // 1. ISTRUZIONI DI SISTEMA
    $system_instruction = "
    Sei il sistema contabile automatico della M 3.5 S.R.L. (PMI Innovativa).
    Il tuo compito è analizzare il testo dell'utente E I FILE ALLEGATI per generare un JSON per una fattura pro-forma.
    
    REGOLE:
    1. PRIORITÀ AI FILE: Cerca i dati del cliente (Nome, Indirizzo, P.IVA) e gli importi dentro i file allegati o nel testo estratto.
    2. CAPIRE IL SETTORE:
       - Cavi/Impianti -> Terminologia 'Elettrica'.
       - Siti/Software -> Terminologia 'IT & Sviluppo'.
       - Audit/Diffide -> Terminologia 'Legal Tech'.
    3. MATEMATICA: Calcola Imponibile, IVA (22%) e Totale.
    
    OUTPUT JSON RICHIESTO (Solo JSON puro):
    {
        'cliente_nome': '...',
        'cliente_indirizzo': '...',
        'cliente_cf': '...',
        'causale_legale': '...',
        'righe': [{'desc': '...', 'dettaglio': '...', 'prezzo': '...'}],
        'totale_imponibile': '...',
        'totale_iva': '...',
        'totale_generale': '...'
    }
    ";

    // 2. PREPARAZIONE DATI PER GEMINI (Parts)
    $parts = [];
    
    // Aggiungiamo le istruzioni e il prompt utente
    $parts[] = ["text" => $system_instruction . "\n\nTESTO UTENTE:\n" . $input_user];

    // 3. GESTIONE FILE ALLEGATI
    if (!empty($_FILES['allegati']['name'][0])) {
        $count = count($_FILES['allegati']['name']);
        
        for ($i = 0; $i < $count; $i++) {
            $tmpFilePath = $_FILES['allegati']['tmp_name'][$i];
            $fileType = $_FILES['allegati']['type'][$i];
            $fileName = $_FILES['allegati']['name'][$i];

            if ($tmpFilePath != "") {
                // GESTIONE PDF E IMMAGINI (Nativa Gemini)
                if (strpos($fileType, 'image') !== false || strpos($fileType, 'pdf') !== false) {
                    $data = file_get_contents($tmpFilePath);
                    $base64 = base64_encode($data);
                    
                    $parts[] = [
                        "inline_data" => [
                            "mime_type" => $fileType,
                            "data" => $base64
                        ]
                    ];
                } 
                // GESTIONE WORD .DOCX (Estrazione testo PHP)
                elseif (strpos($fileName, '.docx') !== false) {
                    $text_content = readDocx($tmpFilePath);
                    $parts[] = ["text" => "\n\nCONTENUTO ESTRATTO DAL FILE WORD ($fileName):\n" . $text_content];
                }
            }
        }
    }

    // 4. CHIAMATA API GEMINI
    $payload = ["contents" => [["parts" => $parts]]];
    
    $options = [
        'http' => [
            'header'  => "Content-type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($payload)
        ]
    ];
    
    $context  = stream_context_create($options);
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3-flash-preview:generateContent?key=" . $gemini_api_key;
    
    // Gestione Errori Base
    $result = @file_get_contents($url, false, $context);
    
    if ($result === FALSE) {
        die("Errore nella chiamata a Gemini. Controlla la dimensione dei file o la API Key.");
    }

    $response = json_decode($result, true);
    
    // Pulizia JSON
    $text_response = $response['candidates'][0]['content']['parts'][0]['text'];
    $text_response = str_replace(['```json', '```'], '', $text_response);
    $dati_doc = json_decode($text_response, true);

    // 5. SALVATAGGIO DB
    $stmt = $conn->prepare("INSERT INTO m35_docs (cliente, input_originale, json_dati) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $dati_doc['cliente_nome'], $input_user, $text_response);
    $stmt->execute();
    $id_doc = $stmt->insert_id;
    $numero_doc = str_pad($id_doc, 2, '0', STR_PAD_LEFT) . "/" . date('Y');
    
    // --- OUTPUT PDF (HTML) ---
    ?>
    <!DOCTYPE html>
    <html lang="it">
    <head>
    <meta charset="UTF-8">
    <title>Pro-Forma <?php echo $numero_doc; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=Roboto+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        /* STILE MASTER M 3.5 */
        @page { size: A4; margin: 0; }
        :root { --tech-green: #00D185; --tech-dark: #081014; --tech-gray: #f4f6f8; --text-main: #333; }
        body { font-family: 'Inter', sans-serif; color: var(--text-main); margin: 0; padding: 0; background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .header-strip { background-color: var(--tech-dark); color: #fff; padding: 40px 50px; display: flex; justify-content: space-between; align-items: center; border-bottom: 5px solid var(--tech-green); }
        .brand-area h1 { margin: 0; font-size: 24pt; font-weight: 700; letter-spacing: -1px; }
        .brand-area span { color: var(--tech-green); font-family: 'Roboto Mono', monospace; font-size: 10pt; letter-spacing: 2px; text-transform: uppercase; }
        .doc-meta { text-align: right; }
        .doc-type { font-size: 16pt; font-weight: 600; text-transform: uppercase; color: var(--tech-green); }
        .doc-number { font-family: 'Roboto Mono', monospace; font-size: 10pt; color: #ccc; }
        .container { padding: 40px 50px; }
        .info-grid { display: flex; justify-content: space-between; margin-bottom: 40px; }
        .box-info { width: 45%; }
        .box-label { font-size: 8pt; text-transform: uppercase; color: #888; font-weight: 700; margin-bottom: 5px; letter-spacing: 1px; }
        .box-content { font-size: 11pt; line-height: 1.5; }
        .client-card { background: var(--tech-gray); padding: 20px; border-left: 4px solid var(--tech-green); border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { text-align: left; background-color: var(--tech-dark); color: #fff; padding: 12px 15px; font-size: 9pt; text-transform: uppercase; font-family: 'Roboto Mono', monospace; }
        td { padding: 15px; border-bottom: 1px solid #eee; vertical-align: top; }
        .col-desc { width: 70%; }
        .col-price { width: 30%; text-align: right; font-weight: bold; font-family: 'Roboto Mono', monospace;}
        .totals-area { display: flex; justify-content: flex-end; margin-top: 20px; }
        .totals-table { width: 40%; }
        .total-row { display: flex; justify-content: space-between; padding: 5px 0; font-size: 10pt; }
        .total-final { border-top: 2px solid var(--tech-dark); margin-top: 10px; padding-top: 10px; font-weight: 700; font-size: 14pt; color: var(--tech-dark); }
        .footer-section { margin-top: 60px; border-top: 1px solid #eee; padding-top: 30px; display: flex; justify-content: space-between; align-items: flex-end; }
        .legal-text { width: 60%; font-size: 8pt; color: #666; text-align: justify; }
        .social-box { text-align: right; }
        .social-link { text-decoration: none; color: var(--tech-dark); font-weight: bold; font-size: 9pt; margin-left: 15px; display: inline-flex; align-items: center; }
        .qr-placeholder { width: 80px; height: 80px; background: url('https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=Doc_<?php echo $id_doc; ?>') no-repeat center center; background-size: cover; margin-left: auto; margin-top: 10px; border: 1px solid #eee; }
        .security-hash { position: fixed; bottom: 10px; left: 50px; right: 50px; font-family: 'Roboto Mono', monospace; font-size: 6pt; color: #ccc; text-align: center; text-transform: uppercase; }
        @media print { .no-print { display: none; } }
    </style>
    </head>
    <body>
        <div class="no-print" style="background:#eee; padding:20px; text-align:center; border-bottom:1px solid #ccc;">
            <button onclick="window.print()" style="background:#00D185; color:#fff; border:none; padding:15px 30px; font-size:16px; cursor:pointer; font-weight:bold; border-radius:5px;">🖨️ STAMPA / SALVA PDF</button>
            <a href="index.php" style="margin-left:20px; text-decoration:none; color:#333;">← Nuovo Documento</a>
        </div>

        <div class="header-strip">
            <div class="brand-area">
                <h1>M 3.5 S.R.L.</h1>
                <span>AUDIT & LEGAL TECH DIVISION</span>
            </div>
            <div class="doc-meta">
                <div class="doc-type">Nota Pro-Forma</div>
                <div class="doc-number">N. <?php echo $numero_doc; ?></div>
            </div>
        </div>

        <div class="container">
            <div class="info-grid">
                <div class="box-info">
                    <div class="box-label">FORNITORE (PROVIDER)</div>
                    <div class="box-content">
                        <strong>M 3.5 S.R.L. PMI INNOVATIVA</strong><br>
                        Via Soldato Belfi Giuseppe 11<br>
                        85038 Senise (PZ) - Italia<br>
                        P.IVA: 01999520768<br>
                        PEC: m3.5@pec.it
                    </div>
                </div>
                <div class="box-info client-card">
                    <div class="box-label">CLIENTE (DATA SUBJECT)</div>
                    <div class="box-content">
                        <strong><?php echo $dati_doc['cliente_nome']; ?></strong><br>
                        <?php echo $dati_doc['cliente_indirizzo']; ?><br>
                        <strong>C.F./P.IVA: <?php echo $dati_doc['cliente_cf']; ?></strong>
                    </div>
                </div>
            </div>

            <div style="font-size: 10pt; color: #666; margin-bottom: 10px;">
                <strong>Data Emissione:</strong> <?php echo date('d/m/Y'); ?>
            </div>

            <table>
                <thead>
                    <tr>
                        <th class="col-desc">DESCRIZIONE SERVIZIO / ATTIVITÀ</th>
                        <th class="col-price">IMPORTO (€)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(is_array($dati_doc['righe'])) foreach ($dati_doc['righe'] as $riga): ?>
                    <tr>
                        <td>
                            <strong><?php echo $riga['desc']; ?></strong><br>
                            <span style="font-size:9pt; color:#666;"><?php echo $riga['dettaglio']; ?></span>
                        </td>
                        <td class="col-price"><?php echo $riga['prezzo']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="totals-area">
                <div class="totals-table">
                    <div class="total-row">
                        <span>Imponibile:</span>
                        <span>€ <?php echo $dati_doc['totale_imponibile']; ?></span>
                    </div>
                    <div class="total-row">
                        <span>IVA (22%):</span>
                        <span>€ <?php echo $dati_doc['totale_iva']; ?></span>
                    </div>
                    <div class="total-row total-final">
                        <span>TOTALE:</span>
                        <span>€ <?php echo $dati_doc['totale_generale']; ?></span>
                    </div>
                </div>
            </div>

            <div class="footer-section">
                <div class="legal-text">
                    <strong>CAUSALE / NOTE TECNICHE:</strong><br>
                    <?php echo $dati_doc['causale_legale']; ?><br><br>
                    <em>Documento generato digitalmente dal sistema M 3.5 Legal Tech. ID Univoco: <?php echo uniqid(); ?></em>
                </div>
                <div class="social-box">
                    <div class="box-label">CONNECT WITH US</div>
                    <div class="social-icons">
                        <a href="https://piramide35.com" class="social-link">WEB</a>
                        <a href="#" class="social-link">LINKEDIN</a>
                    </div>
                    <div class="qr-placeholder"></div>
                </div>
            </div>
        </div>
        <div class="security-hash">
            DIGITAL HASH: <?php echo hash('sha256', json_encode($dati_doc)); ?> // M3.5-VERIFIED
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>M 3.5 AI Generator + Docs</title>
    <style>
        body { font-family: sans-serif; background: #f4f6f8; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .card { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); width: 100%; max-width: 500px; }
        h2 { margin-top: 0; color: #081014; }
        textarea { width: 100%; height: 100px; padding: 15px; border: 2px solid #eee; border-radius: 5px; font-size: 16px; margin-bottom: 20px; font-family: inherit; box-sizing: border-box;}
        button { background: #00D185; color: white; border: none; padding: 15px 30px; font-size: 18px; border-radius: 5px; cursor: pointer; width: 100%; font-weight: bold; transition: 0.2s; }
        button:hover { background: #00b371; }
        .logo { font-size: 24px; font-weight: bold; margin-bottom: 20px; display: block; color: #081014; }
        .logo span { color: #00D185; }
        .file-drop { border: 2px dashed #ccc; padding: 20px; text-align: center; margin-bottom: 20px; border-radius: 5px; color: #666; cursor: pointer;}
        .file-drop:hover { background: #f9f9f9; border-color: #00D185; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">M 3.5 <span>AI DOCS</span></div>
        <h2>Genera Documento</h2>
        <p style="color:#666; margin-bottom:20px;">Carica file (PDF, Word, Foto) e/o scrivi istruzioni.</p>
        
        <form method="POST" enctype="multipart/form-data">
            
            <div class="file-drop" onclick="document.getElementById('fileInput').click()">
                📎 Clicca per allegare Visure, Preventivi o Foto
                <input type="file" id="fileInput" name="allegati[]" multiple style="display:none;" onchange="alert(this.files.length + ' file selezionati')">
            </div>

            <textarea name="prompt" placeholder="Es: 'Usa la visura allegata per i dati cliente e aggiungi 500 euro per Audit legale...'"></textarea>
            
            <button type="submit">✨ Genera con IA</button>
        </form>
    </div>
</body>
</html>