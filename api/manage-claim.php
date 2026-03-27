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
$claimId = (int)($payload['claim_id'] ?? 0);
$action = sanitize_input($payload['action'] ?? ''); // 'approve' or 'reject'
$adminNotes = sanitize_input($payload['admin_notes'] ?? '');

if (empty($claimId) || !in_array($action, ['approve', 'reject'])) {
    json_response(['success' => false, 'message' => 'Invalid request'], 422);
}

// Get claim details with item and user info
$stmt = $mysqli->prepare("
    SELECT c.id, c.item_id, c.claimed_by_user_id, c.claim_status,
           i.item_name, i.item_type, i.status as item_status,
           u.name as claimed_by_name, u.email as claimed_by_email
    FROM claims c
    JOIN items i ON c.item_id = i.id
    JOIN users u ON c.claimed_by_user_id = u.id
    WHERE c.id = ?
");
$stmt->bind_param('i', $claimId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    json_response(['success' => false, 'message' => 'Claim not found'], 404);
}

$claim = $result->fetch_assoc();

// Check if claim is still pending
if ($claim['claim_status'] !== 'pending') {
    json_response(['success' => false, 'message' => 'Claim has already been processed'], 409);
}

// Update claim status
$newStatus = $action === 'approve' ? 'approved' : 'rejected';
$stmt = $mysqli->prepare("UPDATE claims SET claim_status = ?, admin_notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
$stmt->bind_param('ssi', $newStatus, $adminNotes, $claimId);

if ($stmt->execute()) {
    // If approved, update item status to 'claimed' and remove corresponding lost requests
    if ($action === 'approve') {
        // Option 1: Update item status to 'claimed'
        $updateStmt = $mysqli->prepare("UPDATE items SET status = 'claimed' WHERE id = ?");
        $updateStmt->bind_param('i', $claim['item_id']);
        $updateStmt->execute();
        
        // NEW: Remove corresponding lost requests for the same item
        if ($claim['item_type'] === 'found') {
            // Find and update corresponding lost items with similar names
            $lostStmt = $mysqli->prepare("
                UPDATE items 
                SET status = 'resolved' 
                WHERE item_type = 'lost' 
                AND status = 'approved' 
                AND (
                    LOWER(item_name) LIKE LOWER(?) 
                    OR LOWER(item_name) LIKE LOWER(?) 
                    OR LOWER(item_name) LIKE LOWER(?)
                )
            ");
            
            $itemName = trim($claim['item_name']);
            $like1 = "%{$itemName}%";
            $like2 = "%{$itemName}%";
            $like3 = "%{$itemName}%";
            
            $lostStmt->bind_param('sss', $like1, $like2, $like3);
            $lostStmt->execute();
            
            // Log removal of lost items
            $removedCount = $mysqli->affected_rows;
            if ($removedCount > 0) {
                $message = sprintf('Automatically resolved %d lost request(s) for "%s" after found item was claimed', $removedCount, $claim['item_name']);
                $logStmt = $mysqli->prepare("INSERT INTO notifications (item_id, channel, message) VALUES (?, 'in-app', ?)");
                $logStmt->bind_param('is', $claim['item_id'], $message);
                $logStmt->execute();
            }
        }
        
        // Create notification for user who claimed
        $message = sprintf('Your claim for %s has been approved!', $claim['item_name']);
        $notificationStmt = $mysqli->prepare("INSERT INTO notifications (item_id, channel, message) VALUES (?, 'in-app', ?)");
        $notificationStmt->bind_param('is', $claim['item_id'], $message);
        $notificationStmt->execute();
    } else {
        // Create notification for user who claimed
        $message = sprintf('Your claim for %s has been rejected.', $claim['item_name']);
        $notificationStmt = $mysqli->prepare("INSERT INTO notifications (item_id, channel, message) VALUES (?, 'in-app', ?)");
        $notificationStmt->bind_param('is', $claim['item_id'], $message);
        $notificationStmt->execute();
    }
    
    json_response([
        'success' => true, 
        'message' => "Claim {$newStatus} successfully"
    ]);
} else {
    json_response(['success' => false, 'message' => 'Failed to update claim'], 500);
}
?>
