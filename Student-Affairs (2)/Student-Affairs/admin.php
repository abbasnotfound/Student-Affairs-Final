<?php
/**
 * Student Affairs - Staff & Admin Management Portal
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

start_secure_session();

$pdo = get_db_connection();

// Logout Action
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header('Location: admin.php?logged_out=1');
    exit;
}

$loginError = '';
$notice = '';
$noticeType = 'success';

// Handle Login Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $loginError = 'Invalid security token. Please refresh the page and try again.';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $loginError = 'Please enter both username and password.';
        } else {
            $stmt = $pdo->prepare('SELECT id, username, password_hash, role, full_name FROM admins WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id'] = (int)$admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_role'] = $admin['role'] ?? 'staff';
                $_SESSION['admin_full_name'] = $admin['full_name'] ?: $admin['username'];
                header('Location: admin.php');
                exit;
            } else {
                $loginError = 'Incorrect username or password.';
            }
        }
    }
}

// -------------------------------------------------------------------------
// 1. RENDER LOGIN VIEW IF NOT LOGGED IN
// -------------------------------------------------------------------------
if (!is_authenticated()):
    $csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Staff Login | Student Affairs</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="admin.css">
</head>
<body class="admin-body">
  <header class="site-header">
    <a class="brand" href="index.html">
      <span class="brand-mark">SA</span>
      <span>
        <strong>Student Affairs</strong>
        <small>Staff Administration</small>
      </span>
    </a>
    <a class="btn secondary" href="index.html">← Student Portal</a>
  </header>

  <main class="admin-wrap">
    <section class="admin-login">
      <span class="eyebrow">RESTRICTED ACCESS</span>
      <h1>Staff login</h1>
      <p>Sign in to review student complaints, assign tickets, and update progress.</p>

      <?php if (!empty($_GET['logged_out'])): ?>
        <p class="admin-success">You have been safely signed out.</p>
      <?php endif; ?>

      <?php if ($loginError): ?>
        <p class="admin-alert"><?php echo htmlspecialchars($loginError); ?></p>
      <?php endif; ?>

      <form method="post" action="admin.php" class="admin-form">
        <input type="hidden" name="login" value="1">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">

        <label>
          Username
          <input name="username" required autocomplete="username" placeholder="Username (e.g. admin or staff)">
        </label>

        <label>
          Password
          <input type="password" name="password" required autocomplete="current-password" placeholder="Password">
        </label>

        <button class="btn primary" type="submit">Sign in <b>→</b></button>
      </form>

      <div class="admin-hint">
        <strong>Default local credentials for review:</strong><br>
        • Administrator: <code>admin</code> / <code>ChangeMe123!</code><br>
        • Staff Officer: <code>staff</code> / <code>Staff@12345</code>
      </div>
    </section>
  </main>
</body>
</html>
<?php
exit;
endif;

// -------------------------------------------------------------------------
// 2. AUTHENTICATED ACTIONS (Complaint Update & Staff Management)
// -------------------------------------------------------------------------
$currentUser = current_user();
$isAdmin = ($currentUser['role'] === 'admin');

// A. Handle Complaint Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_complaint'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $notice = 'Security verification failed. Please try again.';
        $noticeType = 'error';
    } else {
        $complaintId = (int)($_POST['complaint_id'] ?? 0);
        $newStatus = trim((string)($_POST['status'] ?? ''));
        $adminNote = trim((string)($_POST['admin_note'] ?? ''));
        $assignedTo = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;

        $allowedStatuses = ['Pending', 'In Progress', 'Resolved', 'Rejected'];

        if (in_array($newStatus, $allowedStatuses, true) && $complaintId > 0) {
            // Get current complaint status for logging
            $currStmt = $pdo->prepare('SELECT status FROM complaints WHERE id = ? LIMIT 1');
            $currStmt->execute([$complaintId]);
            $currentComplaint = $currStmt->fetch();

            if ($currentComplaint) {
                $oldStatus = $currentComplaint['status'];

                $updateStmt = $pdo->prepare('
                    UPDATE complaints
                    SET status = ?, admin_note = ?, assigned_to = ?
                    WHERE id = ?
                ');
                $updateStmt->execute([$newStatus, $adminNote ?: null, $assignedTo, $complaintId]);

                // Record status change in logs
                $logStmt = $pdo->prepare('
                    INSERT INTO complaint_logs (complaint_id, changed_by, old_status, new_status, note)
                    VALUES (?, ?, ?, ?, ?)
                ');
                $logStmt->execute([
                    $complaintId,
                    $currentUser['id'],
                    $oldStatus,
                    $newStatus,
                    $adminNote ? "Status set to {$newStatus}. Note: {$adminNote}" : "Status set to {$newStatus}."
                ]);

                $notice = "Complaint #{$complaintId} has been successfully updated.";
            } else {
                $notice = 'Complaint record not found.';
                $noticeType = 'error';
            }
        } else {
            $notice = 'Invalid status selected.';
            $noticeType = 'error';
        }
    }
}

// B. Handle New Staff Account Creation (Admins only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_staff'])) {
    if (!$isAdmin) {
        $notice = 'Only administrators can create staff accounts.';
        $noticeType = 'error';
    } elseif (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $notice = 'Security verification failed.';
        $noticeType = 'error';
    } else {
        $newUsername = trim((string)($_POST['new_username'] ?? ''));
        $newFullName = trim((string)($_POST['new_fullname'] ?? ''));
        $newRole = trim((string)($_POST['new_role'] ?? 'staff'));
        $newPassword = (string)($_POST['new_password'] ?? '');

        if ($newUsername === '' || strlen($newPassword) < 6) {
            $notice = 'Username is required and password must be at least 6 characters.';
            $noticeType = 'error';
        } elseif (!in_array($newRole, ['admin', 'staff'], true)) {
            $notice = 'Invalid role specified.';
            $noticeType = 'error';
        } else {
            $checkUser = $pdo->prepare('SELECT id FROM admins WHERE username = ? LIMIT 1');
            $checkUser->execute([$newUsername]);
            if ($checkUser->fetch()) {
                $notice = "The username '{$newUsername}' is already taken.";
                $noticeType = 'error';
            } else {
                $insertStmt = $pdo->prepare('INSERT INTO admins (username, password_hash, role, full_name) VALUES (?, ?, ?, ?)');
                $insertStmt->execute([
                    $newUsername,
                    password_hash($newPassword, PASSWORD_DEFAULT),
                    $newRole,
                    $newFullName
                ]);
                $notice = "Account for '{$newUsername}' created successfully!";
            }
        }
    }
}

// -------------------------------------------------------------------------
// 3. FETCH COMPLAINTS & COUNTERS
// -------------------------------------------------------------------------
$allowedFilters = ['Pending', 'In Progress', 'Resolved', 'Rejected'];
$statusFilter = trim((string)($_GET['status'] ?? ''));
$searchQuery = trim((string)($_GET['q'] ?? ''));

$sql = 'SELECT c.*, a.full_name AS assigned_name, a.username AS assigned_username
        FROM complaints c
        LEFT JOIN admins a ON c.assigned_to = a.id
        WHERE 1=1';
$params = [];

if (in_array($statusFilter, $allowedFilters, true)) {
    $sql .= ' AND c.status = ?';
    $params[] = $statusFilter;
}

if ($searchQuery !== '') {
    $sql .= ' AND (c.tracking_code LIKE ? OR c.student_name LIKE ? OR c.student_class LIKE ? OR c.subject LIKE ? OR c.description LIKE ?)';
    $wildcard = "%{$searchQuery}%";
    $params[] = $wildcard;
    $params[] = $wildcard;
    $params[] = $wildcard;
    $params[] = $wildcard;
    $params[] = $wildcard;
}

$sql .= ' ORDER BY c.created_at DESC LIMIT 100';

$complaintsStmt = $pdo->prepare($sql);
$complaintsStmt->execute($params);
$complaints = $complaintsStmt->fetchAll();

// Stats counters
$total = (int)$pdo->query('SELECT COUNT(*) FROM complaints')->fetchColumn();
$pending = (int)$pdo->query("SELECT COUNT(*) FROM complaints WHERE status = 'Pending'")->fetchColumn();
$progress = (int)$pdo->query("SELECT COUNT(*) FROM complaints WHERE status = 'In Progress'")->fetchColumn();
$resolved = (int)$pdo->query("SELECT COUNT(*) FROM complaints WHERE status = 'Resolved'")->fetchColumn();

// Staff list for assigning
$staffList = $pdo->query('SELECT id, username, full_name, role FROM admins ORDER BY full_name ASC, username ASC')->fetchAll();

$csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Dashboard | Student Affairs</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="admin.css">
</head>
<body class="admin-body">
  <header class="site-header">
    <a class="brand" href="admin.php">
      <span class="brand-mark">SA</span>
      <span>
        <strong>Student Affairs</strong>
        <small>Complaint Management</small>
      </span>
    </a>
    <div class="admin-header-right">
      <span>
        Signed in as <strong><?php echo htmlspecialchars($currentUser['full_name']); ?></strong>
        <span class="badge-role <?php echo $currentUser['role'] === 'admin' ? 'admin' : ''; ?>">
          <?php echo htmlspecialchars(strtoupper($currentUser['role'])); ?>
        </span>
      </span>
      <a class="btn secondary" href="admin.php?logout=1">Log out</a>
    </div>
  </header>

  <main class="admin-dashboard">
    <div class="admin-title">
      <div>
        <span class="eyebrow">PORTAL DASHBOARD</span>
        <h1>Complaints Management</h1>
        <p>Review student feedback, assign investigation responsibilities, and update resolution status.</p>
      </div>
      <a class="btn secondary" href="index.html" target="_blank" rel="noopener">View Student Portal ↗</a>
    </div>

    <?php if ($notice): ?>
      <p class="<?php echo $noticeType === 'error' ? 'admin-alert' : 'admin-success'; ?>">
        <?php echo htmlspecialchars($notice); ?>
      </p>
    <?php endif; ?>

    <!-- Overview Counters -->
    <div class="stats-grid">
      <div class="stat-card">
        <span>Total Complaints</span>
        <strong><?php echo $total; ?></strong>
      </div>
      <div class="stat-card">
        <span>Pending Review</span>
        <strong><?php echo $pending; ?></strong>
      </div>
      <div class="stat-card">
        <span>In Progress</span>
        <strong><?php echo $progress; ?></strong>
      </div>
      <div class="stat-card">
        <span>Resolved</span>
        <strong><?php echo $resolved; ?></strong>
      </div>
    </div>

    <!-- Search & Filter Controls -->
    <div class="search-filter-bar">
      <div class="filters">
        <a href="admin.php<?php echo $searchQuery ? '?q=' . urlencode($searchQuery) : ''; ?>"
           class="<?php echo $statusFilter === '' ? 'active' : ''; ?>">All (<?php echo $total; ?>)</a>
        <?php foreach ($allowedFilters as $s): ?>
          <a href="admin.php?status=<?php echo urlencode($s); ?><?php echo $searchQuery ? '&q=' . urlencode($searchQuery) : ''; ?>"
             class="<?php echo $statusFilter === $s ? 'active' : ''; ?>">
            <?php echo htmlspecialchars($s); ?>
          </a>
        <?php endforeach; ?>
      </div>

      <form method="get" action="admin.php" class="search-form">
        <?php if ($statusFilter): ?>
          <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
        <?php endif; ?>
        <input type="text" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Search tracking code, student, keyword...">
        <button class="btn secondary" type="submit">Search</button>
        <?php if ($searchQuery || $statusFilter): ?>
          <a class="btn secondary" href="admin.php">Reset</a>
        <?php endif; ?>
      </form>
    </div>

    <!-- Complaints Listing -->
    <div class="complaints-heading">
      <h2>
        Submissions
        <?php if ($statusFilter): ?> · <span style="font-weight:normal;color:var(--muted);"><?php echo htmlspecialchars($statusFilter); ?></span><?php endif; ?>
        <?php if ($searchQuery): ?> · <span style="font-weight:normal;font-size:14px;color:var(--muted);">matching "<?php echo htmlspecialchars($searchQuery); ?>"</span><?php endif; ?>
      </h2>
    </div>

    <?php if (empty($complaints)): ?>
      <div class="empty-state">
        <p>No complaints found matching your current filter.</p>
      </div>
    <?php else: ?>
      <div class="complaint-list">
        <?php foreach ($complaints as $c): ?>
          <article class="complaint-card">
            <div class="complaint-top">
              <div>
                <span class="tracking">Tracking: <?php echo htmlspecialchars($c['tracking_code']); ?></span>
                <h3><?php echo htmlspecialchars($c['subject']); ?></h3>
                <p class="complaint-student-info">
                  Student: <strong><?php echo htmlspecialchars($c['student_name']); ?></strong> ·
                  Class: <strong><?php echo htmlspecialchars($c['student_class']); ?></strong>
                  <?php if (!empty($c['section_name'])): ?>
                    · Section: <strong><?php echo htmlspecialchars($c['section_name']); ?></strong>
                  <?php endif; ?>
                  · Email: <a href="mailto:<?php echo htmlspecialchars($c['email']); ?>"><?php echo htmlspecialchars($c['email']); ?></a>
                </p>
              </div>

              <?php
                $statusClass = match($c['status']) {
                  'In Progress' => 'in-progress',
                  'Resolved'    => 'resolved',
                  'Rejected'    => 'rejected',
                  default       => ''
                };
              ?>
              <span class="status-pill <?php echo $statusClass; ?>">
                <?php echo htmlspecialchars($c['status']); ?>
              </span>
            </div>

            <div class="complaint-meta">
              <span>Category: <strong><?php echo htmlspecialchars($c['category']); ?></strong></span>
              <span>Submitted: <?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($c['created_at']))); ?></span>
              <?php if (!empty($c['assigned_name'])): ?>
                <span>Assigned to: <strong><?php echo htmlspecialchars($c['assigned_name']); ?></strong></span>
              <?php endif; ?>
            </div>

            <p class="complaint-description">
              <?php echo nl2br(htmlspecialchars($c['description'])); ?>
            </p>

            <?php if (!empty($c['admin_note'])): ?>
              <div class="admin-note-box">
                <strong>Current Staff Response:</strong> <?php echo htmlspecialchars($c['admin_note']); ?>
              </div>
            <?php endif; ?>

            <!-- Action / Status Update Form -->
            <form method="post" action="admin.php<?php echo $statusFilter ? '?status=' . urlencode($statusFilter) : ''; ?>" class="update-form">
              <input type="hidden" name="update_complaint" value="1">
              <input type="hidden" name="complaint_id" value="<?php echo (int)$c['id']; ?>">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">

              <label>
                Update Status
                <select name="status">
                  <?php foreach ($allowedFilters as $st): ?>
                    <option value="<?php echo htmlspecialchars($st); ?>" <?php echo $c['status'] === $st ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($st); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>

              <label>
                Staff Response / Progress Note (visible to student)
                <input name="admin_note" maxlength="2000"
                       value="<?php echo htmlspecialchars($c['admin_note'] ?? ''); ?>"
                       placeholder="e.g. Investigation underway by Department Chair.">
              </label>

              <button class="btn primary" type="submit">Save Update</button>
            </form>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- User / Staff Management Section (Admin Only) -->
    <?php if ($isAdmin): ?>
      <section class="admin-section">
        <h2>Team & Staff Management</h2>
        <p style="font-size:13px;color:var(--muted);margin-top:0;">
          Create accounts for faculty or staff members handling student complaints.
        </p>

        <div class="staff-grid">
          <div>
            <h3 style="font-size:15px;margin-bottom:12px;color:var(--navy);">Existing Staff & Admins</h3>
            <table class="staff-table">
              <thead>
                <tr>
                  <th>Username</th>
                  <th>Full Name</th>
                  <th>Role</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($staffList as $st): ?>
                  <tr>
                    <td><strong><?php echo htmlspecialchars($st['username']); ?></strong></td>
                    <td><?php echo htmlspecialchars($st['full_name']); ?></td>
                    <td>
                      <span class="badge-role <?php echo $st['role'] === 'admin' ? 'admin' : ''; ?>">
                        <?php echo htmlspecialchars(strtoupper($st['role'])); ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div>
            <h3 style="font-size:15px;margin-bottom:12px;color:var(--navy);">Create New Team Member</h3>
            <form method="post" action="admin.php" class="staff-form" style="display:grid;gap:12px;">
              <input type="hidden" name="create_staff" value="1">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">

              <label>
                Full Name
                <input name="new_fullname" required placeholder="e.g. Prof. David Miller">
              </label>

              <label>
                Username
                <input name="new_username" required placeholder="e.g. dmiller">
              </label>

              <label>
                Role
                <select name="new_role">
                  <option value="staff">Staff (Complaint Handler)</option>
                  <option value="admin">Administrator (Full Access)</option>
                </select>
              </label>

              <label>
                Password
                <input type="password" name="new_password" required minlength="6" placeholder="Minimum 6 characters">
              </label>

              <button class="btn primary" type="submit" style="justify-self:start;margin-top:6px;">Create Account</button>
            </form>
          </div>
        </div>
      </section>
    <?php endif; ?>

  </main>
</body>
</html>
