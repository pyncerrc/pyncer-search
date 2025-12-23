<?php
namespace Pyncer\Search;

use voku\helper\ASCII;

/** @var array<string, string> */
const PUNCTUATION = [
    '!' => ' ',
    '"' => ' ',
    '#' => ' ',
    '&' => ' ',
    "'" => '',
    '(' => ' ',
    ')' => ' ',
    '*' => ' ',
    '+' => ' ',
    ',' => ' ',
    '-' => ' ',
    '.' => ' ',
    '/' => ' ',
    ':' => ' ',
    ';' => ' ',
    '<' => ' ',
    '>' => ' ',
    '?' => ' ',
    '@' => ' ',
    '[' => ' ',
    '\\' => ' ',
    ']' => ' ',
    '^' => ' ',
    '{' => ' ',
    '|' => ' ',
    '}' => ' ',
    '~' => ' ',
    '¡' => ' ',
    '¢' => ' ',
    '£' => ' ',
    '¤' => ' ',
    '¥' => ' ',
    '¦' => ' ',
    '§' => ' ',
    '¨' => ' ',
    '©' => ' ',
    'ª' => ' ',
    '«' => ' ',
    '¬' => ' ',
    '®' => ' ',
    '¯' => ' ',
    '°' => ' ',
    '±' => ' ',
    '²' => ' ',
    '³' => ' ',
    '´' => ' ',
    'µ' => ' ',
    '¶' => ' ',
    '·' => ' ',
    '¸' => ' ',
    '¹' => ' ',
    'º' => ' ',
    '»' => ' ',
    '¼' => ' ',
    '½' => ' ',
    '¾' => ' ',
    '¿' => ' ',
    '×' => ' ',
    'ƒ' => ' ',
    'ˆ' => ' ',
    '˜' => ' ',
    '–' => ' ',
    '—' => ' ',
    '―' => ' ',
    '_' => ' ',
    '‘' => '',
    '’' => '',
    '‚' => ' ',
    '“' => ' ',
    '”' => ' ',
    '„' => ' ',
    '†' => ' ',
    '‡' => ' ',
    '•' => ' ',
    '‣' => ' ',
    '…' => ' ',
    '‰' => ' ',
    '′' => ' ',
    '″' => ' ',
    '‹' => ' ',
    '›' => ' ',
    '‼' => ' ',
    '‾' => ' ',
    '⁄' => ' ',
    '€' => ' ',
    '™' => ' ',
    '←' => ' ',
    '↑' => ' ',
    '→' => ' ',
    '↓' => ' ',
    '↔' => ' ',
    '↵' => ' ',
    '⇐' => ' ',
    '⇑' => ' ',
    '⇒' => ' ',
    '⇓' => ' ',
    '⇔' => ' ',
    '∀' => ' ',
    '∂' => ' ',
    '∃' => ' ',
    '∅' => ' ',
    '∇' => ' ',
    '∈' => ' ',
    '∉' => ' ',
    '∋' => ' ',
    '∏' => ' ',
    '∑' => ' ',
    '−' => ' ',
    '∗' => ' ',
    '√' => ' ',
    '∝' => ' ',
    '∞' => ' ',
    '∠' => ' ',
    '∧' => ' ',
    '∨' => ' ',
    '∩' => ' ',
    '∪' => ' ',
    '∫' => ' ',
    '∴' => ' ',
    '∼' => ' ',
    '≅' => ' ',
    '≈' => ' ',
    '≠' => ' ',
    '≡' => ' ',
    '≤' => ' ',
    '≥' => ' ',
    '⊂' => ' ',
    '⊃' => ' ',
    '⊄' => ' ',
    '⊆' => ' ',
    '⊇' => ' ',
    '⊕' => ' ',
    '⊗' => ' ',
    '⊥' => ' ',
    '⋅' => ' ',
    '⌈' => ' ',
    '⌉' => ' ',
    '⌊' => ' ',
    '⌋' => ' ',
    '〈' => ' ',
    '〉' => ' ',
    '◊' => ' ',
    '♠' => ' ',
    '♣' => ' ',
    '♥' => ' ',
    '♦' => ' ',
];

/** @var array<string> */
const ELISIONS = [
    'l',
    'm',
    't',
    'qu',
    'n',
    's',
    'j',
    'd',
    'c',
    'jusqu',
    'quoiqu',
    'lorsqu',
    'puisqu',
];

function normalize_keywords(string $string, ?string $locale = null): string
{
    // Remove invalid utf8 multibyte sequences since
    $string = iconv('UTF-8', 'UTF-8//IGNORE', $string);
    if ($string === false) {
        return '';
    }

    // Convert <br> HTML tags to spaces and insert space before any closing tags
    $string = preg_replace(['/<br\s*\/?>/i', '/<\/\w+>/'], [' ', ' $1'], $string);
    if ($string === null) {
        return '';
    }

    // Remove HTML tags
    $string = strip_tags($string);

    // Replace space HTML entities with regular ones
    $string = str_replace(['&nbsp;', '&#160;', '&#xa0;'], ' ', $string);

    // Remove HTML entities
    $string = preg_replace('/&#?[a-z0-9]{2,8};/i', '', $string);
    if ($string === null) {
        return '';
    }

    // Remove emojis
    $string = _replace_mb4($string, '');

    $string = mb_strtolower($string);

    // Remove locale specific punctuation and diacritics
    if ($locale !== null) {
        $string = strtr($string, _ascii_character_map($locale));
    }

    $string = preg_replace(_get_elisions_regex(), '', $string);
    if ($string === null) {
        return '';
    }

    // Remove punctuation and diacritics
    $string = str_replace(array_keys(PUNCTUATION), PUNCTUATION, $string);

    $string = preg_replace(['/[\n\r]+/u', '/\s{2,}/u'], ' ', $string);
    if ($string === null) {
        return '';
    }

    // Remove newlines and extra spaces
    return trim($string);
}

function _replace_mb4(string $string, string $replace): string
{
    return preg_replace_callback(
        '/./u',
        function(array $match) use ($replace): string {
            if (strlen($match[0]) >= 4) {
                return $replace;
            }

            return $match[0];
        },
        $string
    ) ?? '';
}

/**
 * @return array<string, string>
 */
function _ascii_character_map(?string $locale = null): array
{
    /** @var array<string, array<string, string>> */
    static $maps = [];

    if ($locale !== null) {
        $locale = explode('-', $locale)[0];
    }

    $key = ($locale !== null ? $locale : '_');

    if (array_key_exists($key, $maps)) {
        return $maps[$key];
    }

    $map = ASCII::charsArrayWithSingleLanguageValues(false, false);

    if ($locale !== null) {
        /** @var ASCII::*_LANGUAGE_CODE $locale */
        $localeSpecific = ASCII::charsArrayWithOneLanguage($locale, false, false);

        if ($localeSpecific) {
            $map = array_merge($map, $localeSpecific);
        }
    }

    $maps[$key] = $map;

    return $map;
}

function _get_elisions_regex(): string
{
    static $elisions = null;

    if ($elisions === null) {
        $elisions = sprintf('/\b(%s)\'/', implode('|', ELISIONS));
    }

    /** @var string */
    return $elisions;
}
