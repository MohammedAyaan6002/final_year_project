<?php
require_once 'includes/db.php';

echo "<h2>Database Test</h2>";

// Test connection
if ($mysqli->connect_error) {
    echo "<p style='color: red;'>Connection failed: " . $mysqli->connect_error . "</p>";
} else {
    echo "<p style='color: green;'>Connection successful</p>";
}

// Check if claims table exists
$result = $mysqli->query("SHOW TABLES LIKE 'claims'");
if ($result->num_rows > 0) {
    echo "<p style='color: green;'>Claims table exists</p>";
} else {
    echo "<p style='color: red;'>Claims table does NOT exist</p>";
}

// Check if claims table has data
try {
    $result = $mysqli->query("SELECT COUNT(*) as count FROM claims");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<p>Claims count: " . $row['count'] . "</p>";
    } else {
        echo "<p style='color: red;'>Error querying claims table: " . $mysqli->error . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Exception: " . $e->getMessage() . "</p>";
}

// Check items table structure
$result = $mysqli->query("SHOW COLUMNS FROM items WHERE Field = 'status'");
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "<p>Items status column type: " . $row['Type'] . "</p>";
} else {
    echo "<p style='color: red;'>Items status column not found</p>";
}

// Test claims query
try {
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
        $stmt->execute();
        $claims = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo "<p>Claims query executed successfully. Found " . count($claims) . " claims.</p>";
        
        if (!empty($claims)) {
            echo "<table border='1'><tr><th>ID</th><th>Item</th><th>Claimed By</th><th>Status</th></tr>";
            foreach ($claims as $claim) {
                echo "<tr>";
                echo "<td>" . $claim['id'] . "</td>";
                echo "<td>" . htmlspecialchars($claim['item_name']) . "</td>";
                echo "<td>" . htmlspecialchars($claim['claimed_by_name']) . "</td>";
                echo "<td>" . $claim['claim_status'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<p style='color: red;'>Failed to prepare claims query: " . $mysqli->error . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Exception in claims query: " . $e->getMessage() . "</p>";
}
?>
