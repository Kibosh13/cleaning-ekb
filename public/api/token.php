<?php
declare(strict_types=1);
require __DIR__.'/../../app/bootstrap.php';
common_headers();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(['token'=>csrf()]);
