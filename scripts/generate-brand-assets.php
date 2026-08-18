<?php

declare(strict_types=1);

function roundedRectangle(GdImage $image, int $x, int $y, int $width, int $height, int $radius, int $color): void
{
    imagefilledrectangle($image, $x + $radius, $y, $x + $width - $radius, $y + $height, $color);
    imagefilledrectangle($image, $x, $y + $radius, $x + $width, $y + $height - $radius, $color);
    imagefilledellipse($image, $x + $radius, $y + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($image, $x + $width - $radius, $y + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($image, $x + $radius, $y + $height - $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($image, $x + $width - $radius, $y + $height - $radius, $radius * 2, $radius * 2, $color);
}

function roundedLine(GdImage $image, int $x1, int $y1, int $x2, int $y2, int $width, int $color): void
{
    imagesetthickness($image, $width);
    imageline($image, $x1, $y1, $x2, $y2, $color);
    imagefilledellipse($image, $x1, $y1, $width, $width, $color);
    imagefilledellipse($image, $x2, $y2, $width, $width, $color);
}

function brandMark(int $size): GdImage
{
    $image = imagecreatetruecolor($size, $size);
    imagesavealpha($image, true);
    imagealphablending($image, false);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
    imagealphablending($image, true);
    $orange = imagecolorallocate($image, 255, 107, 53);
    $white = imagecolorallocate($image, 255, 255, 255);
    $dot = imagecolorallocate($image, 255, 216, 201);
    $margin = (int) round($size * .09);
    roundedRectangle($image, $margin, $margin, $size - ($margin * 2), $size - ($margin * 2), (int) round($size * .21), $orange);

    $center = (int) round($size * .5);
    $diameter = (int) round($size * .48);
    $stroke = max(3, (int) round($size * .035));
    imagefilledellipse($image, $center, $center, $diameter, $diameter, $white);
    imagefilledellipse($image, $center, $center, $diameter - ($stroke * 2), $diameter - ($stroke * 2), $orange);
    roundedLine($image, $center, (int) round($size * .35), $center, (int) round($size * .51), $stroke, $white);
    roundedLine($image, $center, (int) round($size * .51), (int) round($size * .61), (int) round($size * .575), $stroke, $white);
    imagefilledellipse($image, (int) round($size * .65), (int) round($size * .25), (int) round($size * .075), (int) round($size * .075), $dot);

    return $image;
}

$public = dirname(__DIR__).DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR;
$png = brandMark(512);
imagepng($png, $public.'brand-mark.png', 9);

$favicon = brandMark(256);
ob_start();
imagepng($favicon, null, 9);
$faviconPng = (string) ob_get_clean();

$ico = pack('vvv', 0, 1, 1)
    .pack('CCCCvvVV', 0, 0, 0, 0, 1, 32, strlen($faviconPng), 22)
    .$faviconPng;
file_put_contents($public.'favicon.ico', $ico);

echo "Generated public/brand-mark.png and public/favicon.ico\n";
