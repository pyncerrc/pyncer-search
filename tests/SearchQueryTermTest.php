<?php
namespace Pyncer\Tests\Search;

use PHPUnit\Framework\TestCase;

class SearchQueryTermTest extends TestCase
{
    public function testSearchQueryTerm(): void
    {
        $term = new \Pyncer\Search\SearchQueryTerm(
            'A Test Case'
        );

        $this->assertEquals(
            $term->getKeywords(),
            [
                'Test',
                'Case',
            ]
        );

        $this->assertEquals(
            count($term->getPermutations()),
            5
        );

        $this->assertEquals($term->getNormalizedTerm(), 'a test case');
        $this->assertEquals(
            $term->getNormalizedTerms(),
            [
                'test',
                'case',
            ]
        );
    }
}
