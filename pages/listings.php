<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/auth.php';
$baseUrl = APP_BASE_URL;

// Redirect to login if not logged in
if (!is_logged_in()) {
    header('Location: ' . APP_BASE_URL . '/pages/login.php');
    exit;
}

$userId = $_SESSION['user_id'];

$stmt = $mysqli->prepare("
    SELECT * FROM items 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$items = $result->fetch_all(MYSQLI_ASSOC);
?>
<div class="container">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4">
        <div>
            <h2 class="mb-1 text-primary">My Listings</h2>
            <p class="text-muted mb-0">Your lost and found submission history.</p>
        </div>
        <a href="<?php echo $baseUrl; ?>/pages/search.php" class="btn btn-outline-primary mt-3 mt-md-0">Advanced Search</a>
    </div>
    <div class="row g-4">
        <?php if (empty($items)): ?>
            <div class="col-12">
                <div class="alert alert-info">You haven't submitted any listings yet. <a href="<?php echo $baseUrl; ?>/pages/report-lost.php" class="alert-link">Report a lost item</a> or <a href="<?php echo $baseUrl; ?>/pages/report-found.php" class="alert-link">report a found item</a> to get started!</div>
            </div>
        <?php endif; ?>
        <?php foreach ($items as $item): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm <?php echo $item['item_type'] === 'lost' ? 'card-lost' : 'card-found'; ?>">
                    <?php if (!empty($item['image_path'])): ?>
                        <img src="<?php echo APP_BASE_URL . $item['image_path']; ?>" class="card-img-top" alt="<?php echo $item['item_name']; ?>">
                    <?php endif; ?>
                    <div class="card-body">
                        <?php if ($item['status'] === 'pending'): ?>
                            <span class="badge bg-warning status-badge text-uppercase mb-2">PENDING APPROVAL</span>
                        <?php elseif ($item['status'] === 'approved'): ?>
                            <span class="badge bg-success status-badge text-uppercase mb-2">APPROVED</span>
                        <?php elseif ($item['status'] === 'rejected'): ?>
                            <span class="badge bg-danger status-badge text-uppercase mb-2">REJECTED</span>
                        <?php endif; ?>
                        <span class="badge bg-secondary status-badge text-uppercase"><?php echo $item['item_type']; ?></span>
                        <h5 class="card-title mt-2"><?php echo $item['item_name']; ?></h5>
                        <p class="card-text text-muted small mb-2">Location: <?php echo $item['location']; ?></p>
                        <p class="card-text"><?php echo substr($item['description'], 0, 120); ?>...</p>
                        <p class="card-text"><small class="text-muted">Reported on <?php echo format_date($item['created_at']); ?></small></p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

