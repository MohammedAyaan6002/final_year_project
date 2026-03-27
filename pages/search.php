<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

$keyword = isset($_GET['q']) ? sanitize_input($_GET['q']) : '';
$items = [];

if ($keyword) {
    $search = '%' . $keyword . '%';
    $stmt = $mysqli->prepare("
        SELECT * FROM items 
        WHERE status IN ('approved', 'resolved') 
        AND id NOT IN (SELECT item_id FROM claims WHERE claim_status = 'approved')
        AND (item_name LIKE ? OR description LIKE ? OR location LIKE ?) 
        ORDER BY 
            CASE 
                WHEN status = 'approved' THEN 1
                WHEN status = 'resolved' THEN 2
            END,
            created_at DESC
    ");
    $stmt->bind_param('sss', $search, $search, $search);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<div class="container">
    <h2 class="text-primary mb-4">Search Lost or Found Items</h2>
    
    <!-- Enhanced Search with Image Upload -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form class="row g-3 mb-3" method="GET">
                <div class="col-md-8">
                    <input type="text" class="form-control" name="q" value="<?php echo $keyword; ?>" placeholder="Search by keyword, location, description" required>
                </div>
                <div class="col-md-4 d-grid d-md-flex gap-2">
                    <button class="btn btn-success flex-fill" type="submit">Search</button>
                    <button class="btn btn-outline-primary flex-fill" type="button" id="btnAiSuggest" data-query="<?php echo $keyword; ?>">AI Suggest</button>
                </div>
            </form>
            
            <!-- AI Image Matching Section -->
            <div class="border-top pt-3">
                <h6 class="mb-3">🤖 AI Image Matching (More Accurate)</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <textarea class="form-control" id="imageDescription" rows="2" placeholder="Describe what you're looking for..."></textarea>
                    </div>
                    <div class="col-md-4">
                        <input type="file" class="form-control" id="imageUpload" accept="image/*">
                        <small class="text-muted">Upload a photo for better matching</small>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button class="btn btn-primary h-100" type="button" id="btnImageMatch">
                            📸 AI Match
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div id="alertPlaceholder"></div>
    <div id="aiResults" class="mb-4"></div>
    <div class="row g-4">
        <?php if ($keyword && empty($items)): ?>
            <div class="col-12">
                <div class="alert alert-warning">No results found for "<?php echo $keyword; ?>". Try AI suggestions.</div>
            </div>
        <?php endif; ?>
        <?php foreach ($items as $item): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm <?php echo $item['item_type'] === 'lost' ? 'card-lost' : 'card-found'; ?>">
                    <?php if (!empty($item['image_path'])): ?>
                        <img src="<?php echo APP_BASE_URL . $item['image_path']; ?>" class="card-img-top" alt="<?php echo $item['item_name']; ?>">
                    <?php endif; ?>
                    <div class="card-body">
                        <?php if ($item['status'] === 'resolved'): ?>
                            <span class="badge bg-danger status-badge text-uppercase mb-2">RESOLVED</span>
                        <?php endif; ?>
                        <span class="badge bg-secondary status-badge text-uppercase"><?php echo $item['item_type']; ?></span>
                        <h5 class="card-title mt-2"><?php echo $item['item_name']; ?></h5>
                        <p class="card-text"><?php echo substr($item['description'], 0, 120); ?>...</p>
                        <p class="text-muted small mb-2"><?php echo $item['location']; ?> · <?php echo format_date($item['created_at']); ?></p>
                        
                        <?php if ($item['item_type'] === 'found' && is_logged_in() && $item['status'] !== 'resolved'): ?>
                            <div class="mt-3">
                                <button class="btn btn-success btn-sm claim-item-btn" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#claimModal"
                                        data-item-id="<?php echo $item['id']; ?>"
                                        data-item-name="<?php echo htmlspecialchars($item['item_name']); ?>"
                                        data-contact-name="<?php echo htmlspecialchars($item['contact_name']); ?>"
                                        data-contact-email="<?php echo htmlspecialchars($item['contact_email']); ?>"
                                        data-contact-phone="<?php echo htmlspecialchars($item['contact_phone'] ?? 'Not provided'); ?>">
                                    Claim Item
                                </button>
                            </div>
                        <?php elseif ($item['item_type'] === 'found' && !is_logged_in() && $item['status'] !== 'resolved'): ?>
                            <div class="mt-3">
                                <a href="<?php echo APP_BASE_URL; ?>/pages/login.php" class="btn btn-outline-success btn-sm">Login to Claim</a>
                            </div>
                        <?php elseif ($item['status'] === 'resolved'): ?>
                            <div class="mt-3">
                                <span class="text-muted small">This item has been resolved and is no longer available for claiming.</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Claim Item Modal -->
<div class="modal fade" id="claimModal" tabindex="-1" aria-labelledby="claimModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="claimModalLabel">Claim Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="claimModalContent">
                    <!-- Content will be populated by JavaScript -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const claimModal = document.getElementById('claimModal');
    const claimModalContent = document.getElementById('claimModalContent');
    
    claimModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const itemId = button.getAttribute('data-item-id');
        const itemName = button.getAttribute('data-item-name');
        const contactName = button.getAttribute('data-contact-name');
        const contactEmail = button.getAttribute('data-contact-email');
        const contactPhone = button.getAttribute('data-contact-phone');
        
        claimModalContent.innerHTML = `
            <div class="alert alert-info">
                <h6>Item Details:</h6>
                <p class="mb-2"><strong>${itemName}</strong></p>
            </div>
            
            <div class="alert alert-warning">
                <small><strong>Note:</strong> The person who found your belonging had returned the item to the administrator, kindly collect it and bring your ID proof.</small>
            </div>
            
            <div class="mt-3">
                <button class="btn btn-primary btn-sm" id="markClaimedBtn" data-item-id="${itemId}">
                    Mark as Claimed
                </button>
                <small class="text-muted ms-2">This will send an approval request to admin</small>
            </div>
        `;
        
        // Add event listener for mark claimed button
        const markClaimedBtn = document.getElementById('markClaimedBtn');
        if (markClaimedBtn) {
            markClaimedBtn.addEventListener('click', function() {
                const itemId = this.getAttribute('data-item-id');
                submitClaim(itemId);
            });
        }
    });
    
    document.getElementById('btnAiSuggest').addEventListener('click', async function () {
        const query = document.querySelector('input[name="q"]').value.trim();
        if (!query) {
            alert('Enter a description before requesting AI suggestions.');
            return;
        }
        const aiResults = document.getElementById('aiResults');
        aiResults.innerHTML = '<div class="alert alert-info">Fetching AI suggestions...</div>';
        try {
            const baseUrl = document.body.dataset.baseUrl || '';
            const response = await fetch(`${baseUrl}/api/ai-match.php`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ description: query })
            });
            
            // Handle error responses
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                aiResults.innerHTML = '<div class="alert alert-danger">' +
                    '<strong>Error:</strong> ' + (errorData.message || 'Failed to get AI suggestions') + '<br>' +
                    (errorData.help ? '<small>' + errorData.help + '</small>' : '') +
                    '</div>';
                return;
            }
            
            const data = await response.json();
            if (data.success !== false && data.matches && data.matches.length > 0) {
                let html = '<div class="card shadow-sm"><div class="card-body"><h5>AI Suggested Matches</h5><ul class="list-group list-group-flush">';
                data.matches.forEach(match => {
                    const isLoggedIn = <?php echo is_logged_in() ? 'true' : 'false'; ?>;
                    const claimButton = match.item_type === 'found' ? 
                        (isLoggedIn ? 
                            `<button class="btn btn-success btn-sm claim-item-btn mt-2" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#claimModal"
                                    data-item-id="${match.id}"
                                    data-item-name="${match.item_name}"
                                    data-contact-name="${match.contact_name}"
                                    data-contact-email="${match.contact_email}"
                                    data-contact-phone="${match.contact_phone || 'Not provided'}">
                                Claim Item
                            </button>` :
                            `<a href="<?php echo APP_BASE_URL; ?>/pages/login.php" class="btn btn-outline-success btn-sm mt-2">Login to Claim</a>`) : '';
                    
                    html += `<li class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <strong>${match.item_name}</strong>
                                <div class="small text-muted">${match.location} · Score: ${(match.score * 100).toFixed(1)}%</div>
                                <p class="mb-0">${match.description ? match.description.substring(0, 120) + '...' : 'No description'}</p>
                                ${claimButton}
                            </div>
                        </div>
                    </li>`;
                });
                html += '</ul></div></div>';
                aiResults.innerHTML = html;
            } else {
                aiResults.innerHTML = '<div class="alert alert-warning">No AI matches found. Try adjusting your search terms.</div>';
            }
        } catch (error) {
            aiResults.innerHTML = '<div class="alert alert-danger">' +
                '<strong>AI Service Error:</strong><br>' +
                'Failed to contact AI matching service. Please check your internet connection and AI API key configuration.' +
                '</div>';
        }
    });
});

async function submitClaim(itemId) {
    const markClaimedBtn = document.getElementById('markClaimedBtn');
    const originalText = markClaimedBtn.innerHTML;
    
    // Show loading state
    markClaimedBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
    markClaimedBtn.disabled = true;
    
    try {
        const baseUrl = document.body.dataset.baseUrl || '';
        const response = await fetch(`${baseUrl}/api/submit-claim.php`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({item_id: itemId})
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Show success message
            const claimModalContent = document.getElementById('claimModalContent');
            claimModalContent.innerHTML += `
                <div class="alert alert-success mt-3">
                    <strong>Success!</strong> ${data.message}
                </div>
            `;
            
            // Hide the mark claimed button
            markClaimedBtn.style.display = 'none';
            
            // Optionally close modal after delay
            setTimeout(() => {
                const modal = bootstrap.Modal.getInstance(document.getElementById('claimModal'));
                modal.hide();
            }, 3000);
        } else {
            // Show error message
            const claimModalContent = document.getElementById('claimModalContent');
            claimModalContent.innerHTML += `
                <div class="alert alert-danger mt-3">
                    <strong>Error:</strong> ${data.message}
                </div>
            `;
            
            // Restore button
            markClaimedBtn.innerHTML = originalText;
            markClaimedBtn.disabled = false;
        }
    } catch (error) {
        // Show error message
        const claimModalContent = document.getElementById('claimModalContent');
        claimModalContent.innerHTML += `
            <div class="alert alert-danger mt-3">
                <strong>Error:</strong> Failed to submit claim. Please try again.
            </div>
        `;
        
        // Restore button
        markClaimedBtn.innerHTML = originalText;
        markClaimedBtn.disabled = false;
    }
}

// AI Image Matching
document.getElementById('btnImageMatch').addEventListener('click', async function() {
    const description = document.getElementById('imageDescription').value.trim();
    const imageFile = document.getElementById('imageUpload').files[0];
    
    if (!description) {
        alert('Please describe what you are looking for');
        return;
    }
    
    const aiResults = document.getElementById('aiResults');
    const btn = this;
    const originalText = btn.innerHTML;
    
    // Show loading state
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Analyzing...';
    btn.disabled = true;
    aiResults.innerHTML = '<div class="alert alert-info"><strong>AI is analyzing your image...</strong><br>This may take a few seconds.</div>';
    
    try {
        let imageData = '';
        
        // Convert image to base64 if uploaded
        if (imageFile) {
            imageData = await new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = e => resolve(e.target.result);
                reader.onerror = reject;
                reader.readAsDataURL(imageFile);
            });
        }
        
        const baseUrl = document.body.dataset.baseUrl || '';
        const response = await fetch(`${baseUrl}/api/ai-image-match.php`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                description: description,
                image_data: imageData
            })
        });
        
        // Handle error responses
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            aiResults.innerHTML = '<div class="alert alert-danger">' +
                '<strong>Error:</strong> ' + (errorData.message || 'Failed to get AI image matches') + '<br>' +
                (errorData.help ? '<small>' + errorData.help + '</small>' : '') +
                '</div>';
            return;
        }
        
        const data = await response.json();
        if (data.success !== false && data.matches && data.matches.length > 0) {
            let html = '<div class="card shadow-sm"><div class="card-body"><h5>🤖 AI Image Matches</h5><ul class="list-group list-group-flush">';
            data.matches.forEach(match => {
                const isLoggedIn = <?php echo is_logged_in() ? 'true' : 'false'; ?>;
                const claimButton = match.item_type === 'found' ? 
                    (isLoggedIn ? 
                        `<button class="btn btn-success btn-sm claim-item-btn mt-2" 
                                data-bs-toggle="modal" 
                                data-bs-target="#claimModal"
                                data-item-id="${match.item_id}"
                                data-item-name="${match.item_name}"
                                data-contact-name="${match.contact_name}"
                                data-contact-email="${match.contact_email}"
                                data-contact-phone="${match.contact_phone || 'Not provided'}">
                            Claim Item
                        </button>` :
                        `<a href="<?php echo APP_BASE_URL; ?>/pages/login.php" class="btn btn-outline-success btn-sm mt-2">Login to Claim</a>`) : '';
                
                html += `<li class="list-group-item">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <strong>${match.item_name}</strong>
                            <div class="small text-muted">${match.location} · Score: ${(match.score * 100).toFixed(1)}%</div>
                            <p class="mb-0">${match.description ? match.description.substring(0, 120) + '...' : 'No description'}</p>
                            ${match.match_reason ? `<div class="text-muted small mb-2"><strong>AI Reason:</strong> ${match.match_reason}</div>` : ''}
                            ${claimButton}
                        </div>
                        ${match.image_path ? `<img src="${APP_BASE_URL}${match.image_path}" style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px;" alt="${match.item_name}">` : ''}
                    </div>
                </li>`;
            });
            html += '</ul></div></div>';
            aiResults.innerHTML = html;
        } else {
            aiResults.innerHTML = '<div class="alert alert-warning">No AI image matches found. Try uploading a clearer photo or adjusting your description.</div>';
        }
    } catch (error) {
        aiResults.innerHTML = '<div class="alert alert-danger">' +
            '<strong>AI Image Service Error:</strong><br>' +
            'Failed to contact AI image matching service. Please check your internet connection and AI API key configuration.' +
            '</div>';
    } finally {
        // Restore button
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

