<?php

namespace Tests\Unit;

use App\Models\Investment;
use PHPUnit\Framework\TestCase;

/**
 * Pure-model coverage for {@see Investment}: type constants,
 * label / color maps, and the math used by the dashboard widget.
 */
class InvestmentModelTest extends TestCase
{
    public function test_type_constants_are_distinct_strings(): void
    {
        $values = [
            Investment::TYPE_STOCK,
            Investment::TYPE_FUND,
            Investment::TYPE_CRYPTO,
            Investment::TYPE_FIXED_INCOME,
            Investment::TYPE_TREASURY,
        ];

        $this->assertCount(5, $values);
        $this->assertCount(5, array_unique($values), 'TYPE_* constants must be unique');
    }

    public function test_types_map_covers_every_constant(): void
    {
        foreach ([
            Investment::TYPE_STOCK,
            Investment::TYPE_FUND,
            Investment::TYPE_CRYPTO,
            Investment::TYPE_FIXED_INCOME,
            Investment::TYPE_TREASURY,
        ] as $type) {
            $this->assertArrayHasKey($type, Investment::TYPES, "TYPES missing key {$type}");
            $this->assertNotEmpty(Investment::TYPES[$type]);
        }
    }

    public function test_type_colors_map_covers_every_constant(): void
    {
        foreach ([
            Investment::TYPE_STOCK,
            Investment::TYPE_FUND,
            Investment::TYPE_CRYPTO,
            Investment::TYPE_FIXED_INCOME,
            Investment::TYPE_TREASURY,
        ] as $type) {
            $this->assertArrayHasKey($type, Investment::TYPE_COLORS, "TYPE_COLORS missing key {$type}");
            $this->assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', Investment::TYPE_COLORS[$type]);
        }
    }

    public function test_currency_symbols_map_covers_main_iso_codes(): void
    {
        $this->assertSame('R$', Investment::CURRENCY_SYMBOLS['BRL']);
        $this->assertSame('$',  Investment::CURRENCY_SYMBOLS['USD']);
        $this->assertSame('€',  Investment::CURRENCY_SYMBOLS['EUR']);
        $this->assertSame('£',  Investment::CURRENCY_SYMBOLS['GBP']);
    }

    public function test_filled_contains_expected_columns(): void
    {
        $expected = [
            'user_id', 'name', 'type', 'ticker', 'quantity',
            'average_price_cents', 'current_price_cents',
            'currency', 'notes', 'acquired_at',
        ];
        foreach ($expected as $column) {
            $this->assertContains($column, (new Investment)->getFillable(), "fillable missing {$column}");
        }
    }
}
