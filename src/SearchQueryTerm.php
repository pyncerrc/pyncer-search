<?php
namespace Pyncer\Search;

use Pyncer\Exception\InvalidArgumentException;

use const Pyncer\Search\DEFAULT_LOCALE as PYNCER_SEARCH_LOCALE;
use const Pyncer\Search\DEFAULT_MIN_KEYWORD_LENGTH as PYNCER_SEARCH_DEFAULT_MIN_KEYWORD_LENGTH;

use function Pyncer\Search\normalize_keywords as pyncer_search_normalize_keywords;

readonly class SearchQueryTerm
{
    public function __construct(
        protected string $term,
        protected bool $exclude = false,
        protected bool $phrase = false,
        protected bool $group = false,
        protected ?string $locale = PYNCER_SEARCH_LOCALE,
        protected int $minKeywordLength = PYNCER_SEARCH_DEFAULT_MIN_KEYWORD_LENGTH,
    ) {
        if ($phrase && $group) {
            throw new InvalidArgumentException('Search term cannot be both a phrase and a group.');
        }
    }

    public function getTerm(): string
    {
        return $this->term;
    }

    public function getExclude(): bool
    {
        return $this->exclude;
    }

    public function getPhrase(): bool
    {
        return $this->phrase;
    }

    public function getGroup(): bool
    {
        return $this->group;
    }

    /**
     * @return array<int, string>
     */
    public function getKeywords(): array
    {
        $terms = explode(' ', $this->getTerm());

        foreach ($terms as $key => $value) {
            if (mb_strlen($value) < $this->minKeywordLength) {
                unset($terms[$key]);
            }
        }

        $terms = array_values($terms);

        return $terms;
    }

    /**
     * @return array<int, array<int, string>> An array of search term permutations.
     */
    public function getPermutations(): array
    {
        $terms = explode(' ', $this->getTerm());

        $permutations = $this->getPermutationsRecursive($terms);
        $permutations = $this->sortPermutations($permutations, $terms);
        $permutations = $this->mergePermutations($permutations, $terms);

        return $this->cleanPermutations($permutations);
    }

    /**
     * @param array<int, string> $terms
     * @param int $start
     * @param array<int, string> $current
     * @return array<int, array<int, string>>
     */
    private function getPermutationsRecursive(
        array $terms,
        int $start = 0,
        array $current = []
    ): array
    {
        $result = [];

        for ($i = $start; $i < count($terms); ++$i) {
            $current[] = $terms[$i];

            // Add the current combination to the result.
            $result[] = $current;

            // Recursively generate permutations for the rest of the words.
            $result = array_merge(
                $result,
                $this->getPermutationsRecursive($terms, $i + 1, $current)
            );

            // Remove the last word to backtrack and try the next combination.
            array_pop($current);
        }

        return $result;
    }

    /**
     * @param array<int, array<int, string>> $permutations
     * @param array<int, string> $terms
     * @return array<int, array<int, string>>
     */
    private function sortPermutations(
        array $permutations,
        array $terms
    ): array
    {
        usort($permutations, function(array $a, array $b) use($terms) {
            $test = (count($a) <=> count($b));

            // Multipe words first
            if ($test !== 0) {
                return -$test;
            }

            $sequenceCountA = 0;
            $sequenceCountB = 0;

            // Prioritize term order first
            $previous = -1;
            foreach ($a as $key => $value) {
                $index = array_search($value, $terms);

                if ($previous === -1) {
                    $previous = $index;
                    continue;
                }

                if ($index === $previous + 1) {
                    ++$sequenceCountA;
                }
            }

            $previous = -1;
            foreach ($b as $key => $value) {
                $index = array_search($value, $terms);

                if ($previous === -1) {
                    $previous = $index;
                    continue;
                }

                if ($index === $previous + 1) {
                    ++$sequenceCountB;
                }
            }

            return -($sequenceCountA <=> $sequenceCountB);
        });

        return $permutations;
    }

    /**
     * @param array<int, array<int, string>> $permutations
     * @param array<int, string> $terms
     * @return array<int, array<int, string>>
     */
    private function mergePermutations(
        array $permutations,
        array $terms
    ): array
    {
        $result = [];

        foreach ($permutations as $permutation) {
            if (count($permutation) === 1) {
                continue;
            }

            $merged = [implode(' ', $permutation)];

            foreach ($terms as $value) {
                if (!in_array($value, $permutation)) {
                    $merged[] = $value;
                }
            }

            $result[] = $merged;
        }

        $result[] = $terms;

        return $result;
    }

    /**
     * @param array<int, array<int, string>> $permutations An array of permutations.
     * @return array<int, array<int, string>>
     */
    private function cleanPermutations(array $permutations): array
    {
        foreach ($permutations as $key => $value) {
            foreach ($value as $key2 => $value2) {
                if (str_contains($value2, ' ')) {
                    continue;
                }

                if (mb_strlen($value2) < $this->minKeywordLength) {
                    unset($value[$key2]);
                }
            }

            $permutations[$key] = $value;
        }

        return $permutations;
    }

    public function getNormalizedTerm(): string
    {
        return pyncer_search_normalize_keywords(
            $this->term,
            $this->locale,
        );
    }

    /**
     * @return array<int, string>
     */
    public function getNormalizedTerms(): array
    {
        $normalizedTerms = [];

        foreach ($this->getKeywords() as $value) {
            $normalizedTerms[] = pyncer_search_normalize_keywords(
                $value,
                $this->locale,
            );
        }

        return $normalizedTerms;
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function getNormalizedPermutations(): array
    {
        $normalizedPermutations = [];

        foreach ($this->getPermutations() as $value) {
            foreach ($value as $key => $value2) {
                $value[$key] = pyncer_search_normalize_keywords(
                    $value2,
                    $this->locale
                );
            }

            $normalizedPermutations[] = $value;
        }

        return $normalizedPermutations;
    }
}
