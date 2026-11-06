<?php
/**
 * EGroupware Api: Framework::ajax_set_preference() test
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage framework
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Framework;

require_once __DIR__.'/../LoggedInTest.php';

use EGroupware\Api;
use EGroupware\Api\Framework;
use EGroupware\Api\LoggedInTest;

/**
 * Framework::ajax_set_preference() is called by the client for every preference it keeps (columns, favorites,
 * which parts of a page are open, ...), with nothing to tie the call to a page (no exec_id), so any logged in
 * user can call it with anything.  What is promised is this:
 *
 * - it sets, or with an empty string unsets, a preference of the user who is logged in, and only that,
 * - nothing sent reaches another account, or the default, group or forced preferences,
 * - what can not be a preference (the application is not a name of the kind of an application, the name is not
 *   a string) is not stored, and does not give an error the user would see,
 * - the names the client really uses are accepted.
 *
 * Setup: runs as the user the tests are logged in as (not an admin) and checks what is stored in the database,
 * not what is in memory.  What is stored for another account (the first admin it can find) and for the default,
 * forced and group preferences is read before and after, and has to be the same.  Everything set here is unset
 * again afterwards.
 *
 * Pass criteria: the stored preferences are what the contract says, as written for each test.
 */
class AjaxSetPreferenceTest extends LoggedInTest
{
	const APP = 'infolog';
	const NAME = 'phpunit_ajax_set_preference';

	/** Applications and names set by a test, to unset again */
	protected array $set = [];

	protected function tearDown() : void
	{
		foreach($this->set as [$app, $name])
		{
			Framework::ajax_set_preference($app, $name, '');
		}
		$this->set = [];
		parent::tearDown();
	}

	protected function set(string $app, string $name, $value) : void
	{
		$this->set[] = [$app, $name];
		Framework::ajax_set_preference($app, $name, $value);
	}

	protected function ownId() : int
	{
		return (int)$GLOBALS['egw_info']['user']['account_id'];
	}

	/**
	 * What is stored for an account, for all of the preference types
	 */
	protected function stored(int $account) : array
	{
		$prefs = new Api\Preferences($account);
		$prefs->read_repository();
		return ['user' => $prefs->user, 'default' => $prefs->default, 'forced' => $prefs->forced, 'group' => $prefs->group ?? null];
	}

	public function testSetsAPreferenceOfTheUser() : void
	{
		$value = ['a' => 1, 'b' => ['c' => 'two', 'd' => [true, null]]];
		$this->set(self::APP, self::NAME, $value);

		$this->assertSame($value, $this->stored($this->ownId())['user'][self::APP][self::NAME] ?? null,
			'the preference was not stored for the user');
	}

	public function testUnsetsAPreferenceWithAnEmptyString() : void
	{
		$this->set(self::APP, self::NAME, 'something');
		$this->assertSame('something', $this->stored($this->ownId())['user'][self::APP][self::NAME] ?? null);

		Framework::ajax_set_preference(self::APP, self::NAME, '');
		$this->assertArrayNotHasKey(self::NAME, $this->stored($this->ownId())['user'][self::APP] ?? [],
			'an empty string did not unset the preference');
	}

	public function testStoresAnEmptyArrayWithoutAWarning() : void
	{
		// (an empty array used to be turned into the text "Array" to see if it is empty, with a warning)
		$warnings = [];
		set_error_handler(static function($errno, $errstr) use (&$warnings) { $warnings[] = $errstr; return true; });
		try
		{
			$this->set(self::APP, self::NAME, []);
		}
		finally
		{
			restore_error_handler();
		}
		$this->assertSame([], $warnings, 'setting an array gave a PHP warning');
		$this->assertSame([], $this->stored($this->ownId())['user'][self::APP][self::NAME] ?? null, 'an empty array was not kept as one');
	}

	public function testOnlyTheUsersOwnPreferencesAreWritten() : void
	{
		$others = array_filter([
			(int)$GLOBALS['egw']->accounts->name2id('sysop'),
			(int)$GLOBALS['egw']->accounts->name2id('admin'),
			Api\Preferences::DEFAULT_ID, Api\Preferences::FORCED_ID,
		], fn($id) => $id && $id !== $this->ownId());
		$before = array_map(fn($id) => $this->stored($id), $others);

		$this->set(self::APP, self::NAME, ['x' => 'y']);
		$this->set('common', self::NAME, 'z');

		$this->assertSame($before, array_map(fn($id) => $this->stored($id), $others),
			'what is stored for another account, or as default or forced, was changed');
		$own = $this->stored($this->ownId());
		$this->assertArrayNotHasKey(self::NAME, $own['default'][self::APP] ?? [], 'the default preferences were changed');
		$this->assertArrayNotHasKey(self::NAME, $own['forced'][self::APP] ?? [], 'the forced preferences were changed');
	}

	/**
	 * @return array<string, array{0:mixed, 1:mixed}>
	 */
	public static function notAPreferenceProvider() : array
	{
		return [
			'application is an array' => [['infolog'], 'name'],
			'application is not a string' => [5, 'name'],
			'application is null' => [null, 'name'],
			'application is empty' => ['', 'name'],
			'application is longer than the column it is stored in' => ['abcdefghijklmnopq', 'name'],
			'application with a space' => ['info log', 'name'],
			'application with a path' => ['../etc', 'name'],
			'application with a dot' => ['infolog.x', 'name'],
			'application with markup' => ['<b>', 'name'],
			'name is an array' => ['infolog', ['name']],
			'name is not a string' => ['infolog', 5],
			'name is null' => ['infolog', null],
			'name is empty' => ['infolog', ''],
			'name is far too long' => ['infolog', str_repeat('n', 256)],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('notAPreferenceProvider')]
	public function testIgnoresWhatCanNotBeAPreference($app, $name) : void
	{
		$before = $this->stored($this->ownId());
		// (not an exception, which the user would be shown as an error: this is what the client does by mistake,
		// eg. when it does not know the application)
		Framework::ajax_set_preference($app, $name, 'value');

		$this->assertSame($before, $this->stored($this->ownId()), 'something was stored, though it is not a preference');
	}

	/**
	 * @return array<string, array{0:string, 1:string}>
	 */
	public static function namesInUseProvider() : array
	{
		return [
			'common' => ['common', 'phpunit_ajax_set_preference'],
			'an application with an underscore' => ['news_admin', 'phpunit_ajax_set_preference'],
			'the longest application name' => ['abcdefghijklmnop', 'phpunit_ajax_set_preference'],
			'an application with a hyphen, as the client makes them for tabs' => ['phpunit-tab', 'phpunit_ajax_set_preference'],
			'a nextmatch preference' => ['infolog', 'nextmatch-infolog.index.rows-phpunit'],
			'a favorite' => ['infolog', 'favorite_PHPUnit_Favorite-1'],
			'a name with a space and a dot' => ['infolog', 'jdots_sidebox_phpunit one.two'],
			'the longest name' => ['infolog', str_repeat('n', 255)],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('namesInUseProvider')]
	public function testAcceptsTheNamesTheClientUses(string $app, string $name) : void
	{
		$this->set($app, $name, ['v' => 1]);

		$this->assertSame(['v' => 1], $this->stored($this->ownId())['user'][$app][$name] ?? null, 'the preference was not stored');
	}
}
