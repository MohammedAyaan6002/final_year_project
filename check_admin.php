<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

echo "<h2>Admin Access Check</h2>";

// Check if logged in
if (!is_logged_in()) {
    echo "<p style='color: red;'>Not logged in</p>";
    echo "<p><a href='pages/login.php'>Please login</a></p>";
    exit;
}

echo "<p style='color: green;'>Logged in as: " . $_SESSION['user_name'] . "</p>";
echo "<p style='color: blue;'>User role: " . $_SESSION['user_role'] . "</p>";
echo "<p style='color: blue;'>User ID: " . $_SESSION['user_id'] . "</p>";

// Check if user exists in database
$stmt = $mysqli->prepare("SELECT name, email, role FROM users WHERE id = ?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "<p style='color: red;'>User not found in database!</p>";
} else {
    $user = $result->fetch_assoc();
    echo "<p style='color: green;'>Database role: " . $user['role'] . "</p>";
    echo "<p style='color: green;'>Database email: " . $user['email'] . "</p>";
}

// Quick fix to make user admin
if (isset($_GET['make_admin']) && $_GET['make_admin'] === 'true') {
    $stmt = $mysqli->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
    $stmt->bind_param('i', $_SESSION['user_id']);
    if ($stmt->execute()) {
        echo "<p style='color: green; font-weight: bold;'>✓ User updated to admin role!</p>";
        echo "<p><a href='admin/dashboard.php'>Go to Admin Dashboard</a></p>";
        
        // Update session
        $_SESSION['user_role'] = 'admin';
    } else {
        echo "<p style='color: red;'>Failed to update user role</p>";
    }
}

echo "<hr>";
echo "<p><a href='index.php'>Back to Home</a></p>";
echo "<p><a href='check_admin.php?make_admin=true'>Make Current User Admin (TEMPORARY)</a></p>";
?>
