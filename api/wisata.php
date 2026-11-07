<?php
declare(strict_types=1);

require_once '../config.php';
require_once '../helpers/response.php';

allow_methods(['GET', 'POST', 'PUT', 'DELETE']);

$method = $_SERVER['REQUEST_METHOD'];

// Fungsi untuk mengikat parameter SQL
function bind_values($stmt, string $types, array $values): void
{
    $refs = [];
    foreach ($values as $key => $value) {
        $refs[$key] = &$values[$key];
    }

    array_unshift($refs, $types);
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

// Fungsi mengecek kategori
function cek_kategori(mysqli $conn, int $id): bool
{
    $stmt = $conn->prepare(
        "SELECT id_kategori FROM kategori WHERE id_kategori = ?"
    );

    $stmt->bind_param('i', $id);
    $stmt->execute();

    return $stmt->get_result()->num_rows > 0;
}

// Fungsi menangani error SQL
function error_sql($stmt): void
{
    error_log('SQL Error: ' . $stmt->error);

    if ($stmt->errno === 1062) {
        json_response(409, false, 'Kode wisata sudah digunakan.', null);
    }

    if ($stmt->errno === 1452) {
        json_response(404, false, 'Kategori tidak ditemukan.', null);
    }

    json_response(500, false, 'Terjadi kesalahan pada server.', null);
}


// ======================================
// GET - MENAMPILKAN DATA WISATA
// ======================================

if ($method === 'GET') {

    if (isset($_GET['id'])) {

        $id = query_id();

        $sql = "SELECT w.*, k.nama_kategori
                FROM wisata w
                LEFT JOIN kategori k
                ON w.id_kategori = k.id_kategori
                WHERE w.id_wisata = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();

        $result = $stmt->get_result();
        $data = $result->fetch_assoc();

        if (!$data) {
            json_response(404, false, 'Data wisata tidak ditemukan.', null);
        }

        json_response(200, true, 'Data wisata berhasil ditampilkan.', $data);
    }

    $search = trim($_GET['search'] ?? '');

    $sql = "SELECT w.*, k.nama_kategori
            FROM wisata w
            LEFT JOIN kategori k
            ON w.id_kategori = k.id_kategori";

    $types = '';
    $values = [];

    if ($search !== '') {
        $sql .= " WHERE w.nama_wisata LIKE ?
                  OR w.lokasi LIKE ?
                  OR w.kode_wisata LIKE ?";

        $keyword = "%{$search}%";

        $types .= 'sss';
        $values[] = $keyword;
        $values[] = $keyword;
        $values[] = $keyword;
    }

    $pagination = isset($_GET['page']) || isset($_GET['limit']);

    if ($pagination) {
        $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
        $limit = filter_var($_GET['limit'] ?? 10, FILTER_VALIDATE_INT);

        if (!$page || !$limit || $page < 1 || $limit < 1 || $limit > 100) {
            json_response(400, false, 'Page atau limit tidak valid.', null);
        }

        $offset = ($page - 1) * $limit;

        $sql .= " ORDER BY w.id_wisata DESC LIMIT ? OFFSET ?";

        $types .= 'ii';
        $values[] = $limit;
        $values[] = $offset;
    } else {
        $sql .= " ORDER BY w.id_wisata DESC";
    }

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log($conn->error);
        json_response(500, false, 'Terjadi kesalahan pada server.', null);
    }

    if ($values) {
        bind_values($stmt, $types, $values);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];

    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    json_response(200, true, 'Data wisata berhasil ditampilkan.', $data);
}


// ======================================
// POST - MENAMBAHKAN DATA WISATA
// ======================================

if ($method === 'POST') {

    $data = json_input();

    require_fields($data, [
        'id_kategori',
        'kode_wisata',
        'nama_wisata',
        'lokasi',
        'deskripsi',
        'harga_tiket'
    ]);

    $id_kategori = filter_var($data['id_kategori'], FILTER_VALIDATE_INT);
    $harga_tiket = filter_var($data['harga_tiket'], FILTER_VALIDATE_FLOAT);

    if (!$id_kategori || $id_kategori < 1 ||
        $harga_tiket === false || $harga_tiket < 0) {
        json_response(400, false, 'ID kategori atau harga tiket tidak valid.', null);
    }

    if (!cek_kategori($conn, $id_kategori)) {
        json_response(404, false, 'Kategori tidak ditemukan.', null);
    }

    $kode = trim($data['kode_wisata']);
    $nama = trim($data['nama_wisata']);
    $lokasi = trim($data['lokasi']);
    $deskripsi = trim($data['deskripsi']);

    if ($kode === '' || $nama === '' || $lokasi === '' || $deskripsi === '') {
        json_response(400, false, 'Data wisata tidak boleh kosong.', null);
    }

    $sql = "INSERT INTO wisata
            (id_kategori, kode_wisata, nama_wisata, lokasi, deskripsi, harga_tiket)
            VALUES (?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        'issssd',
        $id_kategori,
        $kode,
        $nama,
        $lokasi,
        $deskripsi,
        $harga_tiket
    );

    if (!$stmt->execute()) {
        error_sql($stmt);
    }

    json_response(201, true, 'Data wisata berhasil ditambahkan.', [
        'id' => $stmt->insert_id
    ]);
}


// ======================================
// PUT - MENGUBAH DATA WISATA
// ======================================

if ($method === 'PUT') {

    $id = query_id();
    $data = json_input();

    require_fields($data, [
        'id_kategori',
        'kode_wisata',
        'nama_wisata',
        'lokasi',
        'deskripsi',
        'harga_tiket'
    ]);

    $id_kategori = filter_var($data['id_kategori'], FILTER_VALIDATE_INT);
    $harga_tiket = filter_var($data['harga_tiket'], FILTER_VALIDATE_FLOAT);

    if (!$id_kategori || $id_kategori < 1 ||
        $harga_tiket === false || $harga_tiket < 0) {
        json_response(400, false, 'ID kategori atau harga tiket tidak valid.', null);
    }

    if (!cek_kategori($conn, $id_kategori)) {
        json_response(404, false, 'Kategori tidak ditemukan.', null);
    }

    $kode = trim($data['kode_wisata']);
    $nama = trim($data['nama_wisata']);
    $lokasi = trim($data['lokasi']);
    $deskripsi = trim($data['deskripsi']);

    if ($kode === '' || $nama === '' || $lokasi === '' || $deskripsi === '') {
        json_response(400, false, 'Data wisata tidak boleh kosong.', null);
    }

    $sql = "UPDATE wisata SET
            id_kategori = ?,
            kode_wisata = ?,
            nama_wisata = ?,
            lokasi = ?,
            deskripsi = ?,
            harga_tiket = ?
            WHERE id_wisata = ?";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        'issssdi',
        $id_kategori,
        $kode,
        $nama,
        $lokasi,
        $deskripsi,
        $harga_tiket,
        $id
    );

    if (!$stmt->execute()) {
        error_sql($stmt);
    }

    if ($stmt->affected_rows === 0) {
        $cek = $conn->prepare(
            "SELECT id_wisata FROM wisata WHERE id_wisata = ?"
        );
        $cek->bind_param('i', $id);
        $cek->execute();

        if ($cek->get_result()->num_rows === 0) {
            json_response(404, false, 'Data wisata tidak ditemukan.', null);
        }
    }

    json_response(200, true, 'Data wisata berhasil diperbarui.', [
        'id' => $id
    ]);
}


// ======================================
// DELETE - MENGHAPUS DATA WISATA
// ======================================

if ($method === 'DELETE') {

    $id = query_id();

    $stmt = $conn->prepare(
        "DELETE FROM wisata WHERE id_wisata = ?"
    );

    $stmt->bind_param('i', $id);

    if (!$stmt->execute()) {
        error_sql($stmt);
    }

    if ($stmt->affected_rows === 0) {
        json_response(404, false, 'Data wisata tidak ditemukan.', null);
    }

    json_response(200, true, 'Data wisata berhasil dihapus.', [
        'id' => $id
    ]);
}
// ======================================