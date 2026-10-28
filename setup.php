<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
require __DIR__ . '/config.php';

$count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($count > 0) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['full_name'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!verifyCsrfToken()) {
        $error = 'A página expirou. Atualiza e tenta novamente.';
    } elseif ($name === '' || mb_strlen($name) > 100) {
        $error = 'Indica o teu nome (até 100 caracteres).';
    } elseif (!preg_match('/^[\p{L}\p{N}_.-]{3,50}$/u', $username)) {
        $error = 'O utilizador deve ter 3 a 50 letras, números, ponto, hífen ou underscore.';
    } elseif (strlen($password) < 10) {
        $error = 'A senha deve ter pelo menos 10 caracteres.';
    } else {
        // Esta página só cria o administrador se ainda não existir nenhuma conta.
        try {
            $statement = $pdo->prepare(
                "INSERT INTO users (full_name, username, password_hash, role)
                 VALUES (:name, :username, :password_hash, 'admin')"
            );
            $statement->execute([
                'name' => $name,
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $pdo->lastInsertId();
            $_SESSION['full_name'] = $name;
            $_SESSION['role'] = 'admin';
            header('Location: admin.php');
            exit;
        } catch (PDOException $exception) {
            error_log('Falha ao criar administrador: ' . $exception->getMessage());
            $error = 'Não foi possível criar a conta. Confere os dados e tenta novamente.';
        }
    }
}
?><!doctype html>
<html lang="pt-PT">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#101411">
  <link rel="manifest" href="manifest.webmanifest">
  <link rel="stylesheet" href="assets/styles.css">
  <title>Configurar administrador — REP•LOG</title>
</head>
<body>
  <main class="auth-layout">
    <a class="brand" href="index.php">REP<span>•</span>LOG</a>
    <section class="panel auth-panel">
      <p class="eyebrow">Configuração inicial</p>
      <h1>Conta de administrador</h1>
      <p class="muted">Esta conta poderá criar alunos e editar as fichas de treino.</p>
      <?php if ($error !== ''): ?><p class="notice error" role="alert"><?= escapeHtml($error) ?></p><?php endif; ?>
      <form method="post" action="setup.php">
        <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
        <label for="full_name">Nome</label>
        <input id="full_name" name="full_name" autocomplete="name" required maxlength="100">
        <label for="username">Nome de utilizador</label>
        <input id="username" name="username" autocomplete="username" required minlength="3" maxlength="50">
        <label for="password">Senha (mínimo 10 caracteres)</label>
        <input id="password" name="password" type="password" autocomplete="new-password" required minlength="10">
        <button class="button primary full-width">Criar administrador</button>
      </form>
    </section>
  </main>
  <script src="assets/app.js" defer></script>
</body>
</html>
