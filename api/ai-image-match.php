<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_post_method();

$payload = json_decode(file_get_contents('php://input'), true);
$description = sanitize_input($payload['description'] ?? '');
$imageData = $payload['image_data'] ?? ''; // Base64 encoded image

if (empty($description)) {
    json_response(['success' => false, 'message' => 'Description required'], 422);
}

// Fetch approved items from DB with contact info and images
$stmt = $mysqli->prepare("SELECT id, item_name, description, location, item_type, contact_name, contact_email, contact_phone, image_path FROM items WHERE status = 'approved'");
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Check AI API key
if (!defined('AI_API_KEY') || AI_API_KEY === '' || AI_API_KEY === 'YOUR_OPENROUTER_API_KEY_HERE') {
    json_response([
        'success' => false,
        'message' => 'AI API key is not configured. Please set AI_API_KEY in includes/config.php.',
    ], 500);
}

// Prepare items for AI (include image info)
$itemsForAI = [];
foreach ($items as $item) {
    $itemData = [
        'item_id' => $item['id'],
        'item_name' => $item['item_name'],
        'description' => $item['description'],
        'location' => $item['location'],
        'item_type' => $item['item_type'],
        'has_image' => !empty($item['image_path'])
    ];
    
    // If the item has an image, read and encode it
    if (!empty($item['image_path']) && file_exists(__DIR__ . '/../' . $item['image_path'])) {
        $imageContent = file_get_contents(__DIR__ . '/../' . $item['image_path']);
        $itemData['image_data'] = base64_encode($imageContent);
    }
    
    $itemsForAI[] = $itemData;
}

// Prepare AI prompt for image matching
$promptPayload = [
    'query' => $description,
    'query_image' => $imageData,
    'items' => $itemsForAI,
];

$requestBody = [
    'model' => defined('AI_API_MODEL') ? AI_API_MODEL : 'openai/gpt-4o-mini',
    'response_format' => ['type' => 'json_object'],
    'messages' => [
        [
            'role' => 'system',
            'content' => 'You are an advanced image recognition and matching engine for a lost-and-found system. You MUST always respond ONLY with valid JSON using exactly this structure: {"matches":[{"item_id": number, "item_name": string, "description": string, "location": string, "item_type": string, "score": float between 0 and 1, "query_label": string, "match_reason": string} ...]}. Matching rules: (1) Analyze both text descriptions AND images when available. (2) Use higher scores (>=0.8) when images clearly show the same object. (3) Use medium scores (0.5-0.7) when descriptions match well but images are unclear. (4) Use lower scores (<0.5) for weak matches. (5) Provide a brief "match_reason" explaining why items match. (6) Return at most 5 matches, sorted by score descending. (7) If no items match well, return {"matches":[]}. Do not include any extra fields or text.',
        ],
        [
            'role' => 'user',
            'content' => json_encode($promptPayload),
        ],
    ],
];

$ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . AI_API_KEY,
    'HTTP-Referer: ' . APP_BASE_URL,
    'X-Title: Loyola Lost & Found AI Image Matching',
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    json_response(['success' => false, 'message' => 'AI API request failed', 'http_code' => $httpCode], 500);
}

$data = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    json_response(['success' => false, 'message' => 'Invalid AI response format'], 500);
}

$matches = $data['choices'][0]['message']['content'];
$matchesData = json_decode($matches, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    json_response(['success' => false, 'message' => 'Failed to parse AI matches'], 500);
}

// Enrich matches with contact info
$enrichedMatches = [];
foreach ($matchesData['matches'] as $match) {
    $originalItem = null;
    foreach ($items as $item) {
        if ($item['id'] == $match['item_id']) {
            $originalItem = $item;
            break;
        }
    }
    
    if ($originalItem) {
        $enrichedMatches[] = [
            'item_id' => $match['item_id'],
            'item_name' => $match['item_name'],
            'description' => $match['description'],
            'location' => $match['location'],
            'item_type' => $match['item_type'],
            'score' => $match['score'],
            'query_label' => $match['query_label'] ?? '',
            'match_reason' => $match['match_reason'] ?? '',
            'contact_name' => $originalItem['contact_name'],
            'contact_email' => $originalItem['contact_email'],
            'contact_phone' => $originalItem['contact_phone'] ?? 'Not provided',
            'image_path' => $originalItem['image_path'] ?? ''
        ];
    }
}

// Log the match
$matchLogStmt = $mysqli->prepare("INSERT INTO match_logs (lost_item_name, found_item_name, score, created_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)");
foreach ($enrichedMatches as $match) {
    $matchLogStmt->bind_param('ssd', $description, $match['item_name'], $match['score']);
    $matchLogStmt->execute();
}

json_response([
    'success' => true,
    'matches' => $enrichedMatches,
    'message' => 'Found ' . count($enrichedMatches) . ' matches using AI image recognition'
]);
?>
