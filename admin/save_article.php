<?php
/**
 * Save Article Handler for AkkuApps Admin
 * Handles article creation and updates with CKEditor 5 content
 */

session_start();
require_once '../includes/db.php'; // Your database connection file

// Check admin authentication
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Get admin user ID
$admin_id = $_SESSION['admin_id'] ?? 1;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Sanitize and validate inputs
        $article_type = mysqli_real_escape_string($conn, $_POST['article_type'] ?? 'news');
        $title = mysqli_real_escape_string($conn, trim($_POST['title'] ?? ''));
        $subtitle = mysqli_real_escape_string($conn, trim($_POST['subtitle'] ?? ''));
        $content = $_POST['content'] ?? ''; // CKEditor HTML content
        $meta_description = mysqli_real_escape_string($conn, trim($_POST['meta_description'] ?? ''));
        $tags = mysqli_real_escape_string($conn, trim($_POST['tags'] ?? ''));
        $status = mysqli_real_escape_string($conn, $_POST['status'] ?? 'draft');
        
        // Validate required fields
        if (empty($title)) {
            throw new Exception('Title is required');
        }
        
        if (empty($content)) {
            throw new Exception('Content is required');
        }
        
        // Generate SEO-friendly slug
        $slug = generateSlug($title);
        
        // Handle featured image upload
        $featured_image = null;
        if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
            $featured_image = uploadFeaturedImage($_FILES['featured_image']);
        }
        
        // Auto-generate meta description if not provided
        if (empty($meta_description)) {
            $meta_description = generateMetaDescription($content);
        }
        
        // Prepare data for insertion
        $created_at = date('Y-m-d H:i:s');
        $published_at = ($status === 'published') ? $created_at : null;
        
        // Check if updating existing article
        $article_id = $_POST['article_id'] ?? null;
        
        if ($article_id) {
            // Update existing article
            $sql = "UPDATE news_blogs SET 
                    title = ?,
                    subtitle = ?,
                    slug = ?,
                    content = ?,
                    kind = ?,
                    featured_image = COALESCE(?, featured_image),
                    meta_description = ?,
                    tags = ?,
                    status = ?,
                    updated_at = ?,
                    published_at = CASE WHEN ? = 'published' AND published_at IS NULL THEN ? ELSE published_at END
                    WHERE id = ?";
            
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'ssssssssssssi', 
                $title, $subtitle, $slug, $content, $article_type,
                $featured_image, $meta_description, $tags, $status,
                $created_at, $status, $created_at, $article_id
            );
            
        } else {
            // Insert new article
            $sql = "INSERT INTO news_blogs (
                        title, subtitle, slug, content, kind, 
                        featured_image, meta_description, tags, 
                        status, author_id, created_at, published_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'ssssssssssss',
                $title, $subtitle, $slug, $content, $article_type,
                $featured_image, $meta_description, $tags,
                $status, $admin_id, $created_at, $published_at
            );
        }
        
        // Execute query
        if (mysqli_stmt_execute($stmt)) {
            $new_article_id = $article_id ?: mysqli_insert_id($conn);
            
            // Log the action
            logAdminAction($conn, $admin_id, 'article_saved', $new_article_id);
            
            // Set success message
            $_SESSION['success_message'] = 'Article saved successfully!';
            
            // Redirect back to news page
            header('Location: news.php?article_id=' . $new_article_id . '&success=1');
            exit;
            
        } else {
            throw new Exception('Failed to save article: ' . mysqli_error($conn));
        }
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
        header('Location: news.php?error=1');
        exit;
    }
}

// Generate SEO-friendly slug from title
function generateSlug($title) {
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    
    // Ensure uniqueness
    global $conn;
    $original_slug = $slug;
    $counter = 1;
    
    while (slugExists($conn, $slug)) {
        $slug = $original_slug . '-' . $counter;
        $counter++;
    }
    
    return $slug;
}

// Check if slug already exists
function slugExists($conn, $slug) {
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM news_blogs WHERE slug = ?");
    mysqli_stmt_bind_param($stmt, 's', $slug);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    return $row['count'] > 0;
}

// Upload featured image
function uploadFeaturedImage($file) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    // Validate file type
    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception('Invalid file type. Only JPEG, PNG, WebP, and GIF allowed.');
    }
    
    // Validate file size
    if ($file['size'] > $max_size) {
        throw new Exception('File too large. Maximum size is 5MB.');
    }
    
    // Create upload directory if it doesn't exist
    $upload_dir = '../assets/uploads/articles/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('article_') . '_' . time() . '.' . $extension;
    $upload_path = $upload_dir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return '/assets/uploads/articles/' . $filename;
    } else {
        throw new Exception('Failed to upload image');
    }
}

// Generate meta description from content
function generateMetaDescription($html_content, $max_length = 160) {
    // Strip HTML tags
    $text = strip_tags($html_content);
    
    // Remove extra whitespace
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);
    
    // Truncate to max length
    if (strlen($text) > $max_length) {
        $text = substr($text, 0, $max_length - 3) . '...';
    }
    
    return $text;
}

// Log admin action
function logAdminAction($conn, $admin_id, $action, $reference_id) {
    $sql = "INSERT INTO admin_logs (admin_id, action, reference_id, created_at) 
            VALUES (?, ?, ?, NOW())";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'isi', $admin_id, $action, $reference_id);
    mysqli_stmt_execute($stmt);
}
?>