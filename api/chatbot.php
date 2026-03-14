<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';

// Helper function to load .env manually (if no library is installed)
function loadDotEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(trim($name) . "=" . trim($value));
    }
}

loadDotEnv(__DIR__ . '/../.env');

// --- CONFIGURATION ---
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY')); 
define('USE_MOCK_AI', false); 

$data = json_decode(file_get_contents('php://input'), true);
$query = $data['query'] ?? '';
$action = $data['action'] ?? 'chat';

if ($action === 'history') {
    fetchHistory($pdo);
    exit;
}

if (empty($query)) {
    echo json_encode(['reply' => 'I am here to help. What herbal wisdom are you seeking today?']);
    exit;
}

try {
    // 1. FETCH CONVERSATION HISTORY (Memory)
    $history = [];
    $userId = $_SESSION['user_id'] ?? null;
    
    if ($userId) {
        $historyStmt = $pdo->prepare("SELECT query, response FROM ai_logs WHERE user_id = ? AND action_type = 'chatbot' ORDER BY timestamp DESC LIMIT 10");
        $historyStmt->execute([$userId]);
        $logs = array_reverse($historyStmt->fetchAll(PDO::FETCH_ASSOC));
        
        foreach ($logs as $log) {
            $history[] = ['role' => 'user', 'parts' => [['text' => $log['query']]]];
            $history[] = ['role' => 'model', 'parts' => [['text' => $log['response']]]];
        }
    }

    // 2. DATABASE RETRIEVAL (Local Knowledge)
    $stmt = $pdo->prepare("SELECT * FROM plants WHERE plant_name LIKE ? OR illness_treated LIKE ? LIMIT 3");
    $searchTerm = "%$query%";
    $stmt->execute([$searchTerm, $searchTerm]);
    $localContext = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $contextString = "";
    if ($localContext) {
        $contextString = "LOCAL DATABASE INFO: ";
        foreach ($localContext as $p) {
            $contextString .= "({$p['plant_name']}: treats {$p['illness_treated']}, prep: {$p['preparation_method']}). ";
        }
    }

    // 3. AI GENERATION with Gemini
    $systemInstruction = "You are Aura, the Healing Compass AI. You are an expert in Traditional Medicine. 
                          Context from our database: [$contextString]. 
                          Remember previous parts of this conversation. 
                          If info isn't in context, use your vast knowledge but always advise seeing our healers. 
                          Keep answers concise and helpful.";

    $contents = $history;
    $currentText = "Context/Instruction: $systemInstruction \n\n User Question: $query";
    $contents[] = ['role' => 'user', 'parts' => [['text' => $currentText]]];

    $reply = callGemini($contents);

    // 4. LOG THE INTERACTION
    $logStmt = $pdo->prepare("INSERT INTO ai_logs (user_id, action_type, query, response) VALUES (?, 'chatbot', ?, ?)");
    $logStmt->execute([$userId, $query, $reply]);

    echo json_encode(['reply' => $reply]);

} catch (Exception $e) {
    echo json_encode(['reply' => "The connection to the ancestors is weak. (Error: " . $e->getMessage() . ")"]);
}

function fetchHistory($pdo) {
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) { echo json_encode([]); return; }
    
    $stmt = $pdo->prepare("SELECT query, response, timestamp FROM ai_logs WHERE user_id = ? AND action_type = 'chatbot' ORDER BY timestamp DESC LIMIT 20");
    $stmt->execute([$userId]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function callGemini($contents) {
    $apiKey = GEMINI_API_KEY;
    if (!$apiKey) throw new Exception("API Key not found in environment.");
    
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=' . $apiKey;
    
    $data = ['contents' => $contents];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    if (curl_errno($ch)) throw new Exception(curl_error($ch));
    
    $result = json_decode($response, true);
    curl_close($ch);

    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        return $result['candidates'][0]['content']['parts'][0]['text'];
    } else {
        return "I cannot answer that right now. Error: " . ($result['error']['message'] ?? 'Unknown API response');
    }
}
?>


