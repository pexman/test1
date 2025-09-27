<?php
session_start();
const DATA_FILE = __DIR__ . '/data/data.json';
const RATE_WINDOW = 60;
const RATE_MAX = 60;

function read_data(): array {
    $fp = @fopen(DATA_FILE, 'r');
    if (!$fp) {
        return ['config' => [], 'pages' => [], 'media' => [], 'consents' => [], 'requests' => []];
    }
    flock($fp, LOCK_SH);
    $content = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    $decoded = json_decode($content, true);
    return is_array($decoded) ? $decoded : ['config' => [], 'pages' => [], 'media' => [], 'consents' => [], 'requests' => []];
}

function write_data(array $data): void {
    $fp = fopen(DATA_FILE, 'c+');
    if (!$fp) {
        throw new RuntimeException('Datenbank nicht schreibbar');
    }
    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}

function ensure_rate_limit(): void {
    $now = time();
    $window = $_SESSION['api_window'] ?? $now;
    $count = $_SESSION['api_count'] ?? 0;
    if ($now - $window > RATE_WINDOW) {
        $_SESSION['api_window'] = $now;
        $_SESSION['api_count'] = 1;
        return;
    }
    if ($count >= RATE_MAX) {
        http_response_code(429);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'rate_limited']);
        exit;
    }
    $_SESSION['api_count'] = $count + 1;
}

function respond(array $payload): void {
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

ensure_rate_limit();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$data = read_data();
$config = $data['config'] ?? [];

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }
    $action = $payload['action'] ?? ($_GET['action'] ?? '');
} else {
    $payload = $_GET;
    $action = $_GET['action'] ?? '';
}

switch ($action) {
    case 'consent.accept':
        $version = $payload['payload']['version'] ?? ($config['legal']['policy_version'] ?? '1');
        $ipHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0') . $_SERVER['HTTP_USER_AGENT']);
        $data['consents'][] = [
            'version' => $version,
            'ip_hash' => $ipHash,
            'timestamp' => gmdate('c')
        ];
        write_data($data);
        respond(['success' => true]);
        break;

    case 'ai.generate':
        if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
            respond(['success' => false, 'error' => 'unauthorized']);
        }
        $type = $payload['payload']['type'] ?? 'text';
        $promptBase = $config['openai']['prompt_template'] ?? '';
        $prompt = strtr($promptBase, [
            '{category}' => $payload['payload']['category'] ?? 'Fotoshooting',
            '{tone}' => $payload['payload']['tone'] ?? 'warm',
            '{audience}' => $payload['payload']['audience'] ?? 'Familien',
            '{keywords}' => $payload['payload']['keywords'] ?? ''
        ]);
        $prompt .= "\nBitte generiere einen kurzen Abschnitt für: " . $type;
        $apiKey = $config['openai']['api_key'] ?? '';
        if (!$apiKey) {
            respond(['success' => true, 'text' => 'Bitte trage zuerst einen OpenAI API Key ein.']);
        }
        $ch = curl_init('https://api.openai.com/v1/responses');
        $request = [
            'model' => $config['openai']['model'] ?? 'gpt-4o-mini',
            'input' => $prompt,
            'temperature' => $config['openai']['temperature'] ?? 0.7
        ];
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ],
            CURLOPT_POSTFIELDS => json_encode($request)
        ]);
        $response = curl_exec($ch);
        if ($response === false) {
            curl_close($ch);
            respond(['success' => false, 'error' => 'curl_error']);
        }
        curl_close($ch);
        $decoded = json_decode($response, true);
        $text = $decoded['output'][0]['content'][0]['text'] ?? ($decoded['choices'][0]['text'] ?? '');
        respond(['success' => true, 'text' => trim($text)]);
        break;

    case 'form.submit':
        if ($method !== 'POST') {
            respond(['success' => false, 'error' => 'invalid_method']);
        }
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        $name = htmlspecialchars(trim($_POST['name'] ?? ''), ENT_QUOTES);
        if (!$email || !$name) {
            respond(['success' => false, 'error' => 'validation']);
        }
        $slug = $_GET['slug'] ?? '';
        $entry = [
            'slug' => $slug,
            'name' => $name,
            'email' => $email,
            'category' => htmlspecialchars(trim($_POST['category'] ?? ''), ENT_QUOTES),
            'source' => htmlspecialchars(trim($_POST['source'] ?? ''), ENT_QUOTES),
            'details' => htmlspecialchars(trim($_POST['details'] ?? ''), ENT_QUOTES),
            'date' => $_POST['date'] ?? '',
            'options' => array_map(fn($opt) => htmlspecialchars($opt, ENT_QUOTES), $_POST['options'] ?? []),
            'message' => htmlspecialchars(trim($_POST['message'] ?? ''), ENT_QUOTES),
            'timestamp' => gmdate('c')
        ];
        $data['requests'][] = $entry;
        write_data($data);
        $isAjax = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
        if ($isAjax) {
            respond(['success' => true, 'message' => 'Danke für deine Anfrage!']);
        }
        header('Location: /form/' . urlencode($slug) . '?sent=1');
        exit;
        break;

    case 'export.request':
        $email = filter_var($payload['email'] ?? '', FILTER_VALIDATE_EMAIL);
        if (!$email) {
            respond(['success' => false, 'error' => 'invalid_email']);
        }
        $matches = array_values(array_filter($data['requests'] ?? [], fn($r) => ($r['email'] ?? '') === $email));
        respond(['success' => true, 'data' => $matches]);
        break;

    case 'delete.request':
        $email = filter_var($payload['email'] ?? '', FILTER_VALIDATE_EMAIL);
        if (!$email) {
            respond(['success' => false, 'error' => 'invalid_email']);
        }
        $data['requests'] = array_values(array_filter($data['requests'] ?? [], fn($r) => ($r['email'] ?? '') !== $email));
        write_data($data);
        respond(['success' => true]);
        break;

    default:
        respond(['success' => false, 'error' => 'unknown_action']);
}
