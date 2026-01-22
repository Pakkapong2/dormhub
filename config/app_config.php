<?php
// config/app_config.php

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];

// Get the absolute path to the project root directory (one level up from this file's directory)
$project_root_path = dirname(__DIR__);

// Get the absolute path to the web server's document root
$document_root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');

// Find the project's folder path relative to the document root
$project_folder = str_replace($document_root, '', str_replace('\\', '/', $project_root_path));
$project_folder = '/' . trim($project_folder, '/'); // Ensure leading/trailing slashes are clean

// Construct the final, correct base URL
$base_url = $protocol . "://" . $host . ($project_folder === '/' ? '/' : $project_folder . '/');

define('BASE_URL', $base_url);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

