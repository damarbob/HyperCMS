<?php

use App\Data\Entries\FieldRules;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The search, find and sort rules, run on fixed data with accents, blanks and ties. The expected values were
 * checked against MySQL 8.0.13 with the connection collation `utf8mb4_general_ci`.
 *
 * @internal
 */
final class FieldRulesTest extends CIUnitTestCase
{
    public function testSearchIgnoresCaseAndAccents(): void
    {
        $text = '{"title":"Éclair au chocolat","body":"<p>Zoë</p>"}';

        $this->assertTrue(FieldRules::contains($text, 'eclair'));
        $this->assertTrue(FieldRules::contains($text, 'ÉCLAIR'));
        $this->assertTrue(FieldRules::contains($text, 'zoe'));
        $this->assertFalse(FieldRules::contains($text, 'cake'));
    }

    public function testSearchMatchesKeysAsWellAsValues(): void
    {
        $this->assertTrue(FieldRules::contains('{"title":"x"}', 'title'));
    }

    public function testSearchTreatsPercentAndUnderscoreAsWildcards(): void
    {
        $text = '{"title":"Sample post 21"}';

        $this->assertTrue(FieldRules::contains($text, '%'));
        $this->assertTrue(FieldRules::contains($text, 'Sample_post'));
        $this->assertTrue(FieldRules::contains($text, 'sample%21'));
        $this->assertFalse(FieldRules::contains($text, 'sample__post'));
        $this->assertFalse(FieldRules::contains($text, 'o%p%x'));
        $this->assertTrue(FieldRules::contains('{"a":"50% off"}', '50\\% off'));
        $this->assertFalse(FieldRules::contains('{"a":"50 off"}', '50\\% off'));
    }

    public function testSearchKeepsTheEscapeOfTheTerm(): void
    {
        // The term is HTML escaped before it is searched, so a quote can never match the stored text.
        $this->assertFalse(FieldRules::contains('{"name":"o\'brien"}', esc("o'brien")));
        $this->assertFalse(FieldRules::contains('{"a":"<div>"}', esc('<div')));
    }

    public function testFieldsTextIsUnescapedAndEmptyReadsAsBraces(): void
    {
        $this->assertSame('{}', FieldRules::fieldsText([]));
        $this->assertSame('{"title":"Judul / kopi 日本語"}', FieldRules::fieldsText(['title' => 'Judul / kopi 日本語']));
        $this->assertSame('{"0":"a","1":"b"}', FieldRules::fieldsText([0 => 'a', 1 => 'b']));
    }

    public function testJsonTextFollowsMysqlLayout(): void
    {
        $this->assertSame('plain', FieldRules::jsonText('plain'));
        $this->assertSame('["news", "tech"]', FieldRules::jsonText(['news', 'tech']));
        $this->assertSame('{"a": 1, "b": [true, null]}', FieldRules::jsonText(['a' => 1, 'b' => [true, null]]));
        $this->assertSame('12.5', FieldRules::jsonText(12.5));
        $this->assertSame('null', FieldRules::jsonText(null));
    }

    public function testFindIsPlainTextInOneFieldIgnoringCaseAndAccents(): void
    {
        $fields = ['title' => 'Éclair', 'tags' => ['news', 'tech'], 'views' => '100%'];

        $this->assertTrue(FieldRules::findMatches($fields, 'title', 'ECLAIR'));
        $this->assertTrue(FieldRules::findMatches($fields, 'tags', 'news'));
        $this->assertTrue(FieldRules::findMatches($fields, 'views', '%'));
        $this->assertFalse(FieldRules::findMatches($fields, 'title', '%'));
        $this->assertFalse(FieldRules::findMatches($fields, 'nope', 'x'));
    }

    public function testTextKeyStripsTagsTrimsAndFolds(): void
    {
        $this->assertSame('ECLAIR', FieldRules::textKey(['t' => '  <b>Éclair</b> '], 't'));
        $this->assertSame('', FieldRules::textKey(['t' => ''], 't'));
        $this->assertNull(FieldRules::textKey([], 't'));
    }

    public function testNumberKeyReadsLikeADecimalCast(): void
    {
        $this->assertSame(12.5, FieldRules::numberKey(['n' => '12.50'], 'n'));
        $this->assertSame(0.0, FieldRules::numberKey(['n' => ''], 'n'));
        $this->assertSame(0.0, FieldRules::numberKey(['n' => 'abc'], 'n'));
        $this->assertSame(12.0, FieldRules::numberKey(['n' => '12abc'], 'n'));
        $this->assertSame(1000.0, FieldRules::numberKey(['n' => '1e3'], 'n'));
        $this->assertSame(-3.46, FieldRules::numberKey(['n' => '-3.456'], 'n'));
        $this->assertSame(99999999.99, FieldRules::numberKey(['n' => '6281234567890123456789'], 'n'));
        $this->assertNull(FieldRules::numberKey([], 'n'));
    }

    public function testDateKeyCopiesStrToDate(): void
    {
        // The browser's value has a T where the format has a space, so MySQL gives NULL and the sort copies it.
        $this->assertNull(FieldRules::parseDate('2026-01-05T09:30'));
        $this->assertNull(FieldRules::parseDate('2026-01-05T14:05:00'));
        $this->assertNull(FieldRules::parseDate('0000-00-00T00:00'));
        $this->assertNull(FieldRules::parseDate(''));
        $this->assertSame('2026-01-05 09:30:00', FieldRules::parseDate('2026-01-05 09:30'));
        $this->assertSame('2026-01-05 00:00:00', FieldRules::parseDate('2026-01-05'));
        $this->assertSame('2026-01-05 09:03:07', FieldRules::parseDate('2026-1-5 9:3:7'));
        $this->assertSame('2026-01-05 10:00:00', FieldRules::parseDate('26-01-05 10:00:00'));
        $this->assertNull(FieldRules::parseDate('2026-13-05 10:00:00'));
        $this->assertNull(FieldRules::dateKey([], 'd'));
    }

    public function testNullKeysComeFirstAndEqualKeysCompareEqual(): void
    {
        $this->assertSame(0, FieldRules::compare(null, null));
        $this->assertSame(-1, FieldRules::compare(null, ''));
        $this->assertSame(1, FieldRules::compare('', null));
        $this->assertSame(0, FieldRules::compare(FieldRules::textKey(['t' => 'Apple'], 't'), FieldRules::textKey(['t' => 'apple'], 't')));
        $this->assertLessThan(0, FieldRules::compare(9.5, 10.0));
    }

    /**
     * Sort a few entries the way a list does: by key, the direction reversing the keys only, and ties by id.
     */
    public function testSortOnFixedDataWithAccentsBlanksAndTies(): void
    {
        $entries = [
            1 => ['title' => 'zebra', 'views' => '10', 'published' => '2026-02-02 08:00:00'],
            2 => ['title' => 'Éclair', 'views' => '', 'published' => '2026-01-05T09:30'],
            3 => ['title' => 'eclair', 'views' => '9.5'],
            4 => ['title' => '  <i>Apple</i>', 'views' => 'abc', 'published' => '2025-12-31 23:59:59'],
            5 => [],
            6 => ['title' => 'apple', 'views' => '10.00', 'published' => '2026-02-02 08:00:00'],
        ];

        $order = function (string $kind, string $field, string $dir) use ($entries): array {
            $ids = array_keys($entries);
            usort($ids, static function (int $a, int $b) use ($entries, $kind, $field, $dir): int {
                $cmp = FieldRules::compare(FieldRules::sortKey($kind, $entries[$a], $field), FieldRules::sortKey($kind, $entries[$b], $field));

                return ($dir === 'desc' ? -$cmp : $cmp) ?: $a <=> $b;
            });

            return $ids;
        };

        // Entry 5 has no title, so it comes first, and the accent and the case make no difference.
        $this->assertSame([5, 4, 6, 2, 3, 1], $order('text', 'title', 'asc'));
        $this->assertSame([1, 2, 3, 4, 6, 5], $order('text', 'title', 'desc'));
        // A blank or text value is 0, entry 5 has no value, and 10 and 10.00 tie.
        $this->assertSame([5, 2, 4, 3, 1, 6], $order('number', 'views', 'asc'));
        $this->assertSame([1, 6, 3, 2, 4, 5], $order('number', 'views', 'desc'));
        // Only a value written with a space is a date, so entry 2 has none, and 1 and 6 tie.
        $this->assertSame([2, 3, 5, 4, 1, 6], $order('date', 'published', 'asc'));
        $this->assertSame([1, 6, 4, 2, 3, 5], $order('date', 'published', 'desc'));
    }

    public function testFoldingByStretchesGivesWhatFoldingTheWholeTextGives(): void
    {
        $lower = Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC; Lower');
        $upper = Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC; Upper');

        $samples = [
            'Éclair au chocolat, Ångström and ZOË',
            "e\u{0301}clair with a loose mark, a\u{030A}b and plain ASCII",
            'Ünïcödé 日本語 text 😀 with emoji and ÀÉÎÕÜ at the end É',
            'İstanbul and ǅ in one line',
            'ASCII only, nothing to strip',
            'É',
            '',
        ];
        foreach ($samples as $text) {
            $this->assertSame($lower->transliterate($text), FieldRules::fold($text), $text);
            $this->assertSame(rtrim($upper->transliterate($text), ' '), FieldRules::sortFold($text), $text);
        }
        // The sharp s is read as one s by the collation.
        $this->assertSame('STRASE', FieldRules::sortFold('Straße'));
    }

    public function testASearchOverALargeBodyWithAccentsStaysFast(): void
    {
        // Folding a body of 2 MB in one piece took about 30 seconds, and a stretch at a time takes a fraction of a second.
        $body  = str_repeat('<p>Éclair au chocolat et Ångström, quarterly report. </p>', 40000);
        $start = microtime(true);

        $this->assertFalse(FieldRules::contains($body, 'kiwi'));
        $this->assertTrue(FieldRules::contains($body, 'angstrom'));
        $this->assertNotSame('', FieldRules::sortFold($body));
        $this->assertLessThan(5.0, microtime(true) - $start);
    }
}
