<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';

// Check authentication
if (!is_logged_in()) {
    echo "<p style='color: red;'>Not logged in</p>";
    exit;
}

if ($_SESSION['user_role'] !== 'admin') {
    echo "<p style='color: red;'>Not admin</p>";
    exit;
}

// Get some test claims
$stmt = $mysqli->prepare("
    SELECT c.id, c.item_id, c.claim_status, c.created_at,
           i.item_name, u.name as claimed_by_name
    FROM claims c
    JOIN items i ON c.item_id = i.id
    JOIN users u ON c.claimed_by_user_id = u.id
    WHERE c.claim_status = 'pending'
    ORDER BY c.created_at DESC
    LIMIT 5
");
$stmt->execute();
$claims = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Test Claims Buttons</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>Test Claims Approval Buttons</h2>
        
        <?php if (empty($claims)): ?>
            <div class="alert alert-info">No pending claims found.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Claimed By</th>
                            <th>Status</th>
                            <th>Test Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($claims as $claim): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($claim['item_name']); ?></strong>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($claim['claimed_by_name']); ?>
                                </td>
                                <td>
                                    <span class="badge bg-warning"><?php echo $claim['claim_status']; ?></span>
                                </td>
                                <td>
                                    <button class="btn btn-success btn-sm test-approve-btn" 
                                            data-claim-id="<?php echo $claim['id']; ?>"
                                            data-item-name="<?php echo htmlspecialchars($claim['item_name']); ?>"
                                            data-user-name="<?php echo htmlspecialchars($claim['claimed_by_name']); ?>">
                                        Test Approve
                                    </button>
                                    <button class="btn btn-danger btn-sm test-reject-btn ms-1" 
                                            data-claim-id="<?php echo $claim['id']; ?>"
                                            data-item-name="<?php echo htmlspecialchars($claim['item_name']); ?>"
                                            data-user-name="<?php echo htmlspecialchars($claim['claimed_by_name']); ?>">
                                        Test Reject
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Action Modal -->
    <div class="modal fade" id="actionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Process Claim</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM loaded');
        
        const actionModal = new bootstrap.Modal(document.getElementById('actionModal'));
        const actionModalContent = document.getElementById('actionModalContent');
        const confirmActionBtn = document.getElementById('confirmActionBtn');
        let currentAction = '';
        let currentClaimId = '';

        // Test approve buttons
        document.querySelectorAll('.test-approve-btn').forEach(btn => {
            console.log('Found approve button:', btn);
            btn.addEventListener('click', function() {
                console.log('Approve button clicked');
                const claimId = this.getAttribute('data-claim-id');
                const itemName = this.getAttribute('data-item-name');
                const userName = this.getAttribute('data-user-name');
                
                currentAction = 'approve';
                currentClaimId = claimId;
                
                actionModalContent.innerHTML = `
                    <div class="alert alert-warning">
                        <h6>Confirm Approval</h6>
                        <p>Are you sure you want to approve claim for <strong>${itemName}</strong> by <strong>${userName}</strong>?</p>
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

        // Test reject buttons
        document.querySelectorAll('.test-reject-btn').forEach(btn => {
            console.log('Found reject button:', btn);
            btn.addEventListener('click', function() {
                console.log('Reject button clicked');
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
            console.log('Confirm action clicked:', currentAction, currentClaimId);
            const adminNotes = document.getElementById('adminNotes').value;
            
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
            this.disabled = true;
            
            try {
                const response = await fetch(`api/manage-claim.php`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        claim_id: currentClaimId,
                        action: currentAction,
                        admin_notes: adminNotes
                    })
                });
                
                const data = await response.json();
                console.log('Response:', data);
                
                if (data.success) {
                    actionModalContent.innerHTML = `
                        <div class="alert alert-success">
                            <strong>Success!</strong> ${data.message}
                        </div>
                    `;
                    
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
                console.error('Error:', error);
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
</body>
</html>
