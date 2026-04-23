<?php

declare(strict_types=1);

/**
 * Optional: if the whole repository is under a subfolder, visiting that folder
 * can redirect to the PHP backend (relative 302, works on any domain).
 * Rename to `index.php` in that folder if you are NOT using `index.html` as the home page.
 */
header('Location: backend/', true, 302);
exit;
