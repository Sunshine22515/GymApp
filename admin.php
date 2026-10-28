<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
require __DIR__ . '/config.php';
requireRole('admin');

$success = '';
$error = '';
$studentId = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT) ?: 0;
$students = $pdo->query("SELECT id, full_name, username FROM users WHERE role = 'student' ORDER BY full_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        $error = 'A página expirou. Atualiza e tenta novamente.';
    } else {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'create_student') {
            $name = trim((string) ($_POST['full_name'] ?? ''));
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');

            if ($name === '' || mb_strlen($name) > 100) {
                $error = 'Indica o nome do aluno (até 100 caracteres).';
            } elseif (!preg_match('/^[\p{L}\p{N}_.-]{3,50}$/u', $username)) {
                $error = 'O utilizador deve ter 3 a 50 letras, números, ponto, hífen ou underscore.';
            } elseif (strlen($password) < 10) {
                $error = 'A senha inicial deve ter pelo menos 10 caracteres.';
            } else {
                try {
                    $pdo->beginTransaction();
                    $statement = $pdo->prepare(
                        "INSERT INTO users (full_name, username, password_hash, role)
                         VALUES (:name, :username, :password_hash, 'student')"
                    );
                    $statement->execute([
                        'name' => $name,
                        'username' => $username,
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    ]);
                    $newStudentId = (int) $pdo->lastInsertId();
                    $statement = $pdo->prepare('INSERT INTO workout_plans (student_id) VALUES (:student_id)');
                    $statement->execute(['student_id' => $newStudentId]);
                    $pdo->commit();
                    header('Location: admin.php?student_id=' . $newStudentId . '&created=1');
                    exit;
                } catch (PDOException $exception) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log('Falha ao criar aluno: ' . $exception->getMessage());
                    $error = 'Não foi possível criar o aluno. Confere se o nome de utilizador já existe.';
                }
            }
        } else {
            $studentId = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT) ?: 0;
            $statement = $pdo->prepare("SELECT id FROM users WHERE id = :id AND role = 'student'");
            $statement->execute(['id' => $studentId]);
            $studentExists = (bool) $statement->fetchColumn();

            if (!$studentExists) {
                $error = 'Seleciona um aluno válido.';
            } elseif ($action === 'save_plan') {
                $title = trim((string) ($_POST['title'] ?? ''));
                if ($title === '' || mb_strlen($title) > 120) {
                    $error = 'O título deve ter entre 1 e 120 caracteres.';
                } else {
                    $statement = $pdo->prepare('UPDATE workout_plans SET title = :title WHERE student_id = :student_id');
                    $statement->execute(['title' => $title, 'student_id' => $studentId]);
                    header('Location: admin.php?student_id=' . $studentId . '&saved=1');
                    exit;
                }
            } elseif ($action === 'save_exercise') {
                $exerciseId = filter_var($_POST['exercise_id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
                $day = trim((string) ($_POST['training_day'] ?? ''));
                $exerciseName = trim((string) ($_POST['exercise_name'] ?? ''));
                $sets = filter_var($_POST['sets_count'] ?? null, FILTER_VALIDATE_INT);
                $reps = trim((string) ($_POST['reps_target'] ?? ''));
                $notes = trim((string) ($_POST['notes'] ?? ''));

                if ($day === '' || mb_strlen($day) > 80 || $exerciseName === '' || mb_strlen($exerciseName) > 120) {
                    $error = 'Preenche o dia e o nome do exercício.';
                } elseif ($sets === false || $sets < 1 || $sets > 20) {
                    $error = 'O número de séries deve estar entre 1 e 20.';
                } elseif ($reps === '' || mb_strlen($reps) > 30 || mb_strlen($notes) > 500) {
                    $error = 'Confere repetições e notas (máximo 500 caracteres nas notas).';
                } else {
                    $statement = $pdo->prepare('SELECT id FROM workout_plans WHERE student_id = :student_id');
                    $statement->execute(['student_id' => $studentId]);
                    $planId = (int) $statement->fetchColumn();

                    if ($exerciseId > 0) {
                        // A condição plan_id impede editar exercícios de outra ficha.
                        $statement = $pdo->prepare(
                            'UPDATE plan_exercises
                             SET training_day = :day, exercise_name = :name, sets_count = :sets,
                                 reps_target = :reps, notes = :notes
                             WHERE id = :id AND plan_id = :plan_id'
                        );
                        $statement->execute([
                            'day' => $day, 'name' => $exerciseName, 'sets' => $sets,
                            'reps' => $reps, 'notes' => $notes,
                            'id' => $exerciseId, 'plan_id' => $planId,
                        ]);
                    } else {
                        $statement = $pdo->prepare(
                            'INSERT INTO plan_exercises (plan_id, training_day, exercise_name, sets_count, reps_target, notes)
                             VALUES (:plan_id, :day, :name, :sets, :reps, :notes)'
                        );
                        $statement->execute([
                            'plan_id' => $planId, 'day' => $day, 'name' => $exerciseName,
                            'sets' => $sets, 'reps' => $reps, 'notes' => $notes,
                        ]);
                    }
                    header('Location: admin.php?student_id=' . $studentId . '&saved=1');
                    exit;
                }
            } elseif ($action === 'delete_exercise') {
                $exerciseId = filter_var($_POST['exercise_id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
                $statement = $pdo->prepare(
                    'DELETE pe FROM plan_exercises pe
                     JOIN workout_plans wp ON wp.id = pe.plan_id
                     WHERE pe.id = :exercise_id AND wp.student_id = :student_id'
                );
                $statement->execute(['exercise_id' => $exerciseId, 'student_id' => $studentId]);
                header('Location: admin.php?student_id=' . $studentId . '&deleted=1');
                exit;
            }
        }
    }
}

if (isset($_GET['created'])) $success = 'Conta do aluno criada. Podes montar a ficha abaixo.';
if (isset($_GET['saved'])) $success = 'Ficha guardada.';
if (isset($_GET['deleted'])) $success = 'Exercício removido da ficha.';

$selectedStudent = null;
$plan = null;
$exercises = [];
$editingExercise = null;
if ($studentId > 0) {
    $statement = $pdo->prepare("SELECT id, full_name, username FROM users WHERE id = :id AND role = 'student'");
    $statement->execute(['id' => $studentId]);
    $selectedStudent = $statement->fetch() ?: null;
    if ($selectedStudent) {
        $statement = $pdo->prepare('SELECT id, title FROM workout_plans WHERE student_id = :student_id');
        $statement->execute(['student_id' => $studentId]);
        $plan = $statement->fetch() ?: null;
        if ($plan) {
            $statement = $pdo->prepare('SELECT * FROM plan_exercises WHERE plan_id = :plan_id ORDER BY training_day, position, id');
            $statement->execute(['plan_id' => $plan['id']]);
            $exercises = $statement->fetchAll();
            $editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT) ?: 0;
            if ($editId > 0) {
                $statement = $pdo->prepare('SELECT * FROM plan_exercises WHERE id = :id AND plan_id = :plan_id');
                $statement->execute(['id' => $editId, 'plan_id' => $plan['id']]);
                $editingExercise = $statement->fetch() ?: null;
            }
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
  <link rel="icon" href="assets/icon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="assets/styles.css">
  <title>Painel administrativo — REP•LOG</title>
</head>
<body>
  <header class="app-header">
    <a class="brand" href="admin.php">REP<span>•</span>LOG</a>
    <div class="header-user"><span>Olá, <?= escapeHtml($_SESSION['full_name'] ?? '') ?></span><a href="sair.php">Sair</a></div>
  </header>
  <main class="page-layout">
    <section class="page-heading">
      <p class="eyebrow">Área do administrador</p>
      <h1>Fichas dos alunos</h1>
      <p class="muted">Cria contas e prepara uma ficha individual para cada aluno.</p>
    </section>

    <?php if ($success !== ''): ?><p class="notice success" role="status"><?= escapeHtml($success) ?></p><?php endif; ?>
    <?php if ($error !== ''): ?><p class="notice error" role="alert"><?= escapeHtml($error) ?></p><?php endif; ?>

    <div class="admin-grid">
      <aside class="panel">
        <h2>Alunos</h2>
        <?php if (!$students): ?><p class="muted">Ainda não há alunos. Cria a primeira conta abaixo.</p><?php endif; ?>
        <nav class="student-list" aria-label="Lista de alunos">
          <?php foreach ($students as $student): ?>
            <a class="student-link <?= (int) $student['id'] === $studentId ? 'selected' : '' ?>" href="admin.php?student_id=<?= (int) $student['id'] ?>">
              <strong><?= escapeHtml($student['full_name']) ?></strong>
              <span>@<?= escapeHtml($student['username']) ?></span>
            </a>
          <?php endforeach; ?>
        </nav>
        <details class="create-student" <?= isset($_POST['action']) && $_POST['action'] === 'create_student' && $error !== '' ? 'open' : '' ?>>
          <summary>＋ Criar conta de aluno</summary>
          <form method="post" action="admin.php" class="stack-form">
            <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
            <input type="hidden" name="action" value="create_student">
            <label for="new_name">Nome do aluno</label>
            <input id="new_name" name="full_name" required maxlength="100" value="<?= escapeHtml($_POST['full_name'] ?? '') ?>">
            <label for="new_username">Utilizador</label>
            <input id="new_username" name="username" required minlength="3" maxlength="50" value="<?= escapeHtml($_POST['username'] ?? '') ?>">
            <label for="new_password">Senha inicial</label>
            <input id="new_password" name="password" type="password" required minlength="10" autocomplete="new-password">
            <button class="button primary">Criar aluno</button>
          </form>
        </details>
      </aside>

      <section class="panel plan-panel">
        <?php if (!$selectedStudent || !$plan): ?>
          <div class="empty-state"><h2>Seleciona um aluno</h2><p>Escolhe alguém na lista para veres e editares a ficha individual.</p></div>
        <?php else: ?>
          <div class="section-title">
            <div><p class="eyebrow">Ficha individual</p><h2><?= escapeHtml($selectedStudent['full_name']) ?></h2><p class="muted">Utilizador: @<?= escapeHtml($selectedStudent['username']) ?></p></div>
          </div>
          <form method="post" action="admin.php" class="plan-title-form">
            <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
            <input type="hidden" name="action" value="save_plan">
            <input type="hidden" name="student_id" value="<?= (int) $studentId ?>">
            <label for="plan_title">Nome da ficha</label>
            <div class="inline-form"><input id="plan_title" name="title" maxlength="120" required value="<?= escapeHtml($plan['title']) ?>"><button class="button secondary">Guardar nome</button></div>
          </form>

          <div class="exercise-list">
            <?php if (!$exercises): ?><p class="muted">Esta ficha ainda está vazia. Adiciona o primeiro exercício abaixo.</p><?php endif; ?>
            <?php $lastDay = null; foreach ($exercises as $exercise): ?>
              <?php if ($lastDay !== $exercise['training_day']): $lastDay = $exercise['training_day']; ?>
                <h3 class="day-heading"><?= escapeHtml($lastDay) ?></h3>
              <?php endif; ?>
              <article class="exercise-row">
                <div><strong><?= escapeHtml($exercise['exercise_name']) ?></strong><span class="muted"><?= (int) $exercise['sets_count'] ?> séries × <?= escapeHtml($exercise['reps_target']) ?> reps</span>
                  <?php if ($exercise['notes'] !== ''): ?><small><?= escapeHtml($exercise['notes']) ?></small><?php endif; ?>
                </div>
                <div class="row-actions">
                  <a class="button secondary small-button" href="admin.php?student_id=<?= (int) $studentId ?>&edit=<?= (int) $exercise['id'] ?>">Editar</a>
                  <form method="post" action="admin.php" data-confirm="Remover este exercício da ficha?">
                    <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
                    <input type="hidden" name="action" value="delete_exercise">
                    <input type="hidden" name="student_id" value="<?= (int) $studentId ?>">
                    <input type="hidden" name="exercise_id" value="<?= (int) $exercise['id'] ?>">
                    <button class="button danger small-button">Apagar</button>
                  </form>
                </div>
              </article>
            <?php endforeach; ?>
          </div>

          <section class="edit-section" id="exercise-form">
            <h3><?= $editingExercise ? 'Editar exercício' : 'Adicionar exercício' ?></h3>
            <form method="post" action="admin.php?student_id=<?= (int) $studentId ?>#exercise-form" class="exercise-form">
              <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
              <input type="hidden" name="action" value="save_exercise">
              <input type="hidden" name="student_id" value="<?= (int) $studentId ?>">
              <input type="hidden" name="exercise_id" value="<?= (int) ($editingExercise['id'] ?? 0) ?>">
              <div><label for="training_day">Dia / treino</label><input id="training_day" name="training_day" required maxlength="80" placeholder="Ex.: Treino A — membros superiores" value="<?= escapeHtml($editingExercise['training_day'] ?? '') ?>"></div>
              <div><label for="exercise_name">Exercício</label><input id="exercise_name" name="exercise_name" required maxlength="120" placeholder="Ex.: Supino com halteres" value="<?= escapeHtml($editingExercise['exercise_name'] ?? '') ?>"></div>
              <div class="form-pair">
                <div><label for="sets_count">Séries</label><input id="sets_count" name="sets_count" type="number" min="1" max="20" required value="<?= escapeHtml($editingExercise['sets_count'] ?? 3) ?>"></div>
                <div><label for="reps_target">Repetições</label><input id="reps_target" name="reps_target" maxlength="30" required placeholder="8-12" value="<?= escapeHtml($editingExercise['reps_target'] ?? '8-12') ?>"></div>
              </div>
              <div><label for="notes">Notas (opcional)</label><textarea id="notes" name="notes" maxlength="500" rows="3" placeholder="Descanso, técnica ou observações"><?= escapeHtml($editingExercise['notes'] ?? '') ?></textarea></div>
              <div class="row-actions"><button class="button primary"><?= $editingExercise ? 'Guardar alterações' : 'Adicionar à ficha' ?></button>
                <?php if ($editingExercise): ?><a class="button secondary" href="admin.php?student_id=<?= (int) $studentId ?>">Cancelar edição</a><?php endif; ?>
              </div>
            </form>
          </section>
        <?php endif; ?>
      </section>
    </div>
  </main>
  <script src="assets/app.js" defer></script>
</body>
</html>
