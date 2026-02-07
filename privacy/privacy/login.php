<?php
// login.php - Accesso Area Riservata
session_start();
include 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Cerca l'utente
    $stmt = $conn->prepare("SELECT id, nome, password FROM m35_users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    // Verifica Password (semplice check diretto per ora)
    if ($user && $user['password'] === $password) {
        // LOGIN OK -> Salva sessione
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_nome'] = $user['nome'];
        
        // Vai alla Dashboard
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Email o Password non validi.";
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | M 3.5</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #081014; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; color: white; }
        .login-card { background: white; color: #333; padding: 40px; border-radius: 20px; width: 100%; max-width: 350px; text-align: center; }
        h1 { margin: 0 0 20px 0; font-size: 24px; }
        input { width: 100%; padding: 12px; margin-bottom: 15px; border: 2px solid #eee; border-radius: 8px; box-sizing: border-box; }
        button { background: #00D185; color: white; border: none; width: 100%; padding: 15px; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 16px; }
        .error { color: red; font-size: 14px; margin-bottom: 15px; display: block; }
    </style>
</head>
<body>
    <div class="login-card">
        <h1>Area Clienti M 3.5</h1>
        <?php if($error) echo "<span class='error'>$error</span>"; ?>
        <form method="POST">
            <input type="email" name="email" placeholder="La tua Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">ACCEDI</button>
        </form>
    </div>
</body>
</html>