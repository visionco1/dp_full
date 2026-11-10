<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\Storage;

/**
 * يرسم صوراً تجريبية (قطع، بانرات، أقسام) بمكتبة GD بدون أي تحميل من الإنترنت.
 * لا نكتب نصاً عربياً على الصور لأن GD لا يدعم تشكيل الحروف العربية — العناوين تُعرض فوق الصورة في الموقع والتطبيق.
 */
class SeedArt
{
    private const SS = 2;   // رسم بضعف الدقة ثم تصغير لنعومة الحواف

    public const KINDS = ['brakes' => 'disc', 'filters' => 'filter', 'lighting' => 'lamp', 'body' => 'fender',
        'suspension' => 'shock', 'engine' => 'gear', 'electrical' => 'battery', 'fluids' => 'bottle'];

    public static function enabled(): bool
    {
        return extension_loaded('gd') && function_exists('imagecreatetruecolor') && !app()->runningUnitTests();
    }

    // ---------------------------------------------------------------- واجهات الاستخدام

    /** صورة قطعة 800×600 */
    public static function part(string $kind, string $sku, string $grade): ?string
    {
        if (!self::enabled()) {
            return null;
        }
        [$w, $h] = [800, 600];
        $accent = ['oem' => '#FF6B00', 'cert' => '#0EA5E9', 'first' => '#F59E0B'][$grade] ?? '#FF6B00';
        $im = self::canvas($w, $h);
        self::gradient($im, '#FFFFFF', '#E9EBEF', true);
        $s = self::SS;
        imagefilledellipse($im, (int) ($w * $s / 2), (int) ($h * $s * 0.83), (int) ($w * $s * 0.55), (int) ($h * $s * 0.08), self::col($im, '#000000', 105));
        self::motif($im, $kind, $w * $s / 2, $h * $s * 0.46, $h * $s * 0.34, $accent, '#FFFFFF');
        self::text($im, $sku, 18, 26, $h - 24, '#6B7280');
        self::text($im, strtoupper($grade === 'first' ? '1st grade' : $grade), 15, $w - 26, 44, $accent, true);
        return self::save($im, $w, $h, 'parts/seed-'.self::slug($sku).'.jpg');
    }

    /** بانر بخلفية متدرجة ورسمة على اليسار (النص العربي يظهر يميناً فوقها) */
    public static function banner(string $key, int $w, int $h, string $c1, string $c2, string $kind, ?string $badge = null): ?string
    {
        if (!self::enabled()) {
            return null;
        }
        $s = self::SS;
        $im = self::canvas($w, $h);
        self::gradient($im, $c1, $c2, false);
        $white = self::col($im, '#FFFFFF', 118);
        for ($i = -$h; $i < $w; $i += 90) {   // خطوط مائلة خفيفة
            imagesetthickness($im, 14 * $s);
            imageline($im, ($i) * $s, $h * $s, ($i + $h) * $s, 0, $white);
        }
        imagesetthickness($im, 1);
        $tall = $h > $w;
        $cx = ($tall ? $w * 0.5 : $w * 0.24) * $s;
        $cy = ($tall ? $h * 0.34 : $h * 0.52) * $s;
        $r = min($w, $h) * ($tall ? 0.32 : 0.36) * $s;
        imagefilledellipse($im, (int) $cx, (int) $cy, (int) ($r * 2.9), (int) ($r * 2.9), self::col($im, '#FFFFFF', 112));
        imagefilledellipse($im, (int) $cx, (int) $cy, (int) ($r * 2.2), (int) ($r * 2.2), self::col($im, '#FFFFFF', 104));
        self::motif($im, $kind, $cx, $cy, $r, '#FF6B00', '#FFFFFF');
        if ($badge) {
            $bx = $tall ? $w * 0.5 : $w * 0.42;
            $by = $tall ? $h * 0.12 : $h * 0.3;
            imagefilledellipse($im, (int) ($bx * $s), (int) ($by * $s), (int) (min($w, $h) * 0.3 * $s), (int) (min($w, $h) * 0.3 * $s), self::col($im, '#FF6B00'));
            self::text($im, $badge, (int) (min($w, $h) * 0.07), (int) $bx, (int) ($by + min($w, $h) * 0.035), '#111111', false, true);
        }
        return self::save($im, $w, $h, 'banners/seed-'.$key.'.jpg');
    }

    /** أيقونة قسم 400×400 */
    public static function category(string $slug, string $color, ?string $kind = null): ?string
    {
        if (!self::enabled()) {
            return null;
        }
        [$w, $h] = [400, 400];
        $s = self::SS;
        $im = self::canvas($w, $h);
        self::gradient($im, self::shade($color, 0.25), $color, true);
        imagefilledellipse($im, $w * $s / 2, $h * $s / 2, (int) ($w * $s * 0.78), (int) ($w * $s * 0.78), self::col($im, '#FFFFFF', 100));
        self::motif($im, $kind ?? self::KINDS[$slug] ?? 'gear', $w * $s / 2, $h * $s / 2, $w * $s * 0.27, '#111111', $color);
        return self::save($im, $w, $h, 'categories/seed-'.$slug.'.jpg');
    }

    // ---------------------------------------------------------------- الرسومات

    /** $r = نصف الحجم التقريبي للرسمة */
    private static function motif($im, string $kind, float $cx, float $cy, float $r, string $accent, string $bg): void
    {
        $metal = self::col($im, '#B8BEC7');
        $metalDark = self::col($im, '#6B7280');
        $dark = self::col($im, '#1F2937');
        $acc = self::col($im, $accent);
        $light = self::col($im, '#F3F4F6');
        $e = fn ($x, $y, $d, $c) => imagefilledellipse($im, (int) ($cx + $x * $r), (int) ($cy + $y * $r), (int) ($d * $r), (int) ($d * $r), $c);
        $poly = function (array $pts, $c) use ($im, $cx, $cy, $r) {
            $flat = [];
            foreach ($pts as [$x, $y]) {
                $flat[] = (int) ($cx + $x * $r);
                $flat[] = (int) ($cy + $y * $r);
            }
            imagefilledpolygon($im, $flat, $c);
        };
        $rect = fn ($x1, $y1, $x2, $y2, $c) => imagefilledrectangle($im, (int) ($cx + $x1 * $r), (int) ($cy + $y1 * $r), (int) ($cx + $x2 * $r), (int) ($cy + $y2 * $r), $c);

        switch ($kind) {
            case 'disc':   // هوب فرامل مع كاليبر
                $e(0, 0, 2.0, $metalDark);
                $e(0, 0, 1.86, $metal);
                for ($a = 0; $a < 360; $a += 30) {
                    $e(cos(deg2rad($a)) * 0.68, sin(deg2rad($a)) * 0.68, 0.09, $metalDark);
                }
                $e(0, 0, 0.95, $metalDark);
                $e(0, 0, 0.8, $metal);
                for ($a = 0; $a < 360; $a += 72) {
                    $e(cos(deg2rad($a)) * 0.25, sin(deg2rad($a)) * 0.25, 0.1, $dark);
                }
                $e(0, 0, 0.22, $dark);
                $poly([[0.55, -0.85], [0.95, -0.55], [1.12, 0.0], [0.95, 0.55], [0.55, 0.85], [0.42, 0.62], [0.72, 0.0], [0.42, -0.62]], $acc);
                break;

            case 'filter':   // فلتر أسطواني
                $rect(-0.62, -0.75, 0.62, 0.8, $acc);
                for ($x = -0.52; $x <= 0.53; $x += 0.13) {
                    $rect($x, -0.6, $x + 0.035, 0.68, self::col($im, '#FFFFFF', 85));
                }
                imagefilledellipse($im, (int) $cx, (int) ($cy + 0.8 * $r), (int) (1.24 * $r), (int) (0.34 * $r), $acc);
                imagefilledellipse($im, (int) $cx, (int) ($cy - 0.75 * $r), (int) (1.3 * $r), (int) (0.38 * $r), $metalDark);
                imagefilledellipse($im, (int) $cx, (int) ($cy - 0.78 * $r), (int) (1.18 * $r), (int) (0.3 * $r), $metal);
                imagefilledellipse($im, (int) $cx, (int) ($cy - 0.78 * $r), (int) (0.42 * $r), (int) (0.12 * $r), $dark);
                break;

            case 'lamp':   // كشاف أمامي
                $poly([[-1.15, -0.25], [-0.6, -0.62], [0.75, -0.62], [1.15, -0.2], [1.05, 0.45], [0.55, 0.62], [-0.85, 0.62], [-1.15, 0.25]], $dark);
                $poly([[-1.05, -0.2], [-0.55, -0.52], [0.7, -0.52], [1.04, -0.17], [0.96, 0.38], [0.5, 0.52], [-0.8, 0.52], [-1.05, 0.22]], self::col($im, '#9CA3AF'));
                $e(-0.45, 0, 0.72, $dark);
                $e(-0.45, 0, 0.56, $light);
                $e(-0.45, 0, 0.26, self::col($im, '#FDE68A'));
                $e(0.38, 0, 0.56, $dark);
                $e(0.38, 0, 0.42, $light);
                $e(0.38, 0, 0.18, self::col($im, '#FFFFFF'));
                imagesetthickness($im, (int) max(4, $r * 0.06));
                imageline($im, (int) ($cx - 0.9 * $r), (int) ($cy + 0.42 * $r), (int) ($cx + 0.85 * $r), (int) ($cy + 0.42 * $r), $acc);
                imagesetthickness($im, 1);
                break;

            case 'fender':   // رفرف بفتحة الكفر
                $poly([[-1.2, -0.55], [0.3, -0.65], [1.05, -0.4], [1.2, -0.05], [1.2, 0.6], [-1.2, 0.6]], $acc);
                $poly([[-1.2, -0.55], [0.3, -0.65], [1.05, -0.4], [1.0, -0.3], [0.28, -0.52], [-1.2, -0.42]], self::col($im, '#FFFFFF', 80));
                $e(-0.2, 0.62, 1.25, self::col($im, $bg === '#FFFFFF' ? '#EEF0F3' : $bg));
                $e(-0.2, 0.62, 1.05, $dark);
                $e(-0.2, 0.62, 0.55, $metal);
                break;

            case 'shock':   // مساعد مع سستة
                $rect(-0.16, -1.0, 0.16, 0.95, $metalDark);
                $rect(-0.3, -0.05, 0.3, 0.95, $dark);
                $e(0, -1.0, 0.42, $metal);
                $e(0, -1.0, 0.18, $dark);
                $e(0, 0.98, 0.42, $metal);
                $e(0, 0.98, 0.18, $dark);
                imagesetthickness($im, (int) max(6, $r * 0.13));
                for ($i = 0; $i < 7; $i++) {
                    $y = -0.82 + $i * 0.24;
                    imageline($im, (int) ($cx - 0.55 * $r), (int) ($cy + $y * $r), (int) ($cx + 0.55 * $r), (int) ($cy + ($y + 0.12) * $r), $acc);
                }
                imagesetthickness($im, 1);
                break;

            case 'gear':   // ترس محرك
                for ($a = 0; $a < 360; $a += 30) {
                    $t = deg2rad($a);
                    $pts = [];
                    foreach ([[-0.13, 0.7], [0.13, 0.7], [0.1, 1.02], [-0.1, 1.02]] as [$x, $y]) {
                        $pts[] = [$x * cos($t) - $y * sin($t), $x * sin($t) + $y * cos($t)];
                    }
                    $poly($pts, $acc);
                }
                $e(0, 0, 1.6, $acc);
                $e(0, 0, 1.05, self::col($im, '#FFFFFF', 70));
                $e(0, 0, 0.75, $dark);
                $e(0, 0, 0.32, $metal);
                break;

            case 'battery':   // بطارية
                $rect(-1.0, -0.55, 1.0, 0.8, $dark);
                $rect(-1.0, -0.55, 1.0, -0.28, $acc);
                $rect(-0.75, -0.8, -0.45, -0.55, self::col($im, '#DC2626'));
                $rect(0.45, -0.8, 0.75, -0.55, $metal);
                $poly([[0.12, -0.15], [-0.25, 0.25], [-0.02, 0.25], [-0.14, 0.62], [0.25, 0.15], [0.02, 0.15]], self::col($im, '#FACC15'));
                break;

            case 'bottle':   // جالون زيت
                $poly([[-0.62, -0.45], [-0.2, -0.75], [0.25, -0.75], [0.62, -0.45], [0.66, 0.95], [-0.66, 0.95]], $acc);
                $rect(-0.2, -1.0, 0.12, -0.72, $dark);
                $poly([[0.3, -0.62], [0.9, -0.62], [0.9, 0.3], [0.62, 0.3], [0.62, -0.38], [0.3, -0.38]], self::shadeCol($im, $accent));
                $rect(-0.5, -0.12, 0.5, 0.62, $light);
                $poly([[0, 0.02], [0.17, 0.3], [0.17, 0.38], [-0.17, 0.38], [-0.17, 0.3]], $dark);
                $e(0, 0.38, 0.34, $dark);
                break;
        }
    }

    // ---------------------------------------------------------------- أدوات

    private static function canvas(int $w, int $h)
    {
        $im = imagecreatetruecolor($w * self::SS, $h * self::SS);
        imagealphablending($im, true);
        return $im;
    }

    private static function gradient($im, string $from, string $to, bool $vertical): void
    {
        [$w, $h] = [imagesx($im), imagesy($im)];
        [$r1, $g1, $b1] = self::rgb($from);
        [$r2, $g2, $b2] = self::rgb($to);
        $n = $vertical ? $h : $w;
        for ($i = 0; $i < $n; $i++) {
            $t = $i / max(1, $n - 1);
            $c = imagecolorallocate($im, (int) ($r1 + ($r2 - $r1) * $t), (int) ($g1 + ($g2 - $g1) * $t), (int) ($b1 + ($b2 - $b1) * $t));
            $vertical ? imageline($im, 0, $i, $w, $i, $c) : imageline($im, $i, 0, $i, $h, $c);
        }
    }

    private static function text($im, string $s, int $size, int $x, int $y, string $hex, bool $alignEnd = false, bool $center = false): void
    {
        $font = self::font();
        if (!$font || !function_exists('imagettftext')) {
            return;
        }
        $size *= self::SS;
        [$x, $y] = [$x * self::SS, $y * self::SS];
        $box = imagettfbbox($size, 0, $font, $s);
        $tw = $box[2] - $box[0];
        $x = $center ? $x - $tw / 2 : ($alignEnd ? $x - $tw : $x);
        imagettftext($im, $size, 0, (int) $x, (int) $y, self::col($im, $hex), $font, $s);
    }

    private static function font(): ?string
    {
        static $font = false;
        if ($font === false) {
            $font = collect([
                'C:/Windows/Fonts/arialbd.ttf', 'C:/Windows/Fonts/arial.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
                '/System/Library/Fonts/Supplemental/Arial Bold.ttf', '/Library/Fonts/Arial.ttf',
            ])->first(fn ($f) => is_file($f));
        }
        return $font;
    }

    private static function save($im, int $w, int $h, string $path): string
    {
        $out = imagecreatetruecolor($w, $h);
        imagecopyresampled($out, $im, 0, 0, 0, 0, $w, $h, imagesx($im), imagesy($im));
        ob_start();
        imagejpeg($out, null, 86);
        Storage::disk('public')->put($path, ob_get_clean());
        return $path;
    }

    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private static function col($im, string $hex, int $alpha = 0): int
    {
        [$r, $g, $b] = self::rgb($hex);
        return imagecolorallocatealpha($im, $r, $g, $b, $alpha);
    }

    /** نسخة أغمق/أفتح من اللون: $k موجب = أفتح */
    private static function shade(string $hex, float $k): string
    {
        return '#'.collect(self::rgb($hex))->map(fn ($v) => str_pad(dechex((int) max(0, min(255, $k > 0 ? $v + (255 - $v) * $k : $v * (1 + $k)))), 2, '0', STR_PAD_LEFT))->implode('');
    }

    private static function shadeCol($im, string $hex): int
    {
        return self::col($im, self::shade($hex, -0.25));
    }

    private static function slug(string $s): string
    {
        return strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $s));
    }
}
