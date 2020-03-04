<?php

/**
 * Tests for ACL command
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright (c) 2018  Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

// test base providing common stuff
require_once __DIR__.'/CommandBase.php';

use EGroupware\Api\Acl;

class AclCommandTest extends CommandBase {

	// Use the same app for everything
	const APP = 'addressbook';

	// Group ID for testing
	protected $group_id;

	// User for testing
	protected $account_id;

	/**
	 * Create accounts for testing
	 */
	protected function setUp() : void
	{
		parent::setUp();

		admin_cmd::_instanciate_accounts();

		$group = array('set' => array(
			'account_lid' => 'ACL Test Group',
			'account_members' => $GLOBALS['egw_info']['user']['account_id']
		));

		if($group_id = $GLOBALS['egw']->accounts->name2id($group['set']['account_lid']))
		{
			// Already exists, something went wrong
			$GLOBALS['egw']->accounts->delete($group_id);
		}
		$group_cmd = new admin_cmd_edit_group($group);
		$group_cmd->comment = 'Needed for unit test ' . $this->getName();
		$group_cmd->run();
		$this->group_id = $group_cmd->account;
		$this->assertNotEmpty($this->group_id, 'Did not create test group account');

		// Make a new user so we have clean ACL, and it doesn't matter if something
		// goes wrong
		$account = array(
			'account_lid' => 'acl_test',
			'account_firstname' => 'Alice',
			'account_middlename' => 'Charles Lima',
			'account_lastname' => 'Test',
			'account_primary_group' => $this->group_id,
			'account_groups' => array($this->group_id)
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
		$this->assertNotEmpty($this->account_id, 'Did not create test user account');
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
	 * Test giving a user access to another user's data
	 */
	public function testAddForUserWhenEmpty()
	{
		// Set up
		$log_count = $this->get_log_count();

		// Run
		$data = array(
			'allow' => true,
			'account' => $this->account_id,
			'app' => static::APP,
			'location' => $GLOBALS['egw_info']['user']['account_id'],
			'rights' => Acl::ADD,
			'comment' => 'Giving add rights as part of unit test ' . $this->getName()
		);
		$command = new admin_cmd_acl($data);
		$command->run();

		// Check
		$acl = new Acl($this->account_id);
		$this->assertTrue($acl->check($data['location'], Acl::ADD, static::APP));
		$this->assertEquals($data['rights'], $acl->get_specific_rights($data['location'], $data['app']));
		$this->assertGreaterThan($log_count, $this->get_log_count(), "Command ($command) did not log");
	}

	/**
	 * Test removing access to another user's data
	 */
	public function testRemoveForUserToEmpty()
	{
		// Set up
		$acl = new Acl($this->account_id);
		$acl->add_repository(static::APP, $GLOBALS['egw_info']['user']['account_id'], $this->account_id, Acl::ADD);
		$acl->read_repository();
		$log_count = $this->get_log_count();

		$data = array(
			'allow' => false,
			'account' => $this->account_id,
			'app' => static::APP,
			'location' => $GLOBALS['egw_info']['user']['account_id'],
			'rights' => Acl::ADD,
			'comment' => 'Removing add rights as part of unit test ' . $this->getName()
		);
		$command = new admin_cmd_acl($data);
		$command->run();

		// Check
		$acl->read_repository();
		$this->assertFalse($acl->check($data['location'], Acl::ADD, static::APP));
		$this->assertEquals(0, $acl->get_specific_rights($data['location'], $data['app']));
		$this->assertGreaterThan($log_count, $this->get_log_count(), "Command ($command) did not log");
	}

	/**
	 * Test adding access when there are already permissions
	 */
	public function testAddForUser()
	{
		// Set up
		$acl = new Acl($this->account_id);
		$acl->add_repository(static::APP, $GLOBALS['egw_info']['user']['account_id'], $this->account_id, Acl::READ|Acl::ADD|Acl::EDIT);
		$acl->read_repository();
		$log_count = $this->get_log_count();

		// Run - remove delete
		$data = array(
			'allow' => true,
			'account' => $this->account_id,
			'app' => static::APP,
			'location' => $GLOBALS['egw_info']['user']['account_id'],
			'rights' => Acl::DELETE,
			'comment' => 'Giving delete rights as part of unit test ' . $this->getName()
		);
		$command = new admin_cmd_acl($data);
		$command->run();

		// Check
		$acl = new Acl($this->account_id);
		$this->assertTrue($acl->check($data['location'], Acl::READ, static::APP));
		$this->assertTrue($acl->check($data['location'], Acl::ADD, static::APP));
		$this->assertTrue($acl->check($data['location'], Acl::EDIT, static::APP));
		$this->assertTrue($acl->check($data['location'], Acl::DELETE, static::APP));
		$this->assertEquals(Acl::READ|Acl::ADD|Acl::EDIT|Acl::DELETE, $acl->get_specific_rights($data['location'], $data['app']));
		$this->assertGreaterThan($log_count, $this->get_log_count(), "Command ($command) did not log");
	}

	/**
	 * Test removing access when there are already permissions, and leaving some
	 */
	public function testRemoveForUser()
	{
		// Set up
		$acl = new Acl($this->account_id);
		$acl->add_repository(static::APP, $GLOBALS['egw_info']['user']['account_id'], $this->account_id, Acl::READ|Acl::ADD|Acl::EDIT|Acl::DELETE);
		$acl->read_repository();
		$log_count = $this->get_log_count();

		// Run - remove delete
		$data = array(
			'allow' => false,
			'account' => $this->account_id,
			'app' => static::APP,
			'location' => $GLOBALS['egw_info']['user']['account_id'],
			'rights' => Acl::DELETE,
			'comment' => 'Removing delete rights as part of unit test ' . $this->getName()
		);
		$command = new admin_cmd_acl($data);
		$command->run();

		// Check
		$acl = new Acl($this->account_id);
		$this->assertTrue($acl->check($data['location'], Acl::READ, static::APP));
		$this->assertTrue($acl->check($data['location'], Acl::ADD, static::APP));
		$this->assertTrue($acl->check($data['location'], Acl::EDIT, static::APP));
		$this->assertFalse($acl->check($data['location'], Acl::DELETE, static::APP));
		$this->assertEquals(Acl::READ|Acl::ADD|Acl::EDIT, $acl->get_specific_rights($data['location'], $data['app']));
		$this->assertGreaterThan($log_count, $this->get_log_count(), "Command ($command) did not log");
	}

	/**
	 * Test giving a group access to a user's data
	 */
	public function testAddForGroupWhenEmpty()
	{
		$log_count = $this->get_log_count();
		// Set up
		$data = array(
			'allow' => true,
			'account' => $this->group_id,
			'app' => static::APP,
			'location' => $GLOBALS['egw_info']['user']['account_id'],
			'rights' => Acl::ADD,
			'comment' => 'Giving add rights to a group as part of unit test ' . $this->getName()
		);
		$command = new admin_cmd_acl($data);
		$command->run();

		// Check group
		$acl = new Acl($this->group_id);
		$this->assertTrue($acl->check($data['location'], Acl::ADD, static::APP));
		$this->assertEquals($data['rights'], $acl->get_specific_rights($data['location'], $data['app']));

		// Check that user gets it too
		$acl = new Acl($this->account_id);
		$this->assertTrue($acl->check($data['location'], Acl::ADD, static::APP));
		$this->assertEquals($data['rights'], $acl->get_rights($data['location'], $data['app']));
		$this->assertGreaterThan($log_count, $this->get_log_count(), "Command ($command) did not log");
	}

	/**
	 * Test removing group access
	 */
	public function testRemoveForGroupToEmpty()
	{
		// Set up
		$acl = new Acl($this->group_id);
		$acl->add_repository(static::APP, $GLOBALS['egw_info']['user']['account_id'], $this->group_id, Acl::ADD);
		$acl->read_repository();
		$this->assertTrue($acl->check($GLOBALS['egw_info']['user']['account_id'], Acl::ADD, static::APP));
		$log_count = $this->get_log_count();

		$data = array(
			'allow' => false,
			'account' => $this->group_id,
			'app' => static::APP,
			'location' => $GLOBALS['egw_info']['user']['account_id'],
			'rights' => Acl::ADD,
			'comment' => 'Removing add rights from a group as part of unit test ' . $this->getName()
		);
		$command = new admin_cmd_acl($data);
		$command->run();

		// Check group
		$acl = new Acl($this->group_id);
		$acl->read_repository();
		$this->assertFalse($acl->check($data['location'], Acl::ADD, static::APP));

		// Check that user gets it too
		$acl = new Acl($this->account_id);
		$acl->read_repository();
		$this->assertFalse($acl->check($data['location'], Acl::ADD, static::APP));

		$this->assertEquals(0, $acl->get_rights($data['location'], $data['app']));
		$this->assertGreaterThan($log_count, $this->get_log_count(), "Command ($command) did not log");
	}


	/**
	 * Test adding access to a non-numeric location, such as a category or a
	 * specific record.
	 */
	public function testAddForEntry()
	{
		// Set up
		$log_count = $this->get_log_count();

		$data = array(
			'allow' => true,
			'account' => $this->account_id,
			'app' => static::APP,
			'location' => 'A' . $GLOBALS['egw_info']['user']['person_id'],
			'rights' => Acl::EDIT,
			'comment' => 'Adding edit rights as part of unit test ' . $this->getName()
		);
		$command = new admin_cmd_acl($data);
		$command->run();

		// Check
		$acl = new Acl($this->account_id);
		$this->assertTrue($acl->check($data['location'], Acl::EDIT, static::APP));
		$this->assertEquals($data['rights'], $acl->get_specific_rights($data['location'], $data['app']));
		$this->assertGreaterThan($log_count, $this->get_log_count(), "Command ($command) did not log");
	}

	/**
	 * Test removing access from a non-numeric location, such as a category or a
	 * specific record.
	 */
	public function testRemoveForEntry()
	{
		// Set up
		$acl = new Acl($this->account_id);
		$acl->add_repository(static::APP, 'A' . $GLOBALS['egw_info']['user']['person_id'], $this->account_id, Acl::ADD);
		$acl->read_repository();
		$log_count = $this->get_log_count();

		$data = array(
			'allow' => false,
			'account' => $this->account_id,
			'app' => static::APP,
			'location' => 'A' . $GLOBALS['egw_info']['user']['person_id'],
			'rights' => Acl::ADD,
			'comment' => 'Removing add rights as part of unit test ' . $this->getName()
		);
		$command = new admin_cmd_acl($data);
		$command->run();

		// Check
		$acl->read_repository();
		$this->assertFalse($acl->check($data['location'], Acl::ADD, static::APP));
		$this->assertEquals(0, $acl->get_specific_rights($data['location'], $data['app']));
		$this->assertGreaterThan($log_count, $this->get_log_count(), "Command ($command) did not log");
	}
}
