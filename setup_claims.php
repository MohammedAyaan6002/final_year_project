<?php
require_once 'includes/db.php';

echo "<h2>Setting up Claims System</h2>";

// Read and execute add_claims_table.sql
$claimsTableSql = file_get_contents('sql/add_claims_table.sql');
if ($claimsTableSql) {
    // Execute each statement separately
    $statements = array_filter(array_map('trim', explode(';', $claimsTableSql)));
    $success = true;
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            if (!$mysqli->query($statement)) {
                echo "<p style='color: red;'>✗ Error: " . $mysqli->error . "</p>";
                echo "<p style='color: orange;'>SQL: " . htmlspecialchars($statement) . "</p>";
                $success = false;
            }
        }
    }
    
    if ($success) {
        echo "<p style='color: green;'>✓ Claims table created successfully</p>";
    }
    
    // Clear any remaining results
    while ($mysqli->more_results() && $mysqli->next_result()) {}
}

// Read and execute update_items_status.sql
$updateStatusSql = file_get_contents('sql/update_items_status.sql');
if ($updateStatusSql) {
    // Execute each statement separately
    $statements = array_filter(array_map('trim', explode(';', $updateStatusSql)));
    $success = true;
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            if (!$mysqli->query($statement)) {
                echo "<p style='color: red;'>✗ Error updating items status: " . $mysqli->error . "</p>";
                echo "<p style='color: orange;'>SQL: " . htmlspecialchars($statement) . "</p>";
                $success = false;
            }
        }
    }
    
    if ($success) {
        echo "<p style='color: green;'>✓ Items status updated successfully</p>";
    }
}

echo "<p><a href='admin/claims.php'>Go to Claims Management</a></p>";
echo "<p><a href='test_claims.php'>Test Database</a></p>";
?>
