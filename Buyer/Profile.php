<?php
declare(strict_types=1);

session_start();
require '../config.php';
require '../includes/db_helpers.php';

$loggedIn = (bool) ($_SESSION['loggedin'] ?? false);
$userId   = (int)  ($_SESSION['user_id']  ?? 0);

if (!$loggedIn || $userId <= 0) {
    header('Location: ../Login.php');
    exit;
}

$cartCount = bh_cart_count($conn, $userId);

$profile = [
    'username'  => (string) ($_SESSION['userName']  ?? ''),
    'FirstName' => (string) ($_SESSION['FirstName'] ?? ''),
    'LastName'  => (string) ($_SESSION['LastName']  ?? ''),
    'email'     => (string) ($_SESSION['email']     ?? ''),
    'role'      => (string) ($_SESSION['role']      ?? 'buyer'),
];

// Keep profile/role in sync with DB (e.g., after admin approves seller request)
$stmt = $conn->prepare('SELECT username, FirstName, LastName, email, role FROM users WHERE user_id = ?');
if ($stmt) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->bind_result($dbUsername, $dbFirstName, $dbLastName, $dbEmail, $dbRole);
    if ($stmt->fetch()) {
        $normalizedRole = strtolower((string) $dbRole);
        if ($normalizedRole === 'both') {
            $dbRole = 'seller';
            $fixRole = $conn->prepare("UPDATE users SET role = 'seller' WHERE user_id = ?");
            if ($fixRole) {
                $fixRole->bind_param('i', $userId);
                $fixRole->execute();
                $fixRole->close();
            }
        }

        $profile['username']  = (string) $dbUsername;
        $profile['FirstName'] = (string) $dbFirstName;
        $profile['LastName']  = (string) $dbLastName;
        $profile['email']     = (string) $dbEmail;
        $profile['role']      = (string) $dbRole;

        $_SESSION['userName']  = $profile['username'];
        $_SESSION['FirstName'] = $profile['FirstName'];
        $_SESSION['LastName']  = $profile['LastName'];
        $_SESSION['email']     = $profile['email'];
        $_SESSION['role']      = $profile['role'];
    }
    $stmt->close();
}

// Latest seller request status for UI state
$sellerRequestStatus = null;
$stmt = $conn->prepare("SELECT status FROM seller_requests WHERE user_id = ? ORDER BY request_id DESC LIMIT 1");
if ($stmt) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->bind_result($srStatus);
    if ($stmt->fetch()) {
        $sellerRequestStatus = strtolower((string) $srStatus);
    }
    $stmt->close();
}
$roleLabel = match (strtolower($profile['role'])) {
    'both' => 'Seller/Buyer',
    'seller' => 'Seller',
    'admin' => 'Admin',
    default => 'Buyer',
};

$showToast    = false;
$toastMessage = '';
$toastType    = 'success';
$isEditMode   = false;

if (isset($_GET['seller_applied'])) {
    $showToast    = true;
    $toastMessage = 'Your seller application has been submitted! We will review it shortly.';
    $toastType    = 'success';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'edit') {
        $isEditMode = true;
    } elseif ($_POST['action'] === 'save') {
        $username  = trim($_POST['username']   ?? '');
        $firstname = trim($_POST['first_name'] ?? '');
        $lastname  = trim($_POST['last_name']  ?? '');
        $email     = trim($_POST['email']      ?? '');

        if (!empty($username) && !empty($firstname) && !empty($lastname) && !empty($email)) {
            $stmt = $conn->prepare("UPDATE users SET username = ?, FirstName = ?, LastName = ?, email = ? WHERE user_id = ?");
            $stmt->bind_param("ssssi", $username, $firstname, $lastname, $email, $userId);
            $stmt->execute();
            $stmt->close();

            $_SESSION['userName']  = $username;
            $_SESSION['firstname'] = $firstname;
            $_SESSION['lastname']  = $lastname;
            $_SESSION['email']     = $email;

            $profile['username']  = $username;
            $profile['FirstName'] = $firstname;
            $profile['LastName']  = $lastname;
            $profile['email']     = $email;

            $showToast    = true;
            $toastMessage = 'Profile updated successfully!';
            $toastType    = 'success';
        } else {
            $showToast    = true;
            $toastMessage = 'All fields are required.';
            $toastType    = 'danger';
            $isEditMode   = true;
        }
    } elseif ($_POST['action'] === 'cancel') {
        $isEditMode = false;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profile – Brewhub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../Style.css?v=20260423" rel="stylesheet">
</head>
<body class="dashboard-page">

    <nav class="navbar navbar-expand-md navbar-light fixed-top bh-navbar">
        <div class="container-fluid px-4 px-lg-5 bh-nav-container">
            <a class="navbar-brand bh-brand" href="Dashboard.php">Brewhub</a>

            <div class="d-flex align-items-center gap-2 order-md-3 bh-nav-actions">
                <a class="btn bh-icon-btn" href="Profile.php" aria-label="Profile">
                    <i class="bi bi-person"></i>
                </a>
                <a class="btn bh-icon-btn position-relative" href="Cart.php" aria-label="Cart">
                    <i class="bi bi-bag"></i>
                    <span class="bh-cart-count"><?php echo $cartCount; ?></span>
                </a>
                <button class="navbar-toggler border-0 shadow-none p-0 ms-1" type="button"
                    data-bs-toggle="collapse" data-bs-target="#navbarNav"
                    aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
            </div>

            <div class="collapse navbar-collapse justify-content-center order-md-2" id="navbarNav">
                <ul class="navbar-nav align-items-md-center gap-md-4 gap-lg-5 bh-nav-links">
                    <li class="nav-item">
                        <a class="nav-link" href="Dashboard.php">Home</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="productCategoriesDropdown"
                            role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Product Categories
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="productCategoriesDropdown">
                            <li><a class="dropdown-item" href="CoffeeIngredients.php">Coffee &amp; Ingredients</a></li>
                            <li><a class="dropdown-item" href="CupsPackaging.php">Cups &amp; Packaging</a></li>
                            <li><a class="dropdown-item" href="Equipments.php">Equipments</a></li>
                            <li><a class="dropdown-item" href="Pastry.php">Pastry</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="Orders.php">Orders</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="profile-page-main py-5">
        <div class="container profile-container">
            <div class="row justify-content-center">
                <div class="col-12 col-lg-9 col-xl-8">
                    <section class="card border-0 profile-card">
                        <div class="card-body p-4 p-md-5">
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3 mb-4">
                                <div>
                                    <p class="profile-kicker mb-2"><i class="bi bi-cup-hot me-2"></i>Brewhub Account</p>
                                    <h1 class="profile-title h3 mb-0">My Profile</h1>
                                </div>
                            </div>

                            <?php if ($isEditMode): ?>
                            <form method="POST" class="mb-4">
                                <input type="hidden" name="action" value="save">
                                <div class="profile-info-list mb-4">
                                    <div class="profile-info-item flex-column align-items-start">
                                        <label class="profile-info-label mb-2"><i class="bi bi-at me-2"></i>Username</label>
                                        <input type="text" name="username" class="form-control"
                                            value="<?php echo htmlspecialchars($profile['username'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                    </div>

                                    <div class="profile-info-item flex-column align-items-start">
                                        <label class="profile-info-label mb-2"><i class="bi bi-person-vcard me-2"></i>First Name</label>
                                        <input type="text" name="first_name" class="form-control"
                                            value="<?php echo htmlspecialchars($profile['FirstName'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                    </div>

                                    <div class="profile-info-item flex-column align-items-start">
                                        <label class="profile-info-label mb-2"><i class="bi bi-person-vcard me-2"></i>Last Name</label>
                                        <input type="text" name="last_name" class="form-control"
                                            value="<?php echo htmlspecialchars($profile['LastName'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                    </div>

                                    <div class="profile-info-item flex-column align-items-start">
                                        <label class="profile-info-label mb-2"><i class="bi bi-envelope me-2"></i>Email Address</label>
                                        <input type="email" name="email" class="form-control"
                                            value="<?php echo htmlspecialchars($profile['email'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                    </div>
                                </div>
                                <div class="d-flex flex-column flex-sm-row gap-3">
                                    <button type="submit" class="btn profile-btn profile-btn-edit">
                                        <i class="bi bi-check-circle me-2"></i>Save Changes
                                    </button>
                                    <button type="submit" name="action" value="cancel"
                                        class="btn profile-btn profile-btn-seller" style="background:#6c757d; border-color:#6c757d;">
                                        <i class="bi bi-x-circle me-2"></i>Cancel
                                    </button>
                                </div>
                            </form>

                            <?php else: ?>
                            <div class="profile-info-list mb-4">
                                <div class="profile-info-item">
                                    <div class="profile-info-label"><i class="bi bi-at me-2"></i>Username</div>
                                    <div class="profile-info-value">@<?php echo htmlspecialchars($profile['username'], ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>

                                <div class="profile-info-item">
                                    <div class="profile-info-label"><i class="bi bi-person-vcard me-2"></i>First Name</div>
                                    <div class="profile-info-value"><?php echo htmlspecialchars($profile['FirstName'], ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>

                                <div class="profile-info-item">
                                    <div class="profile-info-label"><i class="bi bi-person-vcard me-2"></i>Last Name</div>
                                    <div class="profile-info-value"><?php echo htmlspecialchars($profile['LastName'], ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>

                                <div class="profile-info-item">
                                    <div class="profile-info-label"><i class="bi bi-envelope me-2"></i>Email Address</div>
                                    <div class="profile-info-value"><?php echo htmlspecialchars($profile['email'], ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>

                                <div class="profile-info-item">
                                    <div class="profile-info-label"><i class="bi bi-shield me-2"></i>Role</div>
                                    <div class="profile-info-value"><?php echo htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                            </div>

                            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center gap-3">
                                <div class="d-flex flex-column flex-sm-row gap-3">
                                    <form method="POST" class="m-0">
                                        <input type="hidden" name="action" value="edit">
                                        <button type="submit" class="btn profile-btn profile-btn-edit">
                                            <i class="bi bi-pencil-square me-2"></i>Edit Profile
                                        </button>
                                    </form>
                                    <?php
                                        $roleLower = strtolower((string) ($profile['role'] ?? 'buyer'));
                                        if ($roleLower === 'both') {
                                            $roleLower = 'seller';
                                        }
                                        $hasSellerAccess = in_array($roleLower, ['seller', 'admin'], true);
                                    ?>

                                    <?php if ($hasSellerAccess): ?>
                                        <a class="btn profile-btn profile-btn-seller" href="../Seller/SellerDashboard.php">
                                            <i class="bi bi-shop me-2"></i>Seller Dashboard
                                        </a>
                                    <?php elseif ($sellerRequestStatus === 'pending'): ?>
                                        <button class="btn profile-btn profile-btn-seller" type="button" disabled aria-disabled="true">
                                            <i class="bi bi-hourglass-split me-2"></i>Seller Application Pending
                                        </button>
                                    <?php elseif ($sellerRequestStatus === 'rejected'): ?>
                                        <a class="btn profile-btn profile-btn-seller" href="../BecomeSeller.php">
                                            <i class="bi bi-arrow-repeat me-2"></i>Re-apply as Seller
                                        </a>
                                    <?php else: ?>
                                        <a class="btn profile-btn profile-btn-seller" href="../BecomeSeller.php">
                                            <i class="bi bi-shop me-2"></i>Become a Seller
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <a class="btn profile-btn profile-btn-edit" href="../Login.php">
                                    <i class="bi bi-box-arrow-right me-2"></i>Log out
                                </a>
                            </div>
                            <?php endif; ?>

                        </div>
                    </section>
                </div>
            </div>
        </div>
    </main>

    <footer class="bh-footer-bar px-4 px-lg-5 py-4 mt-5">
        <div class="container-fluid bh-footer-bar-container">
            <div class="bh-footer-bar-left">
                <div class="bh-footer-bar-logo-box">
                    <img src="../Assets/Brew_Hub.png" alt="Brewhub Logo" class="bh-footer-bar-logo">
                </div>
                <div class="bh-footer-bar-meta">
                    <div class="bh-footer-bar-copy">&copy; 2026 Brewhub</div>
                    <div class="bh-footer-bar-legal">
                        <a class="bh-footer-bar-legal-link" href="#">Terms</a>
                        <a class="bh-footer-bar-legal-link" href="#">Privacy</a>
                        <a class="bh-footer-bar-legal-link" href="#">Cookies</a>
                    </div>
                </div>
            </div>
            <nav class="bh-footer-bar-nav">
                <a class="bh-footer-bar-link" href="Dashboard.php">Home</a>
                <a class="bh-footer-bar-link" href="Orders.php">Orders</a>
                <a class="bh-footer-bar-link" href="CoffeeIngredients.php">Coffee &amp; Ingredients</a>
                <a class="bh-footer-bar-link" href="CupsPackaging.php">Cups &amp; Packaging</a>
                <a class="bh-footer-bar-link" href="Equipments.php">Equipments</a>
                <a class="bh-footer-bar-link" href="Pastry.php">Pastry</a>
            </nav>
        </div>
    </footer>

    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:9999;">
        <div id="profileToast" class="toast align-items-center text-white border-0" role="alert"
            style="background-color: <?php echo $toastType === 'success' ? '#2fc31f' : '#dc3545'; ?>;">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi <?php echo $toastType === 'success' ? 'bi-check-circle' : 'bi-exclamation-circle'; ?> me-2"></i>
                    <?php echo htmlspecialchars($toastMessage, ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php if ($showToast): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toast = new bootstrap.Toast(document.getElementById('profileToast'), {
                autohide: true,
                delay: 3000
            });
            toast.show();
        });
    </script>
    <?php endif; ?>

</body>
</html>
