<?php
declare(strict_types=1);

function json_response(int $code, bool $status, string $message, $data = null): void
{
    http_response_code($code);
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function allow_methods(array $allowed): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, $allowed, true)) {
        header('Allow: ' . implode(', ', $allowed));
        json_response(405, false, 'Method tidak diizinkan.', null);
    }
}

function json_input(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    if (!is_array($data)) {
        json_response(400, false, 'Body harus berupa JSON yang valid.', null);
    }
    return $data;
}

function require_fields(array $data, array $fields): void
{
    $missing = [];
    foreach ($fields as $field) {
        if (!array_key_exists($field, $data) || $data[$field] === '' || $data[$field] === null) {
            $missing[] = $field;
        }
    }
    if ($missing) {
        json_response(400, false, 'Field wajib belum lengkap: ' . implode(', ', $missing), null);
    }
}

function query_id(): int
{
    $raw = $_GET['id'] ?? '';
    if (!ctype_digit((string)$raw) || (int)$raw < 1) {
        json_response(400, false, 'Parameter id wajib berupa bilangan bulat positif.', null);
    }
    return (int)$raw;
}

function database_error(mysqli $conn, string $context): void
{
    $errno = $conn->errno;
    error_log($context . ' failed. MySQL errno: ' . $errno . '; error: ' . $conn->error);
    if ($errno === 1062) {
        json_response(409, false, 'Data duplikat. Gunakan kode atau nilai unik yang berbeda.', null);
    }
    if ($errno === 1451 || $errno === 1452) {
        json_response(409, false, 'Data masih memiliki relasi atau referensi yang tidak valid.', null);
    }
    json_response(500, false, 'Terjadi kesalahan pada server.', null);
}

function valid_date(string $date): bool
{
    $d = DateTime::createFromFormat('!Y-m-d', $date);
    return $d !== false && $d->format('Y-m-d') === $date;
}
