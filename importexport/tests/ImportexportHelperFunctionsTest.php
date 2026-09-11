<?php
/**
 * EGroupware importexport: tests for importexport_helper_functions
 *
 * @link http://www.egroupware.org
 * @package importexport
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once realpath(__DIR__.'/../../api/src/loader/common.php');

use PHPUnit\Framework\TestCase;

/**
 * Tests for the fully static, DB-free methods of importexport_helper_functions:
 * custom_strtotime(), conversion(), date_rel2abs() and the pure-input short-circuit
 * branches of is_valid_plugin(). None of these touch the database or a session.
 */
class ImportexportHelperFunctionsTest extends TestCase
{
	/**
	 * With no $_format, custom_strtotime() is a plain passthrough to PHP's strtotime().
	 */
	public function testCustomStrtotimeWithoutFormatUsesPhpStrtotime()
	{
		$this->assertSame(strtotime('2026-01-15'), importexport_helper_functions::custom_strtotime('2026-01-15'));
	}

	/**
	 * With a date-only $_format, the result must be midnight of that date - not
	 * "today's current time on that date", which is what DateTime::createFromFormat()
	 * would produce without a leading '!' to reset the fields the format doesn't
	 * mention. Regression test for the fix: custom_strtotime() used to build this via
	 * mktime() with a 7th (DST) argument, which PHP has not accepted for a very long
	 * time and fatals unconditionally ("mktime() expects at most 6 arguments").
	 */
	public function testCustomStrtotimeWithDateOnlyFormatIsMidnight()
	{
		$result = importexport_helper_functions::custom_strtotime('15.01.2026', 'd.m.Y');

		$this->assertSame(mktime(0, 0, 0, 1, 15, 2026), $result);
	}

	/**
	 * A format including a time component must be honoured exactly.
	 */
	public function testCustomStrtotimeWithDateAndTimeFormat()
	{
		$result = importexport_helper_functions::custom_strtotime('15.01.2026 14:30', 'd.m.Y H:i');

		$this->assertSame(mktime(14, 30, 0, 1, 15, 2026), $result);
	}

	/**
	 * A string that doesn't match the given format must fail gracefully (false),
	 * not throw.
	 */
	public function testCustomStrtotimeWithUnparsableStringReturnsFalse()
	{
		$this->assertFalse(importexport_helper_functions::custom_strtotime('not a date', 'd.m.Y'));
	}

	/**
	 * conversion() with a single pattern|>replacement pair: the whole field value is
	 * replaced when the pattern matches.
	 */
	public function testConversionSimplePatternReplace()
	{
		$record = array('status' => 'yes');
		$conversion = array('status' => '^yes$|>1||^no$|>0');

		$result = importexport_helper_functions::conversion($record, $conversion);

		$this->assertSame('1', $result['status']);
	}

	/**
	 * conversion() tries each ||-separated pattern|>replacement pair in order and
	 * uses the first one that matches - here the second pair, not the first.
	 */
	public function testConversionUsesFirstMatchingPatternPair()
	{
		$record = array('status' => 'no');
		$conversion = array('status' => '^yes$|>1||^no$|>0');

		$result = importexport_helper_functions::conversion($record, $conversion);

		$this->assertSame('0', $result['status']);
	}

	/**
	 * A field whose value matches none of the given patterns is left unchanged.
	 */
	public function testConversionLeavesNonMatchingValueUnchanged()
	{
		$record = array('other' => 'hello');
		$conversion = array('other' => '^zzz$|>changed');

		$result = importexport_helper_functions::conversion($record, $conversion);

		$this->assertSame('hello', $result['other']);
	}

	/**
	 * An empty conversion string for a field means "no conversion for this field" -
	 * conversion() must skip it (continue) and leave the value untouched, while still
	 * processing the other fields normally.
	 */
	public function testConversionSkipsEmptyConversionStringForField()
	{
		$record = array('a' => 'keep', 'b' => 'change');
		$conversion = array('a' => '', 'b' => '^change$|>changed');

		$result = importexport_helper_functions::conversion($record, $conversion);

		$this->assertSame('keep', $result['a']);
		$this->assertSame('changed', $result['b']);
	}

	/**
	 * date_rel2abs('Today') must return the full current day - midnight to midnight
	 * minus one second - as [from, to] unix timestamps. Environment-sensitive: computed
	 * against the same date()/mktime() calls used inside date_rel2abs(), executed a
	 * moment apart in the same test, so this is only unsafe within a few ms of a
	 * midnight rollover.
	 */
	public function testDateRel2absToday()
	{
		$today_midnight = mktime(0, 0, 0, (int)date('m'), (int)date('d'), (int)date('Y'));

		$result = importexport_helper_functions::date_rel2abs('Today');

		$this->assertSame($today_midnight, $result['from']);
		$this->assertSame($today_midnight + 86400 - 1, $result['to']);
	}

	/**
	 * date_rel2abs('Yesterday') must return the full previous day.
	 */
	public function testDateRel2absYesterday()
	{
		$today_midnight = mktime(0, 0, 0, (int)date('m'), (int)date('d'), (int)date('Y'));

		$result = importexport_helper_functions::date_rel2abs('Yesterday');

		$this->assertSame($today_midnight - 86400, $result['from']);
		$this->assertSame($today_midnight - 1, $result['to']);
	}

	/**
	 * A value not present in self::$relative_dates returns null, not an error.
	 */
	public function testDateRel2absUnknownValueReturnsNull()
	{
		$this->assertNull(importexport_helper_functions::date_rel2abs('Not a real relative date'));
	}

	/**
	 * An array of relative-date strings is processed recursively, key by key.
	 */
	public function testDateRel2absArrayInputIsRecursive()
	{
		$result = importexport_helper_functions::date_rel2abs(array('a' => 'Today', 'b' => 'Yesterday'));

		$this->assertSame(array('from', 'to'), array_keys($result['a']));
		$this->assertSame(array('from', 'to'), array_keys($result['b']));
		$this->assertNotEquals($result['a'], $result['b']);
	}

	/**
	 * is_valid_plugin() rejects empty/non-string plugin names immediately, without
	 * touching the plugin registry (which would need the filesystem/cache scan in
	 * get_plugins()).
	 */
	public function testIsValidPluginRejectsEmptyOrNonStringImmediately()
	{
		$this->assertFalse(importexport_helper_functions::is_valid_plugin(''));
		$this->assertFalse(importexport_helper_functions::is_valid_plugin(null));
		$this->assertFalse(importexport_helper_functions::is_valid_plugin(123));
	}

	/**
	 * During setup ($GLOBALS['egw_setup'] set), is_valid_plugin() short-circuits to
	 * true for any non-empty string, since not all apps are installed yet.
	 */
	public function testIsValidPluginDuringSetupAcceptsAnyName()
	{
		$backup = $GLOBALS['egw_setup'] ?? null;
		$GLOBALS['egw_setup'] = true;
		try
		{
			$this->assertTrue(importexport_helper_functions::is_valid_plugin('totally_made_up_plugin_name'));
		}
		finally
		{
			if ($backup === null)
			{
				unset($GLOBALS['egw_setup']);
			}
			else
			{
				$GLOBALS['egw_setup'] = $backup;
			}
		}
	}
}
