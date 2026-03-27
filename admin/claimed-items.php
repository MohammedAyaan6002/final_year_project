<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: ' . APP_BASE_URL . '/index.php');
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/header.php';

$baseUrl = APP_BASE_URL;

// Get claimed items (items with approved claims)
$stmt = $mysqli->prepare("
    SELECT i.id, i.item_name, i.item_type, i.location, i.description, i.image_path,
           c.claim_status, c.admin_notes, c.updated_at as claimed_date,
           u.name as claimed_by_name, u.email as claimed_by_email
    FROM items i
    JOIN claims c ON i.id = c.item_id
    JOIN users u ON c.claimed_by_user_id = u.id
    WHERE c.claim_status = 'approved'
    ORDER BY c.updated_at DESC
");
$stmt->execute();
$claimedItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <div class="admin-sidebar p-3">
                <h5 class="text-white mb-3">Admin Panel</h5>
                <ul class="list-unstyled">
                    <li><a href="<?php echo $baseUrl; ?>/admin/dashboard.php" class="mb-2">Dashboard</a></li>
                    <li><a href="<?php echo $baseUrl; ?>/admin/claims.php" class="mb-2">Pending Claims</a></li>
                    <li><a href="<?php echo $baseUrl; ?>/admin/claimed-items.php" class="mb-2 active">Claimed Items</a></li>
                    <li><a href="<?php echo $baseUrl; ?>/index.php" class="mb-2">Back to Site</a></li>
                </ul>
            </div>
        </div>
        <div class="col-md-9">
            <div class="p-4">
                <h2 class="mb-4">Claimed Items</h2>
                
                <?php if (empty($claimedItems)): ?>
                    <div class="alert alert-info">No items have been claimed yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Claimed By</th>
                                    <th>Location</th>
                                    <th>Claimed Date</th>
                                    <th>Admin Notes</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($claimedItems as $item): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($item['image_path'])): ?>
                                                <img src="<?php echo APP_BASE_URL . $item['image_path']; ?>" 
                                                     style="width: 50px; height: 50px; object-fit: cover; margin-right: 10px;" 
                                                     alt="<?php echo htmlspecialchars($item['item_name']); ?>">
                                            <?php endif; ?>
                                            <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($item['claimed_by_name']); ?><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($item['claimed_by_email']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['location']); ?></td>
                                        <td><?php echo format_date($item['claimed_date']); ?></td>
                                        <td>
                                            <?php if (!empty($item['admin_notes'])): ?>
                                                <?php echo htmlspecialchars($item['admin_notes']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">No notes</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-warning btn-sm" onclick="returnClaim(<?php echo $item['id']; ?>)">
                                                Return to Listings
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function returnClaim(itemId) {
    if (confirm('Are you sure you want to return this item to active listings?')) {
        const baseUrl = document.body.dataset.baseUrl || '';
        fetch(`${baseUrl}/api/return-claimed-item.php`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({item_id: itemId})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Item returned to listings successfully!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error: Failed to return item to listings');
        });
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
