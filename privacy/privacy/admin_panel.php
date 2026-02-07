<?php
// admin_panel.php - Generatore Ticket
include 'config.php';

// LISTA ULTIMI TICKET
$res = $conn->query("SELECT * FROM m35_tickets ORDER BY id DESC LIMIT 5");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente = $_POST['cliente'];
    $oggetto = $_POST['oggetto'];
    $token = bin2hex(random_bytes(16));

    $stmt = $conn->prepare("INSERT INTO m35_tickets (token, cliente, oggetto) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $token, $cliente, $oggetto);
    $stmt->execute();
    
    // Refresh per vedere il nuovo ticket
    header("Location: admin_panel.php"); 
    exit;
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>M 3.5 Admin Panel</title>
    <style>
        body { font-family: sans-serif; padding: 40px; background: #f4f6f8; }
        .card { background: white; padding: 30px; border-radius: 8px; max-width: 800px; margin: 0 auto; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        input, button { width: 100%; padding: 10px; margin-bottom: 15px; box-sizing: border-box; }
        button { background: #00D185; color: white; border: none; font-weight: bold; cursor: pointer; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; border-bottom: 1px solid #eee; text-align: left; font-size: 14px; }
        th { background: #081014; color: white; }
        
        .btn-link { background: #eee; text-decoration: none; color: #333; padding: 5px 10px; border-radius: 4px; font-size: 12px; margin-right: 5px; display: inline-block; }
        .btn-admin { background: #d32f2f; color: white; }
        .btn-client { background: #00D185; color: white; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Nuovo Ticket</h2>
        <form method="POST">
            <input type="text" name="cliente" placeholder="Nome Cliente (es. Rosalia)" required>
            <input type="text" name="oggetto" placeholder="Oggetto (es. Amazon #171)" required>
            <button type="submit">Genera Ticket</button>
        </form>

        <h3>Ultimi Ticket Attivi</h3>
        <table>
            <tr>
                <th>ID</th>
                <th>Cliente</th>
                <th>Oggetto</th>
                <th>Azioni Rapide</th>
            </tr>
            <?php while($row = $res->fetch_assoc()): ?>
            <tr>
                <td>#<?php echo $row['id']; ?></td>
                <td><?php echo $row['cliente']; ?></td>
                <td><?php echo $row['oggetto']; ?></td>
                <td>
                    <a href="ticket.php?t=<?php echo $row['token']; ?>&admin=1" class="btn-link btn-admin" target="_blank">Entra come Admin</a>
                    
                    <a href="ticket.php?t=<?php echo $row['token']; ?>" class="btn-link btn-client" target="_blank">Link Cliente (Copia)</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</body>
</html>