<?php

declare(strict_types=1);

/**
 * Entry when the site URL is …/backend/ (not …/public/) with backend/.htaccess.
 * Apache should rewrite all routes here via backend/.htaccess
 */
require __DIR__ . '/public/index.php';
