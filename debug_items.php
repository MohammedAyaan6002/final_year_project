<?php
require_once 'includes/db.php';

echo "<h2>Items Status Debug</h2>";

// Show all items with their current status
$stmt = $mysqli->prepare("SELECT id, item_name, item_type, status, created_at FROM items ORDER BY created_at DESC");
$stmt->execute();
$allItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo "<h3>All Items in Database:</h3>";
echo "<table border='1' style='width: 100%;'>";
echo "<tr><th>ID</th><th>Name</th><th>Type</th><th>Status</th><th>Created</th></tr>";

foreach ($allItems as $item) {
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
    echo "<td>" . $item['created_at'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Show claims
echo "<h3>Claims:</h3>";
$stmt = $mysqli->prepare("
    SELECT c.id, c.claim_status, c.created_at,
           i.item_name, i.item_type,
           u.name as claimed_by_name
    FROM claims c
    JOIN items i ON c.item_id = i.id
    JOIN users u ON c.claimed_by_user_id = u.id
    ORDER BY c.created_at DESC
");
$stmt->execute();
$claims = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo "<table border='1' style='width: 100%;'>";
echo "<tr><th>Claim ID</th><th>Item Name</th><th>Item Type</th><th>Claim Status</th><th>Claimed By</th><th>Date</th></tr>";

foreach ($claims as $claim) {
    $statusColor = $claim['claim_status'] === 'approved' ? 'green' : ($claim['claim_status'] === 'rejected' ? 'red' : 'orange');
    echo "<tr>";
    echo "<td>" . $claim['id'] . "</td>";
    echo "<td>" . htmlspecialchars($claim['item_name']) . "</td>";
    echo "<td>" . $claim['item_type'] . "</td>";
    echo "<td style='color: $statusColor; font-weight: bold;'>" . $claim['claim_status'] . "</td>";
    echo "<td>" . htmlspecialchars($claim['claimed_by_name']) . "</td>";
    echo "<td>" . $claim['created_at'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Test the listings query
echo "<h3>What Listings Page Shows:</h3>";
$stmt = $mysqli->prepare("SELECT * FROM items WHERE status = 'approved' AND id NOT IN (SELECT item_id FROM claims WHERE claim_status = 'approved') AND status != 'resolved' ORDER BY created_at DESC");
$stmt->execute();
$listings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo "<table border='1' style='width: 100%;'>";
echo "<tr><th>ID</th><th>Name</th><th>Type</th><th>Status</th></tr>";

foreach ($listings as $item) {
    echo "<tr>";
    echo "<td>" . $item['id'] . "</td>";
    echo "<td>" . htmlspecialchars($item['item_name']) . "</td>";
    echo "<td>" . $item['item_type'] . "</td>";
    echo "<td>" . $item['status'] . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";
echo "<p><a href='index.php'>Back to Home</a></p>";
echo "<p><a href='pages/listings.php'>View Listings Page</a></p>";
?>
