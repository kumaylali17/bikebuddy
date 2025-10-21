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
            r.id,
            r.user_id,
            r.bicycle_id,
            r.start_date,
            r.end_date,
            r.return_date,
            r.total_cost,
            r.status,
            r.created_at,
            b.name as bicycle_name,
            b.image_url,
            u.username,
            u.email,
            u.phone,
            p.amount as payment_amount,
            p.payment_date,
            p.status as payment_status
        FROM rental r
        JOIN bicycle b ON r.bicycle_id = b.bicycle_id
        JOIN app_user u ON r.user_id = u.user_id
        LEFT JOIN payment p ON r.id = p.rental_id
        WHERE r.id = ? AND r.user_id = ?
    ");
    $stmt->execute([$rental_id, $_SESSION['user_id']]);
    $rental = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$rental) {
        throw new Exception('Rental not found');
    }
} catch (Exception $e) {
    error_log("Rental Details Error: " . $e->getMessage());
    $_SESSION['error'] = 'Error loading rental details';
    header('Location: my_rentals.php');
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
            <a href="my_rentals.php" class="btn btn-outline-secondary">Back to Rentals</a>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Bicycle Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <?php if (!empty($rental['image_url'])): ?>
                                    <img src="<?= htmlspecialchars($rental['image_url']) ?>" 
                                         alt="<?= htmlspecialchars($rental['bicycle_name']) ?>" 
                                         class="img-fluid rounded">
                                <?php endif; ?>
                            </div>
                            <div class="col-md-8">
                                <h5><?= htmlspecialchars($rental['bicycle_name']) ?></h5>
                                <p class="text-muted">ID: #<?= $rental['bicycle_id'] ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Rental Period</h5>
                    </div>
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-4">Start Date</dt>
                            <dd class="col-sm-8"><?= date('F j, Y H:i', strtotime($rental['start_date'])) ?></dd>
                            
                            <dt class="col-sm-4">End Date</dt>
                            <dd class="col-sm-8">
                                <?= $rental['end_date'] 
                                    ? date('F j, Y H:i', strtotime($rental['end_date']))
                                    : 'Not specified' ?>
                            </dd>
                            
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
                    <div class="card-header">
                        <h5 class="mb-0">Payment Information</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($rental['payment_amount']): ?>
                            <dl class="row">
                                <dt class="col-sm-4">Amount</dt>
                                <dd class="col-sm-8">KES <?= number_format($rental['payment_amount'], 2) ?></dd>
                                
                                <dt class="col-sm-4">Payment Date</dt>
                                <dd class="col-sm-8">
                                    <?= $rental['payment_date'] 
                                        ? date('F j, Y H:i', strtotime($rental['payment_date'])) 
                                        : 'N/A' ?>
                                </dd>
                                
                                <dt class="col-sm-4">Status</dt>
                                <dd class="col-sm-8">
                                    <span class="badge bg-<?= 
                                        $rental['payment_status'] === 'completed' ? 'success' : 
                                        ($rental['payment_status'] === 'pending' ? 'warning' : 'secondary') 
                                    ?>">
                                        <?= ucfirst($rental['payment_status'] ?? 'unpaid') ?>
                                    </span>
                                </dd>
                            </dl>
                            
                            <?php if ($rental['payment_status'] === 'pending'): ?>
                                <button class="btn btn-primary">Pay Now</button>
                            <?php endif; ?>
                        <?php else: ?>
                            <p>No payment information available.</p>
                            <?php if ($rental['status'] === 'active'): ?>
                                <a href="return.php?rental_id=<?= $rental['id'] ?>" 
                                   class="btn btn-primary"
                                   onclick="return confirm('Are you sure you want to return this bicycle?')">
                                    Return Bicycle
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Customer Information</h5>
                    </div>
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