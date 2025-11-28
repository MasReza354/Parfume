<?php
session_start();

// Generate CAPTCHA
function generateCaptcha()
{
  $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
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

// Create CAPTCHA image
$image = imagecreate(120, 40);
$bg_color = imagecolorallocate($image, 240, 240, 240);
$text_color = imagecolorallocate($image, 50, 50, 50);

// Add noise lines
for ($i = 0; $i < 5; $i++) {
  $line_color = imagecolorallocate($image, rand(150, 200), rand(150, 200), rand(150, 200));
  imageline($image, rand(0, 120), rand(0, 40), rand(0, 120), rand(0, 40), $line_color);
}

// Add text
imagestring($image, 5, 35, 12, $captchaCode, $text_color);

// Add noise dots
for ($i = 0; $i < 50; $i++) {
  $dot_color = imagecolorallocate($image, rand(100, 200), rand(100, 200), rand(100, 200));
  imagesetpixel($image, rand(0, 120), rand(0, 40), $dot_color);
}

// Set headers and output image
header('Content-Type: image/png');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

imagepng($image);
imagedestroy($image);
