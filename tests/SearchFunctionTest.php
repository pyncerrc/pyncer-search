<?php
namespace Pyncer\Tests\Search;

use PHPUnit\Framework\TestCase;

class SearchFunctionTest extends TestCase
{
    public function testNormalizeKeywords(): void
    {
        $keywords = \Pyncer\Search\normalize_keywords(
            'test'
        );

        $this->assertEquals($keywords, 'test');

        $keywords = \Pyncer\Search\normalize_keywords(
            'This is a test!<br>wow!'
        );

        $this->assertEquals($keywords, 'this is a test wow');
    }
}
