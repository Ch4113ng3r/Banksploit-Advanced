<?php

declare(strict_types=1);

/**
 * BankSploit auth: logout. Destroys the session and returns to login.
 *
 * Session/core bootstrap happens in index.php; we just tear down and redirect.
 */

Session::destroy();

redirect('/login');
