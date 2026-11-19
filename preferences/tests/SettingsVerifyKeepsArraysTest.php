<?php
/**
 * EGroupware Preferences - verifying stored preferences must not change them
 *
 * @link https://www.egroupware.org
 * @package preferences
 * @subpackage tests
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Preferences;

require_once realpath(__DIR__.'/../../api/tests/LoggedInTest.php');

use EGroupware\Api\LoggedInTest;

/**
 * The settings dialog verifies the stored preferences of an app every time it shows them
 *
 * Contract: that verification leaves a preference stored as an array an array.  Other code keeps
 * such preferences under the same app, eg. a toolbar's map of action ID to "in the menu".  Joined
 * with commas such a map becomes a string of flags ("1,,1"), which is written to the database as
 * soon as anything saves the preferences in the same request, and the toolbar can not use it.
 *
 * Setup: a made up array preference is put into the default preferences in memory only, then
 * the verification is run on them the way the dialog does.  Nothing is saved, and the
 * preferences are read again afterwards, so the database is never touched.
 *
 * Pass: the preference is still the same array.  A string here means the verification joined it.
 */
class SettingsVerifyKeepsArraysTest extends LoggedInTest
{
	const NAME = 'unit-test_toolbar';

	protected function tearDown() : void
	{
		// throw away what the test put into memory
		$GLOBALS['egw']->preferences->read_repository();

		parent::tearDown();
	}

	public function testVerifyKeepsArrayPreference()
	{
		$preferences = $GLOBALS['egw']->preferences;
		$map = ['first' => true, 'second' => false, 'third' => true];
		$preferences->default['mail'][self::NAME] = $map;

		$types = [];
		$settings = new \preferences_settings();
		$settings->process_array($preferences->default, (array)$preferences->default['mail'], $types,
			'mail', 'default', [], true);

		$this->assertSame($map, $preferences->default['mail'][self::NAME]);
	}
}
