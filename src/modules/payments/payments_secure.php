<?php

declare(strict_types=1);

/**
 * SECURE rendering helper for bill payments (level 3 of beneficiary.g3).
 *
 * The stored-XSS fix for payments is output encoding: a biller label is shown
 * RAW while G3 is exploitable and HTML-escaped once it reaches level 3. This is
 * centralised in vuln_out(); payments_render_remark() is a thin, self-
 * documenting wrapper so the code viewer shows the secure-vs-vuln contrast in
 * one place.
 *
 * (The matching input-side hardening — strip_tags() on the biller name/remarks
 * at the secure level — lives in payments_pay() in payments_vuln.php.)
 */

if (!function_exists('payments_render_remark')) {

    /**
     * Render a stored biller/remark string for display.
     *
     * level 0-2 (beneficiary.g3 exploitable): returned RAW -> stored XSS fires.
     * level 3:                                 htmlspecialchars() -> neutralised.
     */
    function payments_render_remark(string $remark): string
    {
        return vuln_out($remark, 'beneficiary', 'g3');
    }
}
