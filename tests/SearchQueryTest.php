<?php
namespace Pyncer\Tests\Search;

use PHPUnit\Framework\TestCase;

class SearchQueryTest extends TestCase
{
    public function testSearchQuery(): void
    {
        $query = new \Pyncer\Search\SearchQuery(
            'a test case'
        );

        $this->assertEquals($query->getQuery(), 'a test case');
        $this->assertTrue(count($query->getTerms()) === 1);
        $this->assertTrue($query->getTerms()[0]->getGroup() === true);

        $query = new \Pyncer\Search\SearchQuery(
            'a +test case'
        );

        $this->assertTrue(count($query->getTerms()) === 3);

        $query = new \Pyncer\Search\SearchQuery(
            'a -test case'
        );

        $this->assertTrue(count($query->getTerms()) === 3);
        $this->assertTrue($query->getTerms()[1]->getExclude() === true);

        $query = new \Pyncer\Search\SearchQuery(
            '"a test case"'
        );

        $this->assertTrue(count($query->getTerms()) === 1);
        $this->assertTrue($query->getTerms()[0]->getPhrase() === true);
    }
}
