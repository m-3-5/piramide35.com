<?php
// dashboard.php - Elenco Pratiche Cliente
session_start();
include 'config.php';

// Se non è loggato, via al login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$nome_utente = $_SESSION['user_nome'];

// Recupera TUTTI i ticket di questo cliente
$stmt = $conn->prepare("SELECT * FROM m35_tickets WHERE user_id = ? ORDER BY id DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$tickets = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | M 3.5</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #eef1f5; margin: 0; }
        .header { background: #081014; color: white; padding: 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 4px solid #00D185; }
        .container { max-width: 800px; margin: 30px auto; padding: 20px; }
        .ticket-card { background: white; padding: 20px; border-radius: 12px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; text-decoration: none; color: #333; transition: transform 0.2s; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .ticket-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); border-left: 5px solid #00D185; }
        .status { font-size: 12px; background: #e0f2f1; color: #00695c; padding: 5px 10px; border-radius: 20px; font-weight: bold; }
        .logout { color: #fff; text-decoration: none; font-size: 14px; opacity: 0.7; }
    </style>
</head>
<body>

    <div class="header">
        <div>Ciao, <strong><?php echo htmlspecialchars($nome_utente); ?></strong></div>
        <a href="login.php" class="logout">Esci</a>
    </div>

    <div class="container">
        <h2 style="color:#081014;">Le tue Pratiche</h2>
        
        <a href="start.php" style="display:block; text-align:center; background:#00D185; color:white; padding:15px; border-radius:10px; text-decoration:none; font-weight:bold; margin-bottom:20px;">+ APRI NUOVA RICHIESTA</a>

        <?php if ($tickets->num_rows > 0): ?>
            <?php while($row = $tickets->fetch_assoc()): ?>
                <a href="ticket.php?t=<?php echo $row['token']; ?>" class="ticket-card">
                    <div>
                        <div style="font-weight:bold; font-size:16px; margin-bottom:5px;">
                            #<?php echo $row['id']; ?> - <?php echo htmlspecialchars($row['oggetto']); ?>
                        </div>
                        <div style="font-size:12px; color:#777;">
                            Creato il: <?php echo date("d/m/Y", strtotime($row['data_creazione'])); ?>
                        </div>
                    </div>
                    <span class="status">APRI CHAT ➤</span>
                </a>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="text-align:center; color:#888;">Nessun ticket presente.</p>
        <?php endif; ?>
    </div>

</body>
</html>