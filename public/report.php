<?php
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => true
]);

require_once __DIR__ . '/../config/db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit();
}

// Get rental statistics
try {
    $stmt = $pdo->query("
        SELECT
            COUNT(*) as total_rentals,
            SUM(total_cost) as total_income,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_rentals,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_rentals
        FROM rental
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Report error: " . $e->getMessage());
    $stats = [
        'total_rentals' => 0,
        'total_income' => 0,
        'active_rentals' => 0,
        'completed_rentals' => 0
    ];
}

// Get bicycle statistics
try {
    $bikeStmt = $pdo->query("
        SELECT
            COUNT(*) as total_bicycles,
            COUNT(CASE WHEN status = 'available' THEN 1 END) as available_bicycles,
            COUNT(CASE WHEN status = 'rented' THEN 1 END) as rented_bicycles
        FROM bicycle
    ");
    $bikeStats = $bikeStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Bike stats error: " . $e->getMessage());
    $bikeStats = [
        'total_bicycles' => 0,
        'available_bicycles' => 0,
        'rented_bicycles' => 0
    ];
}

// Get user statistics
try {
    $userStmt = $pdo->query("
        SELECT
            COUNT(*) as total_users,
            COUNT(CASE WHEN is_admin = TRUE THEN 1 END) as admin_users
        FROM app_user
    ");
    $userStats = $userStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("User stats error: " . $e->getMessage());
    $userStats = [
        'total_users' => 0,
        'admin_users' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - BikeBuddy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container py-4">
        <div class="row">
            <div class="col-md-3">
                <?php include 'navbar.php'; ?>
            </div>
            <div class="col-md-9">
                <h2 class="mb-4">System Reports</h2>

                <!-- Rental Statistics -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Rental Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h3 class="text-primary"><?php echo $stats['total_rentals']; ?></h3>
                                    <p class="text-muted">Total Rentals</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h3 class="text-success">KES <?php echo number_format($stats['total_income'] ?? 0, 2); ?></h3>
                                    <p class="text-muted">Total Income</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h3 class="text-warning"><?php echo $stats['active_rentals']; ?></h3>
                                    <p class="text-muted">Active Rentals</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h3 class="text-info"><?php echo $stats['completed_rentals']; ?></h3>
                                    <p class="text-muted">Completed Rentals</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bicycle Statistics -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Bicycle Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="text-center">
                                    <h3 class="text-primary"><?php echo $bikeStats['total_bicycles']; ?></h3>
                                    <p class="text-muted">Total Bicycles</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center">
                                    <h3 class="text-success"><?php echo $bikeStats['available_bicycles']; ?></h3>
                                    <p class="text-muted">Available</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center">
                                    <h3 class="text-warning"><?php echo $bikeStats['rented_bicycles']; ?></h3>
                                    <p class="text-muted">Currently Rented</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- User Statistics -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">User Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="text-center">
                                    <h3 class="text-primary"><?php echo $userStats['total_users']; ?></h3>
                                    <p class="text-muted">Total Users</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-center">
                                    <h3 class="text-warning"><?php echo $userStats['admin_users']; ?></h3>
                                    <p class="text-muted">Administrators</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <a href="manage_rentals.php" class="btn btn-primary">View All Rentals</a>
                    <a href="manage_bicycles.php" class="btn btn-secondary">Manage Bicycles</a>
                    <a href="manage_users.php" class="btn btn-secondary">Manage Users</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>