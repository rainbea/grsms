<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/api/db.php';

$id = trim((string)($_GET['id'] ?? 'REQ-001'));
$services = $pdo->query('SELECT service_ID, service_name, price FROM service ORDER BY service_ID ASC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Repair request detail — Gadget Repair Shop</title>
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
    .back-link {
      display: inline-block;
      font-size: 13px;
      color: #475569;
      text-decoration: underline;
      margin-bottom: 10px;
    }
    .detail-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: 20px;
      flex-wrap: wrap;
    }
    .title-group {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .title-group h1 {
      margin: 0;
      font-size: 26px;
      font-weight: 700;
      letter-spacing: -0.02em;
    }
    .status-badge {
      padding: 4px 12px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 600;
      background: #dbeafe;
      color: #1d4ed8;
    }
    .step-bar {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 10px;
      margin-bottom: 24px;
    }
    .step-box {
      padding: 11px 14px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 600;
      background: #e2e8f0;
      color: #475569;
      text-align: left;
    }
    .step-box.done {
      background: #d1fae5;
      color: #065f46;
    }
    .step-box.active {
      background: #047857;
      color: #ffffff;
    }
    .detail-cols {
      display: grid;
      grid-template-columns: 320px 1fr;
      gap: 20px;
      align-items: start;
    }
    .card {
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      padding: 20px;
      background: #ffffff;
      margin-bottom: 18px;
    }
    .card h2 {
      margin: 0 0 12px;
      font-size: 17px;
      font-weight: 700;
    }
    .info-row {
      font-size: 13.5px;
      color: #334155;
      margin-bottom: 7px;
      line-height: 1.45;
    }
    .info-row strong.lead {
      color: #0f172a;
      font-size: 14.5px;
      display: block;
      margin-bottom: 4px;
    }
    .controls-row {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
      margin-bottom: 8px;
    }
    select, input[type="number"] {
      padding: 9px 12px;
      border: 1px solid #cbd5e1;
      border-radius: 8px;
      font: inherit;
      font-size: 13.5px;
      background: #ffffff;
      color: #0f172a;
    }
    .btn-green {
      background: #047857;
      color: #ffffff;
      border: none;
      border-radius: 8px;
      padding: 9px 16px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
    }
    .btn-green:hover { background: #065f46; }
    .btn-outline {
      background: #ffffff;
      color: #047857;
      border: 1px solid #047857;
      border-radius: 8px;
      padding: 9px 16px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
    }
    .helper-note {
      font-size: 12px;
      color: #64748b;
      margin: 6px 0 14px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13.5px;
    }
    th {
      text-align: left;
      padding: 10px 12px;
      font-size: 12px;
      color: #475569;
      border-bottom: 1px solid #e2e8f0;
    }
    td {
      padding: 11px 12px;
      border-bottom: 1px solid #f1f5f9;
    }
    tfoot td {
      border-top: 1px solid #e2e8f0;
      border-bottom: none;
      font-weight: 700;
    }
    .add-svc-bar {
      display: flex;
      gap: 12px;
      align-items: flex-end;
      flex-wrap: wrap;
      margin-top: 16px;
    }
    .add-svc-bar label {
      display: block;
      font-size: 12px;
      font-weight: 600;
      margin-bottom: 5px;
    }
    .rec-services {
      margin-top: 8px;
      padding: 10px 12px;
      border-radius: 8px;
      background: #f0fdf4;
      border: 1px solid #bbf7d0;
      font-size: 12.5px;
      color: #166534;
    }
    .rec-chips {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      margin-top: 6px;
    }
    .rec-chip {
      background: #ffffff;
      border: 1px solid #86efac;
      color: #14532d;
      border-radius: 999px;
      padding: 4px 10px;
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
    }
    .rec-chip:hover {
      background: #dcfce7;
    }
    .invoice-box {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      flex-wrap: wrap;
    }
    @media (max-width: 960px) {
      .detail-cols { grid-template-columns: 1fr; }
      .step-bar { grid-template-columns: repeat(2, 1fr); }
    }
  </style>
</head>
<body>
  <div class="layout">
    <?php include __DIR__ . '/partials/nav.php'; ?>

    <main class="main">
      <a href="repair_requests.php" class="back-link">Back to repair requests</a>

      <div class="detail-header">
        <div class="title-group">
          <h1 id="detailTitle">Repair request</h1>
          <span id="detailBadge" class="status-badge">Received</span>
        </div>
        <a id="topInvoiceLink" href="invoices.php" style="font-size:13.5px;color:#0f172a;font-weight:600;text-decoration:underline;">View invoice</a>
      </div>

      <div class="step-bar" id="stepBar"></div>

      <div class="detail-cols">
        <div>
          <section class="card">
            <h2>Customer</h2>
            <div id="customerInfo"></div>
          </section>

          <section class="card">
            <h2>Gadget</h2>
            <div id="gadgetInfo"></div>
          </section>
        </div>

        <div>
          <section class="card">
            <h2>Request details</h2>
            <div id="requestInfo"></div>
          </section>

          <section class="card">
            <h2>Status history</h2>
            <div class="controls-row">
              <select id="statusSelect">
                <option value="Received">Received</option>
                <option value="Diagnosing">Diagnosing</option>
                <option value="Repairing">Repairing</option>
                <option value="Ready for pickup">Ready for pickup</option>
                <option value="Picked up">Picked up</option>
              </select>
              <button type="button" id="saveStatusBtn" class="btn-green">Update status</button>
            </div>
            <p class="helper-note">Each update is saved with your name and the time.</p>

            <table>
              <thead>
                <tr>
                  <th>Status</th>
                  <th>Updated by</th>
                  <th>Date updated</th>
                </tr>
              </thead>
              <tbody id="historyRows"></tbody>
            </table>
          </section>

          <section class="card">
            <h2>Services</h2>
            <table>
              <thead>
                <tr>
                  <th>Service</th>
                  <th>Quantity</th>
                  <th>Actual price</th>
                  <th>Subtotal</th>
                </tr>
              </thead>
              <tbody id="serviceRows"></tbody>
              <tfoot>
                <tr>
                  <td colspan="3">Total</td>
                  <td id="serviceTotal">₱0</td>
                </tr>
              </tfoot>
            </table>

            <form id="addServiceForm" class="add-svc-bar">
              <div style="flex:2;min-width:210px;">
                <label for="svcSelect">Add service</label>
                <select id="svcSelect" style="width:100%;"></select>
                <div id="consoleRecBox" class="rec-services" style="display:none;"></div>
              </div>
              <div style="width:90px;">
                <label for="svcQty">Quantity</label>
                <input id="svcQty" type="number" min="1" value="1" style="width:100%;">
              </div>
              <div style="width:140px;">
                <label for="svcActualPrice">Actual price (₱)</label>
                <input id="svcActualPrice" type="number" step="0.01" min="0" value="1500" style="width:100%;">
              </div>
              <div>
                <button type="submit" class="btn-green">Add</button>
              </div>
            </form>
          </section>

          <section class="card">
            <div class="invoice-box">
              <div>
                <h2 style="margin-bottom:4px;">Invoice</h2>
                <p id="invoiceDesc" style="margin:0;font-size:13px;color:#64748b;">
                  No invoice yet. It is created from the services above.
                </p>
              </div>
              <a id="genInvoiceBtn" href="invoices.php" class="btn-outline">Generate invoice</a>
            </div>
          </section>
        </div>
      </div>
    </main>
  </div>

  <script>
    const reqId = <?= json_encode($id) ?>;
    const allServices = <?= json_encode(array_map(fn($s) => [
        'service_id'    => $s['service_ID'],
        'service_name'  => $s['service_name'],
        'default_price' => (float)$s['price'],
    ], $services), JSON_UNESCAPED_UNICODE) ?>;

    const stages = ['Received', 'Diagnosing', 'Repairing', 'Ready for pickup', 'Picked up'];
    const svcSelect = document.getElementById('svcSelect');
    const svcActualPrice = document.getElementById('svcActualPrice');
    const consoleRecBox = document.getElementById('consoleRecBox');
    let currentRequest = null;

    function peso(n) {
      return '₱' + Number(n || 0).toLocaleString('en-US', { maximumFractionDigits: 0 });
    }

    function esc(v) {
      return String(v ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    }

    function isConsoleRequest(req) {
      const cat = String(req?.category || '').toLowerCase();
      const text = `${req?.brand || ''} ${req?.model || ''} ${req?.reported_issue || ''}`.toLowerCase();
      return (
        cat.includes('console') ||
        /\b(psp|ps1|ps2|ps3|ps4|ps5|playstation|xbox|switch|nintendo|controller|joy-?con|drift|button)\b/.test(text)
      );
    }

    function getRecommendedConsoleServices(req) {
      if (!isConsoleRequest(req) || !allServices.length) return [];

      const issue = String(req?.reported_issue || '').toLowerCase();
      const keywords = [
        'button', 'switch', 'stick', 'drift', 'hdmi', 'port', 'charging',
        'power', 'psu', 'fan', 'thermal', 'disc', 'drive', 'console',
        'controller', 'battery', 'speaker'
      ];

      const scored = allServices.map(srv => {
        const hay = String(srv.service_name || '').toLowerCase();
        let score = 0;

        for (const kw of keywords) {
          if (hay.includes(kw)) score += 10;
        }
        if (issue.includes('button') && (hay.includes('button') || hay.includes('switch') || hay.includes('pad'))) score += 30;
        if ((issue.includes('stick') || issue.includes('drift')) && (hay.includes('stick') || hay.includes('analog') || hay.includes('drift'))) score += 30;
        if (issue.includes('hdmi') && hay.includes('hdmi')) score += 30;
        if (issue.includes('power') && (hay.includes('power') || hay.includes('psu') || hay.includes('board'))) score += 25;

        return { srv, score };
      });

      const matches = scored
        .filter(x => x.score > 0)
        .sort((a, b) => b.score - a.score)
        .map(x => x.srv);

      return matches.length ? matches.slice(0, 5) : allServices.slice(0, 4);
    }

    function syncPriceWithSelectedService() {
      const opt = svcSelect.options[svcSelect.selectedIndex];
      if (opt && opt.dataset.price !== undefined) {
        svcActualPrice.value = Number(opt.dataset.price || 0).toFixed(2);
      }
    }

    function renderServiceDropdown(req) {
      if (!allServices.length) return;

      const isConsole = isConsoleRequest(req);
      const recommended = getRecommendedConsoleServices(req);
      const recIds = new Set(recommended.map(s => String(s.service_id)));

      let optionsHtml = '';
      if (isConsole && recommended.length) {
        optionsHtml += `<optgroup label="Recommended for Console">`;
        optionsHtml += recommended.map(s => `
          <option value="${esc(s.service_id)}" data-price="${Number(s.default_price || 0)}" data-name="${esc(s.service_name)}">
            ${esc(s.service_name)} · ${peso(s.default_price)}
          </option>
        `).join('');
        optionsHtml += `</optgroup>`;

        const rest = allServices.filter(s => !recIds.has(String(s.service_id)));
        if (rest.length) {
          optionsHtml += `<optgroup label="Other services">`;
          optionsHtml += rest.map(s => `
            <option value="${esc(s.service_id)}" data-price="${Number(s.default_price || 0)}" data-name="${esc(s.service_name)}">
              ${esc(s.service_name)} · ${peso(s.default_price)}
            </option>
          `).join('');
          optionsHtml += `</optgroup>`;
        }
      } else {
        optionsHtml = allServices.map(s => `
          <option value="${esc(s.service_id)}" data-price="${Number(s.default_price || 0)}" data-name="${esc(s.service_name)}">
            ${esc(s.service_name)} · ${peso(s.default_price)}
          </option>
        `).join('');
      }

      svcSelect.innerHTML = optionsHtml;
      syncPriceWithSelectedService();

      if (isConsole && recommended.length) {
        consoleRecBox.style.display = 'block';
        consoleRecBox.innerHTML = `
          <div><strong>Recommended console repair options:</strong></div>
          <div class="rec-chips">
            ${recommended.map(s => `
              <button type="button" class="rec-chip" data-id="${esc(s.service_id)}">
                ${esc(s.service_name)} · ${peso(s.default_price)}
              </button>
            `).join('')}
          </div>
        `;

        consoleRecBox.querySelectorAll('.rec-chip').forEach(btn => {
          btn.addEventListener('click', () => {
            svcSelect.value = btn.dataset.id;
            syncPriceWithSelectedService();
          });
        });
      } else {
        consoleRecBox.style.display = 'none';
        consoleRecBox.innerHTML = '';
      }
    }

    svcSelect.addEventListener('change', syncPriceWithSelectedService);

    async function loadDetail() {
      const res = await fetch(`api/endpoints.php?action=request_detail&id=${encodeURIComponent(reqId)}`);
      const data = await res.json();
      const r = data.request;
      if (!r) return;
      currentRequest = r;

      document.getElementById('detailTitle').textContent = `Repair request ${r.request_code}`;
      document.getElementById('detailBadge').textContent = r.status;
      document.getElementById('statusSelect').value = r.status;

      if (r.invoice_id) {
        const invHref = `invoices.php?id=${encodeURIComponent(r.invoice_id)}`;
        document.getElementById('topInvoiceLink').href = invHref;
        document.getElementById('genInvoiceBtn').href = invHref;
        document.getElementById('genInvoiceBtn').textContent = `Open ${r.invoice_code}`;
        document.getElementById('invoiceDesc').textContent = `Invoice ${r.invoice_code} is linked to this repair request.`;
      }

      const activeIdx = stages.indexOf(r.status);
      document.getElementById('stepBar').innerHTML = stages.map((st, idx) => {
        let cls = 'step-box';
        if (idx < activeIdx) cls += ' done';
        else if (idx === activeIdx) cls += ' active';
        return `<div class="${cls}">${esc(st)}</div>`;
      }).join('');

      document.getElementById('customerInfo').innerHTML = `
        <div class="info-row"><strong class="lead">${esc(r.customer_name)}</strong></div>
        <div class="info-row">${esc(r.contact_number)}</div>
        <div class="info-row">${esc(r.email || 'None')}</div>
        <div class="info-row">${esc(r.address || 'None')}</div>
        <div class="info-row" style="color:#64748b;">Registered: ${esc(r.customer_reg_date || '')}</div>
      `;

      document.getElementById('gadgetInfo').innerHTML = `
        <div class="info-row"><strong class="lead">${esc(r.brand)} ${esc(r.model)}</strong></div>
        <div class="info-row">Category: ${esc(r.category)}</div>
        <div class="info-row">Serial number: ${esc(r.serial_number)}</div>
        <div class="info-row" style="color:#64748b;">Registered: ${esc(r.gadget_reg_date || '')}</div>
      `;

      document.getElementById('requestInfo').innerHTML = `
        <div class="info-row">Reported issue: ${esc(r.reported_issue)}</div>
        <div class="info-row">Initial condition: ${esc(r.initial_condition || 'None')}</div>
        <div class="info-row">Accessories included: ${esc(r.accessories_included || 'None')}</div>
        <div class="info-row">Assigned technician: ${esc(r.technician_name)}</div>
        <div class="info-row">Received by: ${esc(r.received_by_name)}</div>
        <div class="info-row">Date submitted: ${esc(r.submitted_date)}</div>
      `;

      document.getElementById('historyRows').innerHTML = (data.history || []).map(h => `
        <tr>
          <td>${esc(h.status)}</td>
          <td>${esc(h.updated_by)}</td>
          <td>${esc(h.formatted_date)}</td>
        </tr>
      `).join('');

      const items = data.items || [];
      let sum = 0;
      document.getElementById('serviceRows').innerHTML = items.length
        ? items.map(it => {
            sum += Number(it.subtotal || 0);
            return `
              <tr>
                <td>${esc(it.service_label)}</td>
                <td>${esc(it.quantity)}</td>
                <td>${peso(it.actual_price)}</td>
                <td>${peso(it.subtotal)}</td>
              </tr>
            `;
          }).join('')
        : `<tr><td colspan="4" style="color:#64748b;">No services added yet.</td></tr>`;

      document.getElementById('serviceTotal').textContent = peso(sum);

      renderServiceDropdown(r);
    }

    document.getElementById('saveStatusBtn').addEventListener('click', async () => {
      await fetch('api/endpoints.php?action=update_request_status', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          request_id: reqId,
          status: document.getElementById('statusSelect').value
        })
      });
      loadDetail();
    });

    document.getElementById('addServiceForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const opt = svcSelect.options[svcSelect.selectedIndex];
      await fetch('api/endpoints.php?action=add_request_service', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          request_id: reqId,
          service_id: opt.value,
          service_label: opt.dataset.name,
          quantity: Number(document.getElementById('svcQty').value),
          actual_price: Number(svcActualPrice.value)
        })
      });
      loadDetail();
    });

    loadDetail();
  </script>
</body>
</html>