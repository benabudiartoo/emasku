<?php

namespace Tests\Unit;

use Tests\TestCase;
use Tests\Unit\Concerns\NisabEightyFiveCases;

class ZakatHanbaliTest extends TestCase
{
    use NisabEightyFiveCases;

    protected function madhhab(): string
    {
        return 'hanbali';
    }

    protected function label(): string
    {
        return 'Hanbali';
    }
}
