<?php
/**
 * CKEditor 5 Image Upload Handler
 * Handles image uploads from the editor
 */

session_start();
require_once '../includes/db.php';

// Check admin authentication
if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    echo json_encode(['error' => ['message' => 'Unauthorized']]);
    exit;
}

// Handle image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['upload'])) {
    try {
        $file = $_FILES['upload'];
        
        // Validate file
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($file['type'], $allowed_types)) {
            throw new Exception('Invalid file type. Only JPEG, PNG, WebP, and GIF allowed.');
        }
        
        if ($file['size'] > $max_size) {
            throw new Exception('File too large. Maximum size is 5MB.');
        }
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Upload error: ' . $file['error']);
        }
        
        // Create upload directory
        $upload_dir = '../assets/uploads/editor/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('img_') . '_' . time() . '.' . $extension;
        $upload_path = $upload_dir . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            // Get the full URL
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'];
            $url = $protocol . '://' . $host . '/assets/uploads/editor/' . $filename;
            
            // Return CKEditor 5 expected format
            echo json_encode([
                'url' => $url,
                'default' => $url
            ]);
            
        } else {
            throw new Exception('Failed to save uploaded file');
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'error' => [
                'message' => $e->getMessage()
            ]
        ]);
    }
} else {
    http_response_code(400);
    echo json_encode([
        'error' => [
            'message' => 'No file uploaded'
        ]
    ]);
}
?>