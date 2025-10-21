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
        SELECT b.*, c.name as category_name
        FROM bicycle b
        LEFT JOIN category c ON b.category_id = c.category_id
        WHERE b.bicycle_id = :bicycle_id
    ");
    $stmt->execute(['bicycle_id' => $bicycle_id]);
    $bicycle = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bicycle) {
        throw new Exception('Bicycle not found');
    }
} catch (Exception $e) {
    error_log("Bicycle details error: " . $e->getMessage());
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
        .status-badge {
            font-size: 0.9rem;
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
                    <?php if (!empty($bicycle['image_url'])): ?>
                        <img src="<?php echo htmlspecialchars($bicycle['image_url']); ?>"
                             class="card-img-top bicycle-image"
                             alt="<?php echo htmlspecialchars($bicycle['name']); ?>">
                    <?php else: ?>
                        <div class="bg-light d-flex align-items-center justify-content-center" style="height: 400px;">
                            <span class="text-muted">No Image Available</span>
                        </div>
                    <?php endif; ?>

                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <h2 class="card-title mb-0"><?php echo htmlspecialchars($bicycle['name']); ?></h2>
                            <span class="badge bg-<?php echo $bicycle['status'] === 'available' ? 'success' : 'warning'; ?> status-badge">
                                <?php echo ucfirst($bicycle['status']); ?>
                            </span>
                        </div>

                        <?php if (!empty($bicycle['category_name'])): ?>
                            <p class="text-muted mb-3">
                                <i class="bi bi-tag"></i> Category: <?php echo htmlspecialchars($bicycle['category_name']); ?>
                            </p>
                        <?php endif; ?>

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
                <?php if ($bicycle['status'] === 'available'): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Rent This Bicycle</h5>
                        </div>
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
                <?php else: ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Not Available</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">This bicycle is currently <?php echo $bicycle['status']; ?>.</p>
                            <a href="bicycles.php" class="btn btn-secondary">Browse Other Bicycles</a>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0">Bicycle Details</h5>
                    </div>
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-4">ID</dt>
                            <dd class="col-sm-8">#<?php echo $bicycle['bicycle_id']; ?></dd>

                            <dt class="col-sm-4">Category</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($bicycle['category_name'] ?? 'Uncategorized'); ?></dd>

                            <dt class="col-sm-4">Price</dt>
                            <dd class="col-sm-8">KES <?php echo number_format($bicycle['price_per_day'], 2); ?>/day</dd>

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

        <div class="mt-4">
            <a href="bicycles.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Bicycles
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</body>
</html>
