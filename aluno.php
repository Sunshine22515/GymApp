<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
require __DIR__ . '/config.php';
requireRole('student');

$studentId = (int) $_SESSION['user_id'];
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $exerciseId = filter_var($_POST['exercise_id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
    $setNumber = filter_var($_POST['set_number'] ?? null, FILTER_VALIDATE_INT);
    $repsDone = filter_var($_POST['reps_done'] ?? null, FILTER_VALIDATE_INT);
    $weight = filter_var($_POST['weight_kg'] ?? null, FILTER_VALIDATE_FLOAT);

    if (!verifyCsrfToken()) {
        $error = 'A página expirou. Atualiza e tenta novamente.';
    } elseif ($setNumber === false || $setNumber < 1 || $setNumber > 99
        || $repsDone === false || $repsDone < 1 || $repsDone > 999
        || $weight === false || $weight < 0 || $weight > 9999) {
        $error = 'Confere o número da série, as repetições e o peso.';
    } else {
        // Esta consulta garante que o exercício pertence à ficha do aluno autenticado.
        $statement = $pdo->prepare(
            'SELECT pe.id FROM plan_exercises pe
             JOIN workout_plans wp ON wp.id = pe.plan_id
             WHERE pe.id = :exercise_id AND wp.student_id = :student_id'
        );
        $statement->execute(['exercise_id' => $exerciseId, 'student_id' => $studentId]);
        if (!$statement->fetchColumn()) {
            http_response_code(404);
            $error = 'Esse exercício não pertence à tua ficha.';
        } else {
            $statement = $pdo->prepare(
                'INSERT INTO training_logs (student_id, exercise_id, set_number, reps_done, weight_kg)
                 VALUES (:student_id, :exercise_id, :set_number, :reps_done, :weight_kg)'
            );
            $statement->execute([
                'student_id' => $studentId,
                'exercise_id' => $exerciseId,
                'set_number' => $setNumber,
                'reps_done' => $repsDone,
                'weight_kg' => number_format($weight, 2, '.', ''),
            ]);
            header('Location: aluno.php?saved=1#exercise-' . $exerciseId);
            exit;
        }
    }
}

$statement = $pdo->prepare('SELECT id, title FROM workout_plans WHERE student_id = :student_id');
$statement->execute(['student_id' => $studentId]);
$plan = $statement->fetch() ?: null;
$exercises = [];
if ($plan) {
    $statement = $pdo->prepare(
        'SELECT pe.* FROM plan_exercises pe WHERE pe.plan_id = :plan_id ORDER BY pe.training_day, pe.position, pe.id'
    );
    $statement->execute(['plan_id' => $plan['id']]);
    $exercises = $statement->fetchAll();
}

$logsByExercise = [];
if ($exercises) {
    $statement = $pdo->prepare(
        'SELECT tl.* FROM training_logs tl
         JOIN plan_exercises pe ON pe.id = tl.exercise_id
         JOIN workout_plans wp ON wp.id = pe.plan_id
         WHERE tl.student_id = :student_id AND wp.id = :plan_id
         ORDER BY tl.completed_at DESC, tl.id DESC LIMIT 100'
    );
    $statement->execute(['student_id' => $studentId, 'plan_id' => $plan['id']]);
    foreach ($statement->fetchAll() as $log) {
        $logsByExercise[(int) $log['exercise_id']][] = $log;
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
  <title>O meu treino — REP•LOG</title>
</head>
<body>
  <header class="app-header">
    <a class="brand" href="aluno.php">REP<span>•</span>LOG</a>
    <div class="header-user"><span>Olá, <?= escapeHtml($_SESSION['full_name'] ?? '') ?></span><a href="sair.php">Sair</a></div>
  </header>
  <main class="page-layout">
    <section class="page-heading">
      <p class="eyebrow">A tua área de treino</p>
      <h1><?= escapeHtml($plan['title'] ?? 'A minha ficha de treino') ?></h1>
      <p class="muted">A ficha é preparada pelo teu treinador. Regista aqui cada série que fizeres.</p>
    </section>

    <?php if (isset($_GET['saved'])): ?><p class="notice success" role="status">Série registada.</p><?php endif; ?>
    <?php if ($error !== ''): ?><p class="notice error" role="alert"><?= escapeHtml($error) ?></p><?php endif; ?>

    <?php if (!$plan || !$exercises): ?>
      <section class="panel empty-state"><h2>A ficha ainda está a ser preparada</h2><p>Quando o administrador adicionar os exercícios, eles aparecerão aqui.</p></section>
    <?php else: ?>
      <div class="student-exercises">
        <?php $lastDay = null; foreach ($exercises as $exercise): ?>
          <?php if ($lastDay !== $exercise['training_day']): $lastDay = $exercise['training_day']; ?>
            <h2 class="student-day-heading"><?= escapeHtml($lastDay) ?></h2>
          <?php endif; ?>
          <article class="panel student-exercise" id="exercise-<?= (int) $exercise['id'] ?>">
            <div class="exercise-heading">
              <div><h3><?= escapeHtml($exercise['exercise_name']) ?></h3><p class="target">Meta: <?= (int) $exercise['sets_count'] ?> séries × <?= escapeHtml($exercise['reps_target']) ?> repetições</p></div>
            </div>
            <?php if ($exercise['notes'] !== ''): ?><p class="exercise-notes"><?= escapeHtml($exercise['notes']) ?></p><?php endif; ?>
            <form method="post" action="aluno.php#exercise-<?= (int) $exercise['id'] ?>" class="log-form">
              <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
              <input type="hidden" name="exercise_id" value="<?= (int) $exercise['id'] ?>">
              <div><label for="set-<?= (int) $exercise['id'] ?>">Série nº</label><input id="set-<?= (int) $exercise['id'] ?>" name="set_number" type="number" min="1" max="99" placeholder="1" required></div>
              <div><label for="reps-<?= (int) $exercise['id'] ?>">Repetições feitas</label><input id="reps-<?= (int) $exercise['id'] ?>" name="reps_done" type="number" min="1" max="999" placeholder="10" required></div>
              <div><label for="weight-<?= (int) $exercise['id'] ?>">Peso (kg)</label><input id="weight-<?= (int) $exercise['id'] ?>" name="weight_kg" type="number" min="0" max="9999" step="0.25" placeholder="0" required></div>
              <button class="button primary">Registar série</button>
            </form>
            <?php $exerciseLogs = $logsByExercise[(int) $exercise['id']] ?? []; ?>
            <?php if ($exerciseLogs): ?>
              <details class="log-history"><summary>Ver registos recentes (<?= count($exerciseLogs) ?>)</summary>
                <ul><?php foreach (array_slice($exerciseLogs, 0, 10) as $log): ?>
                  <li><strong>Série <?= (int) $log['set_number'] ?>:</strong> <?= (int) $log['reps_done'] ?> reps · <?= escapeHtml($log['weight_kg']) ?> kg <time><?= escapeHtml(date('d/m/Y H:i', strtotime($log['completed_at']))) ?></time></li>
                <?php endforeach; ?></ul>
              </details>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>
  <script src="assets/app.js" defer></script>
</body>
</html>
