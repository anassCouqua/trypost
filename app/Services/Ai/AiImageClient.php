<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\Workspace\ContentLanguage;
use App\Enums\Workspace\ImageStyle;
use App\Support\HexColorName;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Image;
use Laravel\Ai\Responses\ImageResponse;
use Throwable;

class AiImageClient
{
    private const BRAND_DESCRIPTION_MAX = 200;

    /**
     * Generate an image via the configured AI_IMAGE_PROVIDER (defaults to OpenAI).
     * Returns a local branded illustration when the remote image provider is unavailable.
     *
     * @param  array<int, string>  $keywords
     * @return array{bytes: string, provider: string, model: string}|null
     */
    public function generate(
        array $keywords,
        ImageStyle $style,
        string $orientation = 'portrait',
        string $language = 'en',
        ?string $brandColor = null,
        ?string $backgroundColor = null,
        ?string $textColor = null,
        ?string $brandDescription = null,
        string $quality = 'low',
        int $timeout = 180,
    ): ?array {
        $keywords = $this->cleanKeywords($keywords);

        if ($keywords === []) {
            return null;
        }

        $prompt = $this->buildPrompt($keywords, $style, $language, $brandColor, $backgroundColor, $textColor, $brandDescription);

        try {
            $builder = Image::of($prompt)->quality($quality)->timeout($timeout);

            $builder = match ($orientation) {
                'portrait' => $builder->portrait(),
                'landscape' => $builder->landscape(),
                default => $builder->square(),
            };

            $result = $this->toResult($builder->generate());

            return $result ?? $this->fallbackImage($keywords, $orientation, $brandColor, $backgroundColor);
        } catch (Throwable $e) {
            Log::warning('AiImageClient: generation failed', [
                'style' => $style->value,
                'orientation' => $orientation,
                'error' => $e->getMessage(),
            ]);

            return $this->fallbackImage($keywords, $orientation, $brandColor, $backgroundColor);
        }
    }

    /**
     * Free local visual fallback so Snay3i never creates a text-only post when
     * the remote image provider is unavailable.
     *
     * @param  array<int, string>  $keywords
     * @return array{bytes: string, provider: string, model: string}
     */
    private function fallbackImage(array $keywords, string $orientation, ?string $brandColor, ?string $backgroundColor): array
    {
        $width = $orientation === 'landscape' ? 1600 : 1080;
        $height = $orientation === 'landscape' ? 900 : 1350;

        $image = imagecreatetruecolor($width, $height);

        [$br, $bg, $bb] = $this->hexToRgb($backgroundColor ?: '#111827');
        [$ar, $ag, $ab] = $this->hexToRgb($brandColor ?: '#F59E0B');

        $background = imagecolorallocate($image, $br, $bg, $bb);
        imagefill($image, 0, 0, $background);

        $panel1 = imagecolorallocatealpha($image, $ar, $ag, $ab, 85);
        $panel2 = imagecolorallocatealpha($image, min(255, $ar + 35), min(255, $ag + 20), $ab, 105);
        imagefilledpolygon($image, [0, 0, $width, 0, $width, (int) ($height * 0.38)], 3, $panel1);
        imagefilledpolygon($image, [0, $height, $width, $height, $width, (int) ($height * 0.65)], 3, $panel2);

        $keywordText = strtolower(implode(' ', $keywords));

        $house = imagecolorallocate($image, 245, 245, 244);
        $metal = imagecolorallocate($image, 148, 163, 184);
        $dark = imagecolorallocate($image, 31, 41, 55);
        $green = imagecolorallocate($image, 34, 197, 94);
        $red = imagecolorallocate($image, 239, 68, 68);
        $blue = imagecolorallocate($image, 59, 130, 246);
        $yellow = imagecolorallocate($image, 250, 204, 21);

        $cx = (int) ($width * 0.50);
        $cy = (int) ($height * 0.48);

        imagefilledrectangle($image, $cx - 230, $cy - 70, $cx + 230, $cy + 220, $house);
        imagefilledpolygon($image, [
            $cx - 280, $cy - 70,
            $cx, $cy - 280,
            $cx + 280, $cy - 70,
        ], 3, $dark);

        imagefilledrectangle($image, $cx - 45, $cy + 70, $cx + 45, $cy + 220, $metal);
        imagefilledrectangle($image, $cx - 165, $cy + 15, $cx - 55, $cy + 95, $blue);
        imagefilledrectangle($image, $cx + 55, $cy + 15, $cx + 165, $cy + 95, $green);

        if (str_contains($keywordText, 'electric')) {
            imagefilledpolygon($image, [
                $cx + 120, $cy - 150,
                $cx + 55, $cy - 5,
                $cx + 110, $cy - 5,
                $cx + 40, $cy + 130,
                $cx + 135, $cy,
                $cx + 82, $cy,
            ], 6, $yellow);
        } elseif (str_contains($keywordText, 'plumb') || str_contains($keywordText, 'water') || str_contains($keywordText, 'robinet')) {
            imagefilledellipse($image, $cx + 140, $cy - 120, $cx + 220, $cy - 20, $blue);
            imageline($image, $cx - 240, $cy + 165, $cx + 240, $cy + 165, $metal, 18);
        } elseif (str_contains($keywordText, 'paint') || str_contains($keywordText, 'peint')) {
            imagefilledrectangle($image, $cx - 260, $cy - 180, $cx - 95, $cy - 125, $red);
            imageline($image, $cx - 95, $cy - 152, $cx + 10, $cy - 70, $metal, 14);
            imageline($image, $cx + 10, $cy - 70, $cx + 45, $cy + 60, $metal, 14);
        } elseif (str_contains($keywordText, 'carpent') || str_contains($keywordText, 'menuis')) {
            imagefilledrectangle($image, $cx - 170, $cy - 145, $cx - 20, $cy - 90, $metal);
            imageline($image, $cx - 20, $cy - 120, $cx + 110, $cy + 30, $yellow, 22);
        } else {
            imageline($image, $cx - 190, $cy - 110, $cx + 80, $cy + 90, $metal, 28);
            imagefilledellipse($image, $cx - 235, $cy - 145, $cx - 145, $cy - 55, $metal);
            imagefilledrectangle($image, $cx + 60, $cy + 85, $cx + 220, $cy + 160, $red);
            imagefilledrectangle($image, $cx + 95, $cy + 55, $cx + 185, $cy + 90, $metal);
        }

        $accent = imagecolorallocatealpha($image, $ar, $ag, $ab, 45);
        for ($i = 0; $i < 8; $i++) {
            $x = 70 + $i * (int) (($width - 140) / 8);
            imagefilledellipse($image, $x, 90 + ($i % 2) * 55, $x + 16, 106 + ($i % 2) * 55, $accent);
        }

        ob_start();
        imagewebp($image, null, 86);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        Log::info('AiImageClient: using local fallback image', [
            'keywords' => $keywords,
            'orientation' => $orientation,
        ]);

        return [
            'bytes' => $bytes,
            'provider' => 'internal',
            'model' => 'snay3i-local-branded-fallback',
        ];
    }

    /**
     * @param  array<int, string>  $keywords
     * @return array<int, string>
     */
    private function cleanKeywords(array $keywords): array
    {
        return collect($keywords)
            ->map(fn (string $keyword) => trim($keyword))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $keywords
     */
    private function buildPrompt(
        array $keywords,
        ImageStyle $style,
        string $language,
        ?string $brandColor,
        ?string $backgroundColor,
        ?string $textColor,
        ?string $brandDescription,
    ): string {
        $palette = $this->buildPaletteContext($brandColor, $backgroundColor, $textColor);

        return view('prompts.post_image.generator', [
            'style' => $style->value,
            'scene' => implode(', ', $keywords),
            'language_name' => $this->languageName($language),
            'has_brand_palette' => data_get($palette, 'is_defined', false),
            'brand_color_name' => data_get($palette, 'brand_color_name'),
            'background_color_name' => data_get($palette, 'background_color_name'),
            'text_color_name' => data_get($palette, 'text_color_name'),
            'brand_context' => $this->resolveBrandContext($brandDescription),
        ])->render();
    }

    private function resolveBrandContext(?string $brandDescription): ?string
    {
        $trimmed = trim((string) $brandDescription);

        if ($trimmed === '') {
            return null;
        }

        return mb_strlen($trimmed) > self::BRAND_DESCRIPTION_MAX
            ? mb_substr($trimmed, 0, self::BRAND_DESCRIPTION_MAX).'…'
            : $trimmed;
    }

    /**
     * Extract the raw image bytes and the provider/model that produced them.
     * Called from inside generate()'s try block so a malformed response
     * (e.g. no images) is treated as a failure, not an uncaught exception.
     *
     * @return array{bytes: string, provider: string, model: string}|null
     */
    private function toResult(ImageResponse $response): ?array
    {
        $bytes = (string) $response;

        if ($bytes === '') {
            return null;
        }

        return [
            'bytes' => $bytes,
            'provider' => (string) $response->meta->provider,
            'model' => (string) $response->meta->model,
        ];
    }

    private function languageName(string $code): string
    {
        return (ContentLanguage::tryFrom($code) ?? ContentLanguage::DEFAULT)->englishName();
    }

    /**
     * @return array{
     *   is_defined: bool,
     *   brand_color_name: ?string,
     *   background_color_name: ?string,
     *   text_color_name: ?string
     * }
     */
    private function buildPaletteContext(
        ?string $brandColor,
        ?string $backgroundColor,
        ?string $textColor,
    ): array {
        $brandColorName = $this->resolveColorName($brandColor);
        $backgroundColorName = $this->resolveColorName($backgroundColor);
        $textColorName = $this->resolveColorName($textColor);

        return [
            'is_defined' => $brandColorName !== null || $backgroundColorName !== null || $textColorName !== null,
            'brand_color_name' => $brandColorName,
            'background_color_name' => $backgroundColorName,
            'text_color_name' => $textColorName,
        ];
    }

    private function resolveColorName(?string $hex): ?string
    {
        if ($hex === null || trim($hex) === '') {
            return null;
        }

        return HexColorName::approximate($hex);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}
