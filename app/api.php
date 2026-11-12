<?php
declare(strict_types=1);

/**
 * Minimal JSON API used by the single-page app.
 */
function json_response(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_input(): array
{
    $raw = file_get_contents('php://input');

    if ($raw === false || $raw === '') {
        return [];
    }

    $data = json_decode($raw, true);

    if (!is_array($data)) {
        throw new ApiException('Body JSON tidak valid.', 400);
    }

    return $data;
}

function api_colors(): array
{
    return ['default', 'yellow', 'orange', 'red', 'pink', 'purple', 'blue', 'teal', 'green', 'gray'];
}

function clean_title(mixed $value): string
{
    return mb_substr(trim((string) $value), 0, 255);
}

function clean_body(mixed $value): string
{
    return mb_substr((string) $value, 0, 200000);
}

function clean_color(mixed $value): string
{
    $value = (string) $value;
    return in_array($value, api_colors(), true) ? $value : 'default';
}

function clean_items(mixed $items): array
{
    if (!is_array($items)) {
        return [];
    }

    $clean = [];

    foreach (array_slice($items, 0, 200) as $item) {
        if (!is_array($item)) {
            continue;
        }

        $content = mb_substr(trim((string) ($item['content'] ?? '')), 0, 1000);

        if ($content === '') {
            continue;
        }

        $clean[] = [
            'content' => $content,
            'checked' => !empty($item['checked']),
            'position' => count($clean),
        ];
    }

    return $clean;
}

function clean_label_ids(mixed $ids, int $userId): array
{
    if (!is_array($ids)) {
        return [];
    }

    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id) => $id > 0)));

    if ($ids === []) {
        return [];
    }

    $in   = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("SELECT id FROM labels WHERE user_id = ? AND id IN ({$in})");
    $stmt->execute(array_merge([$userId], $ids));

    return array_map(static fn (array $row) => (int) $row['id'], $stmt->fetchAll());
}

function tx(callable $callback): mixed
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $result = $callback();
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function note_row_for_user(int $noteId): array
{
    $user = require_user();
    $stmt = db()->prepare('SELECT * FROM notes WHERE id = ? AND user_id = ?');
    $stmt->execute([$noteId, $user['id']]);
    $row = $stmt->fetch();

    if (!$row) {
        throw new ApiException('Catatan tidak ditemukan.', 404);
    }

    return $row;
}

function label_row_for_user(int $labelId): array
{
    $user = require_user();
    $stmt = db()->prepare('SELECT * FROM labels WHERE id = ? AND user_id = ?');
    $stmt->execute([$labelId, $user['id']]);
    $row = $stmt->fetch();

    if (!$row) {
        throw new ApiException('Label tidak ditemukan.', 404);
    }

    return $row;
}

function attachment_to_array(array $row): array
{
    return [
        'id'       => (int) $row['id'],
        'filename' => $row['filename'],
        'url'      => upload_url($row['filename']),
        'thumb'    => upload_url($row['thumb']),
        'mime'     => $row['mime'],
        'size'     => (int) $row['size'],
        'width'    => (int) $row['width'],
        'height'   => (int) $row['height'],
    ];
}

/**
 * Load items, labels and attachments for a set of notes with 3 extra queries.
 */
function hydrate_notes(array $rows): array
{
    if ($rows === []) {
        return [];
    }

    $ids = array_map(static fn (array $row) => (int) $row['id'], $rows);
    $in  = implode(',', array_fill(0, count($ids), '?'));

    $items = [];
    $stmt  = db()->prepare("SELECT * FROM note_items WHERE note_id IN ({$in}) ORDER BY position ASC, id ASC");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $row) {
        $items[(int) $row['note_id']][] = [
            'id'       => (int) $row['id'],
            'content'  => $row['content'],
            'checked'  => (bool) $row['checked'],
            'position' => (int) $row['position'],
        ];
    }

    $labels = [];
    $stmt   = db()->prepare(
        "SELECT nl.note_id, l.id, l.name
         FROM note_labels nl
         JOIN labels l ON l.id = nl.label_id
         WHERE nl.note_id IN ({$in})
         ORDER BY l.name ASC"
    );
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $row) {
        $labels[(int) $row['note_id']][] = [
            'id'   => (int) $row['id'],
            'name' => $row['name'],
        ];
    }

    $attachments = [];
    $stmt        = db()->prepare("SELECT * FROM attachments WHERE note_id IN ({$in}) ORDER BY id ASC");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $row) {
        $attachments[(int) $row['note_id']][] = attachment_to_array($row);
    }

    $notes = [];
    foreach ($rows as $row) {
        $id = (int) $row['id'];

        $notes[] = [
            'id'          => $id,
            'type'        => $row['type'],
            'title'       => $row['title'],
            'body'        => $row['body'] ?? '',
            'color'       => $row['color'],
            'pinned'      => (bool) $row['pinned'],
            'archived'    => (bool) $row['archived'],
            'trashed'     => $row['trashed_at'] !== null,
            'created_at'  => iso_datetime($row['created_at']),
            'updated_at'  => iso_datetime($row['updated_at']),
            'items'       => $items[$id] ?? [],
            'labels'      => $labels[$id] ?? [],
            'attachments' => $attachments[$id] ?? [],
        ];
    }

    return $notes;
}

function labels_with_counts(int $userId): array
{
    $stmt = db()->prepare(
        "SELECT l.id, l.name,
                (SELECT COUNT(*)
                   FROM note_labels nl
                   JOIN notes n ON n.id = nl.note_id
                  WHERE nl.label_id = l.id AND n.trashed_at IS NULL) AS note_count
           FROM labels l
          WHERE l.user_id = ?
          ORDER BY l.name ASC"
    );
    $stmt->execute([$userId]);

    return array_map(static fn (array $row) => [
        'id'    => (int) $row['id'],
        'name'  => $row['name'],
        'count' => (int) $row['note_count'],
    ], $stmt->fetchAll());
}

function note_counts(int $userId): array
{
    $stmt = db()->prepare(
        "SELECT
            SUM(trashed_at IS NULL AND archived = 0) AS notes,
            SUM(trashed_at IS NULL AND archived = 1) AS archive,
            SUM(trashed_at IS NOT NULL) AS trash
         FROM notes
         WHERE user_id = ?"
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch() ?: [];

    return [
        'notes'   => (int) ($row['notes'] ?? 0),
        'archive' => (int) ($row['archive'] ?? 0),
        'trash'   => (int) ($row['trash'] ?? 0),
    ];
}

function save_note_items(int $noteId, array $items): void
{
    $pdo = db();
    $pdo->prepare('DELETE FROM note_items WHERE note_id = ?')->execute([$noteId]);

    $stmt = $pdo->prepare('INSERT INTO note_items (note_id, content, checked, position) VALUES (?, ?, ?, ?)');
    foreach ($items as $item) {
        $stmt->execute([$noteId, $item['content'], $item['checked'] ? 1 : 0, $item['position']]);
    }
}

function save_note_labels(int $noteId, array $labelIds): void
{
    $pdo = db();
    $pdo->prepare('DELETE FROM note_labels WHERE note_id = ?')->execute([$noteId]);

    if ($labelIds === []) {
        return;
    }

    $stmt = $pdo->prepare('INSERT INTO note_labels (note_id, label_id) VALUES (?, ?)');
    foreach ($labelIds as $labelId) {
        $stmt->execute([$noteId, $labelId]);
    }
}

function api_note_by_id(int $noteId): void
{
    $row = note_row_for_user($noteId);
    json_response(['note' => hydrate_notes([$row])[0]]);
}

function api_list_notes(): void
{
    $user = require_user();

    $view = (string) ($_GET['view'] ?? 'notes');
    if (!in_array($view, ['notes', 'archive', 'trash'], true)) {
        $view = 'notes';
    }

    $labelId = isset($_GET['label']) ? (int) $_GET['label'] : 0;
    $query   = trim((string) ($_GET['q'] ?? ''));
    if (mb_strlen($query) > 120) {
        $query = mb_substr($query, 0, 120);
    }

    $where  = ['n.user_id = ?'];
    $params = [$user['id']];

    if ($view === 'trash') {
        $where[] = 'n.trashed_at IS NOT NULL';
    } else {
        $where[] = 'n.trashed_at IS NULL';
        $where[] = $view === 'archive' ? 'n.archived = 1' : 'n.archived = 0';
    }

    if ($labelId > 0) {
        $where[]  = 'EXISTS (SELECT 1 FROM note_labels nl WHERE nl.note_id = n.id AND nl.label_id = ?)';
        $params[] = $labelId;
    }

    if ($query !== '') {
        $like     = like_term($query);
        $where[]  = "(n.title LIKE ? OR n.body LIKE ?
                      OR EXISTS (SELECT 1 FROM note_items ni WHERE ni.note_id = n.id AND ni.content LIKE ?)
                      OR EXISTS (SELECT 1 FROM note_labels nl2 JOIN labels l2 ON l2.id = nl2.label_id WHERE nl2.note_id = n.id AND l2.name LIKE ?))";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql = 'SELECT n.* FROM notes n WHERE ' . implode(' AND ', $where)
        . ' ORDER BY n.pinned DESC, n.updated_at DESC, n.id DESC LIMIT 500';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    json_response([
        'notes'  => hydrate_notes($rows),
        'labels' => labels_with_counts($user['id']),
        'counts' => note_counts($user['id']),
        'view'   => $view,
        'label'  => $labelId > 0 ? $labelId : null,
    ]);
}

function api_create_note(): void
{
    $user  = require_user();
    $input = json_input();

    $type     = (($input['type'] ?? 'text') === 'list') ? 'list' : 'text';
    $title    = clean_title($input['title'] ?? '');
    $body     = clean_body($input['body'] ?? '');
    $color    = clean_color($input['color'] ?? 'default');
    $pinned   = !empty($input['pinned']);
    $archived = !empty($input['archived']);
    $items    = clean_items($input['items'] ?? []);
    $labelIds = clean_label_ids($input['label_ids'] ?? [], $user['id']);

    $noteId = tx(function () use ($user, $type, $title, $body, $color, $pinned, $archived, $items, $labelIds): int {
        $pdo  = db();
        $stmt = $pdo->prepare(
            'INSERT INTO notes (user_id, type, title, body, color, pinned, archived, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $user['id'], $type, $title, $body, $color,
            $pinned ? 1 : 0, $archived ? 1 : 0,
        ]);

        $id = (int) $pdo->lastInsertId();
        save_note_items($id, $items);
        save_note_labels($id, $labelIds);

        return $id;
    });

    api_note_by_id($noteId);
}

function api_update_note(int $noteId): void
{
    $row   = note_row_for_user($noteId);
    $input = json_input();

    $fields = [];
    $params = [];

    if (array_key_exists('title', $input)) {
        $fields[] = 'title = ?';
        $params[] = clean_title($input['title']);
    }

    if (array_key_exists('body', $input)) {
        $fields[] = 'body = ?';
        $params[] = clean_body($input['body']);
    }

    if (array_key_exists('type', $input)) {
        $fields[] = 'type = ?';
        $params[] = ($input['type'] === 'list') ? 'list' : 'text';
    }

    if (array_key_exists('color', $input)) {
        $fields[] = 'color = ?';
        $params[] = clean_color($input['color']);
    }

    if (array_key_exists('pinned', $input)) {
        $fields[] = 'pinned = ?';
        $params[] = !empty($input['pinned']) ? 1 : 0;
    }

    if (array_key_exists('archived', $input)) {
        $fields[] = 'archived = ?';
        $params[] = !empty($input['archived']) ? 1 : 0;
    }

    if (array_key_exists('trashed', $input)) {
        $fields[] = 'trashed_at = ?';
        $params[] = !empty($input['trashed']) ? date('Y-m-d H:i:s') : null;
    }

    $items    = array_key_exists('items', $input) ? clean_items($input['items']) : null;
    $labelIds = array_key_exists('label_ids', $input)
        ? clean_label_ids($input['label_ids'], (int) $row['user_id'])
        : null;

    tx(function () use ($row, $fields, $params, $items, $labelIds): void {
        $pdo = db();

        $set    = $fields;
        $values = $params;
        $set[]  = 'updated_at = NOW()';
        $values[] = (int) $row['id'];

        $pdo->prepare('UPDATE notes SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($values);

        if ($items !== null) {
            save_note_items((int) $row['id'], $items);
        }

        if ($labelIds !== null) {
            save_note_labels((int) $row['id'], $labelIds);
        }
    });

    api_note_by_id($noteId);
}

function api_delete_note(int $noteId): void
{
    $user = require_user();
    $row  = note_row_for_user($noteId);

    $permanent = $row['trashed_at'] !== null || !empty($_GET['permanent']);

    if (!$permanent) {
        db()->prepare('UPDATE notes SET trashed_at = NOW(), updated_at = NOW() WHERE id = ?')->execute([$noteId]);
        json_response(['ok' => true, 'permanent' => false]);
    }

    $files = [];
    $stmt  = db()->prepare('SELECT filename, thumb FROM attachments WHERE note_id = ?');
    $stmt->execute([$noteId]);
    foreach ($stmt->fetchAll() as $attachment) {
        $files[] = $attachment['filename'];
        $files[] = $attachment['thumb'];
    }

    db()->prepare('DELETE FROM notes WHERE id = ? AND user_id = ?')->execute([$noteId, $user['id']]);

    foreach ($files as $file) {
        unlink_upload($file);
    }

    json_response(['ok' => true, 'permanent' => true]);
}

function api_restore_note(int $noteId): void
{
    note_row_for_user($noteId);
    db()->prepare('UPDATE notes SET trashed_at = NULL, updated_at = NOW() WHERE id = ?')->execute([$noteId]);
    api_note_by_id($noteId);
}

function api_empty_trash(): void
{
    $user = require_user();

    $stmt = db()->prepare(
        'SELECT a.filename, a.thumb
           FROM attachments a
           JOIN notes n ON n.id = a.note_id
          WHERE n.user_id = ? AND n.trashed_at IS NOT NULL'
    );
    $stmt->execute([$user['id']]);

    $files = [];
    foreach ($stmt->fetchAll() as $attachment) {
        $files[] = $attachment['filename'];
        $files[] = $attachment['thumb'];
    }

    $delete = db()->prepare('DELETE FROM notes WHERE user_id = ? AND trashed_at IS NOT NULL');
    $delete->execute([$user['id']]);
    $deleted = $delete->rowCount();

    foreach ($files as $file) {
        unlink_upload($file);
    }

    json_response(['ok' => true, 'deleted' => $deleted]);
}

function api_list_labels(): void
{
    $user = require_user();
    json_response(['labels' => labels_with_counts($user['id'])]);
}

function api_create_label(): void
{
    $user  = require_user();
    $input = json_input();
    $name  = mb_substr(trim((string) ($input['name'] ?? '')), 0, 80);

    if ($name === '') {
        throw new ApiException('Nama label tidak boleh kosong.', 422);
    }

    try {
        $stmt = db()->prepare('INSERT INTO labels (user_id, name) VALUES (?, ?)');
        $stmt->execute([$user['id'], $name]);
    } catch (PDOException $e) {
        if ((int) $e->getCode() === 23000) {
            throw new ApiException('Label itu sudah ada.', 409);
        }
        throw $e;
    }

    json_response(['label' => [
        'id'    => (int) db()->lastInsertId(),
        'name'  => $name,
        'count' => 0,
    ]], 201);
}

function api_update_label(int $labelId): void
{
    $row   = label_row_for_user($labelId);
    $input = json_input();
    $name  = mb_substr(trim((string) ($input['name'] ?? '')), 0, 80);

    if ($name === '') {
        throw new ApiException('Nama label tidak boleh kosong.', 422);
    }

    try {
        db()->prepare('UPDATE labels SET name = ? WHERE id = ?')->execute([$name, $row['id']]);
    } catch (PDOException $e) {
        if ((int) $e->getCode() === 23000) {
            throw new ApiException('Label itu sudah ada.', 409);
        }
        throw $e;
    }

    json_response(['label' => ['id' => (int) $row['id'], 'name' => $name]]);
}

function api_delete_label(int $labelId): void
{
    label_row_for_user($labelId);
    db()->prepare('DELETE FROM labels WHERE id = ?')->execute([$labelId]);
    json_response(['ok' => true]);
}

function unlink_upload(?string $filename): void
{
    if ($filename === null || $filename === '') {
        return;
    }

    $safe = basename($filename);
    $path = rtrim((string) config('app.upload_dir'), '/') . '/' . $safe;

    if (is_file($path)) {
        @unlink($path);
    }
}

function gd_resize(GdImage $src, int $width, int $height, int $max, string $mime): GdImage
{
    $scale = min(1.0, $max / max($width, $height));
    $newW  = max(1, (int) round($width * $scale));
    $newH  = max(1, (int) round($height * $scale));

    $dst = imagecreatetruecolor($newW, $newH);

    $hasAlpha = in_array($mime, ['image/png', 'image/webp', 'image/gif'], true);
    if ($hasAlpha) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
    }

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $width, $height);

    return $dst;
}

function gd_write(GdImage $image, string $path, string $extension): bool
{
    return match ($extension) {
        'jpg'  => imagejpeg($image, $path, 85),
        'png'  => imagepng($image, $path, 6),
        'webp' => imagewebp($image, $path, 85),
        'gif'  => imagegif($image, $path),
        default => false,
    };
}

function api_upload_attachment(int $noteId): void
{
    $user = require_user();
    note_row_for_user($noteId);

    if (!isset($_FILES['image']) || !is_array($_FILES['image'])) {
        throw new ApiException('Pilih gambar terlebih dahulu.', 422);
    }

    $file = $_FILES['image'];

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE   => 'Gambar melebihi batas unggah server.',
            UPLOAD_ERR_FORM_SIZE  => 'Gambar terlalu besar.',
            UPLOAD_ERR_PARTIAL    => 'Unggahan terputus. Coba lagi.',
            UPLOAD_ERR_NO_FILE    => 'Tidak ada file yang dipilih.',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder sementara tidak tersedia.',
            UPLOAD_ERR_CANT_WRITE => 'Server gagal menulis file.',
        ];
        throw new ApiException($messages[(int) $file['error']] ?? 'Unggahan gagal.', 400);
    }

    $maxSize = (int) config('app.upload_max');
    $size    = (int) ($file['size'] ?? 0);

    if ($size <= 0) {
        throw new ApiException('File kosong.', 422);
    }

    if ($size > $maxSize) {
        throw new ApiException('Ukuran gambar maksimal ' . round($maxSize / 1048576, 1) . ' MB.', 413);
    }

    $tmp = (string) ($file['tmp_name'] ?? '');

    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new ApiException('Unggahan tidak valid.', 400);
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = (string) $finfo->file($tmp);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    if (!isset($allowed[$mime])) {
        throw new ApiException('Format gambar harus JPG, PNG, WebP, atau GIF.', 415);
    }

    $extension = $allowed[$mime];
    $info      = @getimagesize($tmp);

    if ($info === false) {
        throw new ApiException('File gambar tidak bisa dibaca.', 422);
    }

    $width  = (int) $info[0];
    $height = (int) $info[1];

    if ($width < 1 || $height < 1 || $width > 12000 || $height > 12000) {
        throw new ApiException('Dimensi gambar tidak didukung.', 422);
    }

    $src = match ($mime) {
        'image/jpeg' => imagecreatefromjpeg($tmp),
        'image/png'  => imagecreatefrompng($tmp),
        'image/webp' => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($tmp) : false,
        'image/gif'  => imagecreatefromgif($tmp),
        default      => false,
    };

    if (!$src instanceof GdImage) {
        throw new ApiException('Server tidak bisa memproses gambar ini.', 500);
    }

    $full  = gd_resize($src, $width, $height, 1800, $mime);
    $thumb = gd_resize($src, $width, $height, 560, $mime);

    $dir = rtrim((string) config('app.upload_dir'), '/');
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new ApiException('Folder unggahan tidak bisa dibuat.', 500);
    }

    $token     = bin2hex(random_bytes(10));
    $filename  = $token . '.' . $extension;
    $thumbName = $token . '_thumb.' . $extension;
    $fullPath  = $dir . '/' . $filename;
    $thumbPath = $dir . '/' . $thumbName;

    $ok = gd_write($full, $fullPath, $extension) && gd_write($thumb, $thumbPath, $extension);

    if (!$ok) {
        @unlink($fullPath);
        @unlink($thumbPath);
        throw new ApiException('Gagal menyimpan gambar.', 500);
    }

    $storedSize = (int) (filesize($fullPath) ?: $size);

    $stmt = db()->prepare(
        'INSERT INTO attachments (note_id, user_id, filename, thumb, mime, size, width, height)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$noteId, $user['id'], $filename, $thumbName, $mime, $storedSize, $width, $height]);

    $attachmentId = (int) db()->lastInsertId();

    json_response([
        'attachment' => [
            'id'       => $attachmentId,
            'filename' => $filename,
            'url'      => upload_url($filename),
            'thumb'    => upload_url($thumbName),
            'mime'     => $mime,
            'size'     => $storedSize,
            'width'    => $width,
            'height'   => $height,
        ],
    ], 201);
}

function api_delete_attachment(int $attachmentId): void
{
    $user = require_user();

    $stmt = db()->prepare('SELECT * FROM attachments WHERE id = ? AND user_id = ?');
    $stmt->execute([$attachmentId, $user['id']]);
    $row = $stmt->fetch();

    if (!$row) {
        throw new ApiException('Lampiran tidak ditemukan.', 404);
    }

    db()->prepare('DELETE FROM attachments WHERE id = ?')->execute([$attachmentId]);

    unlink_upload($row['filename']);
    unlink_upload($row['thumb']);

    json_response(['ok' => true]);
}

function api_login(): void
{
    $input = json_input();

    $email    = mb_strtolower(trim((string) ($input['email'] ?? '')));
    $password = (string) ($input['password'] ?? '');

    $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, (string) $user['password_hash'])) {
        throw new ApiException('Email atau kata sandi salah.', 401);
    }

    if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }

    $token = login_user((int) $user['id']);

    json_response([
        'ok'    => true,
        'token' => $token,
        'user'  => [
            'id'         => (int) $user['id'],
            'name'       => $user['name'],
            'email'      => $user['email'],
            'created_at' => $user['created_at'],
        ],
    ]);
}

function api_health(): void
{
    try {
        db()->query('SELECT 1');
        json_response([
            'ok'   => true,
            'db'   => true,
            'time' => gmdate('c'),
            'app'  => config('app.name'),
        ]);
    } catch (Throwable $e) {
        json_response(['ok' => false, 'db' => false], 503);
    }
}

function api_dispatch(string $method, string $path): void
{
    try {
        $path = '/' . trim($path, '/');

        if ($method === 'GET' && $path === '/health') {
            api_health();
        }

        if ($method === 'POST' && $path === '/auth/login') {
            api_login();
        }

        if ($method === 'POST' && $path === '/auth/logout') {
            // Stateless: the server keeps no token state, the browser drops it.
            json_response(['ok' => true]);
        }

        // Everything below requires a valid "Authorization: Bearer <token>".
        $user = require_user();

        if ($method === 'GET' && $path === '/me') {
            json_response(['user' => $user]);
        }

        if ($method === 'GET' && $path === '/notes') {
            api_list_notes();
        }

        if ($method === 'POST' && $path === '/notes') {
            api_create_note();
        }

        if (preg_match('#^/notes/(\d+)$#', $path, $matches)) {
            $noteId = (int) $matches[1];

            if ($method === 'GET') {
                api_note_by_id($noteId);
            }
            if ($method === 'PATCH') {
                api_update_note($noteId);
            }
            if ($method === 'DELETE') {
                api_delete_note($noteId);
            }
        }

        if ($method === 'POST' && preg_match('#^/notes/(\d+)/restore$#', $path, $matches)) {
            api_restore_note((int) $matches[1]);
        }

        if ($method === 'POST' && preg_match('#^/notes/(\d+)/attachments$#', $path, $matches)) {
            api_upload_attachment((int) $matches[1]);
        }

        if ($method === 'DELETE' && preg_match('#^/attachments/(\d+)$#', $path, $matches)) {
            api_delete_attachment((int) $matches[1]);
        }

        if ($method === 'GET' && $path === '/labels') {
            api_list_labels();
        }

        if ($method === 'POST' && $path === '/labels') {
            api_create_label();
        }

        if (preg_match('#^/labels/(\d+)$#', $path, $matches)) {
            $labelId = (int) $matches[1];

            if ($method === 'PATCH') {
                api_update_label($labelId);
            }
            if ($method === 'DELETE') {
                api_delete_label($labelId);
            }
        }

        if ($method === 'POST' && $path === '/trash/empty') {
            api_empty_trash();
        }

        throw new ApiException('Endpoint tidak ditemukan.', 404);
    } catch (ApiException $e) {
        json_response(['error' => $e->getMessage()], $e->status);
    } catch (PDOException $e) {
        error_log('DiarKeep DB error: ' . $e->getMessage());
        json_response([
            'error' => config('app.debug')
                ? 'Database: ' . $e->getMessage()
                : 'Database sedang bermasalah. Coba lagi sebentar lagi.',
        ], 500);
    } catch (Throwable $e) {
        error_log('DiarKeep error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        json_response([
            'error' => config('app.debug')
                ? $e->getMessage()
                : 'Terjadi kesalahan pada server.',
        ], 500);
    }
}
