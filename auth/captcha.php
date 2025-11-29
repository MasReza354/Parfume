<?php
session_start();

// Generate CAPTCHA - Exclude confusing characters (0, O, I, 1, l)
function generateCaptcha()
{
  // Remove confusing characters: 0, O, I, 1, l
  $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
  $length = 6;
  $captcha = '';
  for ($i = 0; $i < $length; $i++) {
    $captcha .= $chars[rand(0, strlen($chars) - 1)];
  }
  $_SESSION['captcha'] = $captcha;
  return $captcha;
}

// Generate new CAPTCHA
$captchaCode = generateCaptcha();

// Create CAPTCHA image with better quality
$width = 160;
$height = 50;
$image = imagecreatetruecolor($width, $height);

// Colors - Pink theme
$bg_color = imagecolorallocate($image, 253, 236, 236); // Light pink background
$text_color = imagecolorallocate($image, 205, 127, 127); // Main pink color
$line_color = imagecolorallocate($image, 245, 205, 205); // Lighter pink for lines

// Fill background
imagefilledrectangle($image, 0, 0, $width, $height, $bg_color);

// Add subtle noise lines (reduced for better readability)
for ($i = 0; $i < 3; $i++) {
  imageline($image, rand(0, $width), rand(0, $height), rand(0, $width), rand(0, $height), $line_color);
}

// Add text with better spacing and larger font
$font_size = 5; // Built-in font size (1-5)
$char_spacing = 22; // Space between characters
$start_x = 15;
$start_y = 15;

// Draw each character separately for better spacing
for ($i = 0; $i < strlen($captchaCode); $i++) {
  $x = $start_x + ($i * $char_spacing);
  $y = $start_y + rand(-3, 3); // Slight vertical variation
  imagestring($image, $font_size, $x, $y, $captchaCode[$i], $text_color);
}

// Add minimal noise dots (reduced for clarity)
for ($i = 0; $i < 30; $i++) {
  $dot_color = imagecolorallocate($image, rand(230, 250), rand(200, 220), rand(200, 220));
  imagesetpixel($image, rand(0, $width), rand(0, $height), $dot_color);
}

// Set headers and output image
header('Content-Type: image/png');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

imagepng($image);
imagedestroy($image);
