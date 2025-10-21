<?php
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => true
]);

require_once __DIR__ . '/../config/db.php';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 6;
$offset = ($page - 1) * $perPage;

// Category filter
$categoryFilter = isset($_GET['category']) ? (int)$_GET['category'] : 0;

// Get categories
try {
    $categoriesStmt = $pdo->query("SELECT * FROM category ORDER BY name");
    $categories = $categoriesStmt->fetchAll();
} catch (PDOException $e) {
    error_log("Categories error: " . $e->getMessage());
    $categories = [];
}

// Build query
$sql = "SELECT b.*, c.name as category_name FROM bicycle b LEFT JOIN category c ON b.category_id = c.category_id WHERE b.status = 'available'";
$params = [];

if ($categoryFilter > 0) {
    $sql .= " AND b.category_id = :category_id";
    $params['category_id'] = $categoryFilter;
}

$sql .= " ORDER BY b.created_at DESC LIMIT :limit OFFSET :offset";

// Get total count for pagination
$countSql = "SELECT COUNT(*) FROM bicycle WHERE status = 'available'";
if ($categoryFilter > 0) {
    $countSql .= " AND category_id = :category_id";
}

try {
    $countStmt = $pdo->prepare($countSql);
    if ($categoryFilter > 0) {
        $countStmt->bindValue(':category_id', $categoryFilter, PDO::PARAM_INT);
    }
    $countStmt->execute();
    $total = $countStmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Count error: " . $e->getMessage());
    $total = 0;
}

// Get bicycles
try {
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    if ($categoryFilter > 0) {
        $stmt->bindValue(':category_id', $categoryFilter, PDO::PARAM_INT);
    }
    $stmt->execute();
    $bicycles = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Bicycles error: " . $e->getMessage());
    $bicycles = [];
}

$totalPages = ceil($total / $perPage);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Bicycles - BikeBuddy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .bike-card {
            transition: transform 0.2s;
            height: 100%;
        }
        .bike-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .bike-img {
            height: 200px;
            object-fit: cover;
        }
        .category-badge {
            position: absolute;
            top: 10px;
            right: 10px;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container py-4">
        <div class="row">
            <div class="col-md-8">
                <h2 class="mb-4">Available Bicycles</h2>
                
                <?php if (empty($bicycles)): ?>
                    <div class="alert alert-info">
                        <h4>No bicycles available</h4>
                        <p>There are currently no bicycles available for rent. Please check back later or contact us for more information.</p>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($bicycles as $bike): ?>
                            <div class="col-md-4 mb-4">
                                <div class="card bike-card">
                                    <?php if (!empty($bike['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($bike['image_url']); ?>" 
                                             class="card-img-top bike-img" 
                                             alt="<?php echo htmlspecialchars($bike['name']); ?>">
                                    <?php else: ?>
                                        <div class="bg-light d-flex align-items-center justify-content-center bike-img">
                                            <span class="text-muted">No Image</span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($bike['category_name'])): ?>
                                        <span class="badge bg-primary category-badge">
                                            <?php echo htmlspecialchars($bike['category_name']); ?>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($bike['name']); ?></h5>
                                        <p class="card-text text-muted">
                                            <?php echo htmlspecialchars(substr($bike['description'] ?? '', 0, 100)); ?>
                                            <?php if (strlen($bike['description'] ?? '') > 100): ?>...<?php endif; ?>
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="h5 text-primary mb-0">
                                                KES <?php echo number_format($bike['price_per_day'], 2); ?>/day
                                            </span>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <div class="d-grid gap-2">
                                            <a href="bicycle_details.php?id=<?php echo $bike['bicycle_id']; ?>" 
                                               class="btn btn-outline-primary">
                                                View Details
                                            </a>
                                            <a href="rent.php?bicycle_id=<?php echo $bike['bicycle_id']; ?>" 
                                               class="btn btn-primary">
                                                Rent Now
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="Bicycles pagination" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $categoryFilter ? '&category=' . $categoryFilter : ''; ?>">
                                            Previous
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo $categoryFilter ? '&category=' . $categoryFilter : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $categoryFilter ? '&category=' . $categoryFilter : ''; ?>">
                                            Next
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="col-md-4">
                <!-- Category Filter -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Filter by Category</h5>
                    </div>
                    <div class="card-body">
                        <a href="?" class="btn btn-outline-secondary mb-2 <?php echo $categoryFilter === 0 ? 'active' : ''; ?>">
                            All Categories
                        </a>
                        <?php foreach ($categories as $category): ?>
                            <a href="?category=<?php echo $category['category_id']; ?>" 
                               class="btn btn-outline-primary mb-2 <?php echo $categoryFilter === $category['category_id'] ? 'active' : ''; ?>">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Quick Stats</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-2">
                            <strong><?php echo $total; ?></strong> bicycles available
                        </p>
                        <p class="mb-2">
                            <strong><?php echo count($categories); ?></strong> categories
                        </p>
                        <p class="mb-0">
                            Page <strong><?php echo $page; ?></strong> of <strong><?php echo $totalPages; ?></strong>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
