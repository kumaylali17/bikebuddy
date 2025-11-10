<?php
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict'
]);

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$rental_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$rental_id) {
    $_SESSION['error'] = 'Invalid rental ID';
    header('Location: my_rentals.php');
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            r.*,
            b.name as bicycle_name,
            b.image_url,
            u.username,
            u.email,
            u.phone,
            br.name as branch_name,
            p.amount as payment_amount,
            p.payment_date,
            p.status as payment_status
        FROM rental r
        JOIN bicycle b ON r.bicycle_id = b.bicycle_id
        JOIN app_user u ON r.user_id = u.user_id
        LEFT JOIN branch br ON r.start_branch_id = br.branch_id
        LEFT JOIN payment p ON r.id = p.rental_id
        WHERE r.id = ?
    ");
    $stmt->execute([$rental_id]);
    $rental = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$rental) {
        throw new Exception('Rental not found');
    }

    // *** NEW: Authorization Check ***
    $user_role = $_SESSION['role'];
    $user_id = $_SESSION['user_id'];
    $user_branch_id = $_SESSION['branch_id'];

    if ($user_role === 'customer' && $rental['user_id'] != $user_id) {
        throw new Exception('You are not authorized to view this rental.');
    }
    
    if ($user_role === 'branch_manager' && $rental['start_branch_id'] != $user_branch_id) {
        throw new Exception('You are not authorized to view this rental.');
    }
    // Admin can view all, so no check needed.

} catch (Exception $e) {
    error_log("Rental Details Error: " . $e->getMessage());
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
    
    // Redirect based on role
    if ($_SESSION['role'] === 'customer') {
        header('Location: my_rentals.php');
    } else {
        header('Location: manage_rentals.php');
    }
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Rental Details - BikeBuddy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Rental Details #<?= $rental['id'] ?></h2>
            <?php if ($_SESSION['role'] === 'customer'): ?>
                <a href="my_rentals.php" class="btn btn-outline-secondary">Back to My Rentals</a>
            <?php else: ?>
                 <a href="manage_rentals.php" class="btn btn-outline-secondary">Back to All Rentals</a>
            <?php endif; ?>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header"><h5 class="mb-0">Bicycle Information</h5></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <img src="<?= htmlspecialchars($rental['image_url'] ?? 'https://placehold.co/400x300/e2e8f0/64748b?text=No+Image') ?>" 
                                     alt="<?= htmlspecialchars($rental['bicycle_name']) ?>" 
                                     class="img-fluid rounded">
                            </div>
                            <div class="col-md-8">
                                <h5><?= htmlspecialchars($rental['bicycle_name']) ?></h5>
                                <p class="text-muted">ID: #<?= $rental['bicycle_id'] ?></p>
                                <p class="text-muted">Branch: <strong><?= htmlspecialchars($rental['branch_name'] ?? 'N/A') ?></strong></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header"><h5 class="mb-0">Rental Period</h5></div>
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-4">Start Date</dt>
                            <dd class="col-sm-8"><?= date('F j, Y', strtotime($rental['start_date'])) ?></dd>
                            
                            <dt class="col-sm-4">End Date</dt>
                            <dd class="col-sm-8"><?= date('F j, Y', strtotime($rental['end_date'])) ?></dd>
                            
                            <dt class="col-sm-4">Return Date</dt>
                            <dd class="col-sm-8">
                                <?= $rental['return_date'] 
                                    ? date('F j, Y H:i', strtotime($rental['return_date']))
                                    : 'Not returned yet' ?>
                            </dd>
                            
                            <dt class="col-sm-4">Status</dt>
                            <dd class="col-sm-8">
                                <span class="badge bg-<?= 
                                    $rental['status'] === 'completed' ? 'success' : 
                                    ($rental['status'] === 'active' ? 'primary' : 'warning') 
                                ?>">
                                    <?= ucfirst($rental['status']) ?>
                                </span>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header"><h5 class="mb-0">Payment Information</h5></div>
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-4">Total Cost</dt>
                            <dd class="col-sm-8">
                                <h4 class="text-success">KES <?= number_format($rental['total_cost'], 2) ?></h4>
                            </dd>
                        </dl>

                        <?php if ($rental['payment_amount']): ?>
                            <dl class="row">
                                <dt class="col-sm-4">Amount Paid</dt>
                                <dd class="col-sm-8">KES <?= number_format($rental['payment_amount'], 2) ?></dd>
                                
                                <dt class="col-sm-4">Payment Date</dt>
                                <dd class="col-sm-8"><?= date('F j, Y H:i', strtotime($rental['payment_date'])) ?></dd>
                                
                                <dt class="col-sm-4">Status</dt>
                                <dd class="col-sm-8">
                                    <span class="badge bg-success">
                                        <?= ucfirst($rental['payment_status'] ?? 'Completed') ?>
                                    </span>
                                </dd>
                            </dl>
                        <?php else: ?>
                            <p>No payment information available.</p>
                            <?php if ($rental['status'] === 'active' && $_SESSION['role'] === 'customer'): ?>
                                <a href="return.php?rental_id=<?= $rental['id'] ?>" 
                                   class="btn btn-primary"
                                   onclick="return confirm('Are you sure you want to return this bicycle?')">
                                    Return Bicycle and Pay
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">Customer Information</h5></div>
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-4">Name</dt>
                            <dd class="col-sm-8"><?= htmlspecialchars($rental['username']) ?></dd>
                            
                            <dt class="col-sm-4">Email</dt>
                            <dd class="col-sm-8"><?= htmlspecialchars($rental['email']) ?></dd>
                            
                            <dt class="col-sm-4">Phone</dt>
                            <dd class="col-sm-8"><?= $rental['phone'] ? htmlspecialchars($rental['phone']) : 'N/A' ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>