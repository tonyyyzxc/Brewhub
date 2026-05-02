<?php
declare(strict_types=1);
session_start();
require '../config.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../Login.php");
    exit();
}

$message = '';

// Add Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_admin') {
    $username  = trim($_POST['username']   ?? '');
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name']  ?? '');
    $email     = trim($_POST['email']      ?? '');
    $password  = trim($_POST['password']   ?? '');

    if (empty($username) || empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
        $message = '<div class="alert alert-danger">All fields are required.</div>';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = '<div class="alert alert-danger">Invalid email address.</div>';
    } else {
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO users (FirstName, LastName, username, email, password, role) VALUES (?, ?, ?, ?, ?, 'admin')");
        $stmt->bind_param('sssss', $firstName, $lastName, $username, $email, $hashed);
        $ok = $stmt->execute();
        $stmt->close();
        $message = $ok
            ? '<div class="alert alert-success">Admin account created successfully!</div>'
            : '<div class="alert alert-danger">Failed. Email or username may already exist.</div>';
    }
}

// Delete User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $userId = (int) ($_POST['user_id'] ?? 0);
    if ($userId <= 0) {
        $message = '<div class="alert alert-danger">Invalid user ID.</div>';
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param('i', $userId);
        $ok = $stmt->execute();
        $stmt->close();
        $message = $ok
            ? '<div class="alert alert-success">User deleted successfully!</div>'
            : '<div class="alert alert-danger">User not found.</div>';
    }
}

// Fetch All Users
$users = [];
$result = $conn->query("
    SELECT 
        user_id,
        username,
        FirstName,
        LastName,
        CONCAT(FirstName, ' ', LastName) AS full_name,
        email,
        role,
        'Active' AS status,
        created_at
    FROM users
    ORDER BY user_id DESC
");
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User Management – Brewhub Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../Style.css?v=20260420" rel="stylesheet">
</head>
<body class="admin-page admin-sidebar-layout d-flex flex-column min-vh-100">

    <nav class="admin-topbar">
        <div class="admin-topbar-container">
            <button class="admin-sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                <i class="bi bi-list"></i>
            </button>
            <a class="admin-topbar-brand" href="admin.php">Brewhub Admin</a>
            <div class="admin-topbar-actions">
                <a class="btn bh-icon-btn position-relative" href="SellerRequests.php" aria-label="Notifications">
                    <i class="bi bi-bell"></i>
                </a>
                <a class="btn bh-icon-btn" href="#" aria-label="Settings"><i class="bi bi-gear"></i></a>
            </div>
        </div>
    </nav>

    <aside class="admin-sidebar" id="adminSidebar">
        <div class="admin-sidebar-header">
            <a class="admin-sidebar-brand" href="admin.php">Brewhub</a>
        </div>
        <nav class="admin-sidebar-nav">
            <a class="admin-sidebar-link" href="admin.php"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a>
            <a class="admin-sidebar-link active" href="UserManagement.php"><i class="bi bi-people"></i><span>User Management</span></a>
            <a class="admin-sidebar-link" href="SellerRequests.php"><i class="bi bi-shop"></i><span>Seller Requests</span></a>
            <a class="admin-sidebar-link" href="Products.php"><i class="bi bi-box-seam"></i><span>Products</span></a>
        </nav>
        <div class="admin-sidebar-footer">
            <a class="admin-sidebar-logout" href="../Login.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>
        </div>
    </aside>

    <main class="admin-main admin-main-with-sidebar">
        <section class="admin-dashboard py-4">
            <div class="container-fluid px-4 px-lg-5">
                <div class="admin-dashboard-header mb-4">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div>
                            <h2 class="admin-dashboard-title mb-1">User Management</h2>
                            <p class="admin-dashboard-subtitle mb-0">Manage all registered users</p>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn admin-btn admin-btn-primary btn-sm"
                                data-bs-toggle="modal" data-bs-target="#addAdminModal">
                                <i class="bi bi-person-plus me-1"></i>Add Admin
                            </button>
                            <input type="text" class="form-control form-control-sm" id="searchUsers"
                                placeholder="Search users..." style="max-width:230px;">
                        </div>
                    </div>
                </div>

                <?php echo $message; ?>

                <div class="row">
                    <div class="col-12">
                        <div class="admin-section-card">
                            <div class="admin-card-header">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="admin-card-icon"><i class="bi bi-people"></i></span>
                                    <h3 class="admin-card-title mb-0">All Users (<?php echo count($users); ?>)</h3>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle admin-table mb-0" id="usersTable">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Username</th>
                                            <th>Full Name</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Joined</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($users)): ?>
                                            <tr><td colspan="8" class="text-muted text-center py-4">No users found.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td class="fw-semibold">#<?php echo (int)$user['user_id']; ?></td>
                                                <td class="fw-semibold"><?php echo htmlspecialchars($user['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars($user['full_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td class="text-muted"><?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><span class="admin-badge"><?php echo htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                                <td><span class="admin-status"><?php echo htmlspecialchars($user['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                                <td class="text-muted"><?php echo !empty($user['created_at']) ? date('M d, Y', strtotime($user['created_at'])) : '-'; ?></td>
                                                <td class="text-end">
                                                    <form method="POST" style="display:inline;"
                                                        onsubmit="return confirm('Delete this user? This cannot be undone.');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="user_id" value="<?php echo (int)$user['user_id']; ?>">
                                                        <button type="submit" class="btn admin-btn admin-btn-danger btn-sm">
                                                            <i class="bi bi-trash3 me-1"></i>Remove
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Add Admin Modal -->
    <div class="modal fade" id="addAdminModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius:18px;border:1px solid rgba(111,78,55,.14);background:linear-gradient(155deg,#fffaf4 0%,#f8f0e4 100%);">
                <div class="modal-header" style="border-bottom:1px solid rgba(111,78,55,.14);">
                    <h5 class="modal-title fw-bold" style="color:#3f291f;"><i class="bi bi-person-plus me-2"></i>Add New Admin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add_admin">
                    <div class="modal-body" style="padding:1.5rem;">
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label" style="font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#7b5d48;">First Name</label>
                                <input type="text" name="first_name" class="form-control" required style="border:1px solid rgba(111,78,55,.24);border-radius:10px;">
                            </div>
                            <div class="col-6">
                                <label class="form-label" style="font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#7b5d48;">Last Name</label>
                                <input type="text" name="last_name" class="form-control" required style="border:1px solid rgba(111,78,55,.24);border-radius:10px;">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" style="font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#7b5d48;"><i class="bi bi-at me-1"></i>Username</label>
                            <input type="text" name="username" class="form-control" required style="border:1px solid rgba(111,78,55,.24);border-radius:10px;">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" style="font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#7b5d48;"><i class="bi bi-envelope me-1"></i>Email</label>
                            <input type="email" name="email" class="form-control" required style="border:1px solid rgba(111,78,55,.24);border-radius:10px;">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" style="font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#7b5d48;"><i class="bi bi-lock me-1"></i>Password</label>
                            <input type="password" name="password" class="form-control" required style="border:1px solid rgba(111,78,55,.24);border-radius:10px;">
                        </div>
                        <div class="alert alert-info border-0" style="background:rgba(150,75,0,.1);color:#7b5d48;font-size:.85rem;">
                            <i class="bi bi-info-circle me-2"></i>This creates an admin account with full access.
                        </div>
                    </div>
                    <div class="modal-footer" style="border-top:1px solid rgba(111,78,55,.14);">
                        <button type="button" class="btn admin-btn admin-btn-ghost btn-sm" data-bs-dismiss="modal"><i class="bi bi-x-circle me-1"></i>Cancel</button>
                        <button type="submit" class="btn admin-btn admin-btn-primary btn-sm"><i class="bi bi-check-circle me-1"></i>Create Admin</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <footer class="bh-footer-bar px-4 px-lg-5 py-4 mt-auto">
        <div class="container-fluid bh-footer-bar-container">
            <div class="bh-footer-bar-left">
                <div class="bh-footer-bar-logo-box">
                    <img src="../Assets/Brew_Hub.png" alt="Brewhub Logo" class="bh-footer-bar-logo">
                </div>
                <div class="bh-footer-bar-meta">
                    <div class="bh-footer-bar-copy">&copy; 2026 Brewhub Admin</div>
                    <div class="bh-footer-bar-legal">
                        <a class="bh-footer-bar-legal-link" href="#">Terms</a>
                        <a class="bh-footer-bar-legal-link" href="#">Privacy</a>
                        <a class="bh-footer-bar-legal-link" href="#">Cookies</a>
                    </div>
                </div>
            </div>
            <nav class="bh-footer-bar-nav">
                <a class="bh-footer-bar-link" href="admin.php">Dashboard</a>
                <a class="bh-footer-bar-link" href="UserManagement.php">User Management</a>
                <a class="bh-footer-bar-link" href="SellerRequests.php">Seller Requests</a>
                <a class="bh-footer-bar-link" href="Products.php">Products</a>
            </nav>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('sidebarToggle').addEventListener('click', () => {
            document.body.classList.toggle('admin-sidebar-collapsed');
        });
        document.getElementById('searchUsers').addEventListener('keyup', function () {
            const val = this.value.toLowerCase();
            document.querySelectorAll('#usersTable tbody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(val) ? '' : 'none';
            });
        });
    </script>
</body>
</html>