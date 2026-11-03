<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers/response.php';

allow_methods(['GET', 'POST', 'PUT', 'DELETE']);

try {
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'GET') {

        $search = trim((string)($_GET['search'] ?? ''));
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 10)));
        $offset = ($page - 1) * $limit;

        // GET berdasarkan ID
        if (isset($_GET['id'])) {
            $id = query_id();

            $stmt = $conn->prepare(
                'SELECT id_kategori, nama_kategori, deskripsi
                 FROM kategori
                 WHERE id_kategori = ?'
            );

            $stmt->bind_param('i', $id);
            $stmt->execute();

            $row = $stmt->get_result()->fetch_assoc();

            if (!$row) {
                json_response(404, false, 'Kategori tidak ditemukan.', null);
            }

            json_response(200, true, 'Detail kategori berhasil diambil.', $row);
        }

        // GET semua data + pencarian + pagination
        $like = '%' . $search . '%';

        $stmt = $conn->prepare(
            'SELECT id_kategori, nama_kategori, deskripsi
             FROM kategori
             WHERE nama_kategori LIKE ?
             ORDER BY id_kategori ASC
             LIMIT ? OFFSET ?'
        );

        $stmt->bind_param('sii', $like, $limit, $offset);
        $stmt->execute();

        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Menghitung total data
        $count = $conn->prepare(
            'SELECT COUNT(*) AS total
             FROM kategori
             WHERE nama_kategori LIKE ?'
        );

        $count->bind_param('s', $like);
        $count->execute();

        $total = (int)$count->get_result()->fetch_assoc()['total'];

        json_response(200, true, 'Daftar kategori berhasil diambil.', [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => (int)ceil($total / $limit)
            ]
        ]);
    }

    if ($method === 'POST') {

        $data = json_input();

        require_fields($data, ['nama_kategori']);

        $nama = trim((string)$data['nama_kategori']);
        $deskripsi = isset($data['deskripsi'])
            ? trim((string)$data['deskripsi'])
            : null;

        if ($nama === '' || strlen($nama) > 100) {
            json_response(400, false, 'Nama kategori tidak valid.', null);
        }

        // Memeriksa nama kategori
        $check = $conn->prepare(
            'SELECT id_kategori FROM kategori WHERE nama_kategori = ?'
        );

        $check->bind_param('s', $nama);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            json_response(409, false, 'Nama kategori sudah digunakan.', null);
        }

        // Insert data
        $stmt = $conn->prepare(
            'INSERT INTO kategori (nama_kategori, deskripsi)
             VALUES (?, ?)'
        );

        $stmt->bind_param('ss', $nama, $deskripsi);

        if (!$stmt->execute()) {
            database_error($conn, 'Insert kategori');
        }

        json_response(201, true, 'Kategori berhasil ditambahkan.', [
            'id_kategori' => $conn->insert_id
        ]);
    }

    // =========================
    // PUT: Mengubah Data Kategori
    // =========================
    if ($method === 'PUT') {

        $id = query_id();
        $data = json_input();

        require_fields($data, ['nama_kategori']);

        $nama = trim((string)$data['nama_kategori']);
        $deskripsi = isset($data['deskripsi'])
            ? trim((string)$data['deskripsi'])
            : null;

        // Memeriksa ID kategori
        $exists = $conn->prepare(
            'SELECT id_kategori FROM kategori WHERE id_kategori = ?'
        );

        $exists->bind_param('i', $id);
        $exists->execute();

        if ($exists->get_result()->num_rows === 0) {
            json_response(404, false, 'Kategori tidak ditemukan.', null);
        }

        if ($nama === '' || strlen($nama) > 100) {
            json_response(400, false, 'Nama kategori tidak valid.', null);
        }

        // Memeriksa nama kategori agar tidak duplikat
        $check = $conn->prepare(
            'SELECT id_kategori
             FROM kategori
             WHERE nama_kategori = ? AND id_kategori <> ?'
        );

        $check->bind_param('si', $nama, $id);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            json_response(409, false, 'Nama kategori sudah digunakan.', null);
        }

        // Update data
        $stmt = $conn->prepare(
            'UPDATE kategori
             SET nama_kategori = ?, deskripsi = ?
             WHERE id_kategori = ?'
        );

        $stmt->bind_param('ssi', $nama, $deskripsi, $id);

        if (!$stmt->execute()) {
            database_error($conn, 'Update kategori');
        }

        json_response(200, true, 'Kategori berhasil diperbarui.', [
            'affected_rows' => $stmt->affected_rows
        ]);
    }

    // =========================
    // DELETE: Menghapus Kategori
    // =========================
    if ($method === 'DELETE') {

        $id = query_id();

        // Memeriksa ID kategori
        $exists = $conn->prepare(
            'SELECT id_kategori FROM kategori WHERE id_kategori = ?'
        );

        $exists->bind_param('i', $id);
        $exists->execute();

        if ($exists->get_result()->num_rows === 0) {
            json_response(404, false, 'Kategori tidak ditemukan.', null);
        }

        // Hapus data
        $stmt = $conn->prepare(
            'DELETE FROM kategori WHERE id_kategori = ?'
        );

        $stmt->bind_param('i', $id);

        if (!$stmt->execute()) {
            database_error($conn, 'Delete kategori');
        }

        json_response(200, true, 'Kategori berhasil dihapus.', [
            'affected_rows' => $stmt->affected_rows
        ]);
    }

} catch (Throwable $e) {

    error_log('API kategori exception: ' . $e->getMessage());

    json_response(500, false, 'Terjadi kesalahan pada server.', null);
}