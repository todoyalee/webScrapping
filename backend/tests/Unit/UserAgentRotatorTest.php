<?php

namespace Tests\Unit;

use App\Services\Scraping\UserAgentRotator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class UserAgentRotatorTest extends TestCase
{
    public function test_it_cycles_through_agents_in_order(): void
    {
        $rotator = new UserAgentRotator(['a', 'b', 'c']);

        $this->assertSame(['a', 'b', 'c', 'a', 'b'], [
            $rotator->next(),
            $rotator->next(),
            $rotator->next(),
            $rotator->next(),
            $rotator->next(),
        ]);
    }

    public function test_it_rejects_an_empty_list(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new UserAgentRotator([]);
    }
}
