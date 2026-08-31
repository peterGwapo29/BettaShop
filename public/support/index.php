<?php

require_once "../includes/config.inc.php";

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
    <img src="/resources/img/logo.png" alt="betta bud logo" id="navlogo" class="navlogo" style="width: 50px;">
    <h1 id="menu" style="font: arial;" >The Betta Shop</h1>
</div>
<br>
  <h1>Help & Support</h1>
  <p>Let's work together to resolve an issue with your order.</p>
</header>

<main>

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
      <button type="button" class="btn-submit" id="btnSendCode">Send Verification Code</button>
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
            <a href="#" onclick="return false;">DOA policy terms</a>.
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
      <div class="ref-number" id="refNumber"></div>
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

  document.getElementById('btnSendCode').addEventListener('click', function () {
        const sendCodeButton = this;
        const email = document.getElementById('emailInput').value.trim();
        const errEl = document.getElementById('err-emailInput');
        const inputEl = document.getElementById('emailInput');
    
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
          inputEl.classList.add('invalid');
          errEl.classList.add('visible');
          return;
        }
        inputEl.classList.remove('invalid');
        errEl.classList.remove('visible');

        if (sendCodeInProgress) return;
        sendCodeInProgress = true;
        sendCodeButton.disabled = true;
        sendCodeButton.textContent = 'Sending...';
    
        verifiedEmail = email;
    
        const formData = new FormData();
        formData.append('email', verifiedEmail);
        
        fetch(`otp_email.api.php`, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
            sendCodeButton.textContent = 'Code Sent';

                document.getElementById('otpEmailDisplay').textContent = verifiedEmail;

                showStage('otp');
                
                setTimeout(() => {
                    document.querySelector('.otp-digit').focus();
                }, 80);
            } else {
              sendCodeInProgress = false;
              sendCodeButton.disabled = false;
              sendCodeButton.textContent = 'Send Verification Code';
                errEl.textContent = data.message || 'Unable to send verification code.';
                errEl.classList.add('visible');
            }
        })
        .catch(error => {
            console.error(error);
            sendCodeInProgress = false;
            sendCodeButton.disabled = false;
            sendCodeButton.textContent = 'Send Verification Code';
            errEl.textContent = 'Unable to send verification code.';
            errEl.classList.add('visible');
        });
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
        
        document.getElementById('verifiedEmailDisplay').textContent =
            verifiedEmail;
        
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
    
  });

  document.getElementById('btnChangeEmail').addEventListener('click', function (e) {
    e.preventDefault();
    resetEmailStage();
    document.getElementById('emailInput').focus();
  });

  // ── Stage management ──
  function showStage(stage) {
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
  // NOTE: per-order SKU/item data isn't available from the backend yet
  // (no confirmed order-items table — see support.class.php notes), so
  // this renders a single "I don't know" option, matching the error
  // copy that already referenced it ("...or 'I don't know'."). Without
  // this, skuBox stayed empty forever and the required-SKU validation
  // could never pass for any order. Swap this for real per-SKU
  // checkboxes once order-item data is available.
  document.getElementById('orderNumber').addEventListener('change', function () {
    var skuBox = document.getElementById('skuBox');

    if (!this.value) {
      skuBox.innerHTML = '<div class="sku-placeholder">Select an order above to see items</div>';
      return;
    }

    skuBox.innerHTML =
      '<label style="display:flex;align-items:center;gap:8px;font-weight:400;">' +
      '<input type="checkbox" name="fishSku[]" value="unknown"> I don\'t know which item(s)' +
      '</label>';
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

    sendCodeInProgress = false;
    sendCodeButton.disabled = false;
    sendCodeButton.textContent = 'Send Verification Code';

    document.getElementById('emailInput').value = '';
    document.getElementById('emailInput').classList.remove('invalid');
    document.getElementById('err-emailInput').classList.remove('visible');

    otpDigits.forEach(function (d) { d.value = ''; d.classList.remove('invalid'); });
    document.getElementById('err-otp').classList.remove('visible');
    document.getElementById('otpEmailDisplay').textContent = '';
    document.getElementById('verifiedEmailDisplay').textContent = '';

    verifiedEmail = '';
    currentOTP = '';
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
