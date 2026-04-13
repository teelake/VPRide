<?php

declare(strict_types=1);

/**
 * Optional: if you upload the whole repo under …/vpride/, visiting …/vpride/ redirects to the API admin.
 * Relative redirect works on any domain.
 */
header('Location: backend/', true, 302);
exit;
