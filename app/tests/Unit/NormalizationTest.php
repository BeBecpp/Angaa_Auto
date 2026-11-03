<?php

namespace Tests\Unit;

use App\Support\Phone;
use App\Support\Plate;
use App\Support\Reports;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NormalizationTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: ?string}>
     */
    public static function phones(): array
    {
        return [
            'local' => ['99112233', '+97699112233'],
            'spaced' => ['9911 2233', '+97699112233'],
            'international' => ['+976 9911-2233', '+97699112233'],
            'country without plus' => ['97699112233', '+97699112233'],
            'too short' => ['991122', null],
            'letters' => ['abc', null],
        ];
    }

    #[DataProvider('phones')]
    public function test_phone_normalization(string $input, ?string $expected): void
    {
        $this->assertSame($expected, Phone::normalize($input));
    }

    public function test_phone_masking_hides_all_but_last_four(): void
    {
        $this->assertSame('•••• 2233', Phone::mask('+97699112233'));
    }

    public function test_plate_normalization_keeps_script_and_never_transliterates(): void
    {
        $this->assertSame('1234УБА', Plate::normalize(' 1234 уба '));
        $this->assertSame('1234UBA', Plate::normalize('1234-uba'));
        $this->assertNotSame(Plate::normalize('1234 УБА'), Plate::normalize('1234 UBA'));
        $this->assertNull(Plate::normalize('  '));
    }

    public function test_csv_cells_are_formula_safe(): void
    {
        $this->assertSame("'=HYPERLINK(\"x\")", Reports::csvSafe('=HYPERLINK("x")'));
        $this->assertSame("'+1", Reports::csvSafe('+1'));
        $this->assertSame('Бат', Reports::csvSafe('Бат'));
    }
}
