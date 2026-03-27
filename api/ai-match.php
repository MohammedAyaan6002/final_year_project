<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_post_method();

$payload = json_decode(file_get_contents('php://input'), true);
$description = sanitize_input($payload['description'] ?? '');

if (empty($description)) {
    json_response(['success' => false, 'message' => 'Description required'], 422);
}

// Fetch approved items from DB with contact info
$stmt = $mysqli->prepare("SELECT id, item_name, description, location, item_type, contact_name, contact_email, contact_phone FROM items WHERE status = 'approved'");
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Decide whether AI API key is configured
if (!defined('AI_API_KEY') || AI_API_KEY === '' || AI_API_KEY === 'YOUR_OPENROUTER_API_KEY_HERE') {
    json_response([
        'success' => false,
        'message' => 'AI API key is not configured. Please set AI_API_KEY in includes/config.php.',
    ], 500);
}

// Always use OpenRouter – no Python required
$data = null;

{
    $promptPayload = [
        'query' => $description,
        'items' => $items,
    ];

    $requestBody = [
        'model' => defined('AI_API_MODEL') ? AI_API_MODEL : 'openai/gpt-4o-mini',
        'response_format' => ['type' => 'json_object'],
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are a matching engine for a lost-and-found system. You MUST always respond ONLY with valid JSON using exactly this structure: {"matches":[{"item_id": number, "item_name": string, "description": string, "location": string, "item_type": string, "score": float between 0 and 1, "query_label": string} ...]}. Behaviour rules: (1) When the items list is not empty, you MUST ALWAYS return at least one match (the best one), even if similarity is low. (2) Return at most 5 matches, sorted by score descending. (3) Use higher scores (>=0.6) only when descriptions clearly describe the same real-world item; use lower scores for weak matches. (4) If the items list is completely empty, return {"matches":[]} . Do not include any extra fields or text.',
            ],
            [
                'role' => 'user',
                'content' => json_encode($promptPayload),
            ],
        ],
    ];

    $ch = curl_init(defined('AI_API_BASE_URL') ? AI_API_BASE_URL : 'https://openrouter.ai/api/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . AI_API_KEY,
            'HTTP-Referer: ' . APP_BASE_URL,
            'X-Title: Loyola Lost & Found AI Matcher',
        ],
        CURLOPT_POSTFIELDS => json_encode($requestBody),
        CURLOPT_TIMEOUT => 20,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        json_response([
            'success' => false,
            'message' => 'AI API request failed',
            'error' => $curlError,
        ], 502);
    }

    if ($httpCode !== 200) {
        json_response([
            'success' => false,
            'message' => 'AI API returned an error',
            'http_code' => $httpCode,
            'response' => $response,
        ], $httpCode >= 500 ? 502 : 400);
    }

    $raw = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || empty($raw['choices'][0]['message']['content'])) {
        json_response([
            'success' => false,
            'message' => 'Invalid AI API response format',
            'error' => json_last_error_msg(),
            'raw_response' => substr($response, 0, 200),
        ], 500);
    }

    $content = $raw['choices'][0]['message']['content'];
    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        json_response([
            'success' => false,
            'message' => 'AI API did not return valid JSON',
            'error' => json_last_error_msg(),
            'raw_content' => substr($content, 0, 200),
        ], 500);
    }
}

// Log matches and create notifications
if (!empty($data['matches'])) {
    $stmtLog = $mysqli->prepare("INSERT INTO match_logs (lost_item_name, found_item_name, score) VALUES (?, ?, ?)");
    $notificationStmt = $mysqli->prepare("INSERT INTO notifications (item_id, channel, message) VALUES (?, 'email', ?)");
    
    // Create a lookup array for item contact info
    $itemLookup = [];
    foreach ($items as $item) {
        $itemLookup[$item['id']] = $item;
    }
    
    // Enrich matches with contact information
    foreach ($data['matches'] as &$match) {
        if (isset($match['item_id']) && isset($itemLookup[$match['item_id']])) {
            $item = $itemLookup[$match['item_id']];
            $match['contact_name'] = $item['contact_name'];
            $match['contact_email'] = $item['contact_email'];
            $match['contact_phone'] = $item['contact_phone'];
        }
        
        $lostName = $match['query_label'] ?? 'Search';
        $foundName = $match['item_name'] ?? '';
        $score = (float)($match['score'] ?? 0);
        $stmtLog->bind_param('ssd', $lostName, $foundName, $score);
        $stmtLog->execute();

        if ($score >= 0.6 && isset($match['item_id'])) {
            $message = sprintf('Potential match found: %s (score %.1f%%)', $foundName, $score * 100);
            $notificationStmt->bind_param('is', $match['item_id'], $message);
            $notificationStmt->execute();
        }
    }
}

json_response(['success' => true, 'matches' => $data['matches'] ?? []]);