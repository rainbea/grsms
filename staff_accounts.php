<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isManager = (($_SESSION['staff']['role'] ?? '') === 'Manager');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Staff accounts — Gadget Repair Shop</title>
  <link rel="stylesheet" href="assets/style.css">
  <style>
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      background: #ffffff;
      color: #0f172a;
    }
    .layout { display: flex; min-height: 100vh; }
    .main {
      flex: 1;
      padding: 28px 36px;
      background: #ffffff;
      min-width: 0;
    }
    .main h1 {
      margin: 0 0 22px;
      font-size: 26px;
      font-weight: 700;
      letter-spacing: -0.02em;
    }
    .staff-grid {
      display: grid;
      grid-template-columns: 1.5fr 350px;
      gap: 24px;
      align-items: start;
    }
    .card {
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      background: #ffffff;
      padding: 20px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
    }
    th {
      text-align: left;
      padding: 11px 12px;
      font-size: 12px;
      color: #475569;
      border-bottom: 1px solid #e2e8f0;
      white-space: nowrap;
    }
    td {
      padding: 12px;
      border-bottom: 1px solid #f1f5f9;
      vertical-align: middle;
    }
    .st-pill {
      display: inline-block;
      padding: 3px 9px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 600;
    }
    .st-active { background: #dcfce7; color: #15803d; }
    .st-inactive { background: #fee2e2; color: #b91c1c; }
    .btn-toggle {
      padding: 5px 10px;
      border-radius: 6px;
      border: 1px solid #cbd5e1;
      background: #ffffff;
      color: #0f172a;
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
    }
    .btn-toggle:hover { background: #f8fafc; }
    .field { margin-bottom: 12px; }
    .field label {
      display: block;
      font-size: 13px;
      font-weight: 600;
      margin-bottom: 5px;
    }
    .field input, .field select {
      width: 100%;
      padding: 8px 11px;
      border: 1px solid #cbd5e1;
      border-radius: 8px;
      font: inherit;
      font-size: 13.5px;
      background: #ffffff;
    }
    @media (max-width: 980px) {
      .staff-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
  <div class="layout">
    <?php include __DIR__ . '/partials/nav.php'; ?>

    <main class="main">
      <h1>Staff accounts</h1>

      <div class="staff-grid">
        <div class="card" style="padding:0;overflow-x:auto;">
          <table>
            <thead>
              <tr>
                <th>Name</th>
                <th>Username</th>
                <th>Contact number</th>
                <th>Role</th>
                <th>Specialization</th>
                <th>Status</th>
                <?php if ($isManager): ?>
                  <th>Manager action</th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody id="staffRows"></tbody>
          </table>
        </div>

        <div class="card">
          <h2 style="margin:0 0 4px;font-size:18px;font-weight:700;">Add a staff account</h2>
          <p style="margin:0 0 16px;font-size:12.5px;color:#64748b;">
            Saved to the system_user table. Passwords are stored as hashes, never as plain text.
          </p>

          <form id="addStaffForm">
            <div class="field"><label for="first_name">First name</label><input id="first_name" type="text" required></div>
            <div class="field"><label for="middle_name">Middle name</label><input id="middle_name" type="text"></div>
            <div class="field"><label for="last_name">Last name</label><input id="last_name" type="text" required></div>
            <div class="field"><label for="username">Username</label><input id="username" type="text" required></div>
            <div class="field"><label for="password">Password</label><input id="password" type="password" required></div>
            <div class="field"><label for="contact_number">Contact number</label><input id="contact_number" type="text" required></div>
            <div class="field">
              <label for="role">Role</label>
              <select id="role">
                <option value="Technician" selected>Technician</option>
                <option value="Receptionist">Receptionist</option>
                <option value="Admin">Admin</option>
                <option value="Cashier">Cashier</option>
              </select>
            </div>
            <div class="field">
              <label for="specialization">Specialization (technicians only)</label>
              <input id="specialization" type="text" placeholder="e.g., Apple & Tablet Devices">
            </div>
            <button type="submit" class="btn btn-green" style="width:100%;">Create account</button>
            <div id="staffMsg" style="margin-top:10px;font-size:13px;"></div>
          </form>
        </div>
      </div>
    </main>
  </div>

  <script>
    const isManager = <?= $isManager ? 'true' : 'false' ?>;

    function esc(v) {
      return String(v ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    }

    function jsQuote(v) {
      return "'" + String(v ?? '').replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";
    }

    async function loadStaff() {
      const res = await fetch('api/endpoints.php?action=staff_list');
      const rows = await res.json();

      document.getElementById('staffRows').innerHTML = rows.map(u => {
        const st = u.account_status || 'Active';
        const nextSt = st === 'Active' ? 'Inactive' : 'Active';
        const pillCls = st === 'Active' ? 'st-active' : 'st-inactive';
        const actionCell = isManager
          ? `<td><button type="button" class="btn-toggle" onclick="toggleStatus(${jsQuote(u.user_id)}, '${nextSt}')">Set ${nextSt}</button></td>`
          : '';

        return `
          <tr>
            <td style="font-weight:600;">${esc(u.full_name)}</td>
            <td>${esc(u.username)}</td>
            <td>${esc(u.contact_number)}</td>
            <td>${esc(u.role)}</td>
            <td>${esc(u.specialization ?? 'None')}</td>
            <td><span class="st-pill ${pillCls}">${esc(st)}</span></td>
            ${actionCell}
          </tr>
        `;
      }).join('');
    }

    async function toggleStatus(userId, newStatus) {
      const res = await fetch('api/endpoints.php?action=toggle_staff_status', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: userId, account_status: newStatus })
      });
      const data = await res.json();
      if (res.ok && data.ok) {
        loadStaff();
      } else {
        alert(data.message || data.error || 'Only a Manager can change account status.');
      }
    }

    document.getElementById('addStaffForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const msg = document.getElementById('staffMsg');
      const res = await fetch('api/endpoints.php?action=create_staff', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          first_name: document.getElementById('first_name').value.trim(),
          middle_name: document.getElementById('middle_name').value.trim(),
          last_name: document.getElementById('last_name').value.trim(),
          username: document.getElementById('username').value.trim(),
          password: document.getElementById('password').value,
          contact_number: document.getElementById('contact_number').value.trim(),
          role: document.getElementById('role').value,
          specialization: document.getElementById('specialization').value.trim() || null,
          account_status: 'Active'
        })
      });
      const data = await res.json();
      if (res.ok && data.ok) {
        e.target.reset();
        msg.style.color = '#15803d';
        msg.textContent = 'Staff account created.';
        loadStaff();
      } else {
        msg.style.color = '#b91c1c';
        msg.textContent = data.message || data.error || 'Error creating account.';
      }
    });

    loadStaff();
  </script>
</body>
</html>