<?php
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => true
]);

require_once __DIR__ . '/../config/db.php';

$bicycle_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$bicycle_id) {
    header('Location: bicycles.php');
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT b.*, c.name as category_name, br.name as branch_name
        FROM bicycle b
        LEFT JOIN category c ON b.category_id = c.category_id
        LEFT JOIN branch br ON b.branch_id = br.branch_id
        WHERE b.bicycle_id = :bicycle_id
    ");
    $stmt->execute(['bicycle_id' => $bicycle_id]);
    $bicycle = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bicycle) {
        throw new Exception('Bicycle not found');
    }

    // *** NEW: Authorization Check ***
    // If user is logged in, they can only view details for bikes at their branch.
    // Logged-out users can view details for any bike.
    if (isset($_SESSION['user_id']) && $bicycle['branch_id'] != $_SESSION['branch_id']) {
        $_SESSION['error'] = 'This bicycle is not available at your branch.';
        header('Location: bicycles.php');
        exit();
    }

} catch (Exception $e) {
    error_log("Bicycle details error: " . $e->getMessage());
    $_SESSION['error'] = $e->getMessage();
    header('Location: bicycles.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($bicycle['name']); ?> - BikeBuddy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .bicycle-image {
            max-height: 400px;
            width: 100%;
            object-fit: cover;
        }
        .price-highlight {
            font-size: 1.5rem;
            font-weight: bold;
            color: #28a745;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container py-4">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="bicycles.php">Bicycles</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($bicycle['name']); ?></li>
            </ol>
        </nav>

        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <img src="<?php echo htmlspecialchars($bicycle['image_url'] ?? 'https://placehold.co/800x400/e2e8f0/64748b?text=No+Image'); ?>"
                         class="card-img-top bicycle-image"
                         alt="<?php echo htmlspecialchars($bicycle['name']); ?>">

                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <h2 class="card-title mb-0"><?php echo htmlspecialchars($bicycle['name']); ?></h2>
                            <span class="badge bg-<?php echo $bicycle['status'] === 'available' ? 'success' : 'warning'; ?> fs-6">
                                <?php echo ucfirst($bicycle['status']); ?>
                            </span>
                        </div>

                        <div class="d-flex justify-content-between text-muted mb-3">
                            <span>
                                <i class="bi bi-tag"></i> Category: 
                                <strong><?php echo htmlspecialchars($bicycle['category_name'] ?? 'N/A'); ?></strong>
                            </span>
                            <span>
                                <i class="bi bi-geo-alt-fill"></i> Branch:
                                <strong><?php echo htmlspecialchars($bicycle['branch_name'] ?? 'N/A'); ?></strong>
                            </span>
                        </div>

                        <p class="card-text">
                            <?php echo nl2br(htmlspecialchars($bicycle['description'] ?? 'No description available.')); ?>
                        </p>

                        <div class="row mt-4">
                            <div class="col-md-6">
                                <h4 class="price-highlight">
                                    KES <?php echo number_format($bicycle['price_per_day'], 2); ?> <small class="text-muted">per day</small>
                                </h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <?php if ($bicycle['status'] === 'available' && isset($_SESSION['user_id'])): ?>
                    <div class="card">
                        <div class="card-header"><h5 class="mb-0">Rent This Bicycle</h5></div>
                        <div class="card-body">
                            <a href="rent.php?bicycle_id=<?php echo $bicycle['bicycle_id']; ?>"
                               class="btn btn-primary btn-lg w-100">
                                <i class="bi bi-cart-plus"></i> Rent Now
                            </a>
                            <p class="text-muted mt-2 small">
                                Click to proceed with rental booking
                            </p>
                        </div>
                    </div>
                <?php elseif (!isset($_SESSION['user_id'])): ?>
                     <div class="card">
                        <div class="card-header"><h5 class="mb-0">Login to Rent</h5></div>
                        <div class="card-body">
                            <p class="text-muted">You must be logged in to rent a bicycle.</p>
                            <a href="login.php?redirect=bicycle_details.php?id=<?= $bicycle_id ?>" class="btn btn-primary w-100">Login</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-header"><h5 class="mb-0">Not Available</h5></div>
                        <div class="card-body">
                            <p class="text-muted">This bicycle is currently <?php echo $bicycle['status']; ?>.</p>
                            <a href="bicycles.php" class="btn btn-secondary">Browse Other Bicycles</a>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card mt-3">
                    <div class="card-header"><h5 class="mb-0">Bicycle Details</h5></div>
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-4">ID</dt>
                            <dd class="col-sm-8">#<?php echo $bicycle['bicycle_id']; ?></dd>

                            <dt class="col-sm-4">Category</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($bicycle['category_name'] ?? 'Uncategorized'); ?></dd>
                            
                            <dt class="col-sm-4">Branch</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($bicycle['branch_name'] ?? 'N/A'); ?></dd>

                            <dt class="col-sm-4">Status</dt>
                            <dd class="col-sm-8">
                                <span class="badge bg-<?php echo $bicycle['status'] === 'available' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst($bicycle['status']); ?>
                                </span>
                            </dd>
                            
                            <dt class="col-sm-4">Added</dt>
                            <dd class="col-sm-8"><?php echo date('M j, Y', strtotime($bicycle['created_at'])); ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>