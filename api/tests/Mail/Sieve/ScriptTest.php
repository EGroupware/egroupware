<?php
/**
 * EGroupware Api: tests for the tokenised Sieve filter generator
 *
 * Unit tests for the opt-in "tokenised search syntax" added to Mail filter
 * rules (forum thread 79137, follow-up to the search-side #240 and the
 * Sieve-side #241). Pure unit tests on the static generator helpers, so they
 * extend TestCase directly (no session / DB needed).
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage mail
 * @author Gabriele Novembri (A.T. Advanced Technologies S.r.l.)
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Mail\Sieve;

use PHPUnit\Framework\TestCase;

/**
 * Tests for Script::buildTokenizedSieveTest() and Script::parseSieveTokens().
 *
 * The factories below are byte-identical to the per-condition closures used in
 * Script::retrieveRules() (subject / from / to / custom header / body), so the
 * asserted output is exactly what production emits for a tokenised rule.
 *
 * Gating (tokenised only when the flag is set AND regexp is off AND the value
 * has no * / ? wildcards) lives in retrieveRules() and is covered by the
 * end-to-end deployment tests, not here.
 */
class ScriptTest extends TestCase
{
	private static function fSubject(string $term, bool $not): string
	{
		return ($not ? 'not ' : '') . 'header :contains "subject" "' . addslashes($term) . '"';
	}
	private static function fFrom(string $term, bool $not): string
	{
		return ($not ? 'not ' : '') . 'address :contains ["From"] "' . addslashes($term) . '"';
	}
	private static function fTo(string $term, bool $not): string
	{
		return ($not ? 'not ' : '') . 'address :contains ["To","TO","Cc","CC"] "' . addslashes($term) . '"';
	}
	private static function fBody(string $term, bool $not): string
	{
		return ($not ? 'not ' : '') . 'body :text :contains "' . addslashes($term) . '"';
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('parseProvider')]
	public function testParseSieveTokens(string $input, array $expected): void
	{
		$this->assertSame($expected, Script::parseSieveTokens($input));
	}

	public static function parseProvider(): array
	{
		return [
			'whitespace split'     => ['a b c', ['a', 'b', 'c']],
			'double-quoted phrase' => ['"foo bar" baz', ['foo bar', 'baz']],
			'single-quoted phrase' => ["'due parole' x", ['due parole', 'x']],
			'unicode token'        => ["citt\u{00e0} blu", ["citt\u{00e0}", 'blu']],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('subjectProvider')]
	public function testSubjectTokenization(string $value, string $expected): void
	{
		$factory = fn(string $t, bool $n): string => self::fSubject($t, $n);
		$this->assertSame($expected, Script::buildTokenizedSieveTest($value, $factory));
	}

	public static function subjectProvider(): array
	{
		return [
			'empty value'           => ['', ''],
			'single token'          => ['fattura', 'header :contains "subject" "fattura"'],
			'single +token'         => ['+fattura', 'header :contains "subject" "fattura"'],
			'whitespace is OR'      => ['fattura dicembre',
				'anyof (header :contains "subject" "fattura", header :contains "subject" "dicembre")'],
			'plus plus is AND'      => ['+fattura +dicembre',
				'allof (header :contains "subject" "fattura", header :contains "subject" "dicembre")'],
			'bare plus required'    => ['fattura +dicembre',
				'allof (header :contains "subject" "fattura", header :contains "subject" "dicembre")'],
			'minus is forbidden'    => ['fattura -spam',
				'allof (header :contains "subject" "fattura", not header :contains "subject" "spam")'],
			'and keyword'           => ['fattura and dicembre',
				'allof (header :contains "subject" "fattura", header :contains "subject" "dicembre")'],
			'or keyword'            => ['fattura or dicembre',
				'anyof (header :contains "subject" "fattura", header :contains "subject" "dicembre")'],
			'quoted phrase verbatim'=> ['"fattura di dicembre"',
				'header :contains "subject" "fattura di dicembre"'],
			'single negation'       => ['-spam', 'not header :contains "subject" "spam"'],
			'three required'        => ['+a +b +c',
				'allof (header :contains "subject" "a", header :contains "subject" "b", header :contains "subject" "c")'],
			'mixed fold a +b +c'    => ['a +b +c',
				'allof (allof (header :contains "subject" "a", header :contains "subject" "b"), header :contains "subject" "c")'],
			'mixed fold a b -c'     => ['a b -c',
				'allof (anyof (header :contains "subject" "a", header :contains "subject" "b"), not header :contains "subject" "c")'],
			'escapes double-quote'  => ['va"lue', 'header :contains "subject" "va\"lue"'],
		];
	}

	public function testOtherConditionFactories(): void
	{
		$this->assertSame(
			'allof (address :contains ["From"] "mario", address :contains ["From"] "rossi")',
			Script::buildTokenizedSieveTest('+mario +rossi', fn($t, $n) => self::fFrom($t, $n)),
			'From, two required tokens');

		$this->assertSame(
			'address :contains ["To","TO","Cc","CC"] "ufficio"',
			Script::buildTokenizedSieveTest('ufficio', fn($t, $n) => self::fTo($t, $n)),
			'To/Cc, single token');

		$this->assertSame(
			'allof (body :text :contains "contratto", not body :text :contains "bozza")',
			Script::buildTokenizedSieveTest('+contratto -bozza', fn($t, $n) => self::fBody($t, $n)),
			'Body, required + forbidden');
	}
}
