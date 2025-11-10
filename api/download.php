<?php
$file = $_GET['file'] ?? '';
if (!preg_match('/^coloring-book-.+\.pdf$/', $file)) {
    http_response_code(400);
    exit('Invalid file');
}

$path = sys_get_temp_dir() . '/' . $file;
if (!file_exists($path)) {
    http_response_code(404);
    exit('File not found');
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
unlink($path);