<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Create staff account — Gadget Repair Shop</title>
  <link rel="stylesheet" href="assets/style.css">
  <style>
    .auth-shell {
      min-height: 100vh;
      display: grid;
      grid-template-columns: 280px 1fr;
      background: #f8fafc;
    }
    .auth-aside {
      background: #0f172a;
      color: #cbd5e1;
      padding: 32px 24px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    .auth-aside h2 {
      color: #ffffff;
      font-size: 20px;
      margin-bottom: 8px;
    }
    .auth-aside p {
      font-size: 13px;
      color: #94a3b8;
      line-height: 1.5;
    }
    .auth-main {
      display: grid;
      place-items: center;
      padding: 28px;
    }
    .auth-card {
      width: 100%;
      max-width: 480px;
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 30px;
    }
    .brand-tag {
      color: #059669;
      font-weight: 700;
      font-size: 13px;
      margin-bottom: 6px;
    }
    .auth-card h1 {
      font-size: 24px;
      font-weight: 700;
      margin-bottom: 4px;
    }
    .auth-sub {
      color: #64748b;
      font-size: 13px;
      margin-bottom: 20px;
    }
    .grid-3 {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px;
    }
    .grid-2 {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 12px;
    }
    .field {
      margin-bottom: 14px;
    }
    .field label {
      display: block;
      font-size: 13px;
      font-weight: 600;
      margin-bottom: 6px;
    }
    .field input,
    .field select {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid #cbd5e1;
      border-radius: 8px;
      font: inherit;
      background: #ffffff;
      color: #0f172a;
    }
    .pw-wrap {
      position: relative;
      display: flex;
      align-items: center;
    }
    .pw-wrap input {
      padding-right: 42px;
    }
    .eye-btn {
      position: absolute;
      right: 8px;
      width: 30px;
      height: 30px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: transparent;
      border: none;
      border-radius: 6px;
      color: #64748b;
      cursor: pointer;
      user-select: none;
      -webkit-user-select: none;
      touch-action: manipulation;
    }
    .eye-btn:hover,
    .eye-btn.active {
      color: #0f172a;
      background: #f1f5f9;
    }
    .msg-err {
      margin-top: 10px;
      color: #b91c1c;
      font-size: 13px;
      font-weight: 500;
    }
    .switch-link {
      margin-top: 16px;
      padding-top: 14px;
      border-top: 1px solid #e2e8f0;
      font-size: 13px;
      text-align: center;
    }
    .switch-link a {
      color: #059669;
      font-weight: 600;
      text-decoration: none;
    }
    @media (max-width: 820px) {
      .auth-shell {
        grid-template-columns: 1fr;
      }
      .auth-aside {
        display: none;
      }
      .grid-3,
      .grid-2 {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
  <div class="auth-shell">
    <aside class="auth-aside">
      <div>
        <h2>Gadget Fix Shop</h2>
        <p>Create a staff account to access repair ticket intake, diagnostics, billing, and customer records.</p>
      </div>
      <p style="font-size:12px;">Passwords are hashed securely before saving to the database.</p>
    </aside>

    <main class="auth-main">
      <div class="auth-card">
        <div class="brand-tag">Gadget Fix Shop</div>
        <h1>Create a staff account</h1>
        <p class="auth-sub">Register your staff profile to sign in.</p>

        <form id="signupForm">
          <div class="grid-3">
            <div class="field">
              <label for="first_name">First name</label>
              <input id="first_name" type="text" required autofocus>
            </div>
            <div class="field">
              <label for="middle_name">Middle name</label>
              <input id="middle_name" type="text">
            </div>
            <div class="field">
              <label for="last_name">Last name</label>
              <input id="last_name" type="text" required>
            </div>
          </div>

          <div class="grid-2">
            <div class="field">
              <label for="username">Username</label>
              <input id="username" type="text" required>
            </div>
            <div class="field">
              <label for="password">Password</label>
              <div class="pw-wrap">
                <input id="password" type="password" required>
                <button
                  type="button"
                  id="togglePwBtn"
                  class="eye-btn"
                  aria-label="Show or hold to peek at password"
                  title="Click to toggle or press and hold to peek"
                >
                  <svg id="eyeOpenIcon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                  <svg id="eyeOffIcon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                    <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"></path>
                    <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"></path>
                    <line x1="1" y1="1" x2="23" y2="23"></line>
                  </svg>
                </button>
              </div>
            </div>
          </div>

          <div class="grid-2">
            <div class="field">
              <label for="contact_number">Contact number</label>
              <input id="contact_number" type="text" required>
            </div>
            <div class="field">
              <label for="role">Role</label>
              <select id="role">
                <option value="Technician" selected>Technician</option>
                <option value="Receptionist">Receptionist</option>
                <option value="Cashier">Cashier</option>
                <option value="Admin">Admin</option>
                <option value="Manager">Manager</option>
              </select>
            </div>
          </div>

          <div class="field">
            <label for="specialization">Specialization (technicians only)</label>
            <input id="specialization" type="text" placeholder="e.g., Mobile & Micro-soldering">
          </div>

          <button type="submit" class="btn btn-green" style="width:100%;">Create account</button>
          <div id="signupErr" class="msg-err"></div>
        </form>

        <div class="switch-link">
          Already have an account? <a href="login.php">Sign in</a>
        </div>
      </div>
    </main>
  </div>

  <script>
    const pwInput = document.getElementById('password');
    const togglePwBtn = document.getElementById('togglePwBtn');
    const eyeOpenIcon = document.getElementById('eyeOpenIcon');
    const eyeOffIcon = document.getElementById('eyeOffIcon');

    let isVisible = false;
    let holdTimer = null;
    let wasLongPress = false;

    function setPasswordVisibility(visible) {
      isVisible = visible;
      pwInput.type = visible ? 'text' : 'password';
      eyeOpenIcon.style.display = visible ? 'none' : 'inline';
      eyeOffIcon.style.display = visible ? 'inline' : 'none';
      togglePwBtn.classList.toggle('active', visible);
    }

    function startHoldPeek() {
      wasLongPress = false;
      clearTimeout(holdTimer);
      holdTimer = setTimeout(() => {
        wasLongPress = true;
        setPasswordVisibility(true);
      }, 220);
    }

    function endHoldPeek() {
      clearTimeout(holdTimer);
      if (wasLongPress) {
        setPasswordVisibility(false);
        setTimeout(() => { wasLongPress = false; }, 0);
      }
    }

    togglePwBtn.addEventListener('mousedown', startHoldPeek);
    togglePwBtn.addEventListener('touchstart', startHoldPeek, { passive: true });
    window.addEventListener('mouseup', endHoldPeek);
    window.addEventListener('touchend', endHoldPeek);
    togglePwBtn.addEventListener('mouseleave', () => {
      if (wasLongPress) endHoldPeek();
    });

    togglePwBtn.addEventListener('click', () => {
      if (wasLongPress) return;
      setPasswordVisibility(!isVisible);
    });

    document.getElementById('signupForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const errEl = document.getElementById('signupErr');
      errEl.textContent = '';

      try {
        const res = await fetch('api/endpoints.php?action=create_staff', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            first_name: document.getElementById('first_name').value.trim(),
            middle_name: document.getElementById('middle_name').value.trim(),
            last_name: document.getElementById('last_name').value.trim(),
            username: document.getElementById('username').value.trim(),
            password: pwInput.value,
            contact_number: document.getElementById('contact_number').value.trim(),
            role: document.getElementById('role').value,
            specialization: document.getElementById('specialization').value.trim() || null
          })
        });
        const data = await res.json();
        if (res.ok && data.ok) {
          window.location.href = 'login.php?registered=1';
        } else {
          errEl.textContent = data.message || data.error || 'Could not create account.';
        }
      } catch (err) {
        errEl.textContent = 'Unable to reach the server. Check Apache and MySQL in XAMPP.';
      }
    });
  </script>
</body>
</html>