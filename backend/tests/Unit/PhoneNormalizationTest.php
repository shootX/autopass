<?php

namespace Tests\Unit;

use App\Support\Phone;
use PHPUnit\Framework\TestCase;

class PhoneNormalizationTest extends TestCase
{
    public function test_country_code_and_spaces_keep_the_same_local_number(): void
    {
        $expected = '555 11 22 33';

        $this->assertSame($expected, Phone::normalize('+995 555 11 22 33'));
        $this->assertSame($expected, Phone::normalize('995555112233'));
        $this->assertSame($expected, Phone::normalize('555112233'));
        $this->assertSame($expected, Phone::normalize('555 11 22 33'));
        $this->assertSame('995555112233', Phone::forSms('+995 555 11 22 33'));
    }

    public function test_extra_digits_are_not_trimmed_into_another_number(): void
    {
        $this->assertNull(Phone::normalize('+995 555 11 22 339'));
        $this->assertNull(Phone::normalize('5551122'));
    }
}
