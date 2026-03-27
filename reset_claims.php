<?php
require_once 'includes/db.php';

echo "<h2>Reset Claims System</h2>";

// Clear existing claims (optional - uncomment if you want to clear)
if (isset($_GET['clear_claims'])) {
    $result = $mysqli->query("DELETE FROM claims");
    if ($result) {
        echo "<p style='color: green;'>✓ All existing claims cleared</p>";
    } else {
        echo "<p style='color: red;'>✗ Error clearing claims: " . $mysqli->error . "</p>";
    }
}

// Clear notifications (optional - uncomment if you want to clear)
if (isset($_GET['clear_notifications'])) {
    $result = $mysqli->query("DELETE FROM notifications");
    if ($result) {
        echo "<p style='color: green;'>✓ All notifications cleared</p>";
    } else {
        echo "<p style='color: red;'>✗ Error clearing notifications: " . $mysqli->error . "</p>";
    }
}

// Show current status
echo "<h3>Current Status</h3>";

// Check claims count
$result = $mysqli->query("SELECT COUNT(*) as count FROM claims");
if ($result) {
    $row = $result->fetch_assoc();
    echo "<p>Current claims count: " . $row['count'] . "</p>";
} else {
    echo "<p style='color: red;'>Error checking claims count</p>";
}

// Show all claims
$stmt = $mysqli->prepare("
    SELECT c.id, c.item_id, c.claimed_by_user_id, c.claim_status, c.created_at,
           i.item_name, u.name as claimed_by_name, u.email as claimed_by_email
    FROM claims c
    JOIN items i ON c.item_id = i.id
    JOIN users u ON c.claimed_by_user_id = u.id
    ORDER BY c.created_at DESC
");
$stmt->execute();
$claims = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (!empty($claims)) {
    echo "<table border='1' style='width: 100%;'>";
    echo "<tr><th>ID</th><th>Item</th><th>Claimed By</th><th>Email</th><th>Status</th><th>Date</th></tr>";
    foreach ($claims as $claim) {
        echo "<tr>";
        echo "<td>" . $claim['id'] . "</td>";
        echo "<td>" . htmlspecialchars($claim['item_name']) . "</td>";
        echo "<td>" . htmlspecialchars($claim['claimed_by_name']) . "</td>";
        echo "<td>" . htmlspecialchars($claim['claimed_by_email']) . "</td>";
        echo "<td>" . $claim['claim_status'] . "</td>";
        echo "<td>" . $claim['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No claims found</p>";
}

echo "<h3>Actions</h3>";
echo "<p><a href='reset_claims.php?clear_claims=1'>Clear All Claims</a></p>";
echo "<p><a href='reset_claims.php?clear_notifications=1'>Clear All Notifications</a></p>";
echo "<p><a href='debug_claim.php'>Debug Claim System</a></p>";
echo "<p><a href='admin/claims.php'>Admin Claims Page</a></p>";
?>
