<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
session_start();

action_dispatch();

function action_dispatch(): void
{
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    if ($action === '') {
        echo json_encode(['error' => 'Action missing']);
        return;
    }
    switch ($action) {
        case 'get_content':
            ensure_authenticated();
            $content = read_json(CONTENT_FILE);
            echo json_encode($content);
            break;
        case 'save_content':
            ensure_authenticated();
            save_content();
            break;
        case 'upload':
            ensure_authenticated();
            handle_upload();
            break;
        case 'backup_list':
            ensure_authenticated();
            list_backups();
            break;
        default:
            echo json_encode(['error' => 'Unknown action']);
    }
}

function save_content(): void
{
    $payload = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid payload']);
        return;
    }
    $content = read_json(CONTENT_FILE);
    $merged = array_replace_recursive($content, $payload);
    backup_file(CONTENT_FILE);
    if (!write_json(CONTENT_FILE, $merged)) {
        http_response_code(500);
        echo json_encode(['error' => 'Speichern fehlgeschlagen']);
        return;
    }
    secure_log('Content updated via API');
    echo json_encode(['success' => true]);
}

function handle_upload(): void
{
    if (!isset($_FILES['files'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Keine Dateien erhalten']);
        return;
    }
    $files = $_FILES['files'];
    $uploaded = [];
    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }
        if ($files['size'][$i] > MAX_UPLOAD_SIZE) {
            continue;
        }
        $tmp = $files['tmp_name'][$i];
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($files['name'][$i]));
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp','svg'];
        if (!in_array($ext, $allowed, true)) {
            continue;
        }
        $destination = UPLOAD_DIR . '/' . uniqid('upload_', true) . '.' . $ext;
        if (!move_uploaded_file($tmp, $destination)) {
            continue;
        }
        $uploaded[] = [
            'name' => $filename,
            'url' => str_replace(__DIR__ . '/', '', $destination)
        ];
    }
    if (!$uploaded) {
        http_response_code(400);
        echo json_encode(['error' => 'Keine Dateien gespeichert']);
        return;
    }
    echo json_encode(['success' => true, 'files' => $uploaded]);
}

function list_backups(): void
{
    $files = glob(BACKUP_DIR . '/*.json');
    $entries = [];
    foreach ($files as $file) {
        $entries[] = [
            'name' => basename($file),
            'size' => filesize($file),
            'modified' => date('c', filemtime($file))
        ];
    }
    echo json_encode(['backups' => $entries]);
}
