<?php

require_once "../includes/config.inc.php";
require_once "../includes/dbh.inc.php";
require_once "../includes/users.class.php";

$otpCooldownRemaining = 0;
$now = time();

if (!empty($_SESSION['otp_cooldown_until'])) {
    $remaining = (int) $_SESSION['otp_cooldown_until'] - $now;
    if ($remaining > 0) {
        $otpCooldownRemaining = $remaining;
    } else {
        unset($_SESSION['otp_cooldown_until']);
    }
}

if (!empty($_SESSION['resend_cooldown_until']) && is_array($_SESSION['resend_cooldown_until'])) {
    foreach ($_SESSION['resend_cooldown_until'] as $emailKey => $until) {
        $remaining = (int) $until - $now;
        if ($remaining > 0) {
            $otpCooldownRemaining = max($otpCooldownRemaining, $remaining);
        } else {
            unset($_SESSION['resend_cooldown_until'][$emailKey]);
        }
    }
}

$hasActiveOtpSession = !empty($_SESSION['otp']['key']) && $now <= (int) ($_SESSION['otp']['expiry'] ?? 0);
$hasVerifiedSession  = !empty($_SESSION['verified_email']);
$serverSessionActive = $hasActiveOtpSession || $hasVerifiedSession;
$serverActiveEmail   = $hasVerifiedSession ? ($_SESSION['verified_email'] ?? '') : ($hasActiveOtpSession ? ($_SESSION['otp']['email'] ?? '') : '');
$serverActiveStage   = $hasVerifiedSession ? 'form' : ($hasActiveOtpSession ? 'otp' : '');

$initialOrders = [];
$initialCustomer = ['firstname' => '', 'lastname' => ''];
if ($hasVerifiedSession && !empty($serverActiveEmail)) {
    $initialOrders = users::getUserOrdersByEmail($pdo, $serverActiveEmail);
    if (!empty($initialOrders)) {
        $initialCustomer = [
            'firstname' => $initialOrders[0]['first_name'] ?? '',
            'lastname'  => $initialOrders[0]['last_name'] ?? '',
        ];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Help & Support</title>

  <link rel="stylesheet" href="../style/support.css" />


</head>
<body>

<header style="height: 250px;">
<div class="logoitems">
    <img src="../images/logo.webp" alt="betta bud logo" id="navlogo" class="navlogo" style="width: 50px;">
    <h1 id="menu" style="font: arial;" >The Betta Shop</h1>
</div>
<br>
  <h1>Help & Support</h1>
  <p>Let's work together to resolve an issue with your order.</p>
</header>

<main>

  <div id="refreshWarningModal" class="refresh-modal" aria-hidden="true">
    <div class="refresh-modal-card" role="dialog" aria-modal="true" aria-labelledby="refreshWarningTitle">
      <h3 id="refreshWarningTitle">Verification session active</h3>
      <p>You have an active OTP verification session. A new verification code can only be sent after 60 seconds. Are you sure you want to refresh and restart?</p>
      <div class="refresh-modal-actions">
        <button type="button" id="btnRefreshYes" class="btn-submit">Yes</button>
        <button type="button" id="btnRefreshNo" class="btn-secondary">No</button>
      </div>
    </div>
  </div>

  <div class="policy-banner" style="">
        <strong>DOA Policy</strong>
    <p>To ensure your request is processed swiftly, please ensure you have read and understand our Refund & Returns Policy before submitting a claim.</p>
  </div>

  <!-- ── Progress Steps ── -->
  <div class="steps-bar">
    <div class="step-item active" id="stepItem1">
      <div class="step-circle">1</div>
      <div class="step-label">Email</div>
    </div>
    <div class="step-line" id="stepLine1"></div>
    <div class="step-item" id="stepItem2">
      <div class="step-circle">2</div>
      <div class="step-label">Verify</div>
    </div>
    <div class="step-line" id="stepLine2"></div>
    <div class="step-item" id="stepItem3">
      <div class="step-circle">3</div>
      <div class="step-label">Submit</div>
    </div>
  </div>

  <div class="card">

    <!-- ── Stage 1: Email Entry ── -->
    <div id="stage-email">
      <p class="section-label">Verify your email to begin</p>
      <p class="stage-intro">We'll send a 6-digit code to confirm your identity and look up your past orders.</p>
      <div class="field">
        <label for="emailInput">Email address <span class="req">*</span></label>
        <input type="email" id="emailInput" name="emailInput" autocomplete="email" placeholder="jane@example.com" />
        <div class="field-error" id="err-emailInput">Please enter a valid email address.</div>
      </div>
      <div class="email-match-note">
        <strong>Important:</strong> Please enter the same email address you used when placing your order. Your order information will only appear if the email matches an existing order.
      </div>
      <button type="button" class="btn-submit" id="btnSendCode">Send Verification Code</button>
      <div class="check-status-prompt" style="text-align: center; margin-top: 16px; font-size: 0.86rem; color: var(--muted);">
        Already submitted a refund request?
        <a href="../my-portal/customer/support.php" style="color: var(--blue-light); font-weight: 600; text-decoration: underline; margin-left: 4px;">Check Request Status</a>
      </div>
    </div>

    <!-- ── Stage 2: OTP ── -->
    <div id="stage-otp" style="display:none">
      <p class="section-label">Enter verification code</p>
      <p class="otp-sent-msg">A 6-digit code was sent to <strong id="otpEmailDisplay"></strong></p>
      <div class="demo-notice" id="demoCodeNotice"></div>
      <div class="otp-inputs" id="otpInputs">
        <input class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="one-time-code" />
        <input class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" />
        <input class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" />
        <input class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" />
        <input class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" />
        <input class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" />
      </div>
      <div class="field-error" id="err-otp" style="text-align:center;margin-bottom:4px;">Incorrect code — please try again.</div>
      <button type="button" class="btn-submit" id="btnVerify">Verify</button>
      <div class="otp-actions">
        <a href="#" id="btnResend">Resend code</a>
        <span class="otp-sep">·</span>
        <a href="#" id="btnChangeEmail">Change email</a>
      </div>
    </div>

    <!-- ── Stage 3: Full Form ── -->
    <div id="stage-form" style="display:none">

      <div class="verified-badge">
        <span>✉️</span>
        <span id="verifiedEmailDisplay" style="font-weight:600;"></span>
        <span class="verified-tag">✓ Verified</span>
      </div>

      <div class="no-orders-banner" id="noOrdersNotice" style="display:none">
        <p><strong>No orders found for this email address.</strong> Please go back and enter the email address you used when placing your order.</p>
        <a href="#" id="btnNoOrdersBack">Go back and try a different email</a>
      </div>

      <form id="doaForm" novalidate>

        <p class="section-label">Your Details</p>

        <div class="row-2">
          <div class="field">
            <label for="firstName">First name <span class="req">*</span></label>
            <input type="text" id="firstName" name="firstName" autocomplete="given-name" placeholder="Jane" />
            <div class="field-error" id="err-firstName">Please enter your first name.</div>
          </div>
          <div class="field">
            <label for="lastName">Last name <span class="req">*</span></label>
            <input type="text" id="lastName" name="lastName" autocomplete="family-name" placeholder="Smith" />
            <div class="field-error" id="err-lastName">Please enter your last name.</div>
          </div>
        </div>

        <hr class="divider" />
        <p class="section-label">Order Information</p>

        <div class="row-2">
          <div class="field">
            <label for="orderNumber">Order <span class="req">*</span></label>
            <select id="orderNumber" name="orderNumber">
              <option value="">— Select your order —</option>
              <!-- Populated from database based on verified email -->
              <option value="ORD-10482">ORD-10482 — May 15, 2026</option>
              <option value="ORD-10391">ORD-10391 — Apr 28, 2026</option>
              <option value="ORD-10307">ORD-10307 — Apr 10, 2026</option>
            </select>
            <div class="field-error" id="err-orderNumber">Please select your order.</div>
          </div>
          <div class="field">
            <label for="deliveryDate">Delivery date <span class="req">*</span></label>
            <input type="date" id="deliveryDate" name="deliveryDate" />
            <div class="field-error" id="err-deliveryDate">Please select the delivery date.</div>
          </div>
        </div>

        <hr class="divider" />
        <p class="section-label">DOA Details</p>

        <div class="field">
          <label>Fish SKU(s) <span class="req">*</span> <span class="hint">Select all affected items</span></label>
          <div class="sku-box" id="skuBox">
            <div class="sku-placeholder">Select an order above to see items</div>
          </div>
          <div class="field-error" id="err-fishSku">Please select at least one SKU or "I don't know".</div>
        </div>

        <div class="row-2">
          <div class="field">
            <label for="doaCount">Number of DOA fish <span class="req">*</span></label>
            <input type="number" id="doaCount" name="doaCount" min="1" max="999" placeholder="0" />
            <div class="field-error" id="err-doaCount">Please enter a valid quantity (1 or more).</div>
          </div>
          <div class="field">
            <label for="resolution">Preferred resolution <span class="req">*</span></label>
            <select id="resolution" name="resolution">
              <option value="">— Select —</option>
              <option value="store-credit">Store credit</option>
              <option value="replacement">Replacement fish</option>
              <option value="other">Other (please specify)</option>
            </select>
            <div class="field-error" id="err-resolution">Please select your preferred resolution.</div>
          </div>
        </div>

        <div class="field" id="resolutionOtherField" style="display:none">
          <label for="resolutionOther">Please specify <span class="req">*</span></label>
          <input type="text" id="resolutionOther" name="resolutionOther" placeholder="Describe your preferred resolution…" />
          <div class="field-error" id="err-resolutionOther">Please describe your preferred resolution.</div>
        </div>

        <div class="field">
          <label for="description">
            Description <span class="req">*</span>
            <span class="hint">What did you observe on arrival?</span>
          </label>
          <textarea id="description" name="description" placeholder="e.g. Bag arrived cold, fish was motionless at the bottom. Water appeared clear with no visible injury."></textarea>
          <div class="field-error" id="err-description">Please provide a brief description.</div>
        </div>

        <div class="field">
          <label>Photos <span class="hint">Required — fish in unopened bag</span></label>
          <div class="file-drop" id="fileDrop">
            <input type="file" id="photos" name="photos[]" accept="image/*" multiple />
            <span class="upload-icon">📷</span>
            <p><strong>Click to upload</strong> or drag &amp; drop</p>
            <p>JPG, PNG, HEIC — up to 10 MB each</p>
          </div>
          <div class="file-list" id="fileList"></div>
        </div>

        <div class="check-field">
          <input type="checkbox" id="policyAck" name="policyAck" />
          <label for="policyAck">
            I confirm the fish arrived dead and that this claim is being submitted within 24 hours of delivery. I agree to the
            <a href="../policies/user-agreement.pdf"  target="_blank" rel="noopener noreferrer">DOA policy terms</a>.
          </label>
        </div>
        <div class="field-error" id="err-policyAck" style="margin-top:6px;">You must acknowledge the DOA policy.</div>

        <button type="submit" class="btn-submit" id="btnSubmitTicket">Submit Refund Request</button>
        <div class="field-error" id="err-submit" style="margin-top:10px;text-align:center;">Something went wrong — please try again.</div>

      </form>
    </div>

    <!-- ── Confirmation ── -->
    <div id="confirmation">
      <div class="check-circle">✅</div>
      <h2>Request Received</h2>
      <p>Thank you — we're sorry for your loss and we'll make this right.</p>

      <div class="ref-block">
        <div class="ref-label">Ticket Reference</div>
        <div class="ref-number" id="refNumber"></div>
      </div>

      <div class="ref-important-note">
        <strong>IMPORTANT:</strong>
        <p>Please save or screenshot your Ticket Reference. You will need it together with your email address to check your refund/support request status and view responses from our support team.</p>
      </div>

      <p>We'll review your claim and respond to <strong id="confEmail"></strong> within <strong>1–2 business days</strong>.<br />Please keep the fish and bag refrigerated (do not freeze) until we confirm your case.</p>
      <button class="btn-new" onclick="resetForm()">Submit another claim</button>
    </div>

  </div>
</main>

<footer>
  Need help? Email us at <a href="mailto:support@bettabud.com">support@bettabud.com</a>
</footer>

<script>

  // ── Stage 1: Send Code ──
  var sendCodeInProgress = false;
  var currentSupportStage = 'email';
  var verifiedEmail = <?php echo json_encode($serverActiveEmail); ?> || '';
  var currentOTP = '';
  var otpCooldownTimer = null;
  var otpCooldownRemaining = Number(<?php echo json_encode((int) $otpCooldownRemaining); ?>) || 0;
  var serverHasActiveSession = <?php echo json_encode((bool) $serverSessionActive); ?>;
  var serverActiveStage = <?php echo json_encode($serverActiveStage); ?>;
  var customerOrdersData = <?php echo json_encode($initialOrders); ?> || [];
  var refreshGuardStorageKey = 'supportOtpRefreshGuard';
  var refreshStateStorageKey = 'supportOtpFlowState';

  function updateOtpButtonState(secondsRemaining) {
    const sendCodeButton = document.getElementById('btnSendCode');
    const resendLink = document.getElementById('btnResend');
    const activeSeconds = Math.max(0, Number(secondsRemaining) || 0);

    if (activeSeconds > 0) {
      sendCodeButton.disabled = true;
      sendCodeButton.textContent = 'Wait ' + activeSeconds + 's';
      if (resendLink) {
        resendLink.textContent = 'Resend in ' + activeSeconds + 's';
        resendLink.classList.add('is-disabled');
        resendLink.setAttribute('aria-disabled', 'true');
        resendLink.style.pointerEvents = 'none';
      }
      return;
    }

    sendCodeButton.disabled = false;
    sendCodeButton.textContent = 'Send Verification Code';
    if (resendLink) {
      resendLink.textContent = 'Resend code';
      resendLink.classList.remove('is-disabled');
      resendLink.setAttribute('aria-disabled', 'false');
      resendLink.style.pointerEvents = 'auto';
    }
  }

  function startOtpCooldown(secondsRemaining) {
    const totalSeconds = Math.max(0, Math.ceil(Number(secondsRemaining) || 0));
    otpCooldownRemaining = totalSeconds;
    if (otpCooldownTimer) {
      clearInterval(otpCooldownTimer);
      otpCooldownTimer = null;
    }

    if (totalSeconds > 0) {
      updateOtpButtonState(totalSeconds);
      const startedAt = Date.now();
      otpCooldownTimer = setInterval(function () {
        const elapsedMs = Date.now() - startedAt;
        const remaining = Math.max(0, totalSeconds - Math.floor(elapsedMs / 1000));
        otpCooldownRemaining = remaining;
        updateOtpButtonState(remaining);
        if (remaining <= 0) {
          clearInterval(otpCooldownTimer);
          otpCooldownTimer = null;
        }
      }, 1000);
      return;
    }

    otpCooldownRemaining = 0;
    updateOtpButtonState(0);
  }

  function saveRefreshState() {
    const state = {
      stage: currentSupportStage,
      email: verifiedEmail || (document.getElementById('emailInput') ? document.getElementById('emailInput').value.trim() : ''),
      firstName: document.getElementById('firstName') ? document.getElementById('firstName').value : '',
      lastName: document.getElementById('lastName') ? document.getElementById('lastName').value : '',
      orderNumber: document.getElementById('orderNumber') ? document.getElementById('orderNumber').value : '',
      deliveryDate: document.getElementById('deliveryDate') ? document.getElementById('deliveryDate').value : '',
      description: document.getElementById('description') ? document.getElementById('description').value : '',
      resolvedValue: document.getElementById('resolution') ? document.getElementById('resolution').value : '',
      otpDigits: Array.isArray(otpDigits) ? otpDigits.map(function (d) { return d.value; }).join('') : '',
    };
    sessionStorage.setItem(refreshStateStorageKey, JSON.stringify(state));
  }

  function restoreRefreshState() {
    try {
      const rawState = sessionStorage.getItem(refreshStateStorageKey);
      let targetStage = serverActiveStage || 'email';
      let state = null;

      if (rawState) {
        state = JSON.parse(rawState);
      }

      if (state) {
        if (state.stage) targetStage = state.stage;
        if (state.email) verifiedEmail = state.email;
        if (document.getElementById('emailInput') && state.email) {
          document.getElementById('emailInput').value = state.email;
        }
        if (state.firstName && document.getElementById('firstName')) {
          document.getElementById('firstName').value = state.firstName;
        }
        if (state.lastName && document.getElementById('lastName')) {
          document.getElementById('lastName').value = state.lastName;
        }
        if (state.orderNumber && document.getElementById('orderNumber')) {
          document.getElementById('orderNumber').value = state.orderNumber;
        }
        if (state.deliveryDate && document.getElementById('deliveryDate')) {
          document.getElementById('deliveryDate').value = state.deliveryDate;
        }
        if (state.description && document.getElementById('description')) {
          document.getElementById('description').value = state.description;
        }
        if (state.resolvedValue && document.getElementById('resolution')) {
          document.getElementById('resolution').value = state.resolvedValue;
        }
        if (state.otpDigits && Array.isArray(otpDigits)) {
          otpDigits.forEach(function (d, idx) {
            d.value = state.otpDigits[idx] || '';
          });
        }
      } else if (serverActiveEmail) {
        verifiedEmail = serverActiveEmail;
        if (document.getElementById('emailInput')) {
          document.getElementById('emailInput').value = serverActiveEmail;
        }
      }

      if (targetStage === 'otp' || targetStage === 'form') {
        const displayEmail = verifiedEmail || (state && state.email) || serverActiveEmail || '';
        const otpDisplay = document.getElementById('otpEmailDisplay');
        const verDisplay = document.getElementById('verifiedEmailDisplay');
        if (otpDisplay) otpDisplay.textContent = displayEmail;
        if (verDisplay) verDisplay.textContent = displayEmail;
        showStage(targetStage);
      }
    } catch (error) {
      console.error('Could not restore saved support state.', error);
    }
  }

  function showRefreshWarningModal() {
    const modal = document.getElementById('refreshWarningModal');
    if (!modal) return;
    modal.classList.add('visible');
    modal.setAttribute('aria-hidden', 'false');
  }

  function hideRefreshWarningModal() {
    const modal = document.getElementById('refreshWarningModal');
    if (!modal) return;
    modal.classList.remove('visible');
    modal.setAttribute('aria-hidden', 'true');
  }

  function hasActiveVerificationSession() {
    return currentSupportStage === 'otp' || currentSupportStage === 'form' || !!verifiedEmail || (serverHasActiveSession && currentSupportStage !== 'confirmation');
  }

  function shouldWarnBeforeRefresh() {
    return hasActiveVerificationSession() && currentSupportStage !== 'confirmation';
  }

  window.addEventListener('beforeunload', function (event) {
    if (!shouldWarnBeforeRefresh()) {
      sessionStorage.removeItem(refreshGuardStorageKey);
      return;
    }

    saveRefreshState();
    sessionStorage.setItem(refreshGuardStorageKey, '1');
    event.preventDefault();
    event.returnValue = '';
  });

  window.addEventListener('DOMContentLoaded', function () {
    if (otpCooldownRemaining > 0) {
      startOtpCooldown(otpCooldownRemaining);
    }

    const wasRefreshing = sessionStorage.getItem(refreshGuardStorageKey) === '1' || (serverHasActiveSession && currentSupportStage !== 'confirmation');

    if (wasRefreshing) {
      restoreRefreshState();
      showRefreshWarningModal();
    }
  });

  document.getElementById('btnRefreshYes').addEventListener('click', function () {
    sessionStorage.removeItem(refreshGuardStorageKey);
    sessionStorage.removeItem(refreshStateStorageKey);
    hideRefreshWarningModal();

    const formData = new FormData();
    formData.append('action', 'start_refresh_cooldown');

    fetch('otp_email.api.php', {
      method: 'POST',
      body: formData
    })
    .then(function (response) { return response.json(); })
    .then(function (data) {
      const cooldownSecs = Number(data.cooldown_remaining) || 60;
      serverHasActiveSession = false;
      serverActiveStage = '';
      resetForm();
      startOtpCooldown(cooldownSecs);
    })
    .catch(function (err) {
      console.error('Error starting refresh cooldown on server:', err);
      serverHasActiveSession = false;
      serverActiveStage = '';
      resetForm();
      startOtpCooldown(60);
    });
  });

  document.getElementById('btnRefreshNo').addEventListener('click', function () {
    sessionStorage.removeItem(refreshGuardStorageKey);
    hideRefreshWarningModal();
    restoreRefreshState();
  });

  document.addEventListener('keydown', function (event) {
    const isRefreshShortcut = event.key === 'F5' || ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'r');
    if (isRefreshShortcut && shouldWarnBeforeRefresh()) {
      event.preventDefault();
      saveRefreshState();
      sessionStorage.setItem(refreshGuardStorageKey, '1');
      showRefreshWarningModal();
    }
  });

  function requestOtpEmail(isResend) {
    const sendCodeButton = document.getElementById('btnSendCode');
    const resendLink = document.getElementById('btnResend');
    const email = isResend ? (verifiedEmail || document.getElementById('emailInput').value.trim()) : document.getElementById('emailInput').value.trim();
    const errEl = document.getElementById('err-emailInput');
    const inputEl = document.getElementById('emailInput');

    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      inputEl.classList.add('invalid');
      errEl.classList.add('visible');
      return;
    }
    inputEl.classList.remove('invalid');
    errEl.classList.remove('visible');

    // Guard against double-submits
    if (sendCodeInProgress || otpCooldownRemaining > 0) return;
    sendCodeInProgress = true;
    sendCodeButton.disabled = true;
    sendCodeButton.textContent = 'Sending...';
    if (resendLink) {
      resendLink.textContent = 'Sending...';
      resendLink.classList.add('is-disabled');
      resendLink.setAttribute('aria-disabled', 'true');
      resendLink.style.pointerEvents = 'none';
    }

    verifiedEmail = email;

    const formData = new FormData();
    formData.append('email', verifiedEmail);
    if (isResend) {
      formData.append('is_resend', '1');
    }

    fetch(`otp_email.api.php`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        sendCodeInProgress = false;

        if (data.success) {
            sendCodeButton.textContent = 'Code Sent';
            document.getElementById('otpEmailDisplay').textContent = verifiedEmail;
            showStage('otp');
            currentSupportStage = 'otp';
            serverHasActiveSession = true;
            serverActiveStage = 'otp';

            const cooldownSeconds = Number(data.cooldown_remaining) || 0;
            if (cooldownSeconds > 0) {
                startOtpCooldown(cooldownSeconds);
            } else {
                updateOtpButtonState(0);
            }

            saveRefreshState();
            setTimeout(function () {
                document.querySelector('.otp-digit').focus();
            }, 80);
            return;
        }

        const cooldownSeconds = Number(data.cooldown_remaining) || 0;
        if (cooldownSeconds > 0) {
            startOtpCooldown(cooldownSeconds);
        } else {
            updateOtpButtonState(0);
        }

        errEl.textContent = data.message || 'Unable to send verification code.';
        errEl.classList.add('visible');
    })
    .catch(error => {
        console.error(error);
        sendCodeInProgress = false;
        updateOtpButtonState(0);
        errEl.textContent = 'Unable to send verification code.';
        errEl.classList.add('visible');
    });
  }

  document.getElementById('btnSendCode').addEventListener('click', function () {
    requestOtpEmail(false);
  });

  document.getElementById('emailInput').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') document.getElementById('btnSendCode').click();
  });

  // ── OTP digit inputs ──
  const otpDigits = Array.from(document.querySelectorAll('.otp-digit'));

  otpDigits.forEach(function (input, i) {
    input.addEventListener('input', function () {
      this.value = this.value.replace(/[^0-9]/g, '').slice(-1);
      if (this.value && i < otpDigits.length - 1) otpDigits[i + 1].focus();
      clearOtpError();
    });

    input.addEventListener('keydown', function (e) {
      if (e.key === 'Backspace' && !this.value && i > 0) otpDigits[i - 1].focus();
      if (e.key === 'ArrowLeft'  && i > 0)                otpDigits[i - 1].focus();
      if (e.key === 'ArrowRight' && i < otpDigits.length - 1) otpDigits[i + 1].focus();
      if (e.key === 'Enter') document.getElementById('btnVerify').click();
    });

    if (i === 0) {
      input.addEventListener('paste', function (e) {
        const text = (e.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g, '');
        if (text.length >= 6) {
          otpDigits.forEach(function (d, idx) { d.value = text[idx] || ''; });
          otpDigits[Math.min(5, text.length - 1)].focus();
          e.preventDefault();
        }
      });
    }
  });

  function clearOtpError() {
    document.getElementById('err-otp').classList.remove('visible');
    otpDigits.forEach(function (d) { d.classList.remove('invalid'); });
  }

  // ── Stage 2: Verify ──
  document.getElementById('btnVerify').addEventListener('click', function () {
    const entered = otpDigits.map(function (d) { return d.value; }).join('');
    if (entered.length < 6) {
      document.getElementById('err-otp').textContent = 'Please enter all 6 digits.';
      document.getElementById('err-otp').classList.add('visible');
      otpDigits.forEach(function (d) { if (!d.value) d.classList.add('invalid'); });
      return;
    }
    
    const formData = new FormData();
    formData.append('otp', entered);
    
    fetch('otp_verify.api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
    
    if (data.success) {
        customerOrdersData = data.orders || [];
        document.getElementById('firstName').value =
            data.customer.firstname;
        
        document.getElementById('lastName').value =
            data.customer.lastname;
        
        const orderSelect = document.getElementById('orderNumber');
        
        orderSelect.innerHTML =
            '<option value="">— Select your order —</option>';
        
        data.orders.forEach(order => {
        
            const option = document.createElement('option');
        
            option.value = order.order_id;
        
            option.textContent =
                `${order.order_id} — ${order.order_date}`;
        
            orderSelect.appendChild(option);
        
        });
        
        renderOrderSkus(orderSelect.value);

        document.getElementById('verifiedEmailDisplay').textContent =
            verifiedEmail;

        // The email matched a verified customer, but there's no
        // guarantee it matches any *orders* — email typos, using a
        // different address than the one on the order, guest checkouts
        // under another address, etc. Rather than dropping the customer
        // into a form with an empty, unusable order dropdown, show a
        // clear explanation and a way back to try a different email.
        const hasOrders = Array.isArray(data.orders) && data.orders.length > 0;
        document.getElementById('noOrdersNotice').style.display = hasOrders ? 'none' : 'block';
        document.getElementById('doaForm').style.display = hasOrders ? 'block' : 'none';

        currentSupportStage = 'form';
        serverHasActiveSession = true;
        serverActiveStage = 'form';
        saveRefreshState();
        showStage('form');
    
    } else {
    
      document.getElementById('err-otp').textContent = 'Incorrect code — please try again.';
      document.getElementById('err-otp').classList.add('visible');
      otpDigits.forEach(function (d) { d.classList.add('invalid'); });
      otpDigits[0].focus();
    }
  });
});

  document.getElementById('btnResend').addEventListener('click', function (e) {
    e.preventDefault();
    if (this.classList.contains('is-disabled') || sendCodeInProgress || otpCooldownRemaining > 0) return;
    requestOtpEmail(true);
  });

  document.getElementById('btnChangeEmail').addEventListener('click', function (e) {
    e.preventDefault();
    resetEmailStage();
    document.getElementById('emailInput').focus();
  });

  document.getElementById('btnNoOrdersBack').addEventListener('click', function (e) {
    e.preventDefault();
    resetEmailStage();
    document.getElementById('emailInput').focus();
  });

  // ── Stage management ──
  function showStage(stage) {
    currentSupportStage = stage;
    document.getElementById('stage-email').style.display = 'none';
    document.getElementById('stage-otp').style.display   = 'none';
    document.getElementById('stage-form').style.display  = 'none';
    document.getElementById('stage-' + stage).style.display = 'block';

    const num = { email: 1, otp: 2, form: 3 }[stage];
    for (var i = 1; i <= 3; i++) {
      var item = document.getElementById('stepItem' + i);
      item.classList.remove('active', 'done');
      if (i < num)      item.classList.add('done');
      else if (i === num) item.classList.add('active');
    }
    document.getElementById('stepLine1').classList.toggle('done', num > 1);
    document.getElementById('stepLine2').classList.toggle('done', num > 2);
  }

  // ── Order → SKU population ──
  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function renderOrderSkus(orderId) {
    var skuBox = document.getElementById('skuBox');
    if (!skuBox) return;

    if (!orderId) {
      skuBox.innerHTML = '<div class="sku-placeholder">Select an order above to see items</div>';
      return;
    }

    var selectedOrder = customerOrdersData.find(function (o) {
      return String(o.order_id) === String(orderId);
    });

    var rawSku = selectedOrder ? (selectedOrder.sku || '') : '';
    rawSku = rawSku.trim();

    if (rawSku !== '') {
      var skus = rawSku.split(',').map(function (s) { return s.trim(); }).filter(function (s) { return s.length > 0; });
      var html = '';
      skus.forEach(function (sku, idx) {
        var safeSku = escapeHtml(sku);
        var isChecked = skus.length === 1 ? ' checked' : '';
        html += '<label style="display:flex;align-items:center;gap:8px;font-weight:500;margin-bottom:6px;cursor:pointer;">' +
                '<input type="checkbox" name="fishSku[]" value="' + safeSku + '"' + isChecked + ' onchange="setError(\'fishSku\', false);"> ' +
                '<span><code style="background:#eef5fb;padding:2px 6px;border-radius:4px;font-size:0.88rem;color:var(--blue-deep);">' + safeSku + '</code></span>' +
                '</label>';
      });

      html += '<label style="display:flex;align-items:center;gap:8px;font-weight:400;margin-top:6px;font-size:0.85rem;color:var(--muted);cursor:pointer;">' +
              '<input type="checkbox" name="fishSku[]" value="unknown" onchange="setError(\'fishSku\', false);"> I don\'t know which item(s)' +
              '</label>';

      skuBox.innerHTML = html;
    } else {
      skuBox.innerHTML =
        '<label style="display:flex;align-items:center;gap:8px;font-weight:400;cursor:pointer;">' +
        '<input type="checkbox" name="fishSku[]" value="unknown" checked onchange="setError(\'fishSku\', false);"> No SKU on file — General item claim' +
        '</label>';
    }
  }

  document.getElementById('orderNumber').addEventListener('change', function () {
    renderOrderSkus(this.value);
    setError('orderNumber', false);
    setError('fishSku', false);
  });

  // ── Resolution "Other" ──
  document.getElementById('resolution').addEventListener('change', function () {
    var otherField = document.getElementById('resolutionOtherField');
    otherField.style.display = this.value === 'other' ? 'block' : 'none';
    if (this.value !== 'other') {
      document.getElementById('resolutionOther').value = '';
      setError('resolutionOther', false);
    }
    setError('resolution', false);
  });

  document.getElementById('resolutionOther').addEventListener('input', function () {
    setError('resolutionOther', false);
  });

  // ── File upload ──
  var fileDrop   = document.getElementById('fileDrop');
  var photosInput = document.getElementById('photos');
  var fileList   = document.getElementById('fileList');
  var selectedFiles = [];

  ['dragenter', 'dragover'].forEach(function (ev) {
    fileDrop.addEventListener(ev, function (e) { e.preventDefault(); fileDrop.classList.add('dragover'); });
  });
  ['dragleave', 'drop'].forEach(function (ev) {
    fileDrop.addEventListener(ev, function (e) { e.preventDefault(); fileDrop.classList.remove('dragover'); });
  });
  fileDrop.addEventListener('drop', function (e) { addFiles(e.dataTransfer.files); });
  photosInput.addEventListener('change', function () { addFiles(photosInput.files); photosInput.value = ''; });

  function addFiles(files) {
    Array.from(files).forEach(function (f) {
      if (!selectedFiles.find(function (x) { return x.name === f.name && x.size === f.size; })) selectedFiles.push(f);
    });
    renderFileList();
  }

  function removeFile(idx) {
    selectedFiles.splice(idx, 1);
    renderFileList();
  }

  function renderFileList() {
    fileList.innerHTML = selectedFiles.map(function (f, i) {
      return '<span>📄 ' + escHtml(f.name) + ' <button type="button" onclick="removeFile(' + i + ')" title="Remove">✕</button></span>';
    }).join('');
  }

  function escHtml(s) {
    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  // ── Validation ──
  var requiredFields = ['firstName', 'lastName', 'orderNumber', 'deliveryDate', 'doaCount', 'resolution', 'description'];

  function setError(id, show) {
    var el  = document.getElementById(id);
    var err = document.getElementById('err-' + id);
    if (!el || !err) return;
    el.classList.toggle('invalid', show);
    err.classList.toggle('visible', show);
  }

  function validate() {
    var ok = true;

    requiredFields.forEach(function (id) {
      var el    = document.getElementById(id);
      var empty = !el.value.trim();
      if (id === 'doaCount') {
        var bad = empty || parseInt(el.value) < 1;
        setError(id, bad);
        if (bad) ok = false;
      } else {
        setError(id, empty);
        if (empty) ok = false;
      }
    });

    // Fish SKU — only validate if an order was selected
    var orderVal = document.getElementById('orderNumber').value;
    if (orderVal) {
      var skuBox    = document.getElementById('skuBox');
      var checked   = skuBox.querySelectorAll('input[name="fishSku[]"]:checked');
      var skuErr    = document.getElementById('err-fishSku');
      if (checked.length === 0) {
        skuBox.classList.add('invalid');
        skuErr.classList.add('visible');
        ok = false;
      }
    }

    // Resolution "other" specify
    if (document.getElementById('resolution').value === 'other') {
      var otherVal = document.getElementById('resolutionOther').value.trim();
      if (!otherVal) { setError('resolutionOther', true); ok = false; }
    }

    // Policy checkbox
    var ack = document.getElementById('policyAck');
    document.getElementById('err-policyAck').classList.toggle('visible', !ack.checked);
    if (!ack.checked) ok = false;

    return ok;
  }

  requiredFields.forEach(function (id) {
    var el = document.getElementById(id);
    if (!el) return;
    el.addEventListener(el.type === 'checkbox' ? 'change' : 'input', function () { setError(id, false); });
  });

  // ── Submission ──
  document.getElementById('doaForm').addEventListener('submit', function (e) {
    e.preventDefault();
    if (!validate()) return;

    var submitBtn = document.getElementById('btnSubmitTicket');
    var submitErr = document.getElementById('err-submit');
    submitErr.classList.remove('visible');
    submitBtn.disabled = true;
    var originalLabel = submitBtn.textContent;
    submitBtn.textContent = 'Submitting…';

    var formData = new FormData(document.getElementById('doaForm'));
    formData.delete('photos[]');
    selectedFiles.forEach(function (file) {
      formData.append('photos[]', file, file.name);
    });

    fetch('submit_ticket.api.php', {
      method: 'POST',
      body: formData
    })
    .then(function (response) { return response.json(); })
    .then(function (data) {
      submitBtn.disabled = false;
      submitBtn.textContent = originalLabel;

      if (!data.success) {
        submitErr.textContent = data.message || 'Something went wrong — please try again.';
        submitErr.classList.add('visible');
        return;
      }

      document.getElementById('refNumber').textContent = data.ticket_ref;
      document.getElementById('confEmail').textContent = verifiedEmail;

      document.getElementById('stage-form').style.display = 'none';
      document.getElementById('confirmation').style.display = 'block';

      currentSupportStage = 'confirmation';
      serverHasActiveSession = false;
      serverActiveStage = '';
      verifiedEmail = '';
      otpCooldownRemaining = 0;
      if (otpCooldownTimer) {
        clearInterval(otpCooldownTimer);
        otpCooldownTimer = null;
      }
      updateOtpButtonState(0);
      sessionStorage.removeItem(refreshGuardStorageKey);
      sessionStorage.removeItem(refreshStateStorageKey);

      for (var i = 1; i <= 3; i++) {
        var item = document.getElementById('stepItem' + i);
        item.classList.remove('active');
        item.classList.add('done');
      }
      document.getElementById('stepLine1').classList.add('done');
      document.getElementById('stepLine2').classList.add('done');

      window.scrollTo({ top: 0, behavior: 'smooth' });
    })
    .catch(function (error) {
      console.error(error);
      submitBtn.disabled = false;
      submitBtn.textContent = originalLabel;
      submitErr.textContent = 'Network error — please check your connection and try again.';
      submitErr.classList.add('visible');
    });
  });

  function resetEmailStage() {
    var sendCodeButton = document.getElementById('btnSendCode');

    if (otpCooldownTimer) {
      clearInterval(otpCooldownTimer);
      otpCooldownTimer = null;
    }

    sendCodeInProgress = false;
    if (otpCooldownRemaining > 0) {
      startOtpCooldown(otpCooldownRemaining);
    } else {
      sendCodeButton.disabled = false;
      sendCodeButton.textContent = 'Send Verification Code';
    }

    document.getElementById('emailInput').value = '';
    document.getElementById('emailInput').classList.remove('invalid');
    document.getElementById('err-emailInput').classList.remove('visible');

    otpDigits.forEach(function (d) { d.value = ''; d.classList.remove('invalid'); });
    document.getElementById('err-otp').classList.remove('visible');
    document.getElementById('otpEmailDisplay').textContent = '';
    document.getElementById('verifiedEmailDisplay').textContent = '';
    document.getElementById('noOrdersNotice').style.display = 'none';
    document.getElementById('doaForm').style.display = 'block';

    verifiedEmail = '';
    currentOTP = '';
    currentSupportStage = 'email';
    sessionStorage.removeItem(refreshGuardStorageKey);
    sessionStorage.removeItem(refreshStateStorageKey);
    showStage('email');
  }

  function resetForm() {
    document.getElementById('doaForm').reset();
    document.getElementById('confirmation').style.display = 'none';

    resetEmailStage();

    // Reset form stage
    document.getElementById('skuBox').innerHTML = '<div class="sku-placeholder">Select an order above to see items</div>';
    document.getElementById('resolutionOtherField').style.display = 'none';
    selectedFiles = [];
    renderFileList();
    requiredFields.forEach(function (id) { setError(id, false); });
    document.getElementById('err-policyAck').classList.remove('visible');
    document.getElementById('err-fishSku').classList.remove('visible');
    document.getElementById('skuBox').classList.remove('invalid');
    document.getElementById('err-submit').classList.remove('visible');

    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
</script>

</body>
</html>