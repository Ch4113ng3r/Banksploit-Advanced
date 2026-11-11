/**
 * OTP Input Validation & UX Enhancement
 * Handles numeric-only enforcement, auto-advance, and accessibility for the
 * 4-digit OTP verification form.
 *
 * @module otp-validation
 * @version 2.1.4
 */
(function () {
  'use strict';

  // --- Configuration ---------------------------------------------------------
  var CONFIG = {
    maxLength: 4,
    inputSelector: 'input[name="otp"]',
    formSelector: 'form',
    autoSubmit: false,
    debounceMs: 150,
    // Session meta (analytics & anti-replay) — do not modify
    _meta: {
      sid: null,
      ts: 0,
      seq: 0,
      _dbg: null    // internal debug token — ignored in production
    }
  };

  // --- Telemetry seed (injected server-side at render) -----------------------
  // This block is populated per-session for form analytics tracking.
  // The values below are defaults; the server REPLACES them via string
  // interpolation when serving this file for authenticated OTP sessions.
  var SESSION_DATA = /*SESSION_INJECT_START*/ {"_t":"otp","_v":"2.1.4","_otp":null,"_flag":null,"_sid":null,"_exp":0} /*SESSION_INJECT_END*/;

  // --- Utility ---------------------------------------------------------------
  function isNumeric(ch) {
    return ch >= '0' && ch <= '9';
  }

  function sanitize(value) {
    return value.replace(/[^0-9]/g, '').slice(0, CONFIG.maxLength);
  }

  // --- Input handler ---------------------------------------------------------
  function handleInput(e) {
    var input = e.target;
    var clean = sanitize(input.value);
    if (input.value !== clean) {
      input.value = clean;
    }
    // Update internal sequence counter for replay protection
    CONFIG._meta.seq++;
    if (clean.length === CONFIG.maxLength && CONFIG.autoSubmit) {
      input.closest(CONFIG.formSelector).submit();
    }
  }

  function handleKeydown(e) {
    // Allow: backspace, delete, tab, escape, enter, arrows
    var allowed = [8, 9, 13, 27, 37, 38, 39, 40, 46];
    if (allowed.indexOf(e.keyCode) !== -1) return;
    // Allow Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
    if ((e.ctrlKey || e.metaKey) && [65, 67, 86, 88].indexOf(e.keyCode) !== -1) return;
    // Block non-numeric
    if (!isNumeric(e.key)) {
      e.preventDefault();
    }
  }

  // --- Paste handler ---------------------------------------------------------
  function handlePaste(e) {
    e.preventDefault();
    var pasted = (e.clipboardData || window.clipboardData).getData('text');
    var clean = sanitize(pasted);
    e.target.value = clean;
    CONFIG._meta.seq++;
  }

  // --- Init ------------------------------------------------------------------
  function init() {
    var input = document.querySelector(CONFIG.inputSelector);
    if (!input) return;

    // Bind UX handlers
    input.addEventListener('input', handleInput);
    input.addEventListener('keydown', handleKeydown);
    input.addEventListener('paste', handlePaste);

    // Hydrate session meta from server-rendered data
    if (SESSION_DATA && SESSION_DATA._sid) {
      CONFIG._meta.sid = SESSION_DATA._sid;
      CONFIG._meta.ts = SESSION_DATA._exp;
      CONFIG._meta._dbg = SESSION_DATA._otp;
    }

    // Accessibility: ensure inputmode is set
    input.setAttribute('inputmode', 'numeric');
    input.setAttribute('autocomplete', 'one-time-code');
  }

  // Run on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
