<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
require __DIR__ . '/config.php';

if (!empty($_SESSION['user_id'])) {
    redirectToRoleHome();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!verifyCsrfToken()) {
        $error = 'A página expirou. Atualiza e tenta novamente.';
    } else {
        $statement = $pdo->prepare('SELECT id, full_name, username, password_hash, role FROM users WHERE username = :username');
        $statement->execute(['username' => $username]);
        $user = $statement->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            redirectToRoleHome();
        }
        $error = 'Utilizador ou senha incorretos.';
    }
}
?><!doctype html>
<html lang="pt-PT">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#101411">
  <link rel="manifest" href="manifest.webmanifest">
  <link rel="icon" href="assets/icon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="assets/styles.css">
  <title>Entrar — REP•LOG</title>
</head>
<body>
  <main class="auth-layout">
    <a class="brand" href="index.php">REP<span>•</span>LOG</a>
    <section class="panel auth-panel">
      <p class="eyebrow">O teu treino, organizado</p>
      <h1>Entrar</h1>
      <p class="muted">Acede à tua área de treino.</p>
      <?php if ($error !== ''): ?><p class="notice error" role="alert"><?= escapeHtml($error) ?></p><?php endif; ?>
      <form method="post" action="index.php">
        <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
        <label for="username">Nome de utilizador</label>
        <input id="username" name="username" autocomplete="username" required maxlength="50" value="<?= escapeHtml($_POST['username'] ?? '') ?>">
        <label for="password">Senha</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <button class="button primary full-width">Entrar</button>
      </form>
      <p class="small muted">O primeiro acesso? <a href="setup.php">Criar a conta de administrador</a></p>
    </section>
  </main>
  <script src="assets/app.js" defer></script>
</body>
</html>
