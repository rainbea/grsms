<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$staff     = $_SESSION['staff'] ?? null;
$staffName = $staff['full_name'] ?? 'Guest Staff';
$staffRole = $staff['role'] ?? 'Not signed in';
$staffSpec = $staff['specialization'] ?? null;
$current   = basename($_SERVER['PHP_SELF']);

$allNavItems = [
    'repair_requests.php'   => 'Repair requests',
    'new_request.php'       => 'New request',
    'customer_gadgets.php' => 'Customers',
    'service_catalog.php'   => 'Services',
    'invoices.php'          => 'Invoices',
    'staff_accounts.php'    => 'Staff',
];

// Role-based tab visibility
$roleAccess = [
    'Admin'        => ['repair_requests.php', 'new_request.php', 'customer_gadgets.php', 'service_catalog.php', 'invoices.php', 'staff_accounts.php'],
    'Manager'      => ['repair_requests.php', 'new_request.php', 'customer_gadgets.php', 'service_catalog.php', 'invoices.php', 'staff_accounts.php'],
    'Receptionist' => ['repair_requests.php', 'new_request.php', 'customer_gadgets.php', 'service_catalog.php', 'invoices.php'],
    'Technician'   => ['repair_requests.php', 'customer_gadgets.php', 'service_catalog.php'],
    'Cashier'      => ['repair_requests.php', 'invoices.php'],
];

$allowedHrefs = $roleAccess[$staffRole] ?? array_keys($allNavItems);
$navItems = array_intersect_key($allNavItems, array_flip($allowedHrefs));
?>
<style>
  .sidebar {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    bottom: 0 !important;
    width: 220px !important;
    height: 100vh !important;
    overflow-y: auto !important;
    z-index: 999 !important;
  }

  .main {
    margin-left: 220px !important;
  }

  @media (max-width: 820px) {
    .sidebar {
      position: static !important;
      width: 100% !important;
      height: auto !important;
      overflow-y: visible !important;
    }

    .main {
      margin-left: 0 !important;
    }
  }
</style>

<style>
  .layout {
    display: block !important;
    min-height: 100vh !important;
  }

  .sidebar {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    bottom: 0 !important;
    width: 220px !important;
    height: 100vh !important;
    overflow-y: auto !important;
    z-index: 999 !important;
  }

  .main {
    margin-left: 220px !important;
    width: auto !important;
    max-width: none !important;
    min-width: 0 !important;
  }

  @media (max-width: 820px) {
    .sidebar {
      position: static !important;
      width: 100% !important;
      height: auto !important;
      overflow-y: visible !important;
    }

    .main {
      margin-left: 0 !important;
      width: auto !important;
    }
  }
</style>


<aside class="sidebar">
  <div>
    <a class="brand <?= $current === 'index.php' ? 'active' : '' ?>" href="index.php?from_login=1" style="display:block; text-decoration:none; color:inherit;">
      Gadget Repair Shop
    </a>
    <nav class="nav-list">
      <?php foreach ($navItems as $href => $label): ?>
        <a class="nav-link <?= $current === $href ? 'active' : '' ?>" href="<?= htmlspecialchars($href) ?>">
          <?= htmlspecialchars($label) ?>
        </a>
      <?php endforeach; ?>
    </nav>
  </div>

  <div class="sidebar-footer">
    <div class="staff-name"><?= htmlspecialchars($staffName) ?></div>
    <div class="staff-role">
      <?= htmlspecialchars($staffRole) ?>
      <?php if (!empty($staffSpec)): ?>
        · <?= htmlspecialchars($staffSpec) ?>
      <?php endif; ?>
    </div>
    <?php if ($staff): ?>
      <a href="logout.php">Log out</a>
    <?php else: ?>
      <a href="login.php">Log in</a>
    <?php endif; ?>
  </div>
</aside>