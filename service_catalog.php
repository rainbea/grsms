<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Service catalog — Gadget Repair Shop</title>
  <link rel="stylesheet" href="assets/style.css">
  <style>
    .cat-grid { display: grid; grid-template-columns: 1.45fr 1fr; gap: 24px; align-items: start; }
    .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; }
    table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
    th { text-align: left; padding: 10px 12px; font-size: 12px; color: #475569; border-bottom: 1px solid #e2e8f0; }
    td { padding: 12px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
    .field { margin-bottom: 12px; }
    .field label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 5px; }
    .field input, .field textarea { width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font: inherit; }
    .edit-btn { background: none; border: none; color: #059669; font-weight: 600; cursor: pointer; text-decoration: underline; }
    .similar-box {
      display: none;
      margin-top: 8px;
      padding: 10px 12px;
      border-radius: 8px;
      background: #fffbeb;
      border: 1px solid #fde68a;
      font-size: 12.5px;
      color: #92400e;
    }
    .similar-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 8px;
      padding: 5px 0;
      border-top: 1px dashed #fcd34d;
    }
    .similar-item:first-of-type { border-top: none; padding-top: 2px; }
    .use-sim-btn {
      background: #fef3c7;
      border: 1px solid #d97706;
      color: #92400e;
      border-radius: 6px;
      padding: 3px 8px;
      font-size: 11.5px;
      font-weight: 600;
      cursor: pointer;
      white-space: nowrap;
    }
    .use-sim-btn:hover { background: #fde68a; }
    @media (max-width: 900px) { .cat-grid { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
  <div class="layout">
    <?php include __DIR__ . '/partials/nav.php'; ?>
    <main class="main">
      <div class="page-header"><h1>Service catalog</h1></div>

      <div class="cat-grid">
        <div class="card" style="padding:0;overflow-x:auto;">
          <table>
            <thead>
              <tr><th>Service</th><th>Description</th><th>Price</th><th></th></tr>
            </thead>
            <tbody id="srvBody"></tbody>
          </table>
        </div>

        <div class="card">
          <h2 id="formTitle" style="font-size:18px;font-weight:700;margin:0 0 4px;">Add a service</h2>
          <p style="font-size:12.5px;color:#64748b;margin:0 0 16px;">Saved to the service table.</p>

          <form id="srvForm">
            <input type="hidden" id="service_id" value="">
            <div class="field">
              <label for="service_name">Service name</label>
              <input id="service_name" type="text" autocomplete="off" required>
              <div id="similarBox" class="similar-box"></div>
            </div>
            <div class="field"><label for="description">Description</label><textarea id="description" rows="2" required></textarea></div>
            <div class="field"><label for="default_price">Price (₱)</label><input id="default_price" type="number" step="0.01" min="0" required></div>

            <div style="display:flex;gap:10px;align-items:center;">
              <button type="submit" class="btn btn-green">Save service</button>
              <button type="button" id="resetBtn" class="btn" style="background:#f1f5f9;color:#334155;">Clear</button>
            </div>
            <p style="font-size:12px;color:#64748b;margin:12px 0 0;">The price here is the default. The actual price can be changed on each repair request.</p>
            <div id="srvMsg" style="margin-top:8px;font-size:13px;"></div>
          </form>
        </div>
      </div>
    </main>
  </div>

  <script>
    let servicesCache = [];
    const peso = n => '₱' + Number(n || 0).toLocaleString('en-US', { maximumFractionDigits: 2 });
    const esc = s => String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
    const jsQuote = v => "'" + String(v ?? '').replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";

    function findSimilarServices(typedName, editingId) {
      const clean = typedName.trim().toLowerCase().replace(/[^a-z0-9\s]/g, ' ');
      if (clean.length < 2) return [];
      const words = clean.split(/\s+/).filter(w => w.length >= 2);

      return servicesCache
        .filter(s => String(s.service_id) !== String(editingId || ''))
        .map(s => {
          const target = String(s.service_name || '').toLowerCase();
          const targetClean = target.replace(/[^a-z0-9\s]/g, ' ');
          let score = 0;

          if (target === typedName.trim().toLowerCase()) score += 100;
          else if (target.includes(typedName.trim().toLowerCase()) || typedName.trim().toLowerCase().includes(target)) score += 60;

          for (const w of words) {
            if (targetClean.includes(w)) score += 25;
          }
          return { service: s, score };
        })
        .filter(entry => entry.score >= 25)
        .sort((a, b) => b.score - a.score)
        .slice(0, 4)
        .map(entry => entry.service);
    }

    function updateSimilarAlert() {
      const typed = document.getElementById('service_name').value;
      const editingId = document.getElementById('service_id').value;
      const box = document.getElementById('similarBox');
      const matches = findSimilarServices(typed, editingId);

      if (!matches.length) {
        box.style.display = 'none';
        box.innerHTML = '';
        return;
      }

      box.style.display = 'block';
      box.innerHTML = `
        <div style="font-weight:700;margin-bottom:4px;">Similar service already in catalog:</div>
        ${matches.map(m => `
          <div class="similar-item">
            <span><strong>${esc(m.service_name)}</strong> (${peso(m.default_price)})</span>
            <button type="button" class="use-sim-btn" onclick="editSrv(${jsQuote(m.service_id)})">Edit this</button>
          </div>
        `).join('')}
      `;
    }

    async function loadServices() {
      const res = await fetch('api/endpoints.php?action=services_list');
      servicesCache = await res.json();
      document.getElementById('srvBody').innerHTML = servicesCache.map(s => `
        <tr>
          <td style="font-weight:600;">${esc(s.service_name)}</td>
          <td style="color:#475569;">${esc(s.description)}</td>
          <td style="white-space:nowrap;">${peso(s.default_price)}</td>
          <td><button type="button" class="edit-btn" onclick="editSrv(${jsQuote(s.service_id)})">Edit</button></td>
        </tr>
      `).join('');
      updateSimilarAlert();
    }

    function editSrv(id) {
      const item = servicesCache.find(x => String(x.service_id) === String(id));
      if (!item) return;
      document.getElementById('formTitle').textContent = 'Edit service';
      document.getElementById('service_id').value = item.service_id;
      document.getElementById('service_name').value = item.service_name;
      document.getElementById('description').value = item.description;
      document.getElementById('default_price').value = item.default_price;
      updateSimilarAlert();
    }

    document.getElementById('service_name').addEventListener('input', updateSimilarAlert);

    document.getElementById('resetBtn').addEventListener('click', () => {
      document.getElementById('formTitle').textContent = 'Add a service';
      document.getElementById('service_id').value = '';
      document.getElementById('srvForm').reset();
      document.getElementById('srvMsg').textContent = '';
      updateSimilarAlert();
    });

    document.getElementById('srvForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const msg = document.getElementById('srvMsg');
      const res = await fetch('api/endpoints.php?action=save_service', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          service_id: document.getElementById('service_id').value,
          service_name: document.getElementById('service_name').value.trim(),
          description: document.getElementById('description').value.trim(),
          default_price: document.getElementById('default_price').value
        })
      });
      const data = await res.json();
      if (res.ok && data.ok) {
        msg.style.color = '#15803d';
        msg.textContent = 'Service saved.';
        document.getElementById('resetBtn').click();
        loadServices();
      } else {
        msg.style.color = '#b91c1c';
        msg.textContent = data.message || data.error || 'Error saving service.';
      }
    });

    loadServices();
  </script>
</body>
</html>