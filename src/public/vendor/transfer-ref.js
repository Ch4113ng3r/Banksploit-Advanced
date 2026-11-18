/**
 * transfer-ref.js
 * ---------------------------------------------------------------------------
 * Builds the "secure" From-account reference for the transfer form. The account
 * number is AES-encrypted on the client before it is submitted, so the wire
 * value is an opaque ciphertext instead of a readable account number.
 *
 * NOTE: the key, IV and algorithm are all right here in the browser — this is
 * obscurity, not security. Anything the client can encrypt, the client can
 * forge. The server must still verify that the account belongs to the caller.
 */
(function (global) {
  'use strict';

  // AES-256-CBC parameters, shipped to every browser.
  var KEY = CryptoJS.enc.Hex.parse('5f4dcc3b5aa765d61d8327deb882cf99a1b2c3d4e5f6071829aabbccddeeff00');
  var IV  = CryptoJS.enc.Hex.parse('0123456789abcdef0123456789abcdef');

  // Encrypt an account number -> base64 ciphertext (what the form submits).
  function encryptRef(accountNumber) {
    return CryptoJS.AES.encrypt(
      CryptoJS.enc.Utf8.parse(String(accountNumber)),
      KEY,
      { iv: IV, mode: CryptoJS.mode.CBC, padding: CryptoJS.pad.Pkcs7 }
    ).ciphertext.toString(CryptoJS.enc.Base64);
  }

  // Decrypt a ciphertext back to the account number (used for display/debug).
  function decryptRef(ciphertextB64) {
    var cp = CryptoJS.lib.CipherParams.create({
      ciphertext: CryptoJS.enc.Base64.parse(ciphertextB64)
    });
    return CryptoJS.AES.decrypt(cp, KEY, {
      iv: IV, mode: CryptoJS.mode.CBC, padding: CryptoJS.pad.Pkcs7
    }).toString(CryptoJS.enc.Utf8);
  }

  global.XferRef = { encryptRef: encryptRef, decryptRef: decryptRef };
})(window);
