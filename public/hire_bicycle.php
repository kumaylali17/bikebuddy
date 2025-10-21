<?php
require_once __DIR__ . '/../config/db.php';
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get available bicycles
try {
    $bicyclesStmt = $pdo->query("
        SELECT b.* 
        FROM bicycle b 
        WHERE b.status = 'available'
        ORDER BY b.bicycle_name
    ");
    $bicycles = $bicyclesStmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Error fetching available bicycles: ' . $e->getMessage();
    error_log($error);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bicycle_id'])) {
    try {
        $pdo->beginTransaction();
        
        $bicycle_id = (int)$_POST['bicycle_id'];
        $hire_date = $_POST['hire_date'];
        $return_date = $_POST['return_date'];
        
        // Calculate cost based on hours and price per hour
        $hours = (strtotime($return_date) - strtotime($hire_date)) / 3600;
        $hours = max(1, $hours); // Minimum 1 hour
        
        // Get bicycle price
        $priceStmt = $pdo->prepare("SELECT price_per_hour FROM bicycle WHERE bicycle_id = ?");
        $priceStmt->execute([$bicycle_id]);
        $bike = $priceStmt->fetch();
        
        if (!$bike) {
            throw new Exception('Bicycle not found or not available.');
        }
        
        $cost = $hours * $bike['price_per_hour'];
        
        // Create hire record
        $stmt = $pdo->prepare("
            INSERT INTO hire (customer_id, bicycle_id, hire_date, return_date, cost, status) 
            VALUES (?, ?, ?, ?, ?, 'active')
        ");
        $stmt->execute([$user_id, $bicycle_id, $hire_date, $return_date, $cost]);
        
        // Update bicycle status
        $updateStmt = $pdo->prepare("UPDATE bicycle SET status = 'hired' WHERE bicycle_id = ?");
        $updateStmt->execute([$bicycle_id]);
        
        $pdo->commit();
        
        $_SESSION['success'] = 'Bicycle hired successfully! Total cost: ₱' . number_format($cost, 2);
        header('Location: dashboard.php');
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Error processing your request: ' . $e->getMessage();
        error_log($error);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hire a Bicycle - BikeBuddy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .bicycle-card {
            transition: transform 0.2s;
            height: 100%;
        }
        .bicycle-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .bicycle-image {
            height: 200px;
            object-fit: cover;
            width: 100%;
        }
        .price-tag {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <!-- Include the same navigation as dashboard -->
    <?php include 'includes/navbar.php'; ?>

    <div class="container mt-4">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Hire a Bicycle</li>
            </ol>
        </nav>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <h2 class="mb-4">Available Bicycles</h2>
        
        <?php if (empty($bicycles)): ?>
            <div class="alert alert-info">
                No bicycles are currently available for hire. Please check back later.
            </div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-3 g-4">
                <?php foreach ($bicycles as $bike): ?>
                    <div class="col">
                        <div class="card bicycle-card h-100">
                            <?php if ($bike['image_url']): ?>
                                <img src="../<?php echo htmlspecialchars($bike['image_url']); ?>" 
                                     class="card-img-top bicycle-image" 
                                     alt="<?php echo htmlspecialchars($bike['bicycle_name']); ?>">
                            <?php else: ?>
                                <div class="text-center bg-light p-5">
                                    <i class="bi bi-bicycle" style="font-size: 4rem; color: #6c757d;"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="price-tag">
                                ₱<?php echo number_format($bike['price_per_hour'], 2); ?>/hr
                            </div>
                            
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($bike['bicycle_name']); ?></h5>
                                <p class="card-text">
                                    <?php echo nl2br(htmlspecialchars($bike['description'] ?? 'No description available.')); ?>
                                </p>
                                
                                <button type="button" 
                                        class="btn btn-primary w-100" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#hireModal"
                                        data-bike-id="<?php echo $bike['bicycle_id']; ?>"
                                        data-bike-name="<?php echo htmlspecialchars($bike['bicycle_name']); ?>"
                                        data-price="<?php echo $bike['price_per_hour']; ?>">
                                    <i class="bi bi-cart-plus"></i> Hire Now
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Hire Modal -->
    <div class="modal fade" id="hireModal" tabindex="-1" aria-labelledby="hireModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="hireForm" method="POST">
                    <input type="hidden" name="bicycle_id" id="modalBikeId">
                    
                    <div class="modal-header">
                        <h5 class="modal-title" id="hireModalLabel">Hire Bicycle</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="bikeName" class="form-label">Bicycle</label>
                            <input type="text" class="form-control" id="bikeName" readonly>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="hireDate" class="form-label">Hire Date & Time</label>
                                <input type="datetime-local" class="form-control" id="hireDate" name="hire_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="returnDate" class="form-label">Return Date & Time</label>
                                <input type="datetime-local" class="form-control" id="returnDate" name="return_date" required>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <div class="d-flex justify-content-between">
                                <span>Estimated Cost:</span>
                                <strong id="estimatedCost">₱0.00</strong>
                            </div>
                            <small class="text-muted" id="durationText"></small>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Confirm Hire</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize the modal
        const hireModal = document.getElementById('hireModal');
        if (hireModal) {
            hireModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const bikeId = button.getAttribute('data-bike-id');
                const bikeName = button.getAttribute('data-bike-name');
                const pricePerHour = parseFloat(button.getAttribute('data-price'));
                
                // Set bike info
                document.getElementById('modalBikeId').value = bikeId;
                document.getElementById('bikeName').value = bikeName;
                
                // Set default times (now and 1 hour from now)
                const now = new Date();
                const oneHourLater = new Date(now.getTime() + 60 * 60 * 1000);
                
                // Format for datetime-local input (YYYY-MM-DDTHH:MM)
                const formatDateTime = (date) => {
                    return date.toISOString().slice(0, 16);
                };
                
                document.getElementById('hireDate').value = formatDateTime(now);
                document.getElementById('returnDate').value = formatDateTime(oneHourLater);
                
                // Initial calculation
                updateCost(pricePerHour);
                
                // Add event listeners for date/time changes
                document.getElementById('hireDate').addEventListener('change', () => updateCost(pricePerHour));
                document.getElementById('returnDate').addEventListener('change', () => updateCost(pricePerHour));
            });
        }
        
        // Calculate and update cost
        function updateCost(pricePerHour) {
            const hireDate = new Date(document.getElementById('hireDate').value);
            const returnDate = new Date(document.getElementById('returnDate').value);
            
            if (isNaN(hireDate.getTime()) || isNaN(returnDate.getTime())) {
                return;
            }
            
            // Calculate duration in hours (minimum 1 hour)
            const durationHours = Math.max(1, (returnDate - hireDate) / (1000 * 60 * 60));
            const totalCost = durationHours * pricePerHour;
            
            // Update UI
            document.getElementById('estimatedCost').textContent = '₱' + totalCost.toFixed(2);
            
            // Format duration text
            const hours = Math.floor(durationHours);
            const minutes = Math.round((durationHours - hours) * 60);
            let durationText = '';
            
            if (hours > 0) {
                durationText += `${hours} hour${hours !== 1 ? 's' : ''}`;
                if (minutes > 0) {
                    durationText += ` and ${minutes} minute${minutes !== 1 ? 's' : ''}`;
                }
            } else {
                durationText = `${minutes} minute${minutes !== 1 ? 's' : ''}`;
            }
            
            document.getElementById('durationText').textContent = `Duration: ${durationText}`;
        }
        
        // Form validation
        document.getElementById('hireForm').addEventListener('submit', function(e) {
            const hireDate = new Date(document.getElementById('hireDate').value);
            const returnDate = new Date(document.getElementById('returnDate').value);
            
            if (returnDate <= hireDate) {
                e.preventDefault();
                alert('Return date must be after hire date.');
                return false;
            }
            
            if (hireDate < new Date()) {
                e.preventDefault();
                alert('Hire date cannot be in the past.');
                return false;
            }
            
            return true;
        });
        
        // Set minimum date/time for hire date to now
        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date();
            // Set minimum date/time for hire date (now)
            document.getElementById('hireDate').min = now.toISOString().slice(0, 16);
            
            // Set minimum date/time for return date (hire date + 1 hour)
            document.getElementById('hireDate').addEventListener('change', function() {
                const hireDate = new Date(this.value);
                const minReturnDate = new Date(hireDate.getTime() + 60 * 60 * 1000); // 1 hour later
                document.getElementById('returnDate').min = minReturnDate.toISOString().slice(0, 16);
                
                // If current return date is before the new minimum, update it
                const returnDate = new Date(document.getElementById('returnDate').value);
                if (returnDate < minReturnDate) {
                    document.getElementById('returnDate').value = minReturnDate.toISOString().slice(0, 16);
                }
            });
        });
    </script>
</body>
</html>