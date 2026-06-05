<?php
// admin/users.php

// ── 1. KIỂM TRA PHÂN QUYỀN VÀ LOGIC XỬ LÝ (ĐẶT Ở TRÊN CÙNG) ──────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 1) {
    header('Location: /bookstore/index.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

$currentUserId = (int) $_SESSION['user_id'];

// ════════════════════════════════════════════════════════════
// XỬ LÝ CÁC ACTION (chỉ nhận POST)
// ════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action   = $_POST['action']  ?? '';
    $targetId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);

    if (!$targetId || $targetId <= 0) {
        header('Location: /bookstore/admin/users.php?msg=invalid');
        exit;
    }

    // ── ACTION: ĐẢO NGƯỢC ROLE (cấp quyền / hạ quyền) 
    if ($action === 'toggle_role') {

        // Không cho Admin tự hạ quyền chính mình
        if ($targetId === $currentUserId) {
            header('Location: /bookstore/admin/users.php?msg=self_role');
            exit;
        }

        // Lấy role hiện tại của user đó
        $stmtGet = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
        $stmtGet->execute([$targetId]);
        $targetUser = $stmtGet->fetch();

        if (!$targetUser) {
            header('Location: /bookstore/admin/users.php?msg=notfound');
            exit;
        }

        // Đảo ngược: 0 → 1, 1 → 0
        $newRole = ($targetUser['role'] == 1) ? 0 : 1;
        $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")
            ->execute([$newRole, $targetId]);

        $msgKey = ($newRole === 1) ? 'promoted' : 'demoted';
        header("Location: /bookstore/admin/users.php?msg={$msgKey}");
        exit;
    }

    // ── ACTION: XÓA USER
    if ($action === 'delete') {

        // Bảo vệ: không cho Admin tự xóa chính mình
        if ($targetId === $currentUserId) {
            header('Location: /bookstore/admin/users.php?msg=self_delete');
            exit;
        }

        // Kiểm tra user tồn tại
        $stmtCheck = $pdo->prepare("SELECT id, role FROM users WHERE id = ? LIMIT 1");
        $stmtCheck->execute([$targetId]);
        $userToDelete = $stmtCheck->fetch();

        if (!$userToDelete) {
            header('Location: /bookstore/admin/users.php?msg=notfound');
            exit;
        }

        // Dùng transaction: xóa dữ liệu liên quan trước, rồi mới xóa user
        // tránh lỗi foreign key nếu DB có constraint
        try {
            $pdo->beginTransaction();

            // Xóa giỏ hàng của user
            $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$targetId]);

            // Xóa user
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$targetId]);

            // Lưu ý: orders giữ lại để bảo toàn lịch sử đơn hàng
            $pdo->commit();
            header('Location: /bookstore/admin/users.php?msg=deleted');

        } catch (Exception $e) {
            $pdo->rollBack();
            header('Location: /bookstore/admin/users.php?msg=error');
        }
        exit;
    }
}

// ── BỘ LỌC + TÌM KIẾM ────────────────────────────────────────────────────────
$filterRole = $_GET['role']   ?? '';   // '' | '0' | '1'
$search     = trim($_GET['search'] ?? '');

// ── PHÂN TRANG ────────────────────────────────────────────────────────────────
$perPage     = 15;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($currentPage - 1) * $perPage;

// Xây WHERE động
$whereParts  = [];
$whereParams = [];

if ($filterRole !== '' && in_array($filterRole, ['0', '1'])) {
    $whereParts[]  = "role = ?";
    $whereParams[] = (int)$filterRole;
}
if ($search !== '') {
    $whereParts[]  = "(username LIKE ? OR fullname LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $whereParams[] = "%$search%";
    $whereParams[] = "%$search%";
    $whereParams[] = "%$search%";
    $whereParams[] = "%$search%";
}
$whereSQL = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

// Đếm tổng
$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM users $whereSQL");
$stmtTotal->execute($whereParams);
$totalUsers = (int) $stmtTotal->fetchColumn();
$totalPages = (int) ceil($totalUsers / $perPage);

// Query danh sách users
$stmtUsers = $pdo->prepare("
    SELECT id, username, fullname, email, phone, role, created_at
    FROM   users
    $whereSQL
    ORDER BY id DESC
    LIMIT ? OFFSET ?
");
$stmtUsers->execute([...$whereParams, $perPage, $offset]);
$users = $stmtUsers->fetchAll();

// Thống kê nhanh
$totalAll    = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalAdmins = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 1")->fetchColumn();
$totalNormal = $totalAll - $totalAdmins;

// ── MAP THÔNG BÁO ─────────────────────────────────────────────────────────────
$msgMap = [
    'promoted'    => ['type' => 'success', 'text' => '✅ Đã cấp quyền Admin cho tài khoản.'],
    'demoted'     => ['type' => 'warning', 'text' => '⬇️ Đã hạ quyền tài khoản xuống User.'],
    'deleted'     => ['type' => 'danger',  'text' => '🗑️ Đã xóa tài khoản khỏi hệ thống.'],
    'self_delete' => ['type' => 'danger',  'text' => '⛔ Không thể tự xóa tài khoản đang đăng nhập!'],
    'self_role'   => ['type' => 'danger',  'text' => '⛔ Không thể tự thay đổi quyền của tài khoản đang đăng nhập!'],
    'notfound'    => ['type' => 'warning', 'text' => '⚠️ Không tìm thấy tài khoản này.'],
    'invalid'     => ['type' => 'danger',  'text' => '❌ Dữ liệu không hợp lệ.'],
    'error'       => ['type' => 'danger',  'text' => '❌ Có lỗi xảy ra. Vui lòng thử lại.'],
];
$msg = $msgMap[$_GET['msg'] ?? ''] ?? null;

// ── 2. GỌI HEADER ADMIN ───────────────────────────────────────────────────────
require_once __DIR__ . '/../includes/admin_header.php';
?>

<style>
    /* Avatar chữ cái */
    .user-avatar {
        width: 36px; height: 36px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: .85rem; flex-shrink: 0;
    }
    /* Highlight hàng của admin đang đăng nhập */
    tr.current-user-row { background-color: #fff8e1 !important; }
</style>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msg['type'] ?> alert-dismissible fade show shadow-sm d-flex align-items-center gap-2">
        <span><?= $msg['text'] ?></span>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body d-flex align-items-center gap-3 p-4">
                <div class="rounded-circle bg-secondary bg-opacity-15 d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
                    <i class="bi bi-people-fill text-secondary fs-5"></i>
                </div>
                <div>
                    <div class="fw-bold fs-3"><?= number_format($totalAll) ?></div>
                    <div class="text-muted small">Tổng thành viên</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-4">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body d-flex align-items-center gap-3 p-4">
                <div class="rounded-circle bg-danger bg-opacity-15 d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
                    <i class="bi bi-shield-fill-check text-danger fs-5"></i>
                </div>
                <div>
                    <div class="fw-bold fs-3"><?= number_format($totalAdmins) ?></div>
                    <div class="text-muted small">Quản trị viên</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-4">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body d-flex align-items-center gap-3 p-4">
                <div class="rounded-circle bg-primary bg-opacity-15 d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
                    <i class="bi bi-person-fill text-primary fs-5"></i>
                </div>
                <div>
                    <div class="fw-bold fs-3"><?= number_format($totalNormal) ?></div>
                    <div class="text-muted small">Người dùng</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3 px-4">
        <form method="GET" action="" class="d-flex gap-2 align-items-center flex-wrap">
            <div class="input-group" style="max-width:340px;">
                <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="search" class="form-control" placeholder="Tìm username, họ tên, email, SĐT..." value="<?= htmlspecialchars($search) ?>">
            </div>

            <select name="role" class="form-select" style="max-width:160px;">
                <option value=""  <?= $filterRole === ''  ? 'selected' : '' ?>>Tất cả vai trò</option>
                <option value="1" <?= $filterRole === '1' ? 'selected' : '' ?>>Quản trị viên</option>
                <option value="0" <?= $filterRole === '0' ? 'selected' : '' ?>>Người dùng</option>
            </select>

            <button type="submit" class="btn btn-warning fw-semibold">
                <i class="bi bi-funnel me-1"></i>Lọc
            </button>

            <?php if ($search || $filterRole !== ''): ?>
                <a href="/bookstore/admin/users.php" class="btn btn-outline-secondary">
                    <i class="bi bi-x me-1"></i>Xóa lọc
                </a>
            <?php endif; ?>

            <span class="text-muted small ms-auto">Tìm thấy <strong><?= $totalUsers ?></strong> tài khoản</span>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
        <h6 class="fw-bold mb-0">
            Danh sách tài khoản
            <span class="badge bg-secondary ms-1"><?= $totalUsers ?></span>
        </h6>
        <span class="text-muted small d-none d-md-block">
            <i class="bi bi-info-circle me-1"></i>Tài khoản đang đăng nhập được đánh dấu <span class="text-warning fw-semibold">màu vàng</span>
        </span>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table admin-table align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4" style="width:60px;">ID</th>
                        <th>Tài khoản</th>
                        <th>Email</th>
                        <th style="width:130px;">Số điện thoại</th>
                        <th class="text-center" style="width:120px;">Vai trò</th>
                        <th style="width:130px;">Ngày đăng ký</th>
                        <th class="text-center pe-4" style="width:160px;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>Không có tài khoản nào phù hợp.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $user):
                        $isSelf    = ($user['id'] == $currentUserId);
                        $isAdmin   = ($user['role'] == 1);
                        // Màu avatar ngẫu nhiên theo id
                        $avatarColors = ['#ffc107','#0d6efd','#198754','#dc3545','#6f42c1','#0dcaf0','#fd7e14'];
                        $avatarBg  = $avatarColors[$user['id'] % count($avatarColors)];
                        $avatarFg  = in_array($avatarBg, ['#ffc107','#0dcaf0']) ? '#000' : '#fff';
                        $initial   = mb_strtoupper(mb_substr($user['fullname'] ?: $user['username'], 0, 1));
                    ?>
                    <tr class="<?= $isSelf ? 'current-user-row' : '' ?>">
                        <td class="ps-4 text-muted small fw-semibold">#<?= $user['id'] ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="user-avatar" style="background:<?= $avatarBg ?>;color:<?= $avatarFg ?>;">
                                    <?= $initial ?>
                                </div>
                                <div>
                                    <p class="fw-semibold small mb-0">
                                        <?= htmlspecialchars($user['username']) ?>
                                        <?php if ($isSelf): ?>
                                            <span class="badge bg-warning text-dark ms-1" style="font-size:.65rem;">Bạn</span>
                                        <?php endif; ?>
                                    </p>
                                    <p class="text-muted mb-0" style="font-size:.78rem;">
                                        <?= htmlspecialchars($user['fullname'] ?: '—') ?>
                                    </p>
                                </div>
                            </div>
                        </td>
                        <td class="small">
                            <a href="mailto:<?= htmlspecialchars($user['email']) ?>" class="text-dark text-decoration-none">
                                <?= htmlspecialchars($user['email'] ?: '—') ?>
                            </a>
                        </td>
                        <td class="small text-muted"><?= htmlspecialchars($user['phone'] ?: '—') ?></td>
                        <td class="text-center">
                            <?php if ($isAdmin): ?>
                                <span class="badge bg-danger px-3 py-2 rounded-pill"><i class="bi bi-shield-fill-check me-1"></i>Admin</span>
                            <?php else: ?>
                                <span class="badge bg-primary px-3 py-2 rounded-pill"><i class="bi bi-person-fill me-1"></i>User</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted">
                            <?php if (!empty($user['created_at'])): ?>
                                <?= date('d/m/Y', strtotime($user['created_at'])) ?><br>
                                <span style="font-size:.72rem;"><?= date('H:i', strtotime($user['created_at'])) ?></span>
                            <?php else: ?>
                                <span class="fst-italic">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center pe-4">
                            <div class="d-flex justify-content-center gap-2">
                                <form method="POST" action="/bookstore/admin/users.php" class="d-inline"
                                      onsubmit="return confirm('<?= $isAdmin ? 'Hạ quyền tài khoản này xuống User?' : 'Cấp quyền Admin cho tài khoản này?' ?>')">
                                    <input type="hidden" name="action" value="toggle_role">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" class="btn btn-sm <?= $isAdmin ? 'btn-outline-warning' : 'btn-outline-success' ?>"
                                            title="<?= $isAdmin ? 'Hạ xuống User' : 'Cấp quyền Admin' ?>" <?= $isSelf ? 'disabled' : '' ?>>
                                        <i class="bi bi-<?= $isAdmin ? 'arrow-down-circle' : 'arrow-up-circle' ?>"></i> <?= $isAdmin ? 'Hạ quyền' : 'Cấp quyền' ?>
                                    </button>
                                </form>

                                <form method="POST" action="/bookstore/admin/users.php" class="d-inline"
                                      onsubmit="return confirm('Xóa tài khoản «<?= addslashes($user['username']) ?>»?\nHành động này không thể hoàn tác!')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                            title="<?= $isSelf ? 'Không thể tự xóa tài khoản đang đăng nhập' : 'Xóa tài khoản' ?>" <?= $isSelf ? 'disabled' : '' ?>>
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </form>
                            </div>
                            <?php if ($isSelf): ?>
                                <small class="text-warning d-block mt-1" style="font-size:.68rem;"><i class="bi bi-lock-fill me-1"></i>Tài khoản của bạn</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-between align-items-center px-4 py-3 border-top">
            <p class="text-muted small mb-0">Hiển thị <?= $offset + 1 ?>–<?= min($offset + $perPage, $totalUsers) ?> trong <?= $totalUsers ?> tài khoản</p>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $currentPage - 1 ?>&role=<?= urlencode($filterRole) ?>&search=<?= urlencode($search) ?>"><i class="bi bi-chevron-left"></i></a>
                    </li>
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $p ?>&role=<?= urlencode($filterRole) ?>&search=<?= urlencode($search) ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $currentPage + 1 ?>&role=<?= urlencode($filterRole) ?>&search=<?= urlencode($search) ?>"><i class="bi bi-chevron-right"></i></a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php
// ── 4. GỌI FOOTER ADMIN ───────────────────────────────────────────────────────
require_once __DIR__ . '/../includes/admin_footer.php';
?>