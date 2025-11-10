<?php
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => true
]);

require_once __DIR__ . '/../config/db.php';

// Check if user is logged in and is an admin or branch manager
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'branch_manager'])) {
    header('Location: login.php');
    exit();
}

$user_role = $_SESSION['role'];
$user_branch_id = $_SESSION['branch_id'] ?? null;

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// --- Base SQL ---
$sql = "
    SELECT
        r.*,
        u.username,
        u.email,
        b.name as bicycle_name,
        br.name as branch_name
    FROM rental r
    JOIN app_user u ON r.user_id = u.user_id
    JOIN bicycle b ON r.bicycle_id = b.bicycle_id
    LEFT JOIN branch br ON r.start_branch_id = br.branch_id
";
$countSql = "SELECT COUNT(*) FROM rental r";
$params = [];

// *** NEW: Filter by branch for Branch Managers ***
if ($user_role === 'branch_manager') {
    $sql .= " WHERE r.start_branch_id = :branch_id";
    $countSql .= " WHERE r.start_branch_id = :branch_id";
    $params['branch_id'] = $user_branch_id;
}

$sql .= " ORDER BY r.created_at DESC LIMIT :limit OFFSET :offset";
$params['limit'] = $perPage;
$params['offset'] = $offset;

// Get all rentals
try {
    $stmt = $pdo->prepare($sql);
    // Bind all params
    foreach ($params as $key => &$val) {
        $stmt->bindParam(":$key", $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $rentals = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Manage rentals error: " . $e->getMessage());
    $rentals = [];
}

// Get total count for pagination
try {
    $countStmt = $pdo->prepare($countSql);
    // Bind branch_id if it's set
    if (isset($params['branch_id'])) {
        $countStmt->bindParam(":branch_id", $params['branch_id'], PDO::PARAM_INT);
    }
    $countStmt->execute();
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
        .status-badge { font-size: 0.85rem; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Manage Rentals</h2>
            <a href="report.php" class="btn btn-outline-primary">View Reports</a>
        </div>
        <?php if ($user_role === 'branch_manager'): ?>
            <h4 class="text-muted mb-4">For: <?= htmlspecialchars($_SESSION['branch_name'] ?? 'Your Branch') ?></h4>
        <?php endif; ?>

        <?php if (empty($rentals)): ?>
            <div class="alert alert-info">
                <h4>No rentals found</h4>
                <p>There are currently no rentals for this branch.</p>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Rental ID</th>
                                    <th>Bicycle</th>
                                    <th>Customer</th>
                                    <th>Branch</th>
                                    <th>Period</th>
                                    <th>Cost</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rentals as $rental): ?>
                                    <tr>
                                        <td><strong>#<?= $rental['id'] ?></strong></td>
                                        <td><?= htmlspecialchars($rental['bicycle_name']) ?></td>
                                        <td><?= htmlspecialchars($rental['username']) ?></td>
                                        <td><?= htmlspecialchars($rental['branch_name'] ?? 'N/A') ?></td>
                                        <td>
                                            <?= date('M j, Y', strtotime($rental['start_date'])) ?> to
                                            <?= date('M j, Y', strtotime($rental['end_date'])) ?>
                                        </td>
                                        <td>KES <?= number_format($rental['total_cost'], 2) ?></td>
                                        <td>
                                            <span class="badge bg-<?php
                                                echo $rental['status'] === 'completed' ? 'success' :
                                                     ($rental['status'] === 'active' ? 'primary' : 'warning');
                                            ?> status-badge">
                                                <?php echo ucfirst($rental['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                             <a href="rental_details.php?id=<?php echo $rental['id']; ?>"
                                               class="btn btn-outline-primary btn-sm">
                                                Details
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
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