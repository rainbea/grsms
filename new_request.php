<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/api/db.php';

$receivedBy = $_SESSION['staff']['full_name'] ?? 'Sarah Gomez';
$submittedDisplay = date('M j, Y');

$customers = $pdo->query("
    SELECT customer_ID,
           TRIM(CONCAT(first_name, ' ', IFNULL(CONCAT(middle_name, ' '), ''), last_name)) AS full_name,
           contact_number, email, barangay, city, province, zip_code
    FROM customer
    ORDER BY first_name ASC, last_name ASC
")->fetchAll();

$techs = $pdo->query("
    SELECT user_id, TRIM(CONCAT(first_name, ' ', last_name)) AS full_name, specialization
    FROM system_user
    WHERE role = 'Technician' AND account_status = 'Active'
    ORDER BY first_name ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>New repair request — Gadget Repair Shop</title>
  <link rel="stylesheet" href="assets/style.css">
  <style>
    .req-stack { max-width: 780px; display: flex; flex-direction: column; gap: 20px; }
    .section-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; }
    .section-card h2 { font-size: 18px; font-weight: 700; margin: 0 0 4px; }
    .section-sub { font-size: 13px; color: #64748b; margin: 0 0 18px; }
    .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
    .grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; }
    .field { margin-bottom: 14px; }
    .field label { display: block; font-size: 13px; font-weight: 600; color: #1e293b; margin-bottom: 6px; }
    .field input, .field select, .field textarea {
      width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px;
      font: inherit; background: #fff; color: #0f172a;
    }
    .field input[readonly] { background: #f8fafc; color: #475569; }
    .form-actions { display: flex; align-items: center; gap: 14px; margin-top: 6px; }
    .cancel-link { color: #475569; text-decoration: none; font-size: 14px; font-weight: 500; }
    .err-text { color: #b91c1c; font-size: 13px; font-weight: 600; }
    @media (max-width: 720px) { .grid-3, .grid-2 { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
  <div class="layout">
    <?php include __DIR__ . '/partials/nav.php'; ?>
    <main class="main">
      <div class="page-header"><h1>New repair request</h1></div>

      <form id="newReqForm" class="req-stack">
        <section class="section-card">
          <h2>Customer</h2>
          <p class="section-sub">Saved to the customer table. Date registered is set automatically.</p>

          <div class="field">
            <label for="returningCustomerSelect">Returning customer? Search by name or contact number</label>
            <input list="customerDatalist" id="returningCustomerSelect" placeholder="Type name or phone to autofill...">
            <datalist id="customerDatalist">
              <?php foreach ($customers as $c): ?>
                <option
                  value="<?= htmlspecialchars($c['full_name'] . ' (' . $c['contact_number'] . ')') ?>"
                  data-id="<?= htmlspecialchars($c['customer_ID']) ?>"
                  data-name="<?= htmlspecialchars($c['full_name']) ?>"
                  data-phone="<?= htmlspecialchars($c['contact_number']) ?>"
                  data-email="<?= htmlspecialchars($c['email'] ?? '') ?>"
                  data-barangay="<?= htmlspecialchars($c['barangay'] ?? '') ?>"
                  data-city="<?= htmlspecialchars($c['city'] ?? '') ?>"
                  data-province="<?= htmlspecialchars($c['province'] ?? '') ?>"
                  data-zip="<?= htmlspecialchars($c['zip_code'] ?? '') ?>"
                ></option>
              <?php endforeach; ?>
            </datalist>
            <input type="hidden" id="existing_customer_id" value="">
          </div>

          <div class="grid-3">
            <div class="field"><label for="first_name">First name</label><input id="first_name" type="text" required></div>
            <div class="field"><label for="middle_name">Middle name</label><input id="middle_name" type="text"></div>
            <div class="field"><label for="last_name">Last name</label><input id="last_name" type="text" required></div>
          </div>

          <div class="grid-2">
            <div class="field"><label for="contact_number">Contact number</label><input id="contact_number" type="text" required></div>
            <div class="field"><label for="email">Email</label><input id="email" type="email"></div>
          </div>

          <div class="grid-3">
            <div class="field"><label for="barangay">Barangay</label><input id="barangay" type="text"></div>
            <div class="field"><label for="city">City</label><input id="city" type="text"></div>
            <div class="field"><label for="province">Province</label><input id="province" type="text"></div>
          </div>

          <div class="field" style="max-width:240px;margin-bottom:0;">
            <label for="zip_code">Zip code</label><input id="zip_code" type="text">
          </div>
        </section>

        <section class="section-card">
          <h2>Gadget</h2>
          <p class="section-sub">Saved to the gadget table and linked to the customer. Date registered is set automatically.</p>

          <div class="grid-3">
            <div class="field">
              <label for="category">Category</label>
              <select id="category">
                <option value="Smartphone" selected>Smartphone</option>
                <option value="Tablet">Tablet</option>
                <option value="Laptop">Laptop</option>
                <option value="Desktop">Desktop</option>
                <option value="Console">Console</option>
                <option value="Accessory">Accessory</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div class="field"><label for="brand">Brand</label><input id="brand" type="text" required></div>
            <div class="field"><label for="model">Model</label><input id="model" type="text" required></div>
          </div>

          <div class="field" style="margin-bottom:0;">
            <label for="serial_number">Serial number or IMEI</label><input id="serial_number" type="text" required>
          </div>
        </section>

        <section class="section-card">
          <h2>Repair request</h2>
          <p class="section-sub">Saved to the repair_request table. The first status, Received, is added to the status history automatically.</p>

          <div class="field"><label for="reported_issue">Reported issue</label><textarea id="reported_issue" rows="3" required></textarea></div>
          <div class="field"><label for="initial_condition">Initial condition</label><textarea id="initial_condition" rows="3"></textarea></div>
          <div class="field"><label for="accessories_included">Accessories included</label><input id="accessories_included" type="text"></div>

          <div class="grid-3">
            <div class="field">
              <label for="assigned_tech_id">Assigned technician</label>
              <select id="assigned_tech_id">
                <?php foreach ($techs as $t): ?>
                  <option value="<?= htmlspecialchars($t['user_id']) ?>">
                    <?= htmlspecialchars($t['full_name'] . ($t['specialization'] ? ' · ' . $t['specialization'] : '')) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field"><label for="received_by">Received by</label><input id="received_by" type="text" value="<?= htmlspecialchars($receivedBy) ?>" readonly></div>
            <div class="field"><label for="date_submitted">Date submitted</label><input id="date_submitted" type="text" value="<?= htmlspecialchars($submittedDisplay) ?>" readonly></div>
          </div>

          <p style="font-size:12px;color:#64748b;margin-bottom:14px;">Services and prices are added on the request page after the technician checks the gadget.</p>

          <div class="form-actions">
            <button type="submit" class="btn btn-green">Create request</button>
            <a href="repair_requests.php" class="cancel-link">Cancel</a>
            <span id="reqErr" class="err-text"></span>
          </div>
        </section>
      </form>
    </main>
  </div>

  <script>
    const retInput = document.getElementById('returningCustomerSelect');
    const existingIdInput = document.getElementById('existing_customer_id');
    const opts = Array.from(document.querySelectorAll('#customerDatalist option'));

    retInput.addEventListener('input', () => {
      const match = opts.find(o => o.value === retInput.value);
      if (match) {
        existingIdInput.value = match.dataset.id || '';
        const parts = (match.dataset.name || '').trim().split(/\s+/);
        document.getElementById('first_name').value = parts[0] || '';
        document.getElementById('middle_name').value = parts.length > 2 ? parts.slice(1, -1).join(' ') : '';
        document.getElementById('last_name').value = parts.length > 1 ? parts[parts.length - 1] : '';
        document.getElementById('contact_number').value = match.dataset.phone || '';
        document.getElementById('email').value = match.dataset.email || '';
        document.getElementById('barangay').value = match.dataset.barangay || '';
        document.getElementById('city').value = match.dataset.city || '';
        document.getElementById('province').value = match.dataset.province || '';
        document.getElementById('zip_code').value = match.dataset.zip || '';
      } else {
        existingIdInput.value = '';
      }
    });

    document.getElementById('newReqForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const errEl = document.getElementById('reqErr');
      errEl.textContent = '';

      const res = await fetch('api/endpoints.php?action=create_request_full', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          existing_customer_id: existingIdInput.value,
          first_name: document.getElementById('first_name').value.trim(),
          middle_name: document.getElementById('middle_name').value.trim(),
          last_name: document.getElementById('last_name').value.trim(),
          contact_number: document.getElementById('contact_number').value.trim(),
          email: document.getElementById('email').value.trim(),
          barangay: document.getElementById('barangay').value.trim(),
          city: document.getElementById('city').value.trim(),
          province: document.getElementById('province').value.trim(),
          zip_code: document.getElementById('zip_code').value.trim(),
          category: document.getElementById('category').value,
          brand: document.getElementById('brand').value.trim(),
          model: document.getElementById('model').value.trim(),
          serial_number: document.getElementById('serial_number').value.trim(),
          reported_issue: document.getElementById('reported_issue').value.trim(),
          initial_condition: document.getElementById('initial_condition').value.trim(),
          accessories_included: document.getElementById('accessories_included').value.trim(),
          assigned_tech_id: document.getElementById('assigned_tech_id').value
        })
      });
      const data = await res.json();
      if (res.ok && data.ok) {
        window.location.href = `request_detail.php?id=${encodeURIComponent(data.request_id)}`;
      } else {
        errEl.textContent = data.error || data.message || 'Could not create request.';
      }
    });
  </script>
</body>
</html>