<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/header.php';

$pendingStmt = $mysqli->prepare("
    SELECT c.id, c.item_id, c.claimed_by_user_id, c.claim_status, c.claim_notes, c.admin_notes, c.created_at,
           i.item_name, i.item_type, i.location, i.description,
           u.name as claimed_by_name, u.email as claimed_by_email
    FROM claims c
    JOIN items i ON c.item_id = i.id
    JOIN users u ON c.claimed_by_user_id = u.id
    WHERE c.claim_status = 'pending'
    ORDER BY c.created_at ASC
");
$pendingStmt->execute();
$pendingClaims = $pendingStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch pending found items that need admin approval
$pendingItemsStmt = $mysqli->prepare("
    SELECT i.*, u.name as submitted_by_name, u.email as submitted_by_email
    FROM items i
    LEFT JOIN users u ON i.user_id = u.id
    WHERE i.item_type = 'found' AND i.status = 'pending'
    ORDER BY i.created_at ASC
");
$pendingItemsStmt->execute();
$pendingItems = $pendingItemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$recentStmt = $mysqli->prepare("SELECT * FROM items ORDER BY created_at DESC LIMIT 5");
$recentStmt->execute();
$recentItems = $recentStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$matchStmt = $mysqli->prepare("SELECT * FROM match_logs ORDER BY created_at DESC LIMIT 5");
$matchStmt->execute();
$matchLogs = $matchStmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<div class="container-fluid">
    <div class="row">
        <aside class="col-md-3 col-lg-2 admin-sidebar py-4">
            <h5 class="px-3 mb-4">Admin Panel</h5>
            <a href="<?php echo APP_BASE_URL; ?>/admin/dashboard.php" class="active">Dashboard</a>
            <a href="<?php echo APP_BASE_URL; ?>/admin/claims.php">Pending Claims</a>
            <a href="<?php echo APP_BASE_URL; ?>/admin/claimed-items.php">Claimed Items</a>
            <a href="<?php echo APP_BASE_URL; ?>/index.php">Back to Site</a>
        </aside>
        <section class="col-md-9 col-lg-10 p-4">
            <h2 class="mb-4 text-primary">Welcome, Admin</h2>
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">Pending Claims</h5>
                            <p class="display-6 mb-0"><?php echo count($pendingClaims); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">Pending Found Items</h5>
                            <p class="display-6 mb-0"><?php echo count($pendingItems); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">Recent Items</h5>
                            <p class="display-6 mb-0"><?php echo count($recentItems); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">AI Matches Logged</h5>
                            <p class="display-6 mb-0"><?php echo count($matchLogs); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div id="pending" class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <span>Pending Items for Approval</span>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Type</th>
                                <th>Submitted</th>
                                <th>Submitted By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($pendingItems)): ?>
                            <tr><td colspan="5" class="text-center py-4">No pending found items.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($pendingItems as $item): ?>
                            <tr>
                                <td>
                                    <strong><?php echo $item['item_name']; ?></strong>
                                    <div class="small text-muted"><?php echo $item['location']; ?></div>
                                </td>
                                <td><span class="badge bg-success text-uppercase"><?php echo $item['item_type']; ?></span></td>
                                <td><?php echo format_date($item['created_at']); ?></td>
                                <td>
                                    <?php echo $item['submitted_by_name'] ? htmlspecialchars($item['submitted_by_name']) : 'Guest'; ?>
                                    <div class="small text-muted"><?php echo htmlspecialchars($item['contact_email']); ?></div>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-success me-2 btnApproveItem" data-id="<?php echo $item['id']; ?>">Approve</button>
                                    <button class="btn btn-sm btn-outline-danger btnRejectItem" data-id="<?php echo $item['id']; ?>">Reject</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="matches" class="card shadow-sm mb-4">
                <div class="card-header bg-success text-white">AI Match Suggestions</div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                        <tr>
                            <th>Lost Item</th>
                            <th>Found Item</th>
                            <th>Score</th>
                            <th>Logged</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($matchLogs)): ?>
                            <tr><td colspan="4" class="text-center py-4">No AI matches yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($matchLogs as $log): ?>
                            <tr>
                                <td><?php echo $log['lost_item_name']; ?></td>
                                <td><?php echo $log['found_item_name']; ?></td>
                                <td><?php echo number_format($log['score'] * 100, 1); ?>%</td>
                                <td><?php echo format_date($log['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="recent" class="card shadow-sm">
                <div class="card-header bg-light">Recent Activity</div>
                <div class="list-group list-group-flush">
                    <?php foreach ($recentItems as $item): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?php echo $item['item_name']; ?></strong>
                                <div class="small text-muted"><?php echo ucfirst($item['item_type']); ?> · <?php echo $item['status']; ?></div>
                            </div>
                            <small><?php echo format_date($item['created_at']); ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </div>
</div>
<script>
document.querySelectorAll('.btnApprove, .btnReject').forEach(btn => {
    btn.addEventListener('click', async () => {
        const claimId = btn.dataset.id;
        const action = btn.classList.contains('btnApprove') ? 'approve' : 'reject';
        const baseUrl = document.body.dataset.baseUrl || '';
        
        // Show confirmation dialog
        const confirmAction = confirm(`Are you sure you want to ${action} this claim?`);
        if (!confirmAction) return;
        
        try {
            const response = await fetch(`${baseUrl}/api/manage-claim.php`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ 
                    claim_id: claimId, 
                    action: action,
                    admin_notes: ''
                })
            });
            const data = await response.json();
            alert(data.message);
            if (data.success) {
                window.location.reload();
            }
        } catch (error) {
            alert('Failed to process claim. Check server logs.');
        }
    });
});

// Handle approve/reject for pending found items
document.querySelectorAll('.btnApproveItem, .btnRejectItem').forEach(btn => {
    btn.addEventListener('click', async () => {
        const itemId = btn.dataset.id;
        const action = btn.classList.contains('btnApproveItem') ? 'approve' : 'reject';
        const baseUrl = document.body.dataset.baseUrl || '';
        
        // Show confirmation dialog
        const confirmAction = confirm(`Are you sure you want to ${action} this found item?`);
        if (!confirmAction) return;
        
        try {
            const response = await fetch(`${baseUrl}/api/moderate-item.php`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ 
                    id: itemId, 
                    action: action
                })
            });
            const data = await response.json();
            alert(data.message);
            if (data.success) {
                window.location.reload();
            }
        } catch (error) {
            alert('Failed to process item. Check server logs.');
        }
    });
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

