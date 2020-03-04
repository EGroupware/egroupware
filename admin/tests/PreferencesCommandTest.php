<?php

/**
 * Tests for edit preferences command
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright (c) 2018  Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

// test base providing common stuff
require_once __DIR__.'/CommandBase.php';

use EGroupware\Api;

class PreferencesCommandTest extends CommandBase
{

	// Use the same app for everything
	const APP = 'common';

	// If we add a preference, make sure we can delete it for clean up
	protected $preference_name = 'test_preference';

	protected function setUp() : void
	{
		Api\Cache::unsetInstance(Api\Preferences::class, 'forced');
		Api\Cache::unsetInstance(Api\Preferences::class, 'default');
		Api\Cache::unsetInstance(Api\Preferences::class, $GLOBALS['egw_info']['user']['account_id']);
	}
	protected function tearDown() : void
	{
		if($this->preference_name)
		{
			Api\Preferences::delete_preference(static::APP, $this->preference_name, 'user');
			Api\Preferences::delete_preference(static::APP, $this->preference_name, 'default');
			Api\Preferences::delete_preference(static::APP, $this->preference_name, 'forced');
		}

		Api\Cache::unsetInstance(Api\Preferences::class, 'forced');
		Api\Cache::unsetInstance(Api\Preferences::class, 'default');
		Api\Cache::unsetInstance(Api\Preferences::class, $GLOBALS['egw_info']['user']['account_id']);
		parent::tearDown();
	}

	/**
	 * Test that adding a preference works
	 *
	 * @dataProvider typeDataProvider
	 */
	public function testAddPreference($type)
	{
		// Set up
		$log_count = $this->get_log_count();
		$account = $type == 'group' ? $GLOBALS['egw']->accounts->name2id('Default') : $GLOBALS['egw_info']['user']['account_id'];
		$pre_pref = new Api\Preferences($GLOBALS['egw_info']['user']['account_id']);
		$pre = $pre_pref->read(static::APP);
		$this->assertArrayNotHasKey($this->preference_name, $pre);

		$set = array($this->preference_name => 'Yes');

		// Execute
		$command = new admin_cmd_edit_preferences($account, $type, static::APP, $set);
		$command->comment = 'Needed for unit test ' . $this->getName() . " with type $type";
		$command->run();

		// Check
		$post_pref = new Api\Preferences($GLOBALS['egw_info']['user']['account_id']);
		$post = $post_pref->read_repository(false);
		// At user level
		$this->assertArrayHasKey($this->preference_name, $post[static::APP]);
		$this->assertEquals($set[$this->preference_name], $post[static::APP][$this->preference_name]);
		// At type level
		// Get type as an array since direct sub-access doesn't work in 5.6
		$post_app = $post_pref->$type;
		$this->assertArrayHasKey($this->preference_name, $post_app[static::APP],
				"$type preferences does not have {$this->preference_name}");
		$this->assertEquals($set[$this->preference_name], $post_app[static::APP][$this->preference_name]);

		$this->assertGreaterThan($log_count, $this->get_log_count(), "Command ($command) did not log");
	}

	/**
	 * Try to change an existing preference
	 *
	 * We check changing the various combinations with a default set.
	 * All should give a change.
	 *
	 * @dataProvider typeDataProvider
	 */
	public function testChangeWithDefault($type)
	{
		// Set up
		$log_count = $this->get_log_count();
		$account = $GLOBALS['egw_info']['user']['account_id'];

		$set = array($this->preference_name => 'Changed');
		$old = array($this->preference_name => $type . ' original value');

		$prefs = new Api\Preferences('default');
		$prefs->read_repository(false);
		$prefs->add(static::APP, $this->preference_name, 'default original value','default');
		$prefs->save_repository('default');

		$prefs = new Api\Preferences(in_array($type, array('default', 'forced')) ? $type : $account);
		$prefs->read_repository(false);
		$prefs->add(static::APP, $this->preference_name, $old[$this->preference_name], $type);
		$prefs->save_repository($type);

		// Execute
		$command = new admin_cmd_edit_preferences($account, $type, static::APP, $set, $old);
		$command->comment = 'Needed for unit test ' . $this->getName();
		$command->run();

		// Check
		$post_pref = new Api\Preferences($account);
		$post = $post_pref->read_repository(false);

		// At user level
		$this->assertArrayHasKey($this->preference_name, $post[static::APP]);
		$this->assertEquals($set[$this->preference_name], $post[static::APP][$this->preference_name]);

		// At type level
		// Get type as an array since direct sub-access doesn't work in 5.6
		$post_app = $post_pref->$type;
		$this->assertArrayHasKey($this->preference_name, $post_app[static::APP],
				"$type preferences does not have {$this->preference_name}");
		$this->assertEquals($set[$this->preference_name], $post_app[static::APP][$this->preference_name]);

		$this->assertGreaterThan($log_count, $this->get_log_count(), "Command ($command) did not log");
	}

	/**
	 * Try to change an existing preference
	 *
	 * We check changing the various combinations with a default set.
	 * Only forced should give a change.
	 *
	 * @dataProvider typeDataProvider
	 */
	public function testChangeWithForced($type)
	{
		// Set up
		$log_count = $this->get_log_count();
		$account = $GLOBALS['egw_info']['user']['account_id'];

		$set = array($this->preference_name => 'Changed '. $type);
		$old = array($this->preference_name => $type . ' original value');

		$prefs = new Api\Preferences('forced');
		$prefs->read_repository(false);
		$prefs->add(static::APP, $this->preference_name, 'forced original value','forced');
		$prefs->save_repository(false, 'forced');

		$prefs = new Api\Preferences(in_array($type, array('default', 'forced')) ? $type : $account);
		$prefs->read_repository(false);
		$prefs->add(static::APP, $this->preference_name, $old[$this->preference_name], $type);
		$prefs->save_repository(false, $type);

		// Execute
		$command = new admin_cmd_edit_preferences($account, $type, static::APP, $set, $old);
		$command->comment = 'Needed for unit test ' . $this->getName();
		$command->run();

		// Check
		$post_pref = new Api\Preferences($account);
		$post = $post_pref->read_repository(false);

		// At user level
		$this->assertArrayHasKey($this->preference_name, $post[static::APP]);
		$this->assertEquals($type != 'forced' ? 'forced original value' : $set[$this->preference_name], $post[static::APP][$this->preference_name],
				"$type preference overrode forced preference"
		);

		// At type level
		// Get type as an array since direct sub-access doesn't work in 5.6
		$post_app = $post_pref->$type;
		$this->assertArrayHasKey($this->preference_name, $post_app[static::APP],
				"$type preferences does not have {$this->preference_name}");
		$this->assertEquals($set[$this->preference_name], $post_app[static::APP][$this->preference_name]);

		$this->assertGreaterThan($log_count, $this->get_log_count(), "Command ($command) did not log");
	}

	/**
	 * Try to change an existing preference
	 *
	 * We check changing the various combinations, some of which should change
	 *
	 * @dataProvider typeChangeDataProvider
	 */
	public function testChange($type, $check, $change)
	{
		// Set up
		$log_count = $this->get_log_count();
		$account = $GLOBALS['egw_info']['user']['account_id'];


		$set = array($this->preference_name => $type . ' changed');
		$old = array($this->preference_name => $type . ' original value');
		$check_original = $check . ' original value';
		//echo "\n".__METHOD__ . "($type, $check, $change) Change $type but $check should " . ($change ? '' : 'not ') . "change ( " . ($change ? $set[$this->preference_name] : $check_original).")\n";

		$prefs = new Api\Preferences(in_array($type, array('default', 'forced')) ? $type : $account);
		$prefs->read_repository(false);
		$prefs->add(static::APP, $this->preference_name, $old[$this->preference_name], $type);
		$prefs->save_repository(False, $type);

		$prefs = new Api\Preferences(in_array($check, array('default', 'forced')) ? $check : $account);
		$prefs->read_repository(false);
		$prefs->add(static::APP, $this->preference_name, $check_original, $check);
		$prefs->save_repository(False, $check);

		// Execute
		$command = new admin_cmd_edit_preferences($account, $type, static::APP, $set, $old);
		$command->comment = 'Needed for unit test ' . $this->getName();
		$command->run();

		// Check
		$post_pref = new Api\Preferences($account);
		$post = $post_pref->read_repository(false);

		// At type level - should always be what we set
		// Get type as an array since direct sub-access doesn't work in 5.6
		$post_app = $post_pref->$type;
		$this->assertArrayHasKey($this->preference_name, $post_app[static::APP],
				"$type preferences does not have {$this->preference_name}");
		$this->assertEquals($set[$this->preference_name], $post_app[static::APP][$this->preference_name]);

		// At user level - depends on type priority
		$this->assertArrayHasKey($this->preference_name, $post[static::APP]);
		$this->assertEquals($change ? $set[$this->preference_name] : $check_original, $post[static::APP][$this->preference_name]);


		$this->assertGreaterThan($log_count, $this->get_log_count(), "Command ($command) did not log");
	}

	/**
	 * Try to delete an existing preference
	 *
	 * We check the various combinations, such as deleting a default when the user
	 * has a value.
	 *
	 * @dataProvider typeChangeDataProvider
	 */
	public function testDeletePreference($type, $check, $override)
	{
		// Set up
		$log_count = $this->get_log_count();
		$account = $type == 'group' ? $GLOBALS['egw']->accounts->name2id('Default') : $GLOBALS['egw_info']['user']['account_id'];

		$set = array($this->config_name => null);
		$old = array($this->config_name => 'It will log whatever');

		$prefs = new Api\Preferences(in_array($type, array('default', 'forced')) ? $type : $account);
		$prefs->add(static::APP, $this->config_name, $old[$this->config_name], in_array($type, array('default', 'forced')) ? $type : 'user');
		$prefs->save_repository(false, $type);


		// Execute
		$command = new admin_cmd_edit_preferences($account, $type, static::APP, $set, $old);
		$command->comment = 'Needed for unit test ' . $this->getName();
		$command->run();

		// Check
		$account = $check == 'group' ? $GLOBALS['egw']->accounts->name2id('Default') : $GLOBALS['egw_info']['user']['account_id'];
		$post_pref = new Api\Preferences($account);
		$post = $post_pref->read_repository();

		// At user level
		$this->assertEquals($override ? $set[$this->preference_name] : $old[$this->preference_name], $post[static::APP][$this->preference_name]);

		// At type level
		// Get type as an array since direct sub-access doesn't work in 5.6
		$post_app = $post_pref->$check;
		$this->assertNull($post_app[static::APP][$this->preference_name]);

		$this->assertGreaterThan($log_count, $this->get_log_count(), "Command ($command) did not log");
	}


	/**
	 * Give a list of preference levels (types) so we can check them all
	 * They are in priority order.
	 */
	public function typeDataProvider() {
		return array(
			Array('default'),
			Array('user'),
			//Array('group'), Not really supported yet
			Array('forced')
		);
	}

	/**
	 * Get a list of preference levels and if they should be allowed to change
	 * each other
	 */
	public function typeChangeDataProvider() {
		$levels = array(
			// Change and this should/should not change
			Array('default', 'user',   false),
			Array('default', 'forced', false),
			Array('user',    'default', true),
			Array('user',    'forced', false),
			Array('forced',  'user',    true),
			Array('forced',  'default', true),
		);


		return $levels;
	}
}