<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers/response.php';

allow_methods(['GET', 'POST', 'PUT', 'DELETE']);

try {
    $method = $_SERVER['REQUEST_METHOD'];

    // =========================
    // GET: Menampilkan Data Pemesanan
    // =========================
    if ($method === 'GET') {

        $search = trim((string)($_GET['search'] ?? ''));
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 10)));
        $offset = ($page - 1) * $limit;

        if (isset($_GET['id'])) {
            $id = query_id();

            $stmt = $conn->prepare(
                'SELECT id_pemesanan, id_wisata, kode_pemesanan,
                        nama_pengunjung, tanggal_kunjungan,
                        jumlah_tiket, total_harga, status_pemesanan
                 FROM pemesanan
                 WHERE id_pemesanan = ?'
            );

            $stmt->bind_param('i', $id);
            $stmt->execute();

            $row = $stmt->get_result()->fetch_assoc();

            if (!$row) {
                json_response(404, false, 'Pemesanan tidak ditemukan.', null);
            }

            json_response(200, true, 'Detail pemesanan berhasil diambil.', $row);
        }

        $like = '%' . $search . '%';

        $stmt = $conn->prepare(
            'SELECT id_pemesanan, id_wisata, kode_pemesanan,
                    nama_pengunjung, tanggal_kunjungan,
                    jumlah_tiket, total_harga, status_pemesanan
             FROM pemesanan
             WHERE kode_pemesanan LIKE ?
                OR nama_pengunjung LIKE ?
             ORDER BY id_pemesanan ASC
             LIMIT ? OFFSET ?'
        );

        $stmt->bind_param('ssii', $like, $like, $limit, $offset);
        $stmt->execute();

        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $count = $conn->prepare(
            'SELECT COUNT(*) AS total
             FROM pemesanan
             WHERE kode_pemesanan LIKE ?
                OR nama_pengunjung LIKE ?'
        );

        $count->bind_param('ss', $like, $like);
        $count->execute();

        $total = (int)$count->get_result()->fetch_assoc()['total'];

        json_response(200, true, 'Daftar pemesanan berhasil diambil.', [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => (int)ceil($total / $limit)
            ]
        ]);
    }

    // =========================
    // POST: Menambahkan Pemesanan
    // =========================
    if ($method === 'POST') {

        $data = json_input();

        require_fields($data, [
            'id_wisata',
            'kode_pemesanan',
            'nama_pengunjung',
            'tanggal_kunjungan',
            'jumlah_tiket',
            'total_harga',
            'status_pemesanan'
        ]);

        $id_wisata = (int)$data['id_wisata'];
        $kode = trim((string)$data['kode_pemesanan']);
        $nama = trim((string)$data['nama_pengunjung']);
        $tanggal = trim((string)$data['tanggal_kunjungan']);
        $jumlah = (int)$data['jumlah_tiket'];
        $harga = (int)$data['total_harga'];
        $status = trim((string)$data['status_pemesanan']);

        if (
            $id_wisata <= 0 ||
            $kode === '' ||
            strlen($kode) > 50 ||
            $nama === '' ||
            $jumlah <= 0 ||
            $harga < 0
        ) {
            json_response(400, false, 'Data pemesanan tidak valid.', null);
        }

        $date = DateTime::createFromFormat('Y-m-d', $tanggal);

        if (!$date || $date->format('Y-m-d') !== $tanggal) {
            json_response(400, false, 'Format tanggal kunjungan tidak valid.', null);
        }

        $allowed_status = ['menunggu', 'selesai', 'dibayar', 'dibatalkan'];

        if (!in_array($status, $allowed_status, true)) {
            json_response(400, false, 'Status pemesanan tidak valid.', null);
        }

        $check = $conn->prepare(
            'SELECT id_pemesanan
             FROM pemesanan
             WHERE kode_pemesanan = ?'
        );

        $check->bind_param('s', $kode);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            json_response(409, false, 'Kode pemesanan sudah digunakan.', null);
        }

        $wisata = $conn->prepare(
            'SELECT id_wisata FROM wisata WHERE id_wisata = ?'
        );

        $wisata->bind_param('i', $id_wisata);
        $wisata->execute();

        if ($wisata->get_result()->num_rows === 0) {
            json_response(404, false, 'Data wisata tidak ditemukan.', null);
        }

        $stmt = $conn->prepare(
            'INSERT INTO pemesanan
                (id_wisata, kode_pemesanan, nama_pengunjung,
                 tanggal_kunjungan, jumlah_tiket, total_harga,
                 status_pemesanan)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->bind_param(
            'isssiis',
            $id_wisata,
            $kode,
            $nama,
            $tanggal,
            $jumlah,
            $harga,
            $status
        );

        if (!$stmt->execute()) {
            database_error($conn, 'Insert pemesanan');
        }

        json_response(201, true, 'Pemesanan berhasil ditambahkan.', [
            'id_pemesanan' => $conn->insert_id
        ]);
    }

    // =========================
    // PUT: Mengubah Data Pemesanan
    // =========================
    if ($method === 'PUT') {

        $id = query_id();
        $data = json_input();

        require_fields($data, [
            'id_wisata',
            'kode_pemesanan',
            'nama_pengunjung',
            'tanggal_kunjungan',
            'jumlah_tiket',
            'total_harga',
            'status_pemesanan'
        ]);

        $id_wisata = (int)$data['id_wisata'];
        $kode = trim((string)$data['kode_pemesanan']);
        $nama = trim((string)$data['nama_pengunjung']);
        $tanggal = trim((string)$data['tanggal_kunjungan']);
        $jumlah = (int)$data['jumlah_tiket'];
        $harga = (int)$data['total_harga'];
        $status = trim((string)$data['status_pemesanan']);

        $exists = $conn->prepare(
            'SELECT id_pemesanan
             FROM pemesanan
             WHERE id_pemesanan = ?'
        );

        $exists->bind_param('i', $id);
        $exists->execute();

        if ($exists->get_result()->num_rows === 0) {
            json_response(404, false, 'Pemesanan tidak ditemukan.', null);
        }

        if (
            $id_wisata <= 0 ||
            $kode === '' ||
            strlen($kode) > 50 ||
            $nama === '' ||
            $jumlah <= 0 ||
            $harga < 0
        ) {
            json_response(400, false, 'Data pemesanan tidak valid.', null);
        }

        $date = DateTime::createFromFormat('Y-m-d', $tanggal);

        if (!$date || $date->format('Y-m-d') !== $tanggal) {
            json_response(400, false, 'Format tanggal kunjungan tidak valid.', null);
        }

        $allowed_status = ['menunggu', 'selesai', 'dibayar', 'dibatalkan'];

        if (!in_array($status, $allowed_status, true)) {
            json_response(400, false, 'Status pemesanan tidak valid.', null);
        }

        $wisata = $conn->prepare(
            'SELECT id_wisata FROM wisata WHERE id_wisata = ?'
        );

        $wisata->bind_param('i', $id_wisata);
        $wisata->execute();

        if ($wisata->get_result()->num_rows === 0) {
            json_response(404, false, 'Data wisata tidak ditemukan.', null);
        }

        $check = $conn->prepare(
            'SELECT id_pemesanan
             FROM pemesanan
             WHERE kode_pemesanan = ?
               AND id_pemesanan <> ?'
        );

        $check->bind_param('si', $kode, $id);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            json_response(409, false, 'Kode pemesanan sudah digunakan.', null);
        }

        $stmt = $conn->prepare(
            'UPDATE pemesanan
             SET id_wisata = ?,
                 kode_pemesanan = ?,
                 nama_pengunjung = ?,
                 tanggal_kunjungan = ?,
                 jumlah_tiket = ?,
                 total_harga = ?,
                 status_pemesanan = ?
             WHERE id_pemesanan = ?'
        );

        $stmt->bind_param(
            'isssiisi',
            $id_wisata,
            $kode,
            $nama,
            $tanggal,
            $jumlah,
            $harga,
            $status,
            $id
        );

        if (!$stmt->execute()) {
            database_error($conn, 'Update pemesanan');
        }

        json_response(200, true, 'Pemesanan berhasil diperbarui.', [
            'affected_rows' => $stmt->affected_rows
        ]);
    }

    // =========================
    // DELETE: Menghapus Pemesanan
    // =========================
    if ($method === 'DELETE') {

        $id = query_id();

        $exists = $conn->prepare(
            'SELECT id_pemesanan
             FROM pemesanan
             WHERE id_pemesanan = ?'
        );

        $exists->bind_param('i', $id);
        $exists->execute();

        if ($exists->get_result()->num_rows === 0) {
            json_response(404, false, 'Pemesanan tidak ditemukan.', null);
        }

        $stmt = $conn->prepare(
            'DELETE FROM pemesanan
             WHERE id_pemesanan = ?'
        );

        $stmt->bind_param('i', $id);

        if (!$stmt->execute()) {
            database_error($conn, 'Delete pemesanan');
        }

        json_response(200, true, 'Pemesanan berhasil dihapus.', [
            'affected_rows' => $stmt->affected_rows
        ]);
    }

} catch (Throwable $e) {

    error_log('API pemesanan exception: ' . $e->getMessage());

    json_response(500, false, 'Terjadi kesalahan pada server.', null);
}