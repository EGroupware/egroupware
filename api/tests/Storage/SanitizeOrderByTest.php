<?php
/**
 * EGroupware Api: tests for Api\Storage\Base::sanitizeOrderBy
 *
 * Regression tests for the SQL-injection residual of CVE-2024-40614 / CVE-2026-22243:
 * an attacker-controlled ORDER BY fragment (eg. via nextmatch "order"/"sort" sent as a
 * string instead of an array) must never reach the database unvalidated.
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage tests
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;
use PHPUnit\Framework\TestCase;

class SanitizeOrderByTest extends TestCase
{
	/**
	 * Fragments that are legitimate ORDER BY content and must be returned unchanged (or empty/null passed through)
	 */
	public static function allowedProvider()
	{
		return [
			'null' => [null, null],
			'empty string' => ['', ''],
			'plain column ASC' => ['ts_id ASC', 'ts_id ASC'],
			'plain column no direction' => ['ts_id', 'ts_id'],
			'multiple columns' => ['ts_id ASC, ts_start DESC', 'ts_id ASC, ts_start DESC'],
			'dotted column' => ['egw_timesheet.ts_id ASC', 'egw_timesheet.ts_id ASC'],
			'custom field column' => ['#custom_field ASC', '#custom_field ASC'],
			'IS NULL modifier' => ['ts_id IS NULL', 'ts_id IS NULL'],
			'IS NOT NULL modifier' => ['ts_id IS NOT NULL DESC', 'ts_id IS NOT NULL DESC'],
			'<> empty string modifier' => ["ts_id <> '' ASC", "ts_id <> '' ASC"],
			'bitfield modifier' => ['ts_id & 1 ASC', 'ts_id & 1 ASC'],
			'already ORDER BY prefixed, no junk before it' => ['ORDER BY ts_id ASC', 'ORDER BY ts_id ASC'],
			'leading whitespace only before ORDER BY is tolerated' => ["  ORDER BY ts_id ASC", 'ORDER BY ts_id ASC'],
			// COALESCE(col1,col2,...) is treated like a plain column name
			'COALESCE two columns' => ['COALESCE(tr_modified,tr_created) DESC', 'COALESCE(tr_modified,tr_created) DESC'],
			'COALESCE with spaces after commas' => ['COALESCE(tr_modified, tr_created) ASC', 'COALESCE(tr_modified, tr_created) ASC'],
			'COALESCE with dotted columns' => ['COALESCE(egw_tracker.tr_modified,egw_tracker.tr_created) DESC', 'COALESCE(egw_tracker.tr_modified,egw_tracker.tr_created) DESC'],
			'COALESCE with more than two columns' => ['COALESCE(a,b,c) ASC', 'COALESCE(a,b,c) ASC'],
			'COALESCE mixed with a plain column' => ['tr_id ASC, COALESCE(tr_modified,tr_created) DESC', 'tr_id ASC, COALESCE(tr_modified,tr_created) DESC'],
			'COALESCE with IS NULL modifier' => ['COALESCE(tr_modified,tr_created) IS NULL', 'COALESCE(tr_modified,tr_created) IS NULL'],
			'COALESCE with IS NOT NULL modifier and direction' => ['COALESCE(tr_modified,tr_created) IS NOT NULL DESC', 'COALESCE(tr_modified,tr_created) IS NOT NULL DESC'],
		];
	}

	/**
	 * @param ?string $input
	 * @param ?string $expected
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('allowedProvider')]
	public function testAllowed($input, $expected)
	{
		$this->assertSame($expected, Api\Storage\Base::sanitizeOrderBy($input));
	}

	/**
	 * Not-understood fragments that must be silently stripped (returned as ''), without throwing -
	 * these have no GROUP BY/HAVING and nothing before a (fake) ORDER BY, so there's no strong signal
	 * of an overlooked caller, just content that doesn't match the strict grammar
	 */
	public static function strippedProvider()
	{
		return [
			'unbalanced parens, no ORDER BY' => ['ts_id) OR 1=1 OR egw_timesheet.ts_id'],
			'space-containing malicious key, no ORDER BY' => ['1=1 OR 1=1 OR egw_timesheet.ts_id'],
			'UNION appended, no ORDER BY' => ['ts_id ASC UNION SELECT 1,2,3'],
			'stacked query, no ORDER BY' => ['ts_id; DROP TABLE egw_timesheet'],
			'ORDER BY tail invalid, no prefix' => ['ORDER BY 1=1'],
			// COALESCE-shaped exploit attempts, all must reduce to the safe empty string
			'COALESCE with subquery arg' => ["COALESCE(tr_modified, (SELECT password FROM egw_accounts LIMIT 1)) DESC"],
			'COALESCE with nested SLEEP() arg' => ['COALESCE(tr_modified, SLEEP(5)) DESC'],
			'COALESCE first arg is SLEEP()' => ['COALESCE(SLEEP(5), tr_created) DESC'],
			'disallowed function name entirely' => ['SLEEP(5)'],
			'disallowed function, case-varied' => ['SlEeP(tr_modified,tr_created) DESC'],
			'BENCHMARK instead of COALESCE' => ["BENCHMARK(1000000,SHA1('x'))"],
			'LOAD_FILE instead of COALESCE' => ['LOAD_FILE(tr_modified,tr_created) DESC'],
			'string literal as COALESCE arg' => ["COALESCE(tr_modified,'x); DROP TABLE egw_tracker;--') DESC"],
			'double-quoted literal as COALESCE arg' => ['COALESCE(tr_modified,"x") DESC'],
			'nested COALESCE' => ['COALESCE(tr_modified, COALESCE(tr_created,1)) DESC'],
			'numeric literal as COALESCE arg' => ['COALESCE(tr_modified,1) DESC'],
			'only numeric literal args' => ['COALESCE(1,2) DESC'],
			'SQL line comment after valid prefix' => ['COALESCE(tr_modified,tr_created) DESC -- , x'],
			'SQL block comment after valid prefix' => ['COALESCE(tr_modified,tr_created) DESC/*comment*/, x'],
			'SQL hash comment after valid prefix' => ['COALESCE(tr_modified,tr_created) DESC # comment'],
			'stacked query after valid prefix' => ['COALESCE(tr_modified,tr_created) DESC; DROP TABLE egw_tracker;'],
			'UNION after valid prefix' => ['COALESCE(tr_modified,tr_created) DESC UNION SELECT password FROM egw_accounts'],
			'unbalanced paren, missing close' => ['COALESCE(tr_modified,tr_created DESC'],
			'missing opening paren' => ['COALESCE tr_modified,tr_created) DESC'],
			'parenthesized arg' => ['COALESCE((tr_modified),tr_created) DESC'],
			'trailing junk after valid close paren' => ['COALESCE(tr_modified,tr_created))--'],
			'IF() instead of COALESCE' => ['IF(1=1,tr_modified,tr_created) DESC'],
			'CASE WHEN instead of COALESCE' => ['CASE WHEN 1=1 THEN tr_modified ELSE tr_created END DESC'],
			'single-arg COALESCE' => ['COALESCE(tr_modified) DESC'],
			'empty-arg COALESCE' => ['COALESCE() DESC'],
			'backtick-quoted identifier' => ['COALESCE(`tr_modified`,tr_created) DESC'],
			'bracket-quoted identifier' => ['COALESCE([tr_modified],tr_created) DESC'],
			'arithmetic in arg' => ['COALESCE(tr_modified+1,tr_created) DESC'],
			'concatenation operator in arg' => ['COALESCE(tr_modified||tr_created) DESC'],
			'percent-encoded injection attempt' => ['COALESCE(tr_modified,tr_created%29 DROP TABLE x--) DESC'],
		];
	}

	/**
	 * @param string $input
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('strippedProvider')]
	public function testStripped($input)
	{
		$this->assertSame('', Api\Storage\Base::sanitizeOrderBy($input));
	}

	/**
	 * Fragments that must throw \InvalidArgumentException - GROUP BY/HAVING can never be validated here
	 * (trusted callers must bypass sanitizeOrderBy() entirely via sanitize_order_by=false instead), and
	 * content before ORDER BY is never legitimate (ORDER BY is only ever prepended by trusted code AFTER
	 * this method already validated the rest)
	 */
	public static function throwsProvider()
	{
		return [
			'GROUP BY with subquery' => ['GROUP BY (SELECT password FROM egw_accounts LIMIT 1)'],
			'HAVING with subquery, no ORDER BY' => ["HAVING (SELECT 1 FROM egw_accounts) > 1"],
			'GROUP BY + HAVING + ORDER BY combined' => ['GROUP BY org_name HAVING COUNT(*) > 1 ORDER BY org_name ASC'],
			'GROUP BY smuggled alongside a valid-looking column' => ['ts_start GROUP BY (SELECT password FROM egw_accounts LIMIT 1) -- '],
			'content before ORDER BY, tail itself valid' => ['1=1) UNION SELECT password FROM egw_accounts -- ORDER BY contact_id'],
			'content before ORDER BY, tail invalid too' => ['some garbage -- ORDER BY 1=1'],
			'GROUP BY as a COALESCE-adjacent attempt' => ['COALESCE(tr_modified,tr_created) GROUP BY tr_id'],
		];
	}

	/**
	 * @param string $input
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('throwsProvider')]
	public function testThrows($input)
	{
		$this->expectException(\InvalidArgumentException::class);
		Api\Storage\Base::sanitizeOrderBy($input);
	}
}