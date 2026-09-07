<?php
declare(strict_types=1);

namespace WebProject\PhpPackageTemplate\Tests\Unit;

use WebProject\PhpPackageTemplate\Tests\Support\UnitTester;

class ExampleTest extends \Codeception\Test\Unit
{
    protected UnitTester $tester;

    // tests
    public function testSomeFeature(): void
    {
        /** @phpstan-ignore-next-line method.alreadyNarrowedType */
        self::assertTrue(true);
    }
}
