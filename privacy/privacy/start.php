<?php
// start.php - Landing Page con CREAZIONE AUTOMATICA UTENTE
include 'config.php';
require_once 'notifications.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']); // NUOVO CAMPO FONDAMENTALE
    $problema = $_POST['problema'];
    
    // --- 1. GESTIONE UTENTE (CRM LOGIC) ---
    // Controlliamo se la mail esiste già nel database
    $stmt = $conn->prepare("SELECT id, nome FROM m35_users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // CLIENTE ESISTENTE
        $user_id = $row['id'];
        $status_cliente = "RECURRENT"; // Lo diciamo all'IA
        // (Opzionale: potremmo aggiornare il nome se è cambiato, ma per ora teniamo il vecchio)
    } else {
        // NUOVO CLIENTE - Generiamo Password
        $raw_pass = substr(str_shuffle("0123456789abcdefhkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ"), 0, 8); // Pass casuale 8 caratteri
        
        $stmt = $conn->prepare("INSERT INTO m35_users (email, nome, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $email, $nome, $raw_pass); // Salviamo la pass (in chiaro per ora per semplicità, poi la criptiamo)
        $stmt->execute();
        $user_id = $conn->insert_id;
        $status_cliente = "NEW"; 
    }
    // --- 2. CREAZIONE TICKET ---
    $token = bin2hex(random_bytes(16));
    $oggetto_auto = "Richiesta Web: " . substr($problema, 0, 30) . "..."; 
    
    // Inseriamo anche lo user_id per collegarlo al profilo
    $stmt = $conn->prepare("INSERT INTO m35_tickets (token, user_id, cliente, oggetto) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("siss", $token, $user_id, $nome, $oggetto_auto);
    $stmt->execute();
    $ticket_id = $stmt->insert_id;

    // --- 3. PRIMO MESSAGGIO ---
    $stmt = $conn->prepare("INSERT INTO m35_chat (ticket_id, sender, messaggio) VALUES (?, 'CLIENTE', ?)");
    $stmt->bind_param("is", $ticket_id, $problema);
    $stmt->execute();

    // --- 4. NOTIFICHE (WHATSAPP + MAIL) ---
    // Avvisiamo te che c'è un ticket (specificando se è un cliente nuovo o vecchio)
    $tipo_cliente_str = ($status_cliente == 'NEW') ? "🆕 NUOVO CLIENTE" : "🔄 CLIENTE ABITUALE";
    $link_admin = "https://piramide35.com/privacy/ticket.php?t=" . $token . "&admin=1";
    
    inviaNotificaAdmin('ALL', "$nome ($tipo_cliente_str)", $problema, $link_admin);

    // --- 5. ATTIVAZIONE IA (RISPOSTA IMMEDIATA) ---
    // Istruiamo l'IA in base allo status del cliente
    $context_prompt = "";
    if ($status_cliente == 'NEW') {
		// Qui passiamo la password all'IA
        $context_prompt = "È un NUOVO cliente. Benvenuto. IMPORTANTE: Scrivi nel messaggio la sua PASSWORD per l'Area Riservata: '$raw_pass'. Digli di segnarsela.";
    // questo è da cancellare     $context_prompt = "È un NUOVO cliente. Dagli il benvenuto in M 3.5 Digital Solutions. Sii accogliente.";
    } else {
        $context_prompt = "È un cliente ABITUALE (Bentornato). Sii efficiente e diretto, ci conosciamo già.";
    }

    $system_prompt = "
    Sei l'Assistente AI di M 3.5.
    Interlocutore: $nome.
    Status: $context_prompt
    Richiesta: '$problema'.
    
    TUA MISSIONE:
    Rispondi in modo professionale. Conferma l'apertura del ticket #$ticket_id.
    Chiedi se ha allegati o dettagli immediati da aggiungere.
    ";

    $data = ["contents" => [["parts" => [["text" => $system_prompt]]]]];
    $options = [ 'http' => [ 'header' => "Content-type: application/json\r\n", 'method' => 'POST', 'content' => json_encode($data) ] ];
    $context  = stream_context_create($options);
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3-flash-preview:generateContent?key=" . $gemini_api_key;
    
    $result = @file_get_contents($url, false, $context);
    
    if ($result) {
        $response = json_decode($result, true);
        $ai_reply = $response['candidates'][0]['content']['parts'][0]['text'];

        $stmt = $conn->prepare("INSERT INTO m35_chat (ticket_id, sender, messaggio) VALUES (?, 'AI', ?)");
        $stmt->bind_param("is", $ticket_id, $ai_reply);
        $stmt->execute();
    }

    // --- 6. REDIRECT ALLA CHAT ---
    header("Location: ticket.php?t=" . $token);
    exit;
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>M 3.5 Assistenza AI</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #081014; color: white; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .chat-card { background: white; color: #333; width: 100%; max-width: 400px; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,209,133,0.2); }
        .header { background: #081014; padding: 20px; border-bottom: 4px solid #00D185; }
        .header h1 { margin: 0; font-size: 20px; }
        .header span { color: #00D185; font-size: 12px; font-weight: bold; }
        
        .body { padding: 30px; }
        .ai-msg { background: #e0f2f1; color: #00695c; padding: 15px; border-radius: 10px; border-bottom-left-radius: 0; margin-bottom: 20px; position: relative; font-size: 14px; line-height: 1.4; }
        .ai-msg::before { content: "M 3.5 AI"; position: absolute; top: -20px; left: 0; font-size: 10px; color: white; opacity: 0.7; }

        input, textarea { width: 100%; padding: 15px; margin-bottom: 15px; border: 2px solid #eee; border-radius: 10px; box-sizing: border-box; font-family: inherit; }
        input:focus, textarea:focus { border-color: #00D185; outline: none; }
        
        button { background: #00D185; color: white; border: none; width: 100%; padding: 15px; border-radius: 10px; font-weight: bold; font-size: 16px; cursor: pointer; transition: 0.2s; }
        button:hover { background: #00b371; }
    </style>
</head>
<body>

    <div class="chat-card">
        <div class="header">
            <span>BENVENUTO IN</span>
            <h1>M 3.5 SUPPORT</h1>
        </div>
        <div class="body">
            <div class="ai-msg">
                Ciao! Sono l'IA di M 3.5. 🤖<br>
                Identificati e descrivi la tua richiesta. Se sei già nostro cliente, ti riconoscerò dalla mail.
            </div>

            <form method="POST">
                <input type="text" name="nome" placeholder="Nome Completo" required>
                <input type="email" name="email" placeholder="La tua Email" required>
                <textarea name="problema" placeholder="Come possiamo aiutarti oggi?" rows="3" required></textarea>
                
                <button type="submit">AVVIA ASSISTENZA ➤</button>
            </form>
        </div>
    </div>

</body>
</html>