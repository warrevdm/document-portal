<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit("Dit script kan alleen via de command line worden uitgevoerd.\n");
}

$host = (string) envv('DB_HOST', '127.0.0.1');
$port = (string) envv('DB_PORT', '3306');
$name = (string) envv('DB_DATABASE', 'document_portal');
$user = (string) envv('DB_USERNAME', 'root');
$pass = (string) envv('DB_PASSWORD', '');

if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
    fwrite(STDERR, "Ongeldige databasenaam in .env. Gebruik alleen letters, cijfers en underscores.\n");
    exit(1);
}

try {
    $server = new PDO(
        "mysql:host={$host};port={$port};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    echo "Verbonden met MySQL op {$host}:{$port}.\n";

    $server->exec(
        "CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    );
    echo "Database `{$name}` is beschikbaar.\n";

    $database = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $tableCheck = $database->query("SHOW TABLES LIKE 'users'")->fetchColumn();
    if ($tableCheck) {
        echo "Schema lijkt al geimporteerd (`users` bestaat). Geen wijzigingen uitgevoerd.\n";
        echo "Volgende stap: php scripts/create-admin.php email naam wachtwoord\n";
        exit(0);
    }

    $schemaPath = dirname(__DIR__) . '/database/schema.sql';
    $schema = file_get_contents($schemaPath);
    if ($schema === false || trim($schema) === '') {
        throw new RuntimeException('database/schema.sql kon niet worden gelezen.');
    }

    $database->exec($schema);
    echo "Schema succesvol geimporteerd.\n";
    echo "Lokale database is klaar.\n\n";
    echo "Volgende stap:\n";
    echo "php scripts/create-admin.php admin@aertsactionbike.be \"Warre\" \"KIES-EEN-STERK-WACHTWOORD\"\n";
} catch (PDOException $e) {
    fwrite(STDERR, "Database setup mislukt: {$e->getMessage()}\n");
    fwrite(STDERR, "Controleer of MySQL draait en of DB_HOST, DB_PORT, DB_USERNAME en DB_PASSWORD in .env kloppen.\n");
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, "Setup mislukt: {$e->getMessage()}\n");
    exit(1);
}
