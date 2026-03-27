<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_post_method();

// Check if user is logged in and is admin
if (!is_logged_in() || $_SESSION['user_role'] !== 'admin') {
    json_response(['success' => false, 'message' => 'Admin access required'], 403);
}

$payload = json_decode(file_get_contents('php://input'), true);
$itemId = (int)($payload['item_id'] ?? 0);

if (empty($itemId)) {
    json_response(['success' => false, 'message' => 'Item ID is required'], 422);
}

// Start transaction
$mysqli->begin_transaction();

try {
    // Update item status back to 'approved'
    $stmt = $mysqli->prepare("UPDATE items SET status = 'approved' WHERE id = ?");
    $stmt->bind_param('i', $itemId);
    $stmt->execute();
    
    // Update claim status to 'returned'
    $stmt = $mysqli->prepare("UPDATE claims SET claim_status = 'returned', admin_notes = CONCAT(admin_notes, ' | Returned to listings on ', NOW()) WHERE item_id = ?");
    $stmt->bind_param('i', $itemId);
    $stmt->execute();
    
    // Create notification
    $stmt = $mysqli->prepare("SELECT item_name FROM items WHERE id = ?");
    $stmt->bind_param('i', $itemId);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
    
    $message = sprintf('Item %s has been returned to active listings', $item['item_name']);
    $notificationStmt = $mysqli->prepare("INSERT INTO notifications (item_id, channel, message) VALUES (?, 'in-app', ?)");
    $notificationStmt->bind_param('is', $itemId, $message);
    $notificationStmt->execute();
    
    $mysqli->commit();
    
    json_response(['success' => true, 'message' => 'Item returned to listings successfully']);
    
} catch (Exception $e) {
    $mysqli->rollback();
    json_response(['success' => false, 'message' => 'Failed to return item: ' . $e->getMessage()], 500);
}
?>
