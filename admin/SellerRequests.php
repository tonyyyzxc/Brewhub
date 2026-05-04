<?php
declare(strict_types=1);
session_start();
require '../config.php';

// Guard: admin only
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../Login.php');
    exit;
}

$toast = null;

// ── Handle approve / reject / delete ─────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = (string) ($_POST['action']     ?? '');
    $requestId = (int)    ($_POST['request_id'] ?? 0);

    if ($requestId <= 0) {
        $toast = ['type' => 'danger', 'text' => 'Invalid request ID.'];
    } elseif ($action === 'approve') {
        // Get user_id from request
        $row = $conn->query("SELECT user_id FROM seller_requests WHERE request_id = $requestId")->fetch_assoc();
        if ($row) {
            $uid = (int) $row['user_id'];
            $conn->query("UPDATE users SET role = 'seller' WHERE user_id = $uid");
            // Update request status
            $conn->query("UPDATE seller_requests SET status = 'approved' WHERE request_id = $requestId");
            $toast = ['type' => 'success', 'text' => 'Seller request approved successfully!'];
        } else {
            $toast = ['type' => 'danger', 'text' => 'Request not found.'];
        }
    } elseif ($action === 'reject') {
        $ok = $conn->query("UPDATE seller_requests SET status = 'rejected' WHERE request_id = $requestId");
        $toast = $ok
            ? ['type' => 'warning', 'text' => 'Seller request rejected.']
            : ['type' => 'danger', 'text' => 'Request not found.'];
    } elseif ($action === 'delete') {
        $ok = $conn->query("DELETE FROM seller_requests WHERE request_id = $requestId");
        $toast = $ok
            ? ['type' => 'success', 'text' => 'Request deleted successfully!']
            : ['type' => 'danger', 'text' => 'Request not found.'];
    }
}

//  Fetch all requests from DB
$requests = [];
$result = $conn->query("
    SELECT 
        sr.request_id,
        sr.shop_name,
        sr.description,
        sr.contact,
        sr.seller_type,
        sr.address,
        sr.status,
        sr.created_at,
        u.user_id,
        u.username,
        u.email,
        CONCAT(u.FirstName, ' ', u.LastName) AS full_name
    FROM seller_requests sr
    JOIN users u ON sr.user_id = u.user_id
    ORDER BY sr.created_at DESC
");
while ($row = $result->fetch_assoc()) {
    $requests[] = $row;
}

// Pending count for badge 
$pendingCount = 0;
foreach ($requests as $r) {
    if (strtolower($r['status']) === 'pending') $pendingCount++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Seller Requests – Brewhub Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../Style.css?v=20260420" rel="stylesheet">
</head>

<body class="admin-page admin-sidebar-layout d-flex flex-column min-vh-100">

    <!-- Topbar -->
    <nav class="admin-topbar">
        <div class="admin-topbar-container">
            <button class="admin-sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                <i class="bi bi-list"></i>
            </button>
            <a class="admin-topbar-brand" href="admin.php">Brewhub Admin</a>
            <div class="admin-topbar-actions">
                <a class="btn bh-icon-btn position-relative" href="#" aria-label="Notifications">
                    <i class="bi bi-bell"></i>
                    <span class="bh-cart-count" aria-hidden="true"><?php echo $pendingCount; ?></span>
                </a>
                <a class="btn bh-icon-btn" href="#" aria-label="Settings">
                    <i class="bi bi-gear"></i>
                </a>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="admin-sidebar-header">
            <a class="admin-sidebar-brand" href="admin.php">Brewhub</a>
        </div>
        <nav class="admin-sidebar-nav">
            <a class="admin-sidebar-link" href="admin.php">
                <i class="bi bi-speedometer2"></i><span>Dashboard</span>
            </a>
            <a class="admin-sidebar-link" href="UserManagement.php">
                <i class="bi bi-people"></i><span>User Management</span>
            </a>
            <a class="admin-sidebar-link active" href="SellerRequests.php">
                <i class="bi bi-shop"></i><span>Seller Requests</span>
            </a>
            <a class="admin-sidebar-link" href="Products.php">
                <i class="bi bi-box-seam"></i><span>Products</span>
            </a>
        </nav>
        <div class="admin-sidebar-footer">
            <a class="admin-sidebar-logout" href="../Login.php">
                <i class="bi bi-box-arrow-right"></i><span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main -->
    <main class="admin-main admin-main-with-sidebar">
        <section class="admin-dashboard py-4">
            <div class="container-fluid px-4 px-lg-5">
                <div class="admin-dashboard-header mb-4">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div>
                            <h2 class="admin-dashboard-title mb-1">Seller Requests</h2>
                            <p class="admin-dashboard-subtitle mb-0">Review and approve seller applications</p>
                        </div>
                        <div class="d-flex gap-2">
                            <div class="dropdown">
                                <button class="btn btn-sm dropdown-toggle" type="button"
                                    id="filterStatusDropdown" data-bs-toggle="dropdown"
                                    style="background:rgba(255,255,255,0.72);border:1px solid rgba(111,78,55,0.25);color:rgba(63,41,31,0.92);font-weight:600;border-radius:10px;padding:0.5rem 1rem;">
                                    <i class="bi bi-funnel me-1"></i>
                                    <span id="filterStatusText">All Status</span>
                                </button>
                                <ul class="dropdown-menu" aria-labelledby="filterStatusDropdown">
                                    <li><a class="dropdown-item" href="#" data-value="">All Status</a></li>
                                    <li><a class="dropdown-item" href="#" data-value="pending">Pending</a></li>
                                    <li><a class="dropdown-item" href="#" data-value="approved">Approved</a></li>
                                    <li><a class="dropdown-item" href="#" data-value="rejected">Rejected</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($toast): ?>
                    <div class="bh-toast-container position-fixed top-0 end-0 p-3">
                        <div id="bhToast" class="toast bh-toast bh-toast--<?php echo htmlspecialchars($toast['type'], ENT_QUOTES, 'UTF-8'); ?>" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3000">
                            <div class="toast-body d-flex align-items-start gap-2">
                                <?php if ($toast['type'] === 'danger'): ?>
                                    <i class="bi bi-exclamation-triangle-fill bh-toast-icon" aria-hidden="true"></i>
                                <?php elseif ($toast['type'] === 'warning'): ?>
                                    <i class="bi bi-exclamation-circle-fill bh-toast-icon" aria-hidden="true"></i>
                                <?php else: ?>
                                    <i class="bi bi-check2-circle bh-toast-icon" aria-hidden="true"></i>
                                <?php endif; ?>
                                <div class="bh-toast-text"><?php echo htmlspecialchars($toast['text'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <button type="button" class="btn-close ms-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-12">
                        <div class="admin-section-card">
                            <div class="admin-card-header">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="admin-card-icon"><i class="bi bi-shop"></i></span>
                                    <h3 class="admin-card-title mb-0">
                                        All Requests (<?php echo count($requests); ?>)
                                    </h3>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle admin-table mb-0" id="requestsTable">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Applicant</th>
                                            <th>Email</th>
                                            <th>Shop Name</th>
                                            <th>Type</th>
                                            <th>Description</th>
                                            <th>Submitted</th>
                                            <th>Status</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($requests)): ?>
                                            <tr>
                                                <td colspan="9" class="text-muted text-center py-4">
                                                    No seller requests found.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($requests as $r):
                                                $status = strtolower($r['status']);
                                            ?>
                                            <tr data-status="<?php echo $status; ?>">
                                                <td class="fw-semibold">#<?php echo $r['request_id']; ?></td>
                                                <td>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($r['full_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                    <div class="text-muted small">@<?php echo htmlspecialchars($r['username'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                </td>
                                                <td class="text-muted"><?php echo htmlspecialchars($r['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td class="text-muted"><?php echo htmlspecialchars($r['shop_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td class="text-muted"><?php echo htmlspecialchars($r['seller_type'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td class="text-muted" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                                    <?php echo htmlspecialchars($r['description'], ENT_QUOTES, 'UTF-8'); ?>
                                                </td>
                                                <td class="text-muted">
                                                    <?php echo $r['created_at'] ? date('M d, Y', strtotime($r['created_at'])) : '-'; ?>
                                                </td>
                                                <td>
                                                    <span class="admin-status <?php echo $status === 'pending' ? 'admin-status-muted' : ''; ?>">
                                                        <?php echo ucfirst($status); ?>
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <?php if ($status === 'pending'): ?>
                                                    <form method="POST" style="display:inline;"
                                                        onsubmit="return confirm('Approve this seller request?');">
                                                        <input type="hidden" name="action" value="approve">
                                                        <input type="hidden" name="request_id" value="<?php echo $r['request_id']; ?>">
                                                        <button type="submit" class="btn admin-btn admin-btn-primary btn-sm">
                                                            <i class="bi bi-check2-circle me-1"></i>Approve
                                                        </button>
                                                    </form>
                                                    <form method="POST" style="display:inline;"
                                                        onsubmit="return confirm('Reject this seller request?');">
                                                        <input type="hidden" name="action" value="reject">
                                                        <input type="hidden" name="request_id" value="<?php echo $r['request_id']; ?>">
                                                        <button type="submit" class="btn admin-btn admin-btn-ghost btn-sm">
                                                            <i class="bi bi-x-circle me-1"></i>Reject
                                                        </button>
                                                    </form>
                                                    <?php endif; ?>
                                                    <form method="POST" style="display:inline;"
                                                        onsubmit="return confirm('Delete this request?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="request_id" value="<?php echo $r['request_id']; ?>">
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

    <!-- Footer -->
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
        // Toast (success/error feedback)
        document.addEventListener('DOMContentLoaded', () => {
            const toastEl = document.getElementById('bhToast');
            if (toastEl && window.bootstrap && bootstrap.Toast) {
                new bootstrap.Toast(toastEl).show();
            }
        });

        // Sidebar toggle
        document.getElementById('sidebarToggle').addEventListener('click', () => {
            document.body.classList.toggle('admin-sidebar-collapsed');
        });

        // Filter by status
        document.querySelectorAll('#filterStatusDropdown + .dropdown-menu .dropdown-item').forEach(item => {
            item.addEventListener('click', function (e) {
                e.preventDefault();
                const filterValue = this.getAttribute('data-value').toLowerCase();
                document.getElementById('filterStatusText').textContent = this.textContent;
                document.querySelectorAll('#requestsTable tbody tr').forEach(row => {
                    const status = row.getAttribute('data-status') ?? '';
                    row.style.display = filterValue === '' || status === filterValue ? '' : 'none';
                });
            });
        });
    </script>
</body>
</html>
