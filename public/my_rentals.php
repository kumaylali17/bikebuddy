<?php
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => true
]);

require_once __DIR__ . '/../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get user's rentals
try {
    $stmt = $pdo->prepare("
        SELECT 
            r.id as rental_id,
            b.name as bicycle_name,
            b.image_url,
            r.start_date,
            r.return_date,
            r.total_cost,
            r.status
        FROM rental r
        JOIN bicycle b ON r.bicycle_id = b.bicycle_id
        WHERE r.user_id = ?
        ORDER BY r.start_date DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $rentals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching rentals: " . $e->getMessage());
    $rentals = [];
    $_SESSION['error'] = "Error fetching your rentals. Please try again.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Rentals - BikeBuddy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .bike-img {
            width: 100px;
            height: 70px;
            object-fit: cover;
            border-radius: 4px;
        }
        .status-badge {
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container py-4">
        <h2 class="mb-4">My Rentals</h2>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (empty($rentals)): ?>
            <div class="alert alert-info">
                You haven't rented any bicycles yet. <a href="bicycles.php" class="alert-link">Browse available bicycles</a>.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Bicycle</th>
                            <th>Rental Period</th>
                            <th>Status</th>
                            <th class="text-end">Total Cost</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rentals as $rental): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if (!empty($rental['image_url'])): ?>
                                            <img src="<?= htmlspecialchars($rental['image_url']) ?>" 
                                                 alt="<?= htmlspecialchars($rental['bicycle_name']) ?>" 
                                                 class="me-3 bike-img">
                                        <?php endif; ?>
                                        <div>
                                            <h6 class="mb-0"><?= htmlspecialchars($rental['bicycle_name']) ?></h6>
                                            <small class="text-muted">#<?= $rental['rental_id'] ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <div><?= date('M j, Y', strtotime($rental['start_date'])) ?></div>
                                        <small class="text-muted">
                                            <?= $rental['return_date'] 
                                                ? 'to ' . date('M j, Y', strtotime($rental['return_date']))
                                                : 'Ongoing' ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $badgeClass = [
                                        'pending' => 'bg-warning',
                                        'active' => 'bg-primary',
                                        'completed' => 'bg-success',
                                        'cancelled' => 'bg-secondary'
                                    ][$rental['status']] ?? 'bg-secondary';
                                    ?>
                                    <span class="badge rounded-pill <?= $badgeClass ?> status-badge">
                                        <?= ucfirst($rental['status']) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <?= $rental['total_cost'] 
                                        ? 'KES ' . number_format($rental['total_cost'], 2) 
                                        : 'N/A' ?>
                                </td>
                                <td>
                                    <?php if ($rental['status'] === 'active'): ?>
                                        <a href="return.php?rental_id=<?= $rental['rental_id'] ?>" 
                                           class="btn btn-sm btn-outline-primary"
                                           onclick="return confirm('Are you sure you want to return this bicycle?')">
                                            Return
                                        </a>
                                    <?php endif; ?>
                                    <a href="rental_details.php?id=<?= $rental['rental_id'] ?>" 
                                       class="btn btn-sm btn-outline-secondary">
                                        Details
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>