<?php
require_once 'includes/helpers.php';
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

echo "<h2>Debug Claim Submission</h2>";

// Check authentication
if (!is_logged_in()) {
    echo "<p style='color: red;'>Not logged in</p>";
} else {
    echo "<p style='color: green;'>Logged in as: " . $_SESSION['user_name'] . " (ID: " . $_SESSION['user_id'] . ")</p>";
}

// Test with a sample item ID (you can change this)
$itemId = 1; // Change this to test with different item IDs
$userId = $_SESSION['user_id'];

echo "<h3>Testing with Item ID: $itemId</h3>";

// Check if claims table exists
$result = $mysqli->query("SHOW TABLES LIKE 'claims'");
if ($result->num_rows === 0) {
    echo "<p style='color: red;'>Claims table does not exist!</p>";
} else {
    echo "<p style='color: green;'>Claims table exists</p>";
}

// Check if item exists
$stmt = $mysqli->prepare("SELECT id, item_name, item_type, status FROM items WHERE id = ?");
$stmt->bind_param('i', $itemId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<p style='color: red;'>Item $itemId not found</p>";
} else {
    $item = $result->fetch_assoc();
    echo "<p>Item found: " . htmlspecialchars($item['item_name']) . " (Type: " . $item['item_type'] . ", Status: " . $item['status'] . ")</p>";
    
    if ($item['item_type'] !== 'found') {
        echo "<p style='color: red;'>Item is not a 'found' item - cannot be claimed</p>";
    } elseif ($item['status'] !== 'approved') {
        echo "<p style='color: red;'>Item is not 'approved' - cannot be claimed</p>";
    } else {
        echo "<p style='color: green;'>Item is eligible for claiming</p>";
    }
}

// Check existing claims
$stmt = $mysqli->prepare("SELECT id, claim_status FROM claims WHERE item_id = ? AND claimed_by_user_id = ?");
$stmt->bind_param('ii', $itemId, $userId);
$stmt->execute();
$existingClaim = $stmt->get_result();

echo "<h3>Existing Claims Check</h3>";
if ($existingClaim->num_rows === 0) {
    echo "<p style='color: green;'>No existing claims found for this item by this user</p>";
} else {
    echo "<p style='color: red;'>Found " . $existingClaim->num_rows . " existing claims:</p>";
    while ($claim = $existingClaim->fetch_assoc()) {
        echo "<p>Claim ID: " . $claim['id'] . ", Status: " . $claim['claim_status'] . "</p>";
    }
}

// Show all claims for this item
$stmt = $mysqli->prepare("SELECT c.id, c.claim_status, c.created_at, u.name as claimed_by_name FROM claims c JOIN users u ON c.claimed_by_user_id = u.id WHERE c.item_id = ?");
$stmt->bind_param('i', $itemId);
$stmt->execute();
$allClaims = $stmt->get_result();

echo "<h3>All Claims for Item $itemId</h3>";
if ($allClaims->num_rows === 0) {
    echo "<p>No claims found for this item</p>";
} else {
    echo "<table border='1'>";
    echo "<tr><th>Claim ID</th><th>Claimed By</th><th>Status</th><th>Date</th></tr>";
    while ($claim = $allClaims->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $claim['id'] . "</td>";
        echo "<td>" . htmlspecialchars($claim['claimed_by_name']) . "</td>";
        echo "<td>" . $claim['claim_status'] . "</td>";
        echo "<td>" . $claim['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Show all items that can be claimed
$stmt = $mysqli->prepare("SELECT id, item_name, item_type, status FROM items WHERE item_type = 'found' AND status = 'approved'");
$stmt->execute();
$claimableItems = $stmt->get_result();

echo "<h3>All Claimable Items</h3>";
if ($claimableItems->num_rows === 0) {
    echo "<p>No items available for claiming</p>";
} else {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Name</th><th>Type</th><th>Status</th></tr>";
    while ($item = $claimableItems->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $item['id'] . "</td>";
        echo "<td>" . htmlspecialchars($item['item_name']) . "</td>";
        echo "<td>" . $item['item_type'] . "</td>";
        echo "<td>" . $item['status'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}
?>
