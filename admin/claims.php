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

// Get claims with item and user details
$stmt = $mysqli->prepare("
    SELECT c.id, c.item_id, c.claimed_by_user_id, c.claim_status, c.claim_notes, c.admin_notes, c.created_at,
           i.item_name, i.item_type, i.location, i.description,
           u.name as claimed_by_name, u.email as claimed_by_email
    FROM claims c
    JOIN items i ON c.item_id = i.id
    JOIN users u ON c.claimed_by_user_id = u.id
    ORDER BY c.created_at DESC
");
$stmt->execute();
$claims = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <div class="admin-sidebar p-3">
                <h5 class="text-white mb-3">Admin Panel</h5>
                <ul class="list-unstyled">
                    <li><a href="<?php echo $baseUrl; ?>/admin/dashboard.php" class="mb-2">Dashboard</a></li>
                    <li><a href="<?php echo $baseUrl; ?>/admin/claims.php" class="mb-2 active">Pending Claims</a></li>
                    <li><a href="<?php echo $baseUrl; ?>/admin/claimed-items.php" class="mb-2">Claimed Items</a></li>
                    <li><a href="<?php echo $baseUrl; ?>/index.php" class="mb-2">Back to Site</a></li>
                </ul>
            </div>
        </div>
        <div class="col-md-9">
            <div class="p-4">
                <h2 class="mb-4">Claims Management</h2>
                
                <?php if (empty($claims)): ?>
                    <div class="alert alert-info">No claims submitted yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Claimed By</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($claims as $claim): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($claim['item_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($claim['location']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($claim['claimed_by_name']); ?><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($claim['claimed_by_email']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $claim['claim_status'] === 'pending' ? 'warning' : ($claim['claim_status'] === 'approved' ? 'success' : 'danger'); ?>">
                                                <?php echo ucfirst($claim['claim_status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo format_date($claim['created_at']); ?></td>
                                        <td>
                                            <?php if ($claim['claim_status'] === 'pending'): ?>
                                                <button class="btn btn-success btn-sm approve-claim-btn" 
                                                        data-claim-id="<?php echo $claim['id']; ?>"
                                                        data-item-name="<?php echo htmlspecialchars($claim['item_name']); ?>"
                                                        data-user-name="<?php echo htmlspecialchars($claim['claimed_by_name']); ?>">
                                                    Approve
                                                </button>
                                                <button class="btn btn-danger btn-sm reject-claim-btn ms-1" 
                                                        data-claim-id="<?php echo $claim['id']; ?>"
                                                        data-item-name="<?php echo htmlspecialchars($claim['item_name']); ?>"
                                                        data-user-name="<?php echo htmlspecialchars($claim['claimed_by_name']); ?>">
                                                    Reject
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted">Processed</span>
                                            <?php endif; ?>
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

<!-- Action Modal -->
<div class="modal fade" id="actionModal" tabindex="-1" aria-labelledby="actionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="actionModalLabel">Process Claim</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="actionModalContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmActionBtn">Confirm</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const actionModal = new bootstrap.Modal(document.getElementById('actionModal'));
    const actionModalContent = document.getElementById('actionModalContent');
    const confirmActionBtn = document.getElementById('confirmActionBtn');
    let currentAction = '';
    let currentClaimId = '';

    // Approve claim buttons
    document.querySelectorAll('.approve-claim-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const claimId = this.getAttribute('data-claim-id');
            const itemName = this.getAttribute('data-item-name');
            const userName = this.getAttribute('data-user-name');
            
            currentAction = 'approve';
            currentClaimId = claimId;
            
            actionModalContent.innerHTML = `
                <div class="alert alert-warning">
                    <h6>Confirm Approval</h6>
                    <p>Are you sure you want to approve the claim for <strong>${itemName}</strong> by <strong>${userName}</strong>?</p>
                    <p class="mb-0">This will mark the item as claimed and remove it from active listings.</p>
                </div>
                <div class="mb-3">
                    <label for="adminNotes" class="form-label">Admin Notes (optional)</label>
                    <textarea class="form-control" id="adminNotes" rows="3"></textarea>
                </div>
            `;
            
            confirmActionBtn.textContent = 'Approve Claim';
            confirmActionBtn.className = 'btn btn-success';
            actionModal.show();
        });
    });

    // Reject claim buttons
    document.querySelectorAll('.reject-claim-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const claimId = this.getAttribute('data-claim-id');
            const itemName = this.getAttribute('data-item-name');
            const userName = this.getAttribute('data-user-name');
            
            currentAction = 'reject';
            currentClaimId = claimId;
            
            actionModalContent.innerHTML = `
                <div class="alert alert-danger">
                    <h6>Confirm Rejection</h6>
                    <p>Are you sure you want to reject the claim for <strong>${itemName}</strong> by <strong>${userName}</strong>?</p>
                    <p class="mb-0">The item will remain available for others to claim.</p>
                </div>
                <div class="mb-3">
                    <label for="adminNotes" class="form-label">Rejection Reason (optional)</label>
                    <textarea class="form-control" id="adminNotes" rows="3"></textarea>
                </div>
            `;
            
            confirmActionBtn.textContent = 'Reject Claim';
            confirmActionBtn.className = 'btn btn-danger';
            actionModal.show();
        });
    });

    // Confirm action
    confirmActionBtn.addEventListener('click', async function() {
        const adminNotes = document.getElementById('adminNotes').value;
        
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
        this.disabled = true;
        
        try {
            const baseUrl = document.body.dataset.baseUrl || '';
            const response = await fetch(`${baseUrl}/api/manage-claim.php`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    claim_id: currentClaimId,
                    action: currentAction,
                    admin_notes: adminNotes
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                actionModalContent.innerHTML = `
                    <div class="alert alert-success">
                        <strong>Success!</strong> ${data.message}
                    </div>
                `;
                
                // Reload page after delay
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                actionModalContent.innerHTML += `
                    <div class="alert alert-danger mt-3">
                        <strong>Error:</strong> ${data.message}
                    </div>
                `;
                
                this.innerHTML = currentAction === 'approve' ? 'Approve Claim' : 'Reject Claim';
                this.disabled = false;
            }
        } catch (error) {
            actionModalContent.innerHTML += `
                <div class="alert alert-danger mt-3">
                    <strong>Error:</strong> Failed to process claim. Please try again.
                </div>
            `;
            
            this.innerHTML = currentAction === 'approve' ? 'Approve Claim' : 'Reject Claim';
            this.disabled = false;
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
