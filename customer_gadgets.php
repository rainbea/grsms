<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isAdmin = (($_SESSION['staff']['role'] ?? '') === 'Admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Customers and gadgets — Gadget Repair Shop</title>
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
    .top-bar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: 24px;
    }
    .top-bar h1 {
      margin: 0;
      font-size: 26px;
      font-weight: 700;
      letter-spacing: -0.02em;
    }
    .search-input {
      width: 320px;
      max-width: 100%;
      padding: 9px 14px;
      border: 1px solid #cbd5e1;
      border-radius: 8px;
      font-size: 13px;
      color: #0f172a;
      background: #ffffff;
    }
    .search-input:focus {
      outline: 2px solid #047857;
      border-color: #047857;
    }
    .section-heading {
      font-size: 18px;
      font-weight: 700;
      margin: 24px 0 12px;
      color: #0f172a;
    }
    .section-heading:first-of-type { margin-top: 0; }
    .table-card {
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
      white-space: nowrap;
    }
    tbody td {
      padding: 13px 16px;
      border-bottom: 1px solid #f1f5f9;
      color: #0f172a;
    }
    tbody tr:last-child td { border-bottom: none; }
    tbody tr:hover { background: #f8fafc; }
    .muted-cell { color: #64748b; }
    .btn-del {
      padding: 5px 10px;
      border-radius: 6px;
      border: 1px solid #fecaca;
      background: #fef2f2;
      color: #b91c1c;
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
    }
    .btn-del:hover { background: #fee2e2; }
    .err-banner {
      background: #fef2f2;
      border: 1px solid #fecaca;
      color: #b91c1c;
      padding: 12px 16px;
      border-radius: 8px;
      font-size: 13px;
      margin-bottom: 18px;
      display: none;
    }
    @media (max-width: 820px) {
      .top-bar { flex-direction: column; align-items: stretch; }
      .main { padding: 20px; }
    }
  </style>
</head>
<body>
  <div class="layout">
    <?php include __DIR__ . '/partials/nav.php'; ?>

    <main class="main">
      <div class="top-bar">
        <h1>Customers and gadgets</h1>
        <input
          id="cgSearch"
          class="search-input"
          type="text"
          placeholder="Search name, contact number or serial number"
        >
      </div>

      <div id="cgError" class="err-banner"></div>

      <h2 class="section-heading">Customers</h2>
      <div class="table-card">
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Contact number</th>
              <th>Email</th>
              <th>Address</th>
              <th>Date registered</th>
              <?php if ($isAdmin): ?>
                <th>Action</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody id="custBody">
            <tr><td colspan="<?= $isAdmin ? 6 : 5 ?>" class="muted-cell" style="text-align:center;padding:20px;">Loading customers...</td></tr>
          </tbody>
        </table>
      </div>

      <h2 class="section-heading">Gadgets</h2>
      <div class="table-card">
        <table>
          <thead>
            <tr>
              <th>Category</th>
              <th>Brand</th>
              <th>Model</th>
              <th>Serial number</th>
              <th>Owner</th>
              <th>Date registered</th>
            </tr>
          </thead>
          <tbody id="gadgetBody">
            <tr><td colspan="6" class="muted-cell" style="text-align:center;padding:20px;">Loading gadgets...</td></tr>
          </tbody>
        </table>
      </div>

      <?php if ($isAdmin): ?>
        <h2 class="section-heading">Deleted customers</h2>
        <div class="table-card">
          <table>
            <thead>
              <tr>
                <th>Name</th>
                <th>Contact number</th>
                <th>Email</th>
                <th>Address</th>
                <th>Deleted by</th>
                <th>Date deleted</th>
              </tr>
            </thead>
            <tbody id="deletedCustBody">
              <tr><td colspan="6" class="muted-cell" style="text-align:center;padding:20px;">Loading deleted customers...</td></tr>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </main>
  </div>

  <script>
    const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;

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

    function showErr(msg) {
      const el = document.getElementById('cgError');
      el.textContent = msg;
      el.style.display = 'block';
    }

    function clearErr() {
      const el = document.getElementById('cgError');
      el.textContent = '';
      el.style.display = 'none';
    }

    async function deleteCustomer(customerId, customerName) {
      if (!confirm(`Delete customer "${customerName}"? They will be saved in Deleted customers.`)) return;
      clearErr();
      const res = await fetch('api/endpoints.php?action=delete_customer', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ customer_id: customerId })
      });
      const data = await res.json();
      if (res.ok && data.ok) {
        loadData(document.getElementById('cgSearch').value.trim());
      } else {
        showErr(data.message || data.error || 'Could not delete customer.');
      }
    }

    async function loadData(q = '') {
      clearErr();
      try {
        const res = await fetch(`api/endpoints.php?action=customers_and_gadgets&q=${encodeURIComponent(q)}`);
        const text = await res.text();
        let data;
        try {
          data = JSON.parse(text);
        } catch {
          showErr(`API error (HTTP ${res.status}): ${text.slice(0, 200)}`);
          return;
        }

        if (!res.ok || data.error) {
          showErr(data.error || data.message || `Request failed (${res.status})`);
          return;
        }

        const customers = Array.isArray(data.customers) ? data.customers : [];
        const gadgets = Array.isArray(data.gadgets) ? data.gadgets : [];
        const deletedCustomers = Array.isArray(data.deleted_customers) ? data.deleted_customers : [];

        document.getElementById('custBody').innerHTML = customers.length
          ? customers.map(c => `
              <tr>
                <td style="font-weight:500;">${esc(c.full_name)}</td>
                <td>${esc(c.contact_number)}</td>
                <td class="${!c.email || c.email === 'None' ? 'muted-cell' : ''}">${esc(c.email || 'None')}</td>
                <td>${esc(c.address || 'None')}</td>
                <td style="white-space:nowrap;">${esc(c.reg_date || '')}</td>
                ${isAdmin ? `<td><button type="button" class="btn-del" onclick="deleteCustomer(${jsQuote(c.customer_id)}, ${jsQuote(c.full_name)})">Delete</button></td>` : ''}
              </tr>
            `).join('')
          : `<tr><td colspan="${isAdmin ? 6 : 5}" class="muted-cell" style="text-align:center;padding:20px;">No customers found.</td></tr>`;

        document.getElementById('gadgetBody').innerHTML = gadgets.length
          ? gadgets.map(g => `
              <tr>
                <td>${esc(g.category)}</td>
                <td>${esc(g.brand)}</td>
                <td>${esc(g.model)}</td>
                <td>${esc(g.serial_number)}</td>
                <td>${esc(g.owner_name)}</td>
                <td style="white-space:nowrap;">${esc(g.reg_date || '')}</td>
              </tr>
            `).join('')
          : `<tr><td colspan="6" class="muted-cell" style="text-align:center;padding:20px;">No gadgets found.</td></tr>`;

        if (isAdmin) {
          const delBody = document.getElementById('deletedCustBody');
          delBody.innerHTML = deletedCustomers.length
            ? deletedCustomers.map(d => `
                <tr>
                  <td style="font-weight:500;">${esc(d.full_name)}</td>
                  <td>${esc(d.contact_number)}</td>
                  <td class="${!d.email || d.email === 'None' ? 'muted-cell' : ''}">${esc(d.email || 'None')}</td>
                  <td>${esc(d.address || 'None')}</td>
                  <td>${esc(d.deleted_by || '')}</td>
                  <td style="white-space:nowrap;">${esc(d.deleted_date || '')}</td>
                </tr>
              `).join('')
            : `<tr><td colspan="6" class="muted-cell" style="text-align:center;padding:20px;">No deleted customers yet.</td></tr>`;
        }
      } catch (err) {
        showErr(`Network error: ${err.message}`);
      }
    }

    document.getElementById('cgSearch').addEventListener('input', e => loadData(e.target.value.trim()));
    loadData();
  </script>
</body>
</html>