<?php
require_once __DIR__ . '/partials/auth.php';
$selectedId = isset($_GET['id']) ? trim((string)$_GET['id']) : '';
$defaultRecorder = $_SESSION['staff']['full_name'] ?? 'Camille Torres';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Invoices — Gadget Repair Shop</title>
  <link rel="stylesheet" href="assets/style.css">
  <style>
    .inv-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 20px; }
    .stat-box { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px; }
    .stat-box .lbl { font-size: 12px; color: #64748b; margin-bottom: 4px; }
    .stat-box .val { font-size: 20px; font-weight: 700; color: #0f172a; }

    .toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
    .filter-pills { display: flex; gap: 6px; flex-wrap: wrap; }
    .f-pill {
      padding: 6px 12px; border-radius: 999px; border: 1px solid #cbd5e1;
      background: #fff; color: #334155; font-size: 12.5px; font-weight: 600; cursor: pointer;
    }
    .f-pill.active { background: #0f172a; color: #fff; border-color: #0f172a; }

    .inv-layout { display: grid; grid-template-columns: 1.15fr 1fr; gap: 24px; align-items: start; }
    .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; margin-bottom: 18px; }
    table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
    th { text-align: left; padding: 10px 12px; font-size: 12px; color: #475569; border-bottom: 1px solid #e2e8f0; white-space: nowrap; }
    td { padding: 12px; border-bottom: 1px solid #f1f5f9; }
    tr.selected-row { background: #f0fdf4; }
    .inv-link { font-weight: 600; color: #0f172a; text-decoration: underline; cursor: pointer; background: none; border: none; font: inherit; }

    .badge { padding: 3px 9px; border-radius: 999px; font-size: 12px; font-weight: 600; display: inline-block; }
    .b-paid { background: #dcfce7; color: #15803d; }
    .b-partial { background: #fef9c3; color: #854d0e; }
    .b-pending { background: #dbeafe; color: #1d4ed8; }
    .b-unpaid { background: #fee2e2; color: #b91c1c; }

    .field { margin-bottom: 12px; }
    .field label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 5px; }
    .field input, .field select { width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 8px; font: inherit; background: #fff; }
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

    .btn-sm {
      padding: 4px 9px; border-radius: 6px; border: 1px solid #059669;
      background: #ecfdf5; color: #047857; font-size: 11.5px; font-weight: 600; cursor: pointer;
    }
    .btn-sm:hover { background: #d1fae5; }
    .btn-outline {
      padding: 6px 12px; border-radius: 8px; border: 1px solid #cbd5e1;
      background: #fff; color: #0f172a; font-size: 12.5px; font-weight: 600; cursor: pointer;
    }
    .btn-outline:hover { background: #f8fafc; }

    @media (max-width: 960px) {
      .inv-layout { grid-template-columns: 1fr; }
      .inv-stats { grid-template-columns: repeat(2, 1fr); }
    }
    @media print {
      .sidebar, .toolbar, .inv-stats, #invListCard, #recordPaymentCard, .no-print { display: none !important; }
      .layout, .inv-layout { display: block !important; }
      .card { border: none; padding: 0; }
    }
  </style>
</head>
<body>
  <div class="layout">
    <?php include __DIR__ . '/partials/nav.php'; ?>
    <main class="main">
      <div class="page-header">
        <h1>Invoices</h1>
      </div>

      <div class="inv-stats">
        <div class="stat-box"><div class="lbl">Total billed</div><div class="val" id="stTotal">₱0</div></div>
        <div class="stat-box"><div class="lbl">Collected (Completed)</div><div class="val" id="stPaid" style="color:#15803d;">₱0</div></div>
        <div class="stat-box"><div class="lbl">Pending verification</div><div class="val" id="stPending" style="color:#1d4ed8;">₱0</div></div>
        <div class="stat-box"><div class="lbl">Balance due</div><div class="val" id="stBalance" style="color:#b91c1c;">₱0</div></div>
      </div>

      <div class="toolbar">
        <div class="filter-pills" id="statusFilters">
          <button type="button" class="f-pill active" data-st="">All</button>
          <button type="button" class="f-pill" data-st="Unpaid">Unpaid</button>
          <button type="button" class="f-pill" data-st="Pending">Pending</button>
          <button type="button" class="f-pill" data-st="Partially Paid">Partially Paid</button>
          <button type="button" class="f-pill" data-st="Paid">Paid</button>
        </div>
        <input id="invSearch" class="search-input" type="text" placeholder="Search invoice, request, or customer...">
      </div>

      <div class="inv-layout">
        <div id="invListCard" class="card" style="padding:0;overflow-x:auto;">
          <table>
            <thead>
              <tr>
                <th>Invoice</th>
                <th>Request</th>
                <th>Customer</th>
                <th>Total</th>
                <th>Paid</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="invListBody"></tbody>
          </table>
        </div>

        <div id="detailPanel">
          <div class="card">
            <p style="color:#64748b;margin:0;">Select an invoice on the left to view balance and record a payment.</p>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script>
    let allInvoices = [];
    let currentInvId = <?= json_encode($selectedId) ?>;
    let statusFilter = '';
    const defaultRecorder = <?= json_encode($defaultRecorder) ?>;
    const peso = n => '₱' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const esc = s => String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
    const jsQuote = v => "'" + String(v ?? '').replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";

    async function postJson(url, payload) {
      const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      let data = {};
      try {
        data = await res.json();
      } catch (_) {
        data = { ok: false, message: `Server returned HTTP ${res.status}` };
      }
      if (!res.ok || data.ok === false) {
        throw new Error(data.message || data.error || `Request failed (HTTP ${res.status})`);
      }
      return data;
    }

    function statusBadge(st) {
      const val = st || 'Unpaid';
      const cls = val === 'Paid'
        ? 'b-paid'
        : ((val === 'Partially Paid' || val === 'Partial')
            ? 'b-partial'
            : (val === 'Pending' ? 'b-pending' : 'b-unpaid'));
      return `<span class="badge ${cls}">${esc(val)}</span>`;
    }

    function updateSummaryStats() {
      let tot = 0, paid = 0, pend = 0;
      for (const r of allInvoices) {
        tot += Number(r.total_amount || 0);
        paid += Number(r.paid_amount || 0);
        pend += Number(r.pending_amount || 0);
      }
      document.getElementById('stTotal').textContent = peso(tot);
      document.getElementById('stPaid').textContent = peso(paid);
      document.getElementById('stPending').textContent = peso(pend);
      document.getElementById('stBalance').textContent = peso(Math.max(0, tot - paid));
    }

    function renderInvoiceTable() {
      const q = document.getElementById('invSearch').value.trim().toLowerCase();
      const filtered = allInvoices.filter(r => {
        const st = r.payment_status || 'Unpaid';
        const matchSt = !statusFilter || st === statusFilter || (statusFilter === 'Partially Paid' && st === 'Partial');
        const hay = [r.invoice_code, r.request_code, r.customer_name, st].join(' ').toLowerCase();
        return matchSt && (!q || hay.includes(q));
      });

      document.getElementById('invListBody').innerHTML = filtered.length
        ? filtered.map(r => `
            <tr class="${String(r.invoice_id) === String(currentInvId) ? 'selected-row' : ''}">
              <td><button type="button" class="inv-link" onclick="openInvoice(${jsQuote(r.invoice_id)})">${esc(r.invoice_code)}</button></td>
              <td><a href="request_detail.php?id=${encodeURIComponent(r.request_id)}">${esc(r.request_code)}</a></td>
              <td>${esc(r.customer_name)}</td>
              <td>${peso(r.total_amount)}</td>
              <td>${peso(r.paid_amount)}</td>
              <td>${statusBadge(r.payment_status)}</td>
            </tr>
          `).join('')
        : `<tr><td colspan="6" style="text-align:center;color:#64748b;padding:20px;">No matching invoices found.</td></tr>`;
    }

    async function loadInvoices() {
      const res = await fetch('api/endpoints.php?action=invoices_list');
      allInvoices = await res.json();
      updateSummaryStats();

      if (!currentInvId && allInvoices.length) {
        currentInvId = String(allInvoices[0].invoice_id);
      }
      renderInvoiceTable();

      if (currentInvId) {
        openInvoice(currentInvId);
      }
    }

    async function confirmPendingPayment(paymentId) {
      const statusMsg = document.getElementById('invStatusMsg');
      if (statusMsg) statusMsg.textContent = '';
      try {
        await postJson('api/endpoints.php?action=complete_payment', {
          payment_id: paymentId,
          invoice_id: currentInvId
        });
        await loadInvoices();
      } catch (err) {
        alert(err.message);
      }
    }

    async function changeInvoiceStatusDirect(newStatus) {
      const statusMsg = document.getElementById('invStatusMsg');
      if (statusMsg) {
        statusMsg.style.color = '#475569';
        statusMsg.textContent = 'Saving status...';
      }
      try {
        await postJson('api/endpoints.php?action=update_invoice_status', {
          invoice_id: currentInvId,
          payment_status: newStatus
        });
        await loadInvoices();
      } catch (err) {
        if (statusMsg) {
          statusMsg.style.color = '#b91c1c';
          statusMsg.textContent = err.message;
        }
      }
    }

    async function openInvoice(id) {
      currentInvId = String(id);
      renderInvoiceTable();

      const res = await fetch(`api/endpoints.php?action=invoice_detail&id=${encodeURIComponent(currentInvId)}`);
      const d = await res.json();
      if (!d.invoice) return;

      const itemsRows = (d.items || []).map(it => `
        <tr>
          <td>${esc(it.service_label)}</td>
          <td>${esc(it.quantity)}</td>
          <td>${peso(it.actual_price)}</td>
          <td>${peso(it.subtotal)}</td>
        </tr>
      `).join('') || `<tr><td colspan="4" style="color:#64748b;">No services billed yet.</td></tr>`;

      const payRows = (d.payments || []).map(p => `
        <tr>
          <td>${esc(p.payment_code)}</td>
          <td>${esc(p.reference_number || '—')}</td>
          <td>${esc(p.formatted_date)}</td>
          <td>${peso(p.amount_paid)}</td>
          <td>
            ${statusBadge(p.status)}
            ${p.status === 'Pending'
              ? `<button type="button" class="btn-sm no-print" style="margin-left:6px;" onclick="confirmPendingPayment(${jsQuote(p.payment_id)})">Mark Paid</button>`
              : ''}
          </td>
          <td>${esc(p.recorded_by)}</td>
        </tr>
      `).join('') || `<tr><td colspan="6" style="color:#64748b;">No payments recorded yet.</td></tr>`;

      document.getElementById('detailPanel').innerHTML = `
        <div class="card">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:8px;flex-wrap:wrap;">
            <div style="display:flex;align-items:center;gap:10px;">
              <h2 style="margin:0;font-size:18px;">${esc(d.invoice.invoice_code)}</h2>
              ${statusBadge(d.invoice.payment_status)}
            </div>
            <div class="no-print" style="display:flex;align-items:center;gap:8px;">
              <select id="directInvStatus" style="padding:5px 8px;border:1px solid #cbd5e1;border-radius:6px;font-size:12.5px;">
                <option value="Unpaid" ${d.invoice.payment_status === 'Unpaid' ? 'selected' : ''}>Unpaid</option>
                <option value="Pending" ${d.invoice.payment_status === 'Pending' ? 'selected' : ''}>Pending</option>
                <option value="Partially Paid" ${(d.invoice.payment_status === 'Partially Paid' || d.invoice.payment_status === 'Partial') ? 'selected' : ''}>Partially Paid</option>
                <option value="Paid" ${d.invoice.payment_status === 'Paid' ? 'selected' : ''}>Paid</option>
              </select>
              <button type="button" class="btn-outline" onclick="window.print()">Print receipt</button>
            </div>
          </div>
          <div id="invStatusMsg" style="font-size:12.5px;margin-bottom:8px;"></div>
          <p style="font-size:13px;color:#475569;margin:0 0 4px;">
            Request: <strong>${esc(d.invoice.request_code)}</strong> · Issued: <strong>${esc(d.invoice.formatted_date)}</strong>
          </p>
          <p style="font-size:13px;color:#475569;margin:0 0 12px;">
            Customer: <strong>${esc(d.invoice.customer_name)}</strong> (${esc(d.invoice.contact_number || '')}) · Gadget: <strong>${esc(d.invoice.gadget_name)}</strong>
          </p>
          <table>
            <thead><tr><th>Service</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr></thead>
            <tbody>${itemsRows}</tbody>
          </table>
        </div>

        <div class="card">
          <h3 style="margin:0 0 10px;font-size:16px;">Balance summary</h3>
          <div style="display:flex;justify-content:space-between;padding:6px 0;"><span>Total billed</span><strong>${peso(d.total)}</strong></div>
          <div style="display:flex;justify-content:space-between;padding:6px 0;color:#15803d;"><span>Amount paid (Completed)</span><strong>${peso(d.paid)}</strong></div>
          ${Number(d.pending_paid) > 0
            ? `<div style="display:flex;justify-content:space-between;padding:6px 0;color:#1d4ed8;"><span>Pending verification</span><strong>${peso(d.pending_paid)}</strong></div>`
            : ''}
          <div style="display:flex;justify-content:space-between;padding:8px 0 2px;border-top:1px solid #e2e8f0;font-size:15px;"><span>Balance due</span><strong>${peso(d.balance)}</strong></div>
        </div>

        <div class="card">
          <h3 style="margin:0 0 10px;font-size:16px;">Payments</h3>
          <div style="overflow-x:auto;">
            <table>
              <thead><tr><th>Payment</th><th>Ref / Method</th><th>Date</th><th>Amount</th><th>Status</th><th>Recorded by</th></tr></thead>
              <tbody>${payRows}</tbody>
            </table>
          </div>
        </div>

        <div id="recordPaymentCard" class="card">
          <h3 style="margin:0 0 4px;font-size:16px;">Record a payment</h3>
          <p style="font-size:12px;color:#64748b;margin:0 0 12px;">Completed payments deduct from balance; Pending marks the invoice Pending until confirmed.</p>
          <form id="payForm">
            <div class="grid-2">
              <div class="field">
                <label>Payment method</label>
                <select id="pay_method">
                  <option value="Cash">Cash</option>
                  <option value="GCash">GCash</option>
                  <option value="Maya">Maya</option>
                  <option value="Bank Transfer">Bank Transfer</option>
                  <option value="Card">Card</option>
                </select>
              </div>
              <div class="field"><label>Reference number</label><input id="pay_ref" type="text" placeholder="e.g., GC-88231"></div>
            </div>

            <div class="grid-2">
              <div class="field"><label>Payment date</label><input id="pay_date" type="date" value="${new Date().toISOString().slice(0,10)}" required></div>
              <div class="field">
                <label>Payment status</label>
                <select id="pay_status">
                  <option value="Completed" selected>Completed</option>
                  <option value="Pending">Pending</option>
                  <option value="Unpaid">Unpaid</option>
                </select>
              </div>
            </div>

            <div class="grid-2">
              <div class="field"><label>Amount paid (₱)</label><input id="pay_amt" type="number" step="0.01" min="0" value="${d.balance > 0 ? d.balance : ''}" required></div>
              <div class="field"><label>Recorded by</label><input id="pay_by" type="text" value="${esc(defaultRecorder)}" required></div>
            </div>

            <button type="submit" class="btn btn-green">Save payment</button>
            <div id="payMsg" style="margin-top:8px;font-size:13px;color:#b91c1c;"></div>
          </form>
        </div>
      `;

      document.getElementById('directInvStatus').addEventListener('change', (e) => {
        changeInvoiceStatusDirect(e.target.value);
      });

      const payStatusEl = document.getElementById('pay_status');
      const payAmtEl = document.getElementById('pay_amt');
      payStatusEl.addEventListener('change', () => {
        if (payStatusEl.value === 'Unpaid') {
          payAmtEl.value = '0';
          payAmtEl.disabled = true;
        } else {
          payAmtEl.disabled = false;
          if (Number(payAmtEl.value) <= 0 && d.balance > 0) {
            payAmtEl.value = d.balance;
          }
        }
      });

      document.getElementById('payForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const errBox = document.getElementById('payMsg');
        errBox.textContent = '';

        try {
          await postJson('api/endpoints.php?action=record_payment', {
            invoice_id: currentInvId,
            payment_method: document.getElementById('pay_method').value,
            reference_number: document.getElementById('pay_ref').value.trim(),
            payment_date: document.getElementById('pay_date').value,
            amount_paid: payAmtEl.value,
            status: payStatusEl.value,
            recorded_by: document.getElementById('pay_by').value.trim()
          });
          await loadInvoices();
        } catch (err) {
          errBox.textContent = err.message;
        }
      });
    }

    document.querySelectorAll('#statusFilters .f-pill').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('#statusFilters .f-pill').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        statusFilter = btn.dataset.st;
        renderInvoiceTable();
      });
    });

    document.getElementById('invSearch').addEventListener('input', renderInvoiceTable);
    loadInvoices();
  </script>
</body>
</html>