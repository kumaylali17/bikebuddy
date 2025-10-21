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

// Get user's active rentals
try {
    $stmt = $pdo->prepare("
        SELECT r.*, b.name as bicycle_name, b.image_url
        FROM rental r
        JOIN bicycle b ON r.bicycle_id = b.bicycle_id  -- Changed b.id to b.bicycle_id
        WHERE r.user_id = :user_id 
        AND r.status = 'active'
        ORDER BY r.start_date DESC
        LIMIT 5
    ");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $active_rentals = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $active_rentals = [];
}

// Get available bicycles
try {
    $stmt = $pdo->query("
        SELECT * FROM bicycle 
        WHERE status = 'available' 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $available_bikes = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Available bikes error: " . $e->getMessage());
    $available_bikes = [];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard - BikeBuddy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .bike-card {
            transition: transform 0.2s;
        }
        .bike-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .bike-img {
            height: 200px;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container py-4">
        <h2 class="mb-4">Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
        
        <!-- Active Rentals -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Your Active Rentals</h5>
            </div>
            <div class="card-body">
                <?php if (empty($active_rentals)): ?>
                    <p class="text-muted">You don't have any active rentals.</p>
                    <a href="bicycles.php" class="btn btn-primary">Rent a Bike</a>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($active_rentals as $rental): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card h-100">
                                    <?php if (!empty($rental['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($rental['image_url']); ?>" 
                                             class="card-img-top bike-img" 
                                             alt="<?php echo htmlspecialchars($rental['bicycle_name']); ?>">
                                    <?php endif; ?>
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($rental['bicycle_name']); ?></h5>
                                        <p class="card-text">
                                            <small class="text-muted">
                                                Rented on: <?php echo date('M j, Y', strtotime($rental['start_date'])); ?>
                                            </small>
                                        </p>
                                        <a href="rental_details.php?id=<?php echo $rental['id']; ?>" 
                                           class="btn btn-outline-primary btn-sm">
                                            View Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Available Bikes -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Available Bicycles</h5>
            </div>
            <div class="card-body">
                <?php if (empty($available_bikes)): ?>
                    <p class="text-muted">No bicycles available at the moment.</p>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($available_bikes as $bike): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card h-100 bike-card">
                                    <?php if (!empty($bike['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($bike['image_url']); ?>" 
                                             class="card-img-top bike-img" 
                                             alt="<?php echo htmlspecialchars($bike['name']); ?>">
                                    <?php endif; ?>
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($bike['name']); ?></h5>
                                        <p class="card-text">
                                            KES <?php echo number_format($bike['price_per_day'], 2); ?> / day
                                        </p>
                                        <a href="rent.php?bicycle_id=<?php echo $bike['bicycle_id']; ?>" 
                                           class="btn btn-primary">
                                            Rent Now
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-center mt-3">
                        <a href="bicycles.php" class="btn btn-outline-secondary">View All Bicycles</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>