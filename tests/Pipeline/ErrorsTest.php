<?php

namespace Pipeline;

use PHPUnit\Framework\TestCase;

class ErrorsTest extends TestCase
{
    protected function setUp()
    {
        if (ini_get('zend.assertions') != 1) {
            $this->markTestSkipped("Requires internal assertions");
        }
    }

    public function testAssertFalse()
    {
        $this->expectException(\AssertionError::class);
        assert(false);
    }
}
