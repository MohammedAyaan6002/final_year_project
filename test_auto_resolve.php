<?php
require_once 'includes/db.php';

echo "<h2>Test Auto-Resolution Logic</h2>";

// Simulate approving a claim for a found item
$claimId = 1; // Change this to test different claims

echo "<h3>Testing Claim ID: $claimId</h3>";

// Get claim details
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
    echo "<p style='color: red;'>Claim not found</p>";
    exit;
}

$claim = $result->fetch_assoc();

echo "<h4>Claim Details:</h4>";
echo "<pre>";
print_r($claim);
echo "</pre>";

// Test the auto-resolution logic
if ($claim['item_type'] === 'found') {
    echo "<h4>Testing Auto-Resolution Logic:</h4>";
    
    // Find and update corresponding lost items with similar names
    $lostStmt = $mysqli->prepare("
        UPDATE items 
        SET status = 'resolved' 
        WHERE item_type = 'lost' 
        AND status = 'approved' 
        AND (
            item_name LIKE ? 
            OR item_name LIKE ? 
            OR item_name LIKE ?
        )
    ");
    
    $itemName = $claim['item_name'];
    $like1 = "%{$itemName}%";
    $like2 = "%{$itemName}%";
    $like3 = "%{$itemName}%";
    
    echo "<p>Searching for lost items like: '$itemName'</p>";
    echo "<p>Using patterns: '$like1', '$like2', '$like3'</p>";
    
    $lostStmt->bind_param('sss', $like1, $like2, $like3);
    $lostStmt->execute();
    
    $removedCount = $mysqli->affected_rows;
    echo "<p><strong>Items updated: $removedCount</strong></p>";
    
    if ($removedCount > 0) {
        echo "<p style='color: green;'>✓ Auto-resolution worked!</p>";
    } else {
        echo "<p style='color: red;'>✗ No matching lost items found</p>";
    }
}

// Show current item statuses
echo "<h4>Current Item Statuses:</h4>";
$stmt = $mysqli->prepare("SELECT id, item_name, item_type, status FROM items ORDER BY created_at DESC LIMIT 10");
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo "<table border='1' style='width: 100%;'>";
echo "<tr><th>ID</th><th>Name</th><th>Type</th><th>Status</th></tr>";

foreach ($items as $item) {
    $statusColor = '';
    switch($item['status']) {
        case 'approved': $statusColor = 'green'; break;
        case 'claimed': $statusColor = 'orange'; break;
        case 'resolved': $statusColor = 'blue'; break;
        case 'pending': $statusColor = 'yellow'; break;
        case 'rejected': $statusColor = 'red'; break;
        default: $statusColor = 'black';
    }
    
    echo "<tr>";
    echo "<td>" . $item['id'] . "</td>";
    echo "<td>" . htmlspecialchars($item['item_name']) . "</td>";
    echo "<td>" . $item['item_type'] . "</td>";
    echo "<td style='color: $statusColor; font-weight: bold;'>" . $item['status'] . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";
echo "<p><a href='debug_items.php'>View All Items</a></p>";
echo "<p><a href='admin/claims.php'>Admin Claims</a></p>";
?>
