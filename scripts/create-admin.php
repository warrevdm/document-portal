<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Gebruik dit script via CLI.\n");
}

$email = $argv[1] ?? null;
$name = $argv[2] ?? 'Administrator';
$password = $argv[3] ?? null;

if (!$email || !$password || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
    fwrite(STDERR, "Gebruik: php scripts/create-admin.php email naam wachtwoord\nHet wachtwoord moet minimaal 12 tekens bevatten.\n");
    exit(1);
}

$stmt = db()->prepare("INSERT INTO users(name,email,password_hash,role,active) VALUES(?,?,?,'admin',1)");
$stmt->execute([$name, strtolower($email), password_hash($password, PASSWORD_DEFAULT)]);

echo "Admin aangemaakt: {$email}\n";
