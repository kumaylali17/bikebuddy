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

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Get all rentals with user and bicycle info
try {
    $stmt = $pdo->prepare("
        SELECT
            r.*,
            u.username,
            u.email,
            b.name as bicycle_name,
            b.image_url
        FROM rental r
        JOIN app_user u ON r.user_id = u.user_id
        JOIN bicycle b ON r.bicycle_id = b.bicycle_id
        ORDER BY r.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rentals = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Manage rentals error: " . $e->getMessage());
    $rentals = [];
}

// Get total count for pagination
try {
    $countStmt = $pdo->query("SELECT COUNT(*) FROM rental");
    $total = $countStmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Count error: " . $e->getMessage());
    $total = 0;
}

$totalPages = ceil($total / $perPage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Rentals - BikeBuddy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .rental-card {
            transition: transform 0.2s;
        }
        .rental-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .status-badge {
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Manage Rentals</h2>
            <a href="report.php" class="btn btn-outline-primary">View Reports</a>
        </div>

        <?php if (empty($rentals)): ?>
            <div class="alert alert-info">
                <h4>No rentals found</h4>
                <p>There are currently no rentals in the system.</p>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($rentals as $rental): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card rental-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h5 class="card-title mb-0">
                                        <?php echo htmlspecialchars($rental['bicycle_name']); ?>
                                    </h5>
                                    <span class="badge bg-<?php
                                        echo $rental['status'] === 'completed' ? 'success' :
                                             ($rental['status'] === 'active' ? 'primary' : 'warning');
                                    ?> status-badge">
                                        <?php echo ucfirst($rental['status']); ?>
                                    </span>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-sm-4">
                                        <strong>Rental #<?php echo $rental['id']; ?></strong>
                                    </div>
                                    <div class="col-sm-8">
                                        <small class="text-muted">
                                            <?php echo date('M j, Y', strtotime($rental['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-sm-4">Customer:</div>
                                    <div class="col-sm-8">
                                        <a href="mailto:<?php echo htmlspecialchars($rental['email']); ?>">
                                            <?php echo htmlspecialchars($rental['username']); ?>
                                        </a>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-sm-4">Period:</div>
                                    <div class="col-sm-8">
                                        <?php echo date('M j, Y', strtotime($rental['start_date'])); ?>
                                        <?php if ($rental['end_date']): ?>
                                            - <?php echo date('M j, Y', strtotime($rental['end_date'])); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-sm-4">Cost:</div>
                                    <div class="col-sm-8">
                                        <strong>KES <?php echo number_format($rental['total_cost'], 2); ?></strong>
                                    </div>
                                </div>

                                <?php if ($rental['return_date']): ?>
                                    <div class="row mb-3">
                                        <div class="col-sm-4">Returned:</div>
                                        <div class="col-sm-8">
                                            <?php echo date('M j, Y', strtotime($rental['return_date'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="d-grid gap-2">
                                    <a href="rental_details.php?id=<?php echo $rental['id']; ?>"
                                       class="btn btn-outline-primary btn-sm">
                                        View Details
                                    </a>
                                    <?php if ($rental['status'] === 'active'): ?>
                                        <a href="return.php?rental_id=<?php echo $rental['id']; ?>"
                                           class="btn btn-success btn-sm"
                                           onclick="return confirm('Mark this rental as completed?')">
                                            Mark as Completed
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Rentals pagination" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
