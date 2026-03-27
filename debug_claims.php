<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';

echo "<h2>Debug Claims Page</h2>";

// Check authentication
if (!is_logged_in()) {
    echo "<p style='color: red;'>Not logged in</p>";
} else {
    echo "<p style='color: green;'>Logged in as: " . $_SESSION['user_name'] . " (Role: " . $_SESSION['user_role'] . ")</p>";
}

if ($_SESSION['user_role'] !== 'admin') {
    echo "<p style='color: red;'>Not admin - redirecting...</p>";
    header('Location: ' . APP_BASE_URL . '/index.php');
    exit;
} else {
    echo "<p style='color: green;'>Admin access confirmed</p>";
}

// Test simple query
$result = $mysqli->query("SELECT COUNT(*) as count FROM claims");
if ($result) {
    $row = $result->fetch_assoc();
    echo "<p>Claims in database: " . $row['count'] . "</p>";
} else {
    echo "<p style='color: red;'>Error querying claims: " . $mysqli->error . "</p>";
}

// Test claims query
$stmt = $mysqli->prepare("
    SELECT c.id, c.item_id, c.claimed_by_user_id, c.claim_status, c.claim_notes, c.admin_notes, c.created_at,
           i.item_name, i.item_type, i.location, i.description,
           u.name as claimed_by_name, u.email as claimed_by_email
    FROM claims c
    JOIN items i ON c.item_id = i.id
    JOIN users u ON c.claimed_by_user_id = u.id
    ORDER BY c.created_at DESC
");

if ($stmt) {
    echo "<p style='color: green;'>Query prepared successfully</p>";
    $stmt->execute();
    $claims = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    echo "<p>Found " . count($claims) . " claims</p>";
    
    if (!empty($claims)) {
        echo "<table border='1' style='width: 100%;'>";
        echo "<tr><th>ID</th><th>Item</th><th>Claimed By</th><th>Status</th><th>Date</th></tr>";
        foreach ($claims as $claim) {
            echo "<tr>";
            echo "<td>" . $claim['id'] . "</td>";
            echo "<td>" . htmlspecialchars($claim['item_name']) . "</td>";
            echo "<td>" . htmlspecialchars($claim['claimed_by_name']) . "</td>";
            echo "<td>" . $claim['claim_status'] . "</td>";
            echo "<td>" . $claim['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} else {
    echo "<p style='color: red;'>Failed to prepare query: " . $mysqli->error . "</p>";
}
?>
