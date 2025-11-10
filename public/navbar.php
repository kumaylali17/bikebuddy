<?php
$current_page = basename($_SERVER['PHP_SELF']);

$user_role = $_SESSION['role'] ?? 'guest';
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="index.php">BikeBuddy</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'index.php' ? 'active' : '' ?>" 
                       href="index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'bicycles.php' ? 'active' : '' ?>" 
                       href="bicycles.php">Browse Bicycles</a>
                </li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page === 'my_rentals.php' ? 'active' : '' ?>" 
                           href="my_rentals.php">My Rentals</a>
                    </li>
                <?php endif; ?>
                
                <?php if ($user_role === 'admin'): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown">
                        Admin
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="report.php">All Reports</a></li>
                        <li><a class="dropdown-item" href="manage_branches.php">Manage Branches</a></li>
                        <li><a class="dropdown-item" href="manage_users.php">Manage Users</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="manage_bicycles.php">Manage All Bicycles</a></li>
                        <li><a class="dropdown-item" href="manage_rentals.php">Manage All Rentals</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="manage_suppliers.php">Manage Suppliers</a></li>
                        <li><a class="dropdown-item" href="manage_purchases.php">Manage Purchases</a></li>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if ($user_role === 'branch_manager'): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="branchAdminDropdown" role="button" data-bs-toggle="dropdown">
                        Manager
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="report.php">My Branch Report</a></li>
                        <li><a class="dropdown-item" href="manage_bicycles.php">Manage My Bicycles</a></li>
                        <li><a class="dropdown-item" href="manage_rentals.php">Manage My Rentals</a></li>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if ($user_role === 'purchasing_manager'): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="purchasingDropdown" role="button" data-bs-toggle="dropdown">
                        Purchasing
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="manage_purchases.php">Manage Purchases</a></li>
                        <li><a class="dropdown-item" href="manage_suppliers.php">Manage Suppliers</a></li>
                    </ul>
                </li>
                <?php endif; ?>

            </ul>
            
            <ul class="navbar-nav">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if (isset($_SESSION['branch_name'])): ?>
                        <span class="navbar-text me-3" title="Your current branch">
                            <i class="bi bi-geo-alt-fill"></i>
                            <?= htmlspecialchars($_SESSION['branch_name']) ?>
                        </span>
                    <?php endif; ?>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?> (<?= htmlspecialchars(ucfirst(str_replace('_', ' ', $user_role))) ?>)
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php">My Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page === 'login.php' ? 'active' : '' ?>" 
                           href="login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page === 'signup.php' ? 'active' : '' ?>" 
                           href="signup.php">Register</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">