<?php
declare(strict_types=1);

// Carrega segredos locais, se existirem. No alojamento também podes usar variáveis de ambiente.
$localConfigPath = __DIR__ . '/config.local.php';
$localConfig = is_file($localConfigPath) ? require $localConfigPath : [];

$dbHost = getenv('DB_HOST') ?: ($localConfig['host'] ?? '');
$dbName = getenv('DB_NAME') ?: ($localConfig['name'] ?? '');
$dbUser = getenv('DB_USER') ?: ($localConfig['user'] ?? '');
$dbPassword = getenv('DB_PASSWORD') ?: ($localConfig['password'] ?? '');

if ($dbHost === '' || $dbName === '' || $dbUser === '') {
    http_response_code(500);
    exit('Configura a ligação MySQL em config.local.php. Consulta o README.md.');
}

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPassword,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $exception) {
    // O detalhe técnico vai para o log do servidor; não expõe credenciais no ecrã.
    error_log('Falha ao ligar ao MySQL: ' . $exception->getMessage());
    http_response_code(500);
    exit('Não foi possível ligar à base de dados. Confere os dados de config.local.php.');
}
