<?php

namespace Tests\Unit;

use App\Services\Import\PurityParser;
use PHPUnit\Framework\TestCase;

class PurityParserTest extends TestCase
{
    public function test_karat_in_its_common_spellings(): void
    {
        foreach (['17K', '17k', '17 K', '17KT', '17 karat'] as $text) {
            $r = PurityParser::parse($text);
            $this->assertSame('karat', $r['source'], $text);
            $this->assertSame('17', $r['input'], $text);
            $this->assertSame('0.708333', $r['fraction'], $text); // 17 / 24
            $this->assertFalse($r['conflict']);
            $this->assertFalse($r['unreadable']);
        }
    }

    public function test_per_mille_and_percent(): void
    {
        $this->assertSame(['permil', '750', '0.750000'], $this->read('750'));
        $this->assertSame(['permil', '999.9', '0.999900'], $this->read('999.9'));
        $this->assertSame(['permil', '999.9', '0.999900'], $this->read('999,9'));
        $this->assertSame(['permil', '750', '0.750000'], $this->read('750‰'));
        $this->assertSame(['percent', '75.5', '0.755000'], $this->read('75.5%'));
        $this->assertSame(['percent', '75.5', '0.755000'], $this->read('75,5 %'));
    }

    /** @return array{0: ?string, 1: ?string, 2: ?string} */
    private function read(string $text): array
    {
        $r = PurityParser::parse($text);

        return [$r['source'], $r['input'], $r['fraction']];
    }

    public function test_two_readings_that_agree_use_the_karat_without_a_conflict(): void
    {
        $r = PurityParser::parse('750/18K');

        $this->assertSame('karat', $r['source']);
        $this->assertSame('18', $r['input']);
        $this->assertFalse($r['conflict']);
    }

    public function test_two_readings_that_disagree_use_the_karat_and_flag_the_conflict(): void
    {
        $r = PurityParser::parse('750/17K'); // 750 per mille is 18K, not 17K

        $this->assertSame('karat', $r['source']);
        $this->assertSame('17', $r['input']);
        $this->assertTrue($r['conflict']);
        $this->assertFalse($r['unreadable']);

        $this->assertTrue(PurityParser::parse('70%/18K')['conflict']);
        $this->assertTrue(PurityParser::parse('17K/750')['conflict']); // the order does not matter
    }

    public function test_a_difference_within_half_a_percentage_point_is_not_a_conflict(): void
    {
        $this->assertFalse(PurityParser::parse('75.5%/18K')['conflict']); // 0.755 against 0.75: exactly 0.005
        $this->assertFalse(PurityParser::parse('750/18K')['conflict']);
    }

    public function test_an_empty_purity_is_not_filled_in_rather_than_unreadable(): void
    {
        foreach (['', '   ', null] as $text) {
            $r = PurityParser::parse($text);
            $this->assertNull($r['source']);
            $this->assertNull($r['fraction']);
            $this->assertFalse($r['unreadable']);
        }
    }

    public function test_unreadable_values_give_no_purity_never_a_guess(): void
    {
        foreach (['emas', '75', '30K', '120%', '17K/xyz', '0K', '-5%', 'K17'] as $text) {
            $r = PurityParser::parse($text);
            $this->assertTrue($r['unreadable'], $text);
            $this->assertNull($r['source'], $text);
            $this->assertNull($r['input'], $text);
            $this->assertNull($r['fraction'], $text);
        }
    }

    public function test_a_bare_number_is_per_mille_only_when_plainly_one(): void
    {
        $this->assertSame('permil', PurityParser::parse('916')['source']);
        $this->assertTrue(PurityParser::parse('99')['unreadable']); // 99 could be a percent or anything: not guessed
    }
}
