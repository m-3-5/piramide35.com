<?php
// ticket.php - V4 FINAL (Attachments + Auto-Resize + Email + AI)
include 'config.php';

// --- CONFIGURAZIONE EMAIL ---
//$admin_email = 'startupm3.5@gmail.com'; // LA TUA MAIL (Sostituisci se diversa)
//$mittente_notifica = 'info@inm35.net'; // MEGLIO SE REALE

// 1. VERIFICA ACCESSO
if (!isset($_GET['t'])) die("Accesso Negato.");
$token = $_GET['t'];
$is_admin = (isset($_GET['admin']) && $_GET['admin'] == '1');

// Recupera Ticket
$stmt = $conn->prepare("SELECT * FROM m35_tickets WHERE token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();

if (!$ticket) die("Ticket non trovato.");

// --- FUNZIONE PER RIDIMENSIONARE IMMAGINI ---
function resizeImage($file, $max_width) {
    list($width, $height, $type) = getimagesize($file);
    if ($width <= $max_width) return false; // Non serve ridimensionare

    $ratio = $max_width / $width;
    $new_height = $height * $ratio;

    $src = imagecreatefromstring(file_get_contents($file));
    $dst = imagecreatetruecolor($max_width, $new_height);
    
    // Gestione trasparenza per PNG
    if ($type == IMAGETYPE_PNG) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $max_width, $new_height, $width, $height);
    
    // Salva sopra l'originale
    if ($type == IMAGETYPE_JPEG) imagejpeg($dst, $file, 80); // Qualità 80%
    elseif ($type == IMAGETYPE_PNG) imagepng($dst, $file, 8);
    
    imagedestroy($src);
    imagedestroy($dst);
    return true;
}

// 2. GESTIONE INVIO (TESTO + FILE)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $msg_utente = $_POST['messaggio'] ?? '';
    $sender = $is_admin ? 'ADMIN' : 'CLIENTE';
    $allegato_path = null;
    $gemini_file_part = null; // Per inviare l'immagine a Gemini

    // A. GESTIONE UPLOAD FILE
    if (!empty($_FILES['allegato']['name'])) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $file_name = time() . "_" . basename($_FILES['allegato']['name']);
        $target_file = $upload_dir . $file_name;
        $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        if (move_uploaded_file($_FILES['allegato']['tmp_name'], $target_file)) {
            // Se è immagine, RIDIMENSIONA
            if (in_array($file_type, ['jpg', 'jpeg', 'png'])) {
                resizeImage($target_file, 1000); // Max 1000px larghezza
                
                // Prepara per Gemini (Base64)
                $img_data = file_get_contents($target_file);
                $gemini_file_part = [
                    "inline_data" => [
                        "mime_type" => mime_content_type($target_file),
                        "data" => base64_encode($img_data)
                    ]
                ];
            }
            $allegato_path = $target_file;
            $msg_utente .= " [📎 ALLEGATO: $file_name]";
        }
    }

    if (!empty($msg_utente) || $allegato_path) {
        // B. SALVA MESSAGGIO UTENTE NEL DB
        $stmt = $conn->prepare("INSERT INTO m35_chat (ticket_id, sender, messaggio) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $ticket['id'], $sender, $msg_utente);
        $stmt->execute();

        // C. LOGICA INTELLIGENTE (Solo se scrive il Cliente)
        if (!$is_admin) {
            
            // 1. NOTIFICA EMAIL - dismesso!!
			//vecchio sotto qui
           // $subject = "Nuovo Messaggio M 3.5: " . $ticket['cliente'];
           // $body = "Cliente: " . $ticket['cliente'] . "\nMessaggio: " . $msg_utente . "\n";
            //$body .= "Link Admin: https://piramide35.com/privacy/ticket.php?t=" . $token . "&admin=1";
          //  $headers = "From: $mittente_notifica\r\n";
           // @mail($admin_email, $subject, $body, $headers);
			
			// 1. NOTIFICA MODULARE (Chiama il file esterno)
        require_once 'notifications.php';
        $link_admin = "https://piramide35.com/privacy/ticket.php?t=" . $token . "&admin=1";
        // Invia notifica usando le impostazioni di notifications.php
        inviaNotificaAdmin('ALL', $ticket['cliente'], $msg_utente, $link_admin);

            // 2. RECUPERA STORIA CHAT
            $history_query = "SELECT sender, messaggio FROM m35_chat WHERE ticket_id = " . $ticket['id'] . " ORDER BY id ASC";
            $res = $conn->query($history_query);
            $chat_text = "";
            while($r = $res->fetch_assoc()) $chat_text .= $r['sender'] . ": " . $r['messaggio'] . "\n";

            // 3. CHIAMA GEMINI - dismesso da cancellare
           /* $prompt_text = "Sei M 3.5 AI. Parli con " . $ticket['cliente'] . ". Oggetto: " . $ticket['oggetto'] . ". Rispondi brevemente e professionalmente. Se vedi un allegato o dati sensibili, conferma ricezione. STORIA:\n" . $chat_text; */
			
		// 3. CHIAMA GEMINI (Versione "Smart Chat")
            $prompt_text = "
            RUOLO: Sei l'assistente virtuale avanzato di M 3.5 (Digital & Legal Tech).
            INTERLOCUTORE: Stai parlando con " . $ticket['cliente'] . ".
            OGGETTO: " . $ticket['oggetto'] . ".
            
            STORIA DELLA CHAT (Leggila attentamente):
            $chat_text
            
            ISTRUZIONI DI COMPORTAMENTO (STRETTE):
            1. STOP AI SALUTI: Se nella storia della chat ci siamo già salutati, NON scrivere più 'Buongiorno' o 'Salve'. Vai dritto al punto.
            2. NIENTE FIRME: Non firmarti 'M 3.5 AI' o 'Cordiali saluti' alla fine di ogni messaggio. È una chat continua.
            3. TONO: Sii professionale ma dinamico, sintetico e sveglio.
            4. OBIETTIVO: Cerca di capire l'esigenza del cliente. Fai domande specifiche una alla volta se mancano dettagli.
            5. DATI: Se il cliente ti dà dati (file, numeri), conferma con un semplice 'Ricevuto'.
            
            RISPONDI AL CLIENTE ORA (Max 3 righe):";
            
            $parts = [["text" => $prompt_text]];
            if ($gemini_file_part) $parts[] = $gemini_file_part; // Aggiunge l'immagine se c'è

            $data = ["contents" => [["parts" => $parts]]];
            
            $opts = [ 'http' => [ 'header' => "Content-type: application/json\r\n", 'method' => 'POST', 'content' => json_encode($data) ] ];
            $context  = stream_context_create($opts);
            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3-flash-preview:generateContent?key=" . $gemini_api_key;
            
            $result = @file_get_contents($url, false, $context);
            
            if ($result) {
                $resp = json_decode($result, true);
                $ai_reply = $resp['candidates'][0]['content']['parts'][0]['text'] ?? "Grazie, messaggio ricevuto.";
                
                // SALVA RISPOSTA IA
                $stmt = $conn->prepare("INSERT INTO m35_chat (ticket_id, sender, messaggio) VALUES (?, 'AI', ?)");
                $stmt->bind_param("is", $ticket['id'], $ai_reply);
                $stmt->execute();
            } 
        }
        
        // Refresh
        header("Location: ticket.php?t=" . $token . ($is_admin ? "&admin=1" : ""));
        exit;
    }
}

// 3. LEGGI MESSAGGI
$chat_res = $conn->query("SELECT * FROM m35_chat WHERE ticket_id = " . $ticket['id'] . " ORDER BY id ASC");
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Ticket #<?php echo $ticket['id']; ?> | M 3.5</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #eef1f5; margin: 0; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        
        .header { 
            background: <?php echo $is_admin ? '#d32f2f' : '#081014'; ?>; 
            color: white; padding: 15px; flex-shrink: 0; display: flex; justify-content: space-between; align-items: center; border-bottom: 4px solid #00D185; 
        }
        .header h1 { margin: 0; font-size: 16px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .header-badges { display: flex; gap: 10px; align-items: center; }
        .badge { font-size: 10px; padding: 3px 6px; border-radius: 4px; background: rgba(255,255,255,0.2); text-transform: uppercase; }

        .chat-container { 
            flex: 1; 
            overflow-y: auto; 
            padding: 15px; 
            display: flex; 
            flex-direction: column; 
            gap: 15px; 
            scroll-behavior: smooth;
            /* AGGIUNTO: Spazio extra in basso per non far coprire l'ultimo messaggio dalla barra */
            padding-bottom: 150px; 
        }
        
        .msg { max-width: 85%; padding: 10px 14px; border-radius: 12px; font-size: 14px; line-height: 1.4; position: relative; word-wrap: break-word; }
        
        /* COLORI MESSAGGI */
        <?php if ($is_admin): ?>
            .msg-CLIENTE { align-self: flex-start; background: #fff; border: 1px solid #ccc; border-bottom-left-radius: 2px; }
            .msg-AI { align-self: flex-start; background: #e0f2f1; color: #00695c; border: 1px solid #b2dfdb; margin-left: 20px; }
            .msg-ADMIN { align-self: flex-end; background: #d32f2f; color: white; border-bottom-right-radius: 2px; }
        <?php else: ?>
            .msg-CLIENTE { align-self: flex-end; background: #00D185; color: white; border-bottom-right-radius: 2px; }
            .msg-AI { align-self: flex-start; background: #e0f2f1; color: #00695c; border-bottom-left-radius: 2px; }
            .msg-ADMIN { align-self: flex-start; background: #081014; color: white; border-bottom-left-radius: 2px; }
        <?php endif; ?>

        .sender-name { font-size: 9px; opacity: 0.8; margin-bottom: 3px; display: block; font-weight: bold; text-transform: uppercase; }
        
        .attachment-link { display: inline-block; margin-top: 5px; background: rgba(0,0,0,0.05); padding: 5px; border-radius: 4px; color: inherit; text-decoration: none; font-weight: bold; font-size: 12px; }

        /* INPUT AREA */
        .input-area { 
            position: fixed;   /* LA INCHIODA IN BASSO */
            bottom: 0; 
            left: 0; 
            right: 0;
            background: white; 
            padding: 10px; 
            border-top: 1px solid #ddd; 
            display: flex; 
            gap: 8px; 
            align-items: center; 
            z-index: 9999;     /* Assicura che sia SOPRA a tutto */
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1); /* Ombrellina per staccarla */
        }
        
        .file-upload { position: relative; overflow: hidden; cursor: pointer; background: #eee; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .file-upload input { position: absolute; font-size: 100px; opacity: 0; right: 0; top: 0; cursor: pointer; }
        
        textarea { flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 20px; resize: none; height: 40px; font-family: inherit; font-size: 14px; outline: none; }
        textarea:focus { border-color: #00D185; }
        
        button { background: <?php echo $is_admin ? '#d32f2f' : '#00D185'; ?>; color: white; border: none; width: 40px; height: 40px; border-radius: 50%; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 16px; }
    </style>
</head>
<body>

    <div class="header">
        <h1><?php echo htmlspecialchars($ticket['oggetto']); ?></h1>
        <div class="header-badges">
            <?php if($is_admin) echo '<span class="badge" style="background:white; color:#d32f2f">ADMIN</span>'; ?>
            <a href="javascript:window.print()" style="text-decoration:none; color:white;">🖨️</a>
        </div>
    </div>

    <div class="chat-container" id="chatBox">
        <?php while($row = $chat_res->fetch_assoc()): ?>
            <div class="msg msg-<?php echo $row['sender']; ?>">
                <span class="sender-name"><?php echo $row['sender']; ?></span>
                
                <?php 
                // Rendi cliccabili i link agli allegati
                $text = nl2br(htmlspecialchars($row['messaggio']));
				// --- INCOLLA QUI LA TUA RIGA ---
                $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
                // -------------------------------
                $text = preg_replace('/\[📎 ALLEGATO: (.*?)\]/', '<a href="uploads/$1" target="_blank" class="attachment-link">📎 Apri File: $1</a>', $text);
                echo $text; 
                ?>
            </div>
        <?php endwhile; ?>
    </div>

    <form class="input-area" method="POST" enctype="multipart/form-data">
        
        <div class="file-upload">
            📎
            <input type="file" name="allegato" onchange="this.parentElement.style.background='#00D185'; this.parentElement.style.color='white';">
        </div>

        <textarea name="messaggio" placeholder="Scrivi..." required></textarea>
        <button type="submit">➤</button>
    </form>

    <script>
        // Scroll to bottom
        var chatBox = document.getElementById("chatBox");
        chatBox.scrollTop = chatBox.scrollHeight;
        
        // Se alleghi un file, il campo testo non è più obbligatorio
        document.querySelector('input[type="file"]').addEventListener('change', function() {
            document.querySelector('textarea').removeAttribute('required');
            document.querySelector('textarea').placeholder = "File allegato. Premi invio ->";
        });
    </script>
</body>
</html>