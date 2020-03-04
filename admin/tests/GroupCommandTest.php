<?php

/**
 * Tests for edit group command
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

class GroupCommandTest extends CommandBase {

	// User for testing
	protected $account_id;

	// Define group details once, then modify as needed for tests
	protected $group = array(
		'account_lid' => 'Group Command Test',
		'account_members' => array()
	);

	protected function setUp() : void
	{
		// Can't set this until now - value is not available
		$this->group['account_members'] = array($GLOBALS['egw_info']['user']['account_id']);

		if(($account_id = $GLOBALS['egw']->accounts->name2id($this->group['account_lid'])))
		{
			// Delete if there in case something went wrong
			$GLOBALS['egw']->accounts->delete($account_id);
		}
	}
	protected function tearDown() : void
	{
		// Delete the accounts we created
		if($this->group_id)
		{
			$GLOBALS['egw']->accounts->delete($this->group_id);
		}
		if($this->account_id)
		{
			$GLOBALS['egw']->accounts->delete($this->account_id);
		}
		parent::tearDown();
	}

	/**
	 * Test that adding a group works when we give it what it needs
	 */
	public function testAddGroup()
	{
		// Set up
		$pre_search = $GLOBALS['egw']->accounts->search(array('type' => 'both'));
		$log_count = $this->get_log_count();

		// Execute
		$command = new admin_cmd_edit_group(false, $this->group);
		$command->comment = 'Needed for unit test ' . $this->getName();
		$command->run();
		$this->group_id = $command->account;

		// Check
		$post_search = $GLOBALS['egw']->accounts->search(array('type' => 'both'));

		$this->assertNotEmpty($this->group_id, 'Did not create test group account');
		$this->assertEquals(count($pre_search) + 1, count($post_search), 'Should have one more account than before');
		$this->assertArrayHasKey($this->group_id, $post_search);
		$this->assertGreaterThan($log_count, $this->get_log_count(), "Command ($command) did not log");
	}

	/**
	 * Try to add a new group with the same name as existing group.  It should
	 * throw an exception
	 */
	public function testGroupAlreadyExists()
	{
		// Set up
		$pre_search = $GLOBALS['egw']->accounts->search(array('type' => 'both'));
		$this->expectException(Api\Exception\WrongUserinput::class);

		// Execute
		$this->account['account_lid'] = 'Default';
		$command = new admin_cmd_edit_group(false, $this->account);
		$command->comment = 'Needed for unit test ' . $this->getName();
		$command->run();
		$this->group_id = $command->account;

		// Check
		$post_search = $GLOBALS['egw']->accounts->search(array('type' => 'both'));
		$this->assertEquals(count($pre_search), count($post_search), 'Should have same number of accounts as before');
	}

	/**
	 * Try to add a new group without specifying the name.  It should throw an
	 * exception
	 */
	public function testNameMissing()
	{
		// Set up
		$pre_search = $GLOBALS['egw']->accounts->search(array('type' => 'both'));
		$this->expectException(Api\Exception\WrongUserinput::class);
		$account = $this->group;
		unset($account['account_lid']);

		// Execute
		$command = new admin_cmd_edit_group(false, $account);
		$command->comment = 'Needed for unit test ' . $this->getName();
		$command->run();
		$this->group_id = $command->account;

		// Check
		$post_search = $GLOBALS['egw']->accounts->search(array('type' => 'both'));
		$this->assertEquals(count($pre_search), count($post_search), 'Should have same number of accounts as before');
	}

	/**
	 * Try to add a new group without specifying members.  It should throw
	 * an exception
	 */
	public function testMembersMissing()
	{
		// Set up
		$pre_search = $GLOBALS['egw']->accounts->search(array('type' => 'both'));
		$this->expectException(Api\Exception\WrongUserinput::class);
		$account = $this->group;
		unset($account['account_members']);

		// Execute
		$command = new admin_cmd_edit_group(false, $account);
		$command->comment = 'Needed for unit test ' . $this->getName();
		$command->run();
		$this->group_id = $command->account;

		// Check
		$post_search = $GLOBALS['egw']->accounts->search(array('type' => 'both'));
		$this->assertEquals(count($pre_search), count($post_search), 'Should have same number of accounts as before');
	}

	/**
	 * Test adding & removing a new member
	 *
	 * @depends UserCommandTest::testAddUser
	 */
	public function testChangeMembers()
	{
		// Set up
		// Make a new user so it doesn't matter if something goes wrong
		$account = array(
			'account_lid' => 'test_user',
			'account_firstname' => 'TestUser',
			'account_lastname' => 'Test',
			'account_primary_group' => 'Default',
			'account_groups' => array('Default')
		);

		if(($account_id = $GLOBALS['egw']->accounts->name2id($account['account_lid'])))
		{
			// Delete if there in case something went wrong
			$GLOBALS['egw']->accounts->delete($account_id);
		}

		$command = new admin_cmd_edit_user(false, $account);
		$command->comment = 'Needed for unit test ' . $this->getName();
		$command->run();
		$this->account_id = $command->account;

		$command = new admin_cmd_edit_group(false, $this->group);
		$command->comment = 'Needed for unit test ' . $this->getName();
		$command->run();
		$this->group_id = $command->account;

		// Count accounts
		$pre_search = $GLOBALS['egw']->accounts->search(array('type' => 'both'));
		$log_count = $this->get_log_count();

		// Execute
		$account = $this->account;
		$account['account_members'][] = $this->account_id;
		$command = new admin_cmd_edit_group($this->group_id, $account);
		$command->comment = 'Needed for unit test ' . $this->getName();
		$command->run();

		// Check
		$post_search = $GLOBALS['egw']->accounts->search(array('type' => 'both'));
		$this->assertEquals(count($pre_search), count($post_search), 'Should have same number of accounts as before');
		$this->assertGreaterThan($log_count, $this->get_log_count(), "Command ($command) did not log");

		// Now remove
		$pre_search = $post_search;
		$log_count = $this->get_log_count();

		$account = $this->account;
		$command = new admin_cmd_edit_group($this->group_id, $account);
		$command->comment = 'Needed for unit test ' . $this->getName();
		$command->run();

		// Check
		$post_search = $GLOBALS['egw']->accounts->search(array('type' => 'both'));
		$this->assertEquals(count($pre_search), count($post_search), 'Should have same number of accounts as before');
		$this->assertGreaterThan($log_count, $this->get_log_count(), "Command ($command) did not log");
	}
}