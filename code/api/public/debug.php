<?php

declare(strict_types=1);
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'] ?? '?';
$contentType = $_SERVER['CONTENT_TYPE'] ?? 'not set';
$contentLength = $_SERVER['CONTENT_LENGTH'] ?? 'not set';
$rawInput = file_get_contents('php://input');

echo json_encode([
    'method' => $method,
    'content_type' => $contentType,
    'content_length' => $contentLength,
    'raw_input' => $rawInput,
]);
