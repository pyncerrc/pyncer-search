<?php
namespace Pyncer\Search;

use Pyncer\Search\SearchQueryTerm;

use const Pyncer\Search\DEFAULT_LOCALE as PYNCER_SEARCH_DEFAULT_LOCALE;
use const Pyncer\Search\DEFAULT_MIN_KEYWORD_LENGTH as PYNCER_SEARCH_DEFAULT_MIN_KEYWORD_LENGTH;

class SearchQuery
{
    /** @var array<int, \Pyncer\Search\SearchQueryTerm> **/
    protected array $terms;

    public function __construct(
        protected string $query,
        protected ?string $locale = PYNCER_SEARCH_DEFAULT_LOCALE,
        protected int $minKeywordLength = PYNCER_SEARCH_DEFAULT_MIN_KEYWORD_LENGTH,
    ) {
        $this->terms = $this->parseQuery($query);
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * @return array<int, \Pyncer\Search\SearchQueryTerm>
     */
    public function getTerms(): array
    {
        return $this->terms;
    }

    /**
     * @param string $query The search query to parse.
     * @return array<int, \Pyncer\Search\SearchQueryTerm>
     */
    protected function parseQuery(string $query): array
    {
        $terms = [];

        $currentTokens = [];

        $isExclude = false;
        $inGroup = true;
        $hasSymbol = false;
        $inQuotes = false;
        $quoteType = null;

        $tokens = explode(' ', $query);
        $count = count($tokens);

        for ($i = 0; $i < $count; ++$i) {
            $token = $tokens[$i];

            if ($token === '') {
                continue;
            }

            if (!$inQuotes) {
                if ($token === '+') {
                    $hasSymbol = true;
                    $isExclude = false;
                    continue;
                }

                if ($token === '-') {
                    $hasSymbol = true;
                    $isExclude = true;
                    continue;
                }

                if (str_starts_with($token, '-')) {
                    $hasSymbol = true;
                    $isExclude = true;
                    $token = substr($token, 1);
                } else if (str_starts_with($token, '+')) {
                    $hasSymbol = true;
                    $isExclude = false;
                    $token = substr($token, 1);
                }

                if ($hasSymbol && $inGroup) {
                    $inGroup = false;

                    if ($currentTokens) {
                        $terms[] = $this->getGroupTerm($currentTokens);
                        $currentTokens = [];
                    }
                }
            }

            if ($inQuotes) {
                // Accidental quote placement before space instead of after

                /** @var string $quoteType */
                if (str_starts_with($token, $quoteType)) {
                    $terms[] = new SearchQueryTerm(
                        term: implode(' ', $currentTokens),
                        exclude: $isExclude,
                        phrase: (count($currentTokens) > 1),
                        locale: $this->locale,
                        minKeywordLength: $this->minKeywordLength,
                    );

                    $isExclude = false;
                    $inGroup = true;
                    $hasSymbol = false;

                    $inQuotes = false;
                    $currentTokens = [];

                    // Remove quote from token and if not empty
                    // reiterate over it
                    $token = substr($token, 1);
                    if ($token !== '') {
                        $tokens[$i] = $token;
                        --$i;
                    }

                    continue;
                }

                if (str_ends_with($token, $quoteType)) {
                    $currentTokens[] = substr($token, 0, -1);

                    $terms[] = new SearchQueryTerm(
                        term: implode(' ', $currentTokens),
                        exclude: $isExclude,
                        phrase: true,
                        locale: $this->locale,
                        minKeywordLength: $this->minKeywordLength,
                    );

                    $isExclude = false;
                    $inGroup = true;
                    $hasSymbol = false;

                    $inQuotes = false;
                    $currentTokens = [];

                    continue;
                }

                $currentTokens[] = $token;
                continue;
            }

            if (str_starts_with($token, '"') || str_starts_with($token, "'")) {
                if ($inGroup) {
                    $inGroup = false;
                    if ($currentTokens) {
                        $terms[] = $this->getGroupTerm($currentTokens);
                        $currentTokens = [];
                    }
                }

                $quoteType = substr($token, 0, 1);

                // Single word so don't count as phrase
                if (str_ends_with($token, $quoteType)) {
                    $terms[] = new SearchQueryTerm(
                        term: substr($token, 1, -1),
                        exclude: $isExclude,
                        locale: $this->locale,
                        minKeywordLength: $this->minKeywordLength,
                    );

                    $isExclude = false;
                    $inGroup = true;
                    $hasSymbol = false;

                    continue;
                }

                $inQuotes = true;
                $currentTokens = [substr($token, 1)];
                continue;
            }

            // Accidental end quote with no space infront
            if (str_ends_with($token, '"') || str_ends_with($token, "'")) {
                $inQuotes = true;
                $quoteType = substr($token, -1);

                $token = substr($token, 0, -1);

                if ($inGroup) {
                    $inGroup = false;
                    $currentTokens[] = $token;
                    $terms[] = $this->getGroupTerm($currentTokens);
                    continue;
                }
            }

            // If in a group, add to current token set
            if ($inGroup) {
                $currentTokens[] = $token;
                continue;
            }

            // Not in a group so add as a single term
            $terms[] = new SearchQueryTerm(
                term: $token,
                exclude: $isExclude,
                locale: $this->locale,
                minKeywordLength: $this->minKeywordLength,
            );

            // Reset state
            $isExclude = false;
            $inGroup = true;
            $hasSymbol = false;
        }

        // Should the user not close a quote
        if ($inQuotes) {
            $terms[] = new SearchQueryTerm(
                term: implode(' ', $currentTokens),
                exclude: $isExclude,
                phrase: true,
                locale: $this->locale,
                minKeywordLength: $this->minKeywordLength,
            );
        } elseif ($inGroup && $currentTokens) {
            $terms[] = $this->getGroupTerm($currentTokens);
        }

        return $terms;
    }

    /**
     * @param array<string> $tokens
     */
    private function getGroupTerm(array $tokens): SearchQueryTerm
    {
        if (count($tokens) === 1) {
            return new SearchQueryTerm(
                term: $tokens[0],
                locale: $this->locale,
                minKeywordLength: $this->minKeywordLength,
            );
        }

        return new SearchQueryTerm(
            term: implode(' ', $tokens),
            group: true,
            locale: $this->locale,
            minKeywordLength: $this->minKeywordLength,
        );
    }
}
