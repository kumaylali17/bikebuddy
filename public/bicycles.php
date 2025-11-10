<?php
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => true
]);

require_once __DIR__ . '/../config/db.php';

// --- FILTERS & PAGINATION ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 6;
$offset = ($page - 1) * $perPage;

// *** NEW: Branch Filter ***
// If user is logged in, default to their branch. Otherwise, default to 0 (all branches).
$defaultBranch = $_SESSION['branch_id'] ?? 0;
$branchFilter = isset($_GET['branch']) ? (int)$_GET['branch'] : $defaultBranch;

$categoryFilter = isset($_GET['category']) ? (int)$_GET['category'] : 0;

// --- DATA FETCHING ---
$params = [];
$countParams = [];
$catParams = [];

// Base SQL
$sql = "SELECT b.*, c.name as category_name, br.name as branch_name 
        FROM bicycle b 
        LEFT JOIN category c ON b.category_id = c.category_id
        LEFT JOIN branch br ON b.branch_id = br.branch_id
        WHERE b.status = 'available'";

$countSql = "SELECT COUNT(*) 
             FROM bicycle b 
             WHERE b.status = 'available'";

$catSql = "SELECT DISTINCT c.* FROM category c
           JOIN bicycle b ON c.category_id = b.category_id
           WHERE b.status = 'available'";

// Apply Branch Filter (if selected)
if ($branchFilter > 0) {
    $sql .= " AND b.branch_id = :branch_id";
    $countSql .= " AND b.branch_id = :branch_id";
    $catSql .= " AND b.branch_id = :cat_branch_id";
    
    $params['branch_id'] = $branchFilter;
    $countParams['branch_id'] = $branchFilter;
    $catParams['cat_branch_id'] = $branchFilter;
}

// Apply Category Filter (if selected)
if ($categoryFilter > 0) {
    $sql .= " AND b.category_id = :category_id";
    $countSql .= " AND category_id = :category_id";
    
    $params['category_id'] = $categoryFilter;
    $countParams['category_id'] = $categoryFilter;
}

$sql .= " ORDER BY b.created_at DESC LIMIT :limit OFFSET :offset";
$params['limit'] = $perPage;
$params['offset'] = $offset;

$catSql .= " ORDER BY c.name";

// Get Branches for filter
try {
    $branchesStmt = $pdo->query("SELECT * FROM branch ORDER BY name");
    $branches = $branchesStmt->fetchAll();
} catch (PDOException $e) {
    error_log("Branches error: " . $e->getMessage());
    $branches = [];
}

// Get Categories for filter (now branch-aware)
try {
    $categoriesStmt = $pdo->prepare($catSql);
    $categoriesStmt->execute($catParams);
    $categories = $categoriesStmt->fetchAll();
} catch (PDOException $e) {
    error_log("Categories error: " . $e->getMessage());
    $categories = [];
}

// Get total count for pagination
try {
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $total = $countStmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Count error: " . $e->getMessage());
    $total = 0;
}

// Get bicycles
try {
    $stmt = $pdo->prepare($sql);
    // Bind all params
    foreach ($params as $key => &$val) {
        $stmt->bindParam(":$key", $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $bicycles = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Bicycles error: " . $e->getMessage());
    $bicycles = [];
}

$totalPages = ceil($total / $perPage);

// Build pagination query string
$queryString = "";
if ($branchFilter > 0) $queryString .= "&branch=$branchFilter";
if ($categoryFilter > 0) $queryString .= "&category=$categoryFilter";

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
        .branch-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background-color: rgba(0,0,0,0.6) !important;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container py-4">
        <div class="row">
            <div class="col-lg-8">
                <h2 class="mb-4">Available Bicycles</h2>
                
                <?php if (empty($bicycles)): ?>
                    <div class="alert alert-info">
                        <h4>No bicycles available</h4>
                        <p>There are currently no bicycles available matching your criteria. Please try a different branch or category.</p>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($bicycles as $bike): ?>
                            <div class="col-md-6 mb-4">
                                <div class="card bike-card">
                                    <img src="<?php echo htmlspecialchars($bike['image_url'] ?? 'https://placehold.co/400x300/e2e8f0/64748b?text=No+Image'); ?>" 
                                         class="card-img-top bike-img" 
                                         alt="<?php echo htmlspecialchars($bike['name']); ?>">
                                    
                                    <?php if (!empty($bike['category_name'])): ?>
                                        <span class="badge bg-primary category-badge">
                                            <?php echo htmlspecialchars($bike['category_name']); ?>
                                        </span>
                                    <?php endif; ?>

                                    <!-- NEW: Show Branch Badge -->
                                    <?php if (!empty($bike['branch_name'])): ?>
                                        <span class="badge bg-dark branch-badge">
                                            <i class="bi bi-geo-alt-fill"></i> <?php echo htmlspecialchars($bike['branch_name']); ?>
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
                                            <!-- Only show rent button if logged in -->
                                            <?php if (isset($_SESSION['user_id'])): ?>
                                                <a href="rent.php?bicycle_id=<?php echo $bike['bicycle_id']; ?>" 
                                                   class="btn btn-primary">
                                                   Rent Now
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
                        <nav aria-label="Bicycles pagination" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?= $queryString ?>">
                                            Previous
                                        </a>
                                    </li>
                                <?php endif; ?>
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?><?= $queryString ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?= $queryString ?>">
                                            Next
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="col-lg-4">
                <!-- Branch Filter -->
                <div class="card mb-4">
                    <div class="card-header"><h5 class="mb-0">Filter by Branch</h5></div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="?<?php echo $categoryFilter ? 'category=' . $categoryFilter : ''; ?>" 
                               class="btn btn-outline-secondary <?php echo $branchFilter === 0 ? 'active' : ''; ?>">
                                All Branches
                            </a>
                            <?php foreach ($branches as $branch): ?>
                                <a href="?branch=<?php echo $branch['branch_id']; ?><?php echo $categoryFilter ? '&category=' . $categoryFilter : ''; ?>" 
                                   class="btn btn-outline-primary <?php echo $branchFilter === $branch['branch_id'] ? 'active' : ''; ?>">
                                    <?php echo htmlspecialchars($branch['name']); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Category Filter -->
                <div class="card mb-4">
                    <div class="card-header"><h5 class="mb-0">Filter by Category</h5></div>
                    <div class="card-body">
                         <div class="d-grid gap-2">
                            <a href="?<?php echo $branchFilter ? 'branch=' . $branchFilter : ''; ?>" 
                               class="btn btn-outline-secondary <?php echo $categoryFilter === 0 ? 'active' : ''; ?>">
                                All Categories
                            </a>
                            <?php foreach ($categories as $category): ?>
                                <a href="?category=<?php echo $category['category_id']; ?><?php echo $branchFilter ? '&branch=' . $branchFilter : ''; ?>" 
                                   class="btn btn-outline-primary <?php echo $categoryFilter === $category['category_id'] ? 'active' : ''; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>