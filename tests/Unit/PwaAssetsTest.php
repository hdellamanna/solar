<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * FASE 5 — PWA asset sanity tests.
 *
 * Pure filesystem checks: every PWA icon / splash / screenshot we ship
 * must be a real PNG of the correct dimensions. Guards against a future
 * build script silently shipping 0-byte files or wrong-size PNGs.
 */
class PwaAssetsTest extends TestCase
{
    /**
     * Pairs of [absolute path, expected [width, height]].
     * @return array<string, array{0: string, 1: array{0: int, 1: int}}>
     */
    public static function assetProvider(): array
    {
        $base = __DIR__ . '/../../public/pwa';
        return [
            'icon-192'        => [$base . '/icon-192.png',         [192, 192]],
            'icon-256'        => [$base . '/icon-256.png',         [256, 256]],
            'icon-384'        => [$base . '/icon-384.png',         [384, 384]],
            'icon-512'        => [$base . '/icon-512.png',         [512, 512]],
            'apple-touch'     => [$base . '/apple-touch-icon.png', [180, 180]],
            'favicon-16'      => [$base . '/favicon-16.png',       [16, 16]],
            'favicon-32'      => [$base . '/favicon-32.png',       [32, 32]],
            'splash-iphone-x' => [$base . '/splash-1125x2436.png', [1125, 2436]],
            'splash-xs-max'   => [$base . '/splash-1242x2688.png', [1242, 2688]],
            'splash-ipad'     => [$base . '/splash-1536x2048.png', [1536, 2048]],
            'screenshot'      => [$base . '/screenshot-540x720.png', [540, 720]],
        ];
    }

    #[DataProvider('assetProvider')]
    public function test_asset_is_a_valid_png_of_expected_dimensions(
        string $path,
        array $expectedSize,
    ): void {
        $this->assertFileExists($path, "PWA asset missing: {$path}");

        $info = @getimagesize($path);
        $this->assertNotFalse($info, "PWA asset is not a valid image: {$path}");

        $this->assertSame(
            IMAGETYPE_PNG,
            $info[2],
            "PWA asset is not a PNG: {$path}",
        );

        $this->assertSame(
            $expectedSize[0],
            $info[0],
            "PWA asset width mismatch: {$path} (expected {$expectedSize[0]}, got {$info[0]})",
        );
        $this->assertSame(
            $expectedSize[1],
            $info[1],
            "PWA asset height mismatch: {$path} (expected {$expectedSize[1]}, got {$info[1]})",
        );
    }

    public function test_apple_touch_icon_is_exactly_180x180(): void
    {
        $path = __DIR__ . '/../../public/pwa/apple-touch-icon.png';
        $info = getimagesize($path);

        $this->assertNotFalse($info);
        $this->assertSame(180, $info[0]);
        $this->assertSame(180, $info[1]);
    }

    public function test_screenshot_is_exactly_540x720(): void
    {
        $path = __DIR__ . '/../../public/pwa/screenshot-540x720.png';
        $info = getimagesize($path);

        $this->assertNotFalse($info);
        $this->assertSame(540, $info[0]);
        $this->assertSame(720, $info[1]);
    }

    public function test_maskable_icon_dimensions_hold_for_all_pwa_icon_sizes(): void
    {
        $expected = [192, 256, 384, 512];
        $base = __DIR__ . '/../../public/pwa';

        foreach ($expected as $size) {
            $info = getimagesize("{$base}/icon-{$size}.png");
            $this->assertNotFalse($info);
            $this->assertSame($size, $info[0], "icon-{$size}.png width");
            $this->assertSame($size, $info[1], "icon-{$size}.png height");
        }
    }
}
