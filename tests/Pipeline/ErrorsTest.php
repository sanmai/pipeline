<?php

namespace Pipeline;

use PHPUnit\Framework\TestCase;

class ErrorsTest extends TestCase
{
    protected function setUp()
    {
        if (ini_get('zend.assertions') != 1) {
            $this->markTestSkipped("This test case requires internal assertions being enabled");
        }
    }

    public function testAssertFalse()
    {
        $this->expectException(\AssertionError::class);
        assert(false);
    }

    public function testInvalidInitialGenerator()
    {
        $this->expectException(\AssertionError::class);

        $pipeline = new Simple();
        $pipeline->map(function ($a, $b = null) {
            yield;
        });
    }

    public function testNotGenerator()
    {
        $this->expectException(\AssertionError::class);

        $pipeline = new Simple();
        $pipeline->map(function () {
            return 0;
        });
    }
}
