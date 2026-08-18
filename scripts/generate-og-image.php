<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$background = imagecreatefrompng($root.'/resources/brand/og-background.png');
$mark = imagecreatefrompng($root.'/public/brand-mark.png');
$canvas = imagecreatetruecolor(1200, 630);
imagecopyresampled($canvas, $background, 0, 0, 0, 0, 1200, 630, imagesx($background), imagesy($background));

imagealphablending($canvas, true);
$veil = imagecolorallocatealpha($canvas, 5, 6, 15, 36);
imagefilledrectangle($canvas, 0, 0, 760, 630, $veil);
imagecopyresampled($canvas, $mark, 78, 61, 0, 0, 72, 72, imagesx($mark), imagesy($mark));

$fontCandidates = [
    'bold' => [getenv('WINDIR').'\Fonts\arialbd.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'],
    'regular' => [getenv('WINDIR').'\Fonts\arial.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'],
];
$font = static function (string $weight) use ($fontCandidates): string {
    foreach ($fontCandidates[$weight] as $candidate) {
        if (is_string($candidate) && is_file($candidate)) {
            return $candidate;
        }
    }
    throw new RuntimeException("No {$weight} TrueType font was found.");
};

$bold = $font('bold');
$regular = $font('regular');
$white = imagecolorallocate($canvas, 250, 249, 252);
$muted = imagecolorallocate($canvas, 180, 177, 190);
$orange = imagecolorallocate($canvas, 255, 107, 53);

imagettftext($canvas, 25, 0, 172, 108, $white, $bold, 'myhours');
$wordBounds = imagettfbbox(25, 0, $bold, 'myhours');
$wordWidth = $wordBounds[2] - $wordBounds[0];
imagettftext($canvas, 25, 0, 172 + $wordWidth, 108, $orange, $bold, 'pay');

imagettftext($canvas, 52, 0, 78, 258, $white, $bold, 'Track your hours.');
imagettftext($canvas, 52, 0, 78, 326, $white, $bold, 'Know your worth.');
imagettftext($canvas, 20, 0, 80, 397, $muted, $regular, 'Simple, private work-hour records for modern professionals.');
imagettftext($canvas, 16, 0, 80, 550, $orange, $bold, 'myhourspay.com');

imageinterlace($canvas, true);
imagejpeg($canvas, $root.'/public/og-image.jpg', 82);
echo "Generated public/og-image.jpg\n";
