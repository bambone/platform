<?php

/**
 * Однократная генерация лёгких WebP-обложек для демо-программ aflyatunov.
 * Запуск: php database/seeders/Tenant/bin/build_program_covers.php
 */
declare(strict_types=1);

$out = dirname(__DIR__).'/assets/program-covers';
if (! is_dir($out) && ! mkdir($out, 0775, true) && ! is_dir($out)) {
    fwrite(STDERR, "Cannot create {$out}\n");
    exit(1);
}

if (! function_exists('imagecreatetruecolor')) {
    fwrite(STDERR, "PHP GD (imagecreatetruecolor) required.\n");
    exit(1);
}

$w = 640;
$h = 360;

$specs = [
    'single-session.webp' => ['kind' => 'headlight_vignette'],
    'confidence.webp' => ['kind' => 'calm_lanes'],
    'counter-emergency.webp' => ['kind' => 'ice_diagonal'],
    'parking.webp' => ['kind' => 'parking_slots'],
    'city-driving.webp' => ['kind' => 'city_bokeh'],
    'route.webp' => ['kind' => 'route_curve'],
    'motorsport.webp' => ['kind' => 'checkered'],
];

foreach ($specs as $file => $spec) {
    $im = imagecreatetruecolor($w, $h);
    imagealphablending($im, true);
    imagesavealpha($im, true);

    match ($spec['kind']) {
        'headlight_vignette' => fill_headlight_vignette($im, $w, $h),
        'calm_lanes' => fill_calm_lanes($im, $w, $h),
        'ice_diagonal' => fill_ice_diagonal($im, $w, $h),
        'parking_slots' => fill_parking_slots($im, $w, $h),
        'city_bokeh' => fill_city_bokeh($im, $w, $h),
        'route_curve' => fill_route_curve($im, $w, $h),
        'checkered' => fill_checkered($im, $w, $h),
        default => fill_solid($im, $w, $h, 10, 12, 18),
    };

    $path = $out.'/'.$file;
    if (! imagewebp($im, $path, 72)) {
        fwrite(STDERR, "imagewebp failed: {$path}\n");
        exit(1);
    }
    imagedestroy($im);
    $bytes = filesize($path);
    fwrite(STDOUT, sprintf("%s\t%d B\n", $file, $bytes));
}

function rgb($im, int $r, int $g, int $b): int
{
    return imagecolorallocate($im, max(0, min(255, $r)), max(0, min(255, $g)), max(0, min(255, $b)));
}

function fill_solid($im, int $w, int $h, int $r, int $g, int $b): void
{
    $c = rgb($im, $r, $g, $b);
    imagefilledrectangle($im, 0, 0, $w, $h, $c);
}

function lerp(int $a, int $b, float $t): int
{
    return (int) round($a + ($b - $a) * $t);
}

/** Занятие: тёмный кабинет, мягкое «свечение» по центру. */
function fill_headlight_vignette($im, int $w, int $h): void
{
    $cx = (int) ($w * 0.42);
    $cy = (int) ($h * 0.48);
    $max = (int) hypot($w, $h);
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $d = hypot($x - $cx, $y - $cy) / $max;
            $lift = (1 - min(1, $d * 1.35)) * 55;
            $r = lerp(8, 28, $d) + (int) ($lift * 0.4);
            $g = lerp(10, 32, $d) + (int) ($lift * 0.35);
            $b = lerp(18, 48, $d) + (int) ($lift * 0.5);
            imagesetpixel($im, $x, $y, rgb($im, $r, $g, $b));
        }
    }
}

/** Уверенность: спокойный градиент + едва заметные «полосы». */
function fill_calm_lanes($im, int $w, int $h): void
{
    for ($y = 0; $y < $h; $y++) {
        $t = $y / $h;
        $r = lerp(12, 20, $t);
        $g = lerp(18, 28, $t);
        $b = lerp(28, 42, $t);
        $c = rgb($im, $r, $g, $b);
        imageline($im, 0, $y, $w, $y, $c);
    }
    $lane = rgb($im, 35, 48, 58);
    imagesetthickness($im, 2);
    imageline($im, (int) ($w * 0.28), 0, (int) ($w * 0.22), $h, $lane);
    imageline($im, (int) ($w * 0.72), 0, (int) ($w * 0.78), $h, $lane);
    imagesetthickness($im, 1);
}

/** Контраварийка: холодный градиент + редкие светлые полосы (лёгкий файл). */
function fill_ice_diagonal($im, int $w, int $h): void
{
    for ($y = 0; $y < $h; $y++) {
        $t = $y / $h;
        $r = lerp(8, 18, $t);
        $g = lerp(14, 32, $t);
        $b = lerp(24, 48, $t);
        $c = rgb($im, $r, $g, $b);
        imageline($im, 0, $y, $w, $y, $c);
    }
    imagesetthickness($im, 3);
    $frost = rgb($im, 68, 88, 108);
    foreach ([-80, 120, 320] as $off) {
        imageline($im, $off, 0, $off + $h, $h, $frost);
    }
    imagesetthickness($im, 1);
}

/** Парковка: вертикальные «места». */
function fill_parking_slots($im, int $w, int $h): void
{
    fill_solid($im, $w, $h, 14, 14, 18);
    $line = rgb($im, 42, 44, 50);
    $slot = (int) ($w / 7);
    for ($x = $slot; $x < $w; $x += $slot) {
        imageline($im, $x, (int) ($h * 0.15), $x, (int) ($h * 0.85), $line);
    }
    $mark = rgb($im, 60, 58, 52);
    imagefilledrectangle($im, (int) ($w * 0.45), (int) ($h * 0.72), (int) ($w * 0.55), (int) ($h * 0.78), $mark);
}

/** Город: другой рисунок боке (не как общая текстура). */
function fill_city_bokeh($im, int $w, int $h): void
{
    fill_solid($im, $w, $h, 6, 8, 14);
    mt_srand(42);
    for ($i = 0; $i < 85; $i++) {
        $bx = mt_rand(0, $w);
        $by = mt_rand(0, $h);
        $br = mt_rand(2, 28);
        $aa = mt_rand(35, 95);
        $col = imagecolorallocatealpha($im, 180, 150, 110, 127 - (int) ($aa / 2));
        imagefilledellipse($im, $bx, $by, $br, $br, $col);
    }
    mt_srand();
    $dark = rgb($im, 5, 7, 12);
    imagefilledrectangle($im, 0, 0, $w, (int) ($h * 0.25), $dark);
    imagefilledrectangle($im, 0, (int) ($h * 0.72), $w, $h, $dark);
}

/** Маршрут: изогнутая «дорога». */
function fill_route_curve($im, int $w, int $h): void
{
    fill_solid($im, $w, $h, 11, 12, 20);
    $road = rgb($im, 28, 30, 38);
    for ($x = 0; $x < $w; $x++) {
        $yy = (int) ($h * 0.55 + sin($x / $w * M_PI * 1.6) * $h * 0.12);
        imagefilledellipse($im, $x, $yy, 14, 14, $road);
    }
    $gold = rgb($im, 120, 98, 68);
    for ($x = 0; $x < $w; $x += 6) {
        $yy = (int) ($h * 0.52 + sin($x / $w * M_PI * 1.6) * $h * 0.12);
        imagesetpixel($im, $x, $yy, $gold);
    }
}

/** Автоспорт: приглушённая клетка. */
function fill_checkered($im, int $w, int $h): void
{
    fill_solid($im, $w, $h, 9, 9, 12);
    $s = 28;
    $a = rgb($im, 22, 22, 26);
    $b = rgb($im, 14, 14, 18);
    for ($y = 0; $y < $h; $y += $s) {
        for ($x = 0; $x < $w; $x += $s) {
            $c = ((int) ($x / $s) + (int) ($y / $s)) % 2 === 0 ? $a : $b;
            imagefilledrectangle($im, $x, $y, min($x + $s - 1, $w - 1), min($y + $s - 1, $h - 1), $c);
        }
    }
}
