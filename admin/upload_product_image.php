<?php
// Start session to check authentication
session_start();

// Security check - only admin can upload
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'superadmin'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Configuration
$uploadDir = '../images/products/';
$maxFileSize = 5 * 1024 * 1024; // 5MB
$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
$allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];

// Create upload directory if it doesn't exist
if (!file_exists($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Gagal membuat direktori upload']);
        exit;
    }
}

// Check if file was uploaded
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Tidak ada file yang diunggah']);
    exit;
}

$uploadedFile = $_FILES['image'];

// Validate file size
if ($uploadedFile['size'] > $maxFileSize) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Ukuran file terlalu besar (maksimal 5MB)']);
    exit;
}

// Validate MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $uploadedFile['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedMimeTypes)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Tipe file tidak diizinkan']);
    exit;
}

// Get file extension based on MIME type
$extension = '';
switch ($mimeType) {
    case 'image/jpeg':
        $extension = 'jpg';
        break;
    case 'image/png':
        $extension = 'png';
        break;
    case 'image/webp':
        $extension = 'webp';
        break;
    default:
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Tipe file tidak valid']);
        exit;
}

// Generate unique filename
$uniqueId = uniqid('product_', true);
$filename = $uniqueId . '.' . $extension;
$targetPath = $uploadDir . $filename;

// Process and optimize the image
$image = null;
$success = false;

switch ($mimeType) {
    case 'image/jpeg':
        $image = imagecreatefromjpeg($uploadedFile['tmp_name']);
        break;
    case 'image/png':
        $image = imagecreatefrompng($uploadedFile['tmp_name']);
        break;
    case 'image/webp':
        $image = imagecreatefromwebp($uploadedFile['tmp_name']);
        break;
}

if ($image) {
    // Get original dimensions
    $width = imagesx($image);
    $height = imagesy($image);
    
    // Set maximum dimensions (optional resizing for optimization)
    $maxWidth = 1200;
    $maxHeight = 1200;
    
    // Calculate new dimensions while maintaining aspect ratio
    if ($width > $maxWidth || $height > $maxHeight) {
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = round($width * $ratio);
        $newHeight = round($height * $ratio);
        
        // Create resized image
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG and WebP
        if ($mimeType === 'image/png' || $mimeType === 'image/webp') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
            imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
        }
        
        // Copy and resize
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);
        $image = $resized;
    }
    
    // Save the image
    switch ($mimeType) {
        case 'image/jpeg':
            $success = imagejpeg($image, $targetPath, 90); // 90% quality for JPEG
            break;
        case 'image/png':
            $success = imagepng($image, $targetPath, 9); // Max compression for PNG
            break;
        case 'image/webp':
            $success = imagewebp($image, $targetPath, 90); // 90% quality for WebP
            break;
    }
    
    imagedestroy($image);
} else {
    // If image processing fails, try direct move
    $success = move_uploaded_file($uploadedFile['tmp_name'], $targetPath);
}

if ($success) {
    // Return the relative path from the admin directory
    $relativePath = 'images/products/' . $filename;
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'path' => $relativePath,
        'filename' => $filename,
        'message' => 'Gambar berhasil diunggah'
    ]);
} else {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Gagal menyimpan gambar'
    ]);
}
?>
