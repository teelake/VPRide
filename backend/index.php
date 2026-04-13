<?php

declare(strict_types=1);

/**
 * Entry point when the site URL is …/vpride/backend/ (not …/backend/public/).
 * Apache should rewrite all routes here via backend/.htaccess
 */
require __DIR__ . '/public/index.php';
