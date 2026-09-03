<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$root = dirname(__DIR__);
if (file_exists($root . '/vendor/autoload.php')) {
    require_once $root . '/vendor/autoload.php';
}

function load_env(string $path): void
{
    if (!is_file($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $value = trim($value);
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }
        $_ENV[trim($key)] = $value;
    }
}

load_env($root . '/.env');

function envv(string $key, mixed $default = null): mixed
{
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;

    $host = (string) envv('DB_HOST', '127.0.0.1');
    $port = (string) envv('DB_PORT', '3306');
    $name = (string) envv('DB_DATABASE', 'aab_leaseflow');
    $user = (string) envv('DB_USERNAME', 'root');
    $pass = (string) envv('DB_PASSWORD', '');

    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    return $pdo;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_url(string $path = ''): string
{
    $base = rtrim((string) envv('APP_URL', ''), '/');
    if ($base === '') return '/' . ltrim($path, '/');
    return $base . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . base_url($path));
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['_csrf'];
}

function verify_csrf(): void
{
    $sent = (string) ($_POST['_csrf'] ?? '');
    if (!hash_equals((string) ($_SESSION['_csrf'] ?? ''), $sent)) {
        http_response_code(419);
        exit('Sessie verlopen. Vernieuw de pagina en probeer opnieuw.');
    }
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) return null;
    $stmt = db()->prepare('SELECT id,name,email,role,active FROM users WHERE id=? AND active=1');
    $stmt->execute([(int) $_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function require_auth(array $roles = []): array
{
    $user = current_user();
    if (!$user) redirect('login');
    if ($roles && !in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('Geen toegang.');
    }
    return $user;
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function pull_flashes(): array
{
    $items = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $items;
}

function storage_root(): string
{
    $path = dirname(__DIR__) . '/storage/private';
    if (!is_dir($path) && !mkdir($path, 0770, true) && !is_dir($path)) {
        throw new RuntimeException('Private storage kon niet worden aangemaakt.');
    }
    return $path;
}

function case_storage_dir(string $caseNumber): string
{
    $safe = preg_replace('/[^A-Z0-9_-]/i', '_', $caseNumber);
    $dir = storage_root() . '/cases/' . date('Y') . '/' . $safe;
    if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
        throw new RuntimeException('Dossiermap kon niet worden aangemaakt.');
    }
    return $dir;
}

function case_number(int $id): string
{
    return sprintf('L%02d-%06d', (int) date('y'), $id);
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64);
}

function user_agent(): string
{
    return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
}

function audit(?int $caseId, string $event, array $metadata = [], string $actorType = 'user', ?int $actorUserId = null): void
{
    if ($actorType === 'user' && $actorUserId === null) $actorUserId = current_user()['id'] ?? null;
    $stmt = db()->prepare('INSERT INTO audit_logs(lease_case_id,actor_user_id,actor_type,event_type,ip_address,user_agent,metadata) VALUES(?,?,?,?,?,?,?)');
    $stmt->execute([$caseId, $actorUserId, $actorType, $event, client_ip(), user_agent(), $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null]);
}

function change_status(int $caseId, string $to, ?string $reason = null, ?int $userId = null): void
{
    $stmt = db()->prepare('SELECT status FROM lease_cases WHERE id=?');
    $stmt->execute([$caseId]);
    $from = $stmt->fetchColumn();
    if ($from === false) throw new RuntimeException('Dossier niet gevonden.');

    db()->prepare('UPDATE lease_cases SET status=? WHERE id=?')->execute([$to, $caseId]);
    db()->prepare('INSERT INTO status_history(lease_case_id,from_status,to_status,changed_by,reason) VALUES(?,?,?,?,?)')
        ->execute([$caseId, $from, $to, $userId ?? (current_user()['id'] ?? null), $reason]);
    audit($caseId, 'STATUS_CHANGED', ['from' => $from, 'to' => $to, 'reason' => $reason]);
}

function status_label(string $status): string
{
    return [
        'concept'=>'Concept','document_uploaded'=>'Document geüpload','ready_for_signing'=>'Klaar voor ondertekening',
        'waiting_for_customer'=>'Wacht op klant','signed'=>'Ondertekend','ready_for_processing'=>'Klaar voor verwerking',
        'in_processing'=>'In verwerking','missing_info'=>'Ontbrekende info','processed'=>'Verwerkt','completed'=>'Afgerond',
        'cancelled'=>'Geannuleerd','expired'=>'Tekenlink vervallen'
    ][$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function signature_token(): string
{
    return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
}

function require_pdf_upload(array $file): void
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Upload mislukt.');
    $limit = max(1, (int) envv('UPLOAD_MAX_MB', 15)) * 1024 * 1024;
    if (($file['size'] ?? 0) > $limit) throw new RuntimeException('PDF is groter dan de toegestane uploadlimiet.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if ($mime !== 'application/pdf') throw new RuntimeException('Alleen geldige PDF-bestanden zijn toegestaan.');
    if (substr((string) file_get_contents($file['tmp_name'], false, null, 0, 5), 0, 5) !== '%PDF-') throw new RuntimeException('Bestand is geen geldige PDF.');
}
