<?php

namespace Tests\Unit;

use Tests\TestCase;
use Tests\Unit\Concerns\NisabEightyFiveCases;

class ZakatMalikiTest extends TestCase
{
    use NisabEightyFiveCases;

    protected function madhhab(): string
    {
        return 'maliki';
    }

    protected function label(): string
    {
        return 'Maliki';
    }
}
