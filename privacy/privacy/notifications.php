<?php
// notifications.php - Gestore Notifiche WhatsApp (Configurato per Massimo)

function inviaNotificaAdmin($tipo, $cliente, $messaggio, $link_admin) {
    
    // --- 1. CONFIGURAZIONE WHATSAPP (CallMeBot) ---
    $phone_number = '393487564418'; 
    $api_key = '5458750'; 
    
    // Preparazione Testo (Grassetto e Icone)
    $text = "🔔 *M 3.5 ALERT*\n";
    $text .= "👤 *Cliente:* $cliente\n";
    $text .= "💬 *Messaggio:* $messaggio\n";
    $text .= "🔗 *Rispondi:* $link_admin";
    
    // Codifica URL per invio sicuro
    $text_encoded = urlencode($text);
    
    // --- 2. ESECUZIONE INVIO WHATSAPP ---
    if ($tipo == 'WHATSAPP' || $tipo == 'ALL') {
        $url = "https://api.callmebot.com/whatsapp.php?phone=$phone_number&text=$text_encoded&apikey=$api_key";
        
        // Invio silenzioso (senza bloccare la pagina se WhatsApp è lento)
        $ctx = stream_context_create(['http' => ['timeout' => 5]]); // Timeout breve
        @file_get_contents($url, false, $ctx);
    }

    // --- 3. ESECUZIONE INVIO MAIL (Backup di sicurezza) ---
    // Se WhatsApp dovesse fallire, parte comunque la mail
    $email_dest = 'm3.5@pec.it'; 
    $email_mitt = 'info@piramide35.com'; // Assicurati che esista sul server o usa una tua mail reale
    
    if ($tipo == 'EMAIL' || $tipo == 'ALL') {
        $headers = "From: $email_mitt\r\nReply-To: $email_mitt";
        @mail($email_dest, "M 3.5: $cliente", "$messaggio\n\nLink Admin: $link_admin", $headers);
    }
}
?>