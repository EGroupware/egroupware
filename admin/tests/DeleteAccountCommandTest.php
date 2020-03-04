<?php

/**
 * Tests for delete account command
 * 
 * It would be good to check to see if the hooks get called, but that's impossible
 * with static hook calls.
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright (c) 2018  Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

// test base providing common stuff
require_once __DIR__.'/CommandBase.php';

use EGroupware\Api;

class DeleteAccountCommandTest extends CommandBase {

	// User for testing
	protected $account_id;

	// Define account details once, then modify as needed for tests
	protected $account = array(
		'account_lid' => 'user_test',
		'account_firstname' => 'UserCommand',
		'account_lastname' => 'Test'
	);

	protected function setUp() : void
	{
		if(($account_id = $GLOBALS['egw']->accounts->name2id($this->account['account_lid'])))
		{
			// Delete if there in case something went wrong
			$GLOBALS['egw']->accounts->delete($account_id);
		}

		$command = new admin_cmd_edit_user(false, $this->account);
		$command->comment = 'Needed for unit test ' . $this->getName();
		$command->run();
		$this->account_id = $command->account;
		$this->assertNotEmpty($this->account_id, 'Did not create test user account');
	}

	protected function tearDown() : void
	{
		if($this->account_id && ($GLOBALS['egw']->accounts->id2name($this->account_id)))
		{
			$GLOBALS['egw']->accounts->delete($this->account_id);
		}
		parent::tearDown();
	}

	/**
	 * Test that deleting a user works when we give it what it needs
	 */
	public function testDeleteUser()
	{
		// Set up
		$pre_search = $GLOBALS['egw']->accounts->search(array('type' => 'both'));
		$log_count = $this->get_log_count();

		// Execute
		$command = new admin_cmd_delete_account($this->account_id);
		$command->comment = 'Needed for unit test ' . $this->getName();
		$command->run();

		// Check
		$post_search = $GLOBALS['egw']->accounts->search(array('type' => 'both'));

		$this->assertEquals(count($pre_search) - 1, count($post_search), 'Should have one less account than before');
		$this->assertArrayNotHasKey($this->account_id, $post_search);
		$this->assertGreaterThan($log_count, $this->get_log_count(), "Command ($command) did not log");
	}

	/**
	 * Test that deleting a user fails when we tell it it's a group.
	 * It should throw an exception.
	 */
	public function testDeleteUserAsGroup()
	{
		// Set up
		$pre_search = $GLOBALS['egw']->accounts->search(array('type' => 'both'));
		$log_count = $this->get_log_count();
		$this->expectException(Api\Exception\WrongUserinput::class);

		// Execute - we tell it it's a group, even though it's a user
		$command = new admin_cmd_delete_account($this->account_id, null, false);
		$command->comment = 'Needed for unit test ' . $this->getName();
		$command->run();

		// Check
		$post_search = $GLOBALS['egw']->accounts->search(array('type' => 'both'));

		$this->assertEquals(count($pre_search), count($post_search), 'Should have the same number of accounts as before');
		$this->assertArrayNotHasKey($this->account_id, $post_search);
		$this->assertGreaterThan($log_count, $this->get_log_count(), "Command ($command) did not log");
	}
}