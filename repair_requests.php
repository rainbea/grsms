<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Repair requests — Gadget Repair Shop</title>
  <link rel="stylesheet" href="assets/style.css">
  <style>
    * {
      box-sizing: border-box;
    }
    body {
      margin: 0;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      background: #f8fafc;
      color: #0f172a;
    }
    .layout {
      display: flex;
      min-height: 100vh;
    }
    .sidebar {
      width: 220px;
      flex-shrink: 0;
      background: #0b1320;
      color: #e2e8f0;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding: 20px 14px;
    }
    .brand {
      font-size: 16px;
      font-weight: 700;
      color: #ffffff;
      padding: 6px 10px 18px;
    }
    .nav-list {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    .nav-link {
      display: block;
      padding: 10px 12px;
      border-radius: 8px;
      color: #cbd5e1;
      text-decoration: none;
      font-size: 14px;
      font-weight: 500;
    }
    .nav-link:hover {
      background: rgba(255, 255, 255, 0.06);
      color: #ffffff;
    }
    .nav-link.active {
      background: #1e293b;
      color: #ffffff;
      font-weight: 600;
    }
    .sidebar-footer {
      border-top: 1px solid rgba(148, 163, 184, 0.2);
      padding: 14px 10px 4px;
      font-size: 13px;
    }
    .staff-name {
      font-weight: 700;
      color: #ffffff;
      margin-bottom: 2px;
    }
    .staff-role {
      font-size: 12px;
      color: #94a3b8;
      margin-bottom: 8px;
    }
    .sidebar-footer a {
      color: #cbd5e1;
      font-size: 12px;
      text-decoration: underline;
    }

    .main {
      flex: 1;
      padding: 28px 36px;
      background: #ffffff;
    }
    .top-bar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: 22px;
    }
    .top-bar h1 {
      margin: 0;
      font-size: 26px;
      font-weight: 700;
      letter-spacing: -0.02em;
      color: #0f172a;
    }
    .toolbar-right {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .search-input {
      width: 290px;
      padding: 9px 14px;
      border: 1px solid #cbd5e1;
      border-radius: 8px;
      font-size: 13px;
      color: #0f172a;
      background: #ffffff;
    }
    .search-input:focus {
      outline: 2px solid #059669;
      border-color: #059669;
    }
    .btn-green {
      background: #047857;
      color: #ffffff;
      border: none;
      border-radius: 8px;
      padding: 10px 16px;
      font-size: 13px;
      font-weight: 600;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      cursor: pointer;
    }
    .btn-green:hover {
      background: #065f46;
    }

    .summary-grid {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 14px;
      margin-bottom: 24px;
    }
    .stat-card {
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      padding: 16px 18px;
      background: #ffffff;
      cursor: pointer;
      transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }
    .stat-card:hover,
    .stat-card.selected {
      border-color: #047857;
      box-shadow: 0 0 0 1px #047857;
    }
    .stat-label {
      font-size: 12px;
      color: #64748b;
      margin-bottom: 8px;
    }
    .stat-num {
      font-size: 24px;
      font-weight: 700;
      color: #0f172a;
    }

    .table-wrap {
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      overflow-x: auto;
      background: #ffffff;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13.5px;
    }
    thead th {
      text-align: left;
      padding: 12px 16px;
      font-size: 12px;
      font-weight: 600;
      color: #475569;
      border-bottom: 1px solid #e2e8f0;
      background: #ffffff;
      white-space: nowrap;
    }
    tbody td {
      padding: 14px 16px;
      border-bottom: 1px solid #f1f5f9;
      color: #0f172a;
      white-space: nowrap;
    }
    tbody tr:last-child td {
      border-bottom: none;
    }
    tbody tr:hover {
      background: #f8fafc;
    }
    .req-link {
      color: #0f172a;
      font-weight: 600;
      text-decoration: underline;
      text-underline-offset: 2px;
    }

    .pill {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 600;
    }
    .pill-received {
      background: #fef9c3;
      color: #854d0e;
    }
    .pill-diagnosing {
      background: #f3e8ff;
      color: #6b21a8;
    }
    .pill-repairing {
      background: #dbeafe;
      color: #1d4ed8;
    }
    .pill-ready {
      background: #dcfce7;
      color: #15803d;
    }
    .pill-picked {
      background: #f1f5f9;
      color: #334155;
    }

    @media (max-width: 1050px) {
      .summary-grid {
        grid-template-columns: repeat(2, 1fr);
      }
      .top-bar {
        flex-direction: column;
        align-items: flex-start;
      }
      .search-input {
        width: 100%;
      }
    }
  </style>
</head>
<body>
  <div class="layout">
    <?php include __DIR__ . '/partials/nav.php'; ?>

    <main class="main">
      <div class="top-bar">
        <h1>Repair requests</h1>
        <div class="toolbar-right">
          <input
            id="searchInput"
            class="search-input"
            type="text"
            placeholder="Search customer, gadget or request no."
          >
          <a href="new_request.php" class="btn-green">New request</a>
        </div>
      </div>

      <div class="summary-grid" id="summaryCards">
        <div class="stat-card" data-status="">
          <div class="stat-label">Received</div>
          <div class="stat-num" id="cnt-received">0</div>
        </div>
        <div class="stat-card" data-status="Diagnosing">
          <div class="stat-label">Diagnosing</div>
          <div class="stat-num" id="cnt-diagnosing">0</div>
        </div>
        <div class="stat-card" data-status="Repairing">
          <div class="stat-label">Repairing</div>
          <div class="stat-num" id="cnt-repairing">0</div>
        </div>
        <div class="stat-card" data-status="Ready for pickup">
          <div class="stat-label">Ready for pickup</div>
          <div class="stat-num" id="cnt-ready">0</div>
        </div>
        <div class="stat-card" data-status="Picked up">
          <div class="stat-label">Picked up</div>
          <div class="stat-num" id="cnt-picked">0</div>
        </div>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Request</th>
              <th>Customer</th>
              <th>Gadget</th>
              <th>Reported issue</th>
              <th>Assigned technician</th>
              <th>Status</th>
              <th>Date submitted</th>
            </tr>
          </thead>
          <tbody id="requestsBody">
            <tr>
              <td colspan="7" style="text-align:center;color:#64748b;padding:24px;">Loading repair requests...</td>
            </tr>
          </tbody>
        </table>
      </div>
    </main>
  </div>

  <script>
    let allRequests = [];
    let activeStatusFilter = '';

    function statusPill(status) {
      const map = {
        'Received': 'pill-received',
        'Diagnosing': 'pill-diagnosing',
        'Repairing': 'pill-repairing',
        'Ready for pickup': 'pill-ready',
        'Picked up': 'pill-picked'
      };
      const cls = map[status] || 'pill-picked';
      return `<span class="pill ${cls}">${escapeHtml(status || 'Received')}</span>`;
    }

    function escapeHtml(str) {
      return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    }

    function renderTable() {
      const q = document.getElementById('searchInput').value.trim().toLowerCase();
      const tbody = document.getElementById('requestsBody');

      const filtered = allRequests.filter(r => {
        const matchesStatus = !activeStatusFilter || r.status === activeStatusFilter;
        const haystack = [
          r.request_code,
          r.customer_name,
          r.gadget_name,
          r.reported_issue,
          r.technician_name,
          r.status
        ].join(' ').toLowerCase();
        const matchesQuery = !q || haystack.includes(q);
        return matchesStatus && matchesQuery;
      });

      if (!filtered.length) {
        tbody.innerHTML = `
          <tr>
            <td colspan="7" style="text-align:center;color:#64748b;padding:24px;">No matching repair requests found.</td>
          </tr>
        `;
        return;
      }

      tbody.innerHTML = filtered.map(r => `
        <tr>
          <td><a class="req-link" href="request_detail.php?id=${encodeURIComponent(r.request_id)}">${escapeHtml(r.request_code)}</a></td>
          <td>${escapeHtml(r.customer_name)}</td>
          <td>${escapeHtml(r.gadget_name)}</td>
          <td>${escapeHtml(r.reported_issue)}</td>
          <td>${escapeHtml(r.technician_name || 'Unassigned')}</td>
          <td>${statusPill(r.status)}</td>
          <td>${escapeHtml(r.date_label || '')}</td>
        </tr>
      `).join('');
    }

    async function loadDashboard() {
      try {
        const [sumRes, reqRes] = await Promise.all([
          fetch('api/endpoints.php?action=repair_summary'),
          fetch('api/endpoints.php?action=repair_requests')
        ]);

        const summary = await sumRes.json();
        document.getElementById('cnt-received').textContent  = summary['Received'] ?? 0;
        document.getElementById('cnt-diagnosing').textContent = summary['Diagnosing'] ?? 0;
        document.getElementById('cnt-repairing').textContent  = summary['Repairing'] ?? 0;
        document.getElementById('cnt-ready').textContent      = summary['Ready for pickup'] ?? 0;
        document.getElementById('cnt-picked').textContent     = summary['Picked up'] ?? 0;

        allRequests = await reqRes.json();
        renderTable();
      } catch (err) {
        document.getElementById('requestsBody').innerHTML = `
          <tr>
            <td colspan="7" style="text-align:center;color:#b91c1c;padding:24px;">Failed to load repair requests.</td>
          </tr>
        `;
      }
    }

    document.querySelectorAll('.stat-card').forEach(card => {
      card.addEventListener('click', () => {
        const clickedStatus = card.dataset.status;
        if (activeStatusFilter === clickedStatus) {
          activeStatusFilter = '';
          card.classList.remove('selected');
        } else {
          document.querySelectorAll('.stat-card').forEach(c => c.classList.remove('selected'));
          activeStatusFilter = clickedStatus;
          if (clickedStatus) card.classList.add('selected');
        }
        renderTable();
      });
    });

    document.getElementById('searchInput').addEventListener('input', renderTable);
    loadDashboard();
  </script>
</body>
</html>