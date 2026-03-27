<?php
require_once 'includes/db.php';

echo "<h2>Session & Database Debug</h2>";

// Start session manually to check
session_start();

echo "<h3>Session Data:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h3>Database Users:</h3>";
$stmt = $mysqli->prepare("SELECT id, name, email, role FROM users ORDER BY id");
$stmt->execute();
$result = $stmt->get_result();

echo "<table border='1' style='width: 100%;'>";
echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th></tr>";

while ($user = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $user['id'] . "</td>";
    echo "<td>" . htmlspecialchars($user['name']) . "</td>";
    echo "<td>" . htmlspecialchars($user['email']) . "</td>";
    echo "<td>" . $user['role'] . "</td>";
    echo "</tr>";
    
    // Highlight session user
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user['id']) {
        echo "<tr style='background-color: #90EE90;'>";
        echo "<td colspan='4'><strong>← SESSION USER</strong></td>";
        echo "</tr>";
    }
}
echo "</table>";

echo "<hr>";

if (isset($_SESSION['user_id'])) {
    echo "<h3>Session User ID: " . $_SESSION['user_id'] . "</h3>";
    
    // Find this user in database
    $stmt = $mysqli->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $sessionUser = $stmt->get_result()->fetch_assoc();
    
    if ($sessionUser) {
        echo "<p style='color: green;'>✓ Session user found in database:</p>";
        echo "<table border='1'>";
        echo "<tr><th>Field</th><th>Session Value</th><th>Database Value</th></tr>";
        echo "<tr><td>ID</td><td>" . $_SESSION['user_id'] . "</td><td>" . $sessionUser['id'] . "</td></tr>";
        echo "<tr><td>Name</td><td>" . $_SESSION['user_name'] . "</td><td>" . $sessionUser['name'] . "</td></tr>";
        echo "<tr><td>Email</td><td>" . $_SESSION['user_email'] . "</td><td>" . $sessionUser['email'] . "</td></tr>";
        echo "<tr><td>Role</td><td>" . $_SESSION['user_role'] . "</td><td>" . $sessionUser['role'] . "</td></tr>";
        echo "</table>";
    } else {
        echo "<p style='color: red;'>✗ Session user ID " . $_SESSION['user_id'] . " NOT found in database!</p>";
    }
} else {
    echo "<p style='color: red;'>No user session found</p>";
}

echo "<hr>";
echo "<p><a href='index.php'>Back to Home</a></p>";
echo "<p><a href='pages/logout.php'>Logout</a></p>";
echo "<p><a href='check_admin.php'>Make Admin</a></p>";
?>
