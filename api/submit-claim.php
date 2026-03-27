<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_post_method();

// Check if user is logged in
if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'Authentication required'], 401);
}

$payload = json_decode(file_get_contents('php://input'), true);
$itemId = (int)($payload['item_id'] ?? 0);
$claimNotes = sanitize_input($payload['claim_notes'] ?? '');

if (empty($itemId)) {
    json_response(['success' => false, 'message' => 'Item ID is required'], 422);
}

$userId = $_SESSION['user_id'];

// Check if item exists and is a found item
$stmt = $mysqli->prepare("SELECT id, item_name, item_type, status FROM items WHERE id = ? AND item_type = 'found' AND status = 'approved'");
$stmt->bind_param('i', $itemId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    json_response(['success' => false, 'message' => 'Item not found or not eligible for claiming'], 404);
}

$item = $result->fetch_assoc();

// Check if user has already claimed this item
$stmt = $mysqli->prepare("SELECT id FROM claims WHERE item_id = ? AND claimed_by_user_id = ?");
$stmt->bind_param('ii', $itemId, $userId);
$stmt->execute();
$existingClaim = $stmt->get_result();

if ($existingClaim->num_rows > 0) {
    json_response(['success' => false, 'message' => 'You have already claimed this item'], 409);
}

// Insert the claim
$stmt = $mysqli->prepare("INSERT INTO claims (item_id, claimed_by_user_id, claim_notes) VALUES (?, ?, ?)");
$stmt->bind_param('iis', $itemId, $userId, $claimNotes);

if ($stmt->execute()) {
    $claimId = $mysqli->insert_id;
    
    // Create notification for admin
    $message = sprintf('New claim submitted for item: %s by %s', $item['item_name'], $_SESSION['user_name']);
    $notificationStmt = $mysqli->prepare("INSERT INTO notifications (item_id, channel, message) VALUES (?, 'in-app', ?)");
    $notificationStmt->bind_param('is', $itemId, $message);
    $notificationStmt->execute();
    
    json_response([
        'success' => true, 
        'message' => 'Claim submitted successfully. Awaiting admin approval.',
        'claim_id' => $claimId
    ]);
} else {
    json_response(['success' => false, 'message' => 'Failed to submit claim'], 500);
}
?>
