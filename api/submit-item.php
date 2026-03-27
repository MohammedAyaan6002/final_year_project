<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Invalid method'], 405);
}

$itemType = isset($_POST['item_type']) ? sanitize_input($_POST['item_type']) : '';
$allowedTypes = ['lost', 'found'];

if (!in_array($itemType, $allowedTypes, true)) {
    json_response(['success' => false, 'message' => 'Invalid item type'], 422);
}

$fields = [
    'item_name' => sanitize_input($_POST['item_name'] ?? ''),
    'description' => sanitize_input($_POST['description'] ?? ''),
    'location' => sanitize_input($_POST['location'] ?? ''),
    'event_date' => sanitize_input($_POST[$itemType === 'lost' ? 'date_lost' : 'date_found'] ?? ''),
    'contact_name' => sanitize_input($_POST[$itemType === 'lost' ? 'owner_name' : 'finder_name'] ?? ''),
    'contact_email' => sanitize_input($_POST[$itemType === 'lost' ? 'owner_email' : 'finder_email'] ?? ''),
    'contact_phone' => sanitize_input($_POST[$itemType === 'lost' ? 'owner_phone' : 'finder_phone'] ?? ''),
];

foreach (['item_name', 'description', 'location', 'event_date', 'contact_name', 'contact_email', 'contact_phone'] as $required) {
    if (empty($fields[$required])) {
        json_response(['success' => false, 'message' => 'Missing required fields'], 422);
    }
}

// Validate phone number - must be exactly 10 digits
if (!preg_match('/^[0-9]{10}$/', $fields['contact_phone'])) {
    json_response(['success' => false, 'message' => 'Phone number must contain exactly 10 digits'], 422);
}

// Validate email - must end with @gmail.com
if (!preg_match('/^[a-zA-Z0-9._%+-]+@gmail\.com$/', $fields['contact_email'])) {
    json_response(['success' => false, 'message' => 'Email must end with @gmail.com'], 422);
}

// Validate description - minimum 20 characters
if (strlen($fields['description']) < 20) {
    json_response(['success' => false, 'message' => 'Description must be at least 20 characters long'], 422);
}

$imagePath = null;
if (!empty($_FILES['item_image']['name'])) {
    $uploadsDir = __DIR__ . '/../uploads/';
    $extension = pathinfo($_FILES['item_image']['name'], PATHINFO_EXTENSION);
    $filename = uniqid('item_', true) . '.' . strtolower($extension);
    $targetPath = $uploadsDir . $filename;

    if (!move_uploaded_file($_FILES['item_image']['tmp_name'], $targetPath)) {
        json_response(['success' => false, 'message' => 'Failed to upload image'], 500);
    }
    $imagePath = '/uploads/' . $filename;
}

// Get user ID if logged in
$userId = null;
if (is_logged_in()) {
    $userId = $_SESSION['user_id'];
}

// Set status based on item type - lost items are auto-approved, found items need admin approval
$status = ($itemType === 'lost') ? 'approved' : 'pending';

$stmt = $mysqli->prepare("INSERT INTO items (item_type, item_name, description, location, event_date, contact_name, contact_email, contact_phone, image_path, user_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param(
    'sssssssssis',
    $itemType,
    $fields['item_name'],
    $fields['description'],
    $fields['location'],
    $fields['event_date'],
    $fields['contact_name'],
    $fields['contact_email'],
    $fields['contact_phone'],
    $imagePath,
    $userId,
    $status
);

if ($stmt->execute()) {
    if ($itemType === 'lost') {
        json_response(['success' => true, 'message' => 'Your lost item has been successfully submitted and is now visible in listings. Kindly return the found item to the admin\'s office to proceed further.', 'auto_approved' => true]);
    } else {
        json_response(['success' => true, 'message' => 'Your found item submission has been received and is pending admin approval.', 'auto_approved' => false]);
    }
}

json_response(['success' => false, 'message' => 'Database error: ' . $mysqli->error], 500);

