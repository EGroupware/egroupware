<?php

/**
 * Tests for config command
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright (c) 2018  Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

// test base providing common stuff
require_once __DIR__.'/CommandBase.php';

use EGroupware\Api;

class ConfigCommandTest extends CommandBase
{

	// Use the same app for everything
	const APP = 'addressbook';

	// If we add a config, make sure we can delete it for clean up
	protected $config_name = 'test_config';

	protected function tearDown() : void
	{
		if($this->config_name)
		{
			$config = new Api\Config(static::APP);
			$config->delete_value($this->config_name);
			$config->save_repository();
		}
		parent::tearDown();
	}

	/**
	 * Test that adding a setting works
	 */
	public function testAddConfig()
	{
		// Set up
		$log_count = $this->get_log_count();
		$pre = Api\Config::read(static::APP);

		$set = array($this->config_name => 'Yes');

		// Execute
		$command = new admin_cmd_config(static::APP, $set);
		$command->comment = 'Needed for unit test ' . $this->getName();
		$command->run();

		// Check
		$post = Api\Config::read(static::APP);

		$this->assertArrayHasKey($this->config_name, $post);
		$this->assertGreaterThan($log_count, $this->get_log_count(), "Command ($command) did not log");
	}

	/**
	 * Try to change an existing configuration
	 */
	public function testChangeConfig()
	{
		// Set up
		$log_count = $this->get_log_count();
		$pre = Api\Config::read(static::APP);

		$set = array($this->config_name => 'Yes');
		$old = array($this->config_name => 'It will log whatever');

		// Execute
		$command = new admin_cmd_config(static::APP, $set, $old);
		$command->comment = 'Needed for unit test ' . $this->getName();
		$command->run();

		// Check
		$post = Api\Config::read(static::APP);

		$this->assertArrayHasKey($this->config_name, $post);
		$this->assertEquals($set[$this->config_name], $post[$this->config_name]);
		$this->assertNotEquals($pre[$this->config_name], $post[$this->config_name]);
		$this->assertGreaterThan($log_count, $this->get_log_count(), "Command ($command) did not log");
	}

	/**
	 * Try to delete a config
	 */
	public function testDeleteConfig()
	{
		// Set up
		$log_count = $this->get_log_count();
		Api\Config::save_value($this->config_name, 'Delete me', static::APP);
		$pre = Api\Config::read(static::APP);

		$set = array($this->config_name => null);

		// Execute
		$command = new admin_cmd_config(static::APP, $set, array($this->config_name => 'Delete me'));
		$command->comment = 'Needed for unit test ' . $this->getName();
		$command->run();

		// Check
		$post = Api\Config::read(static::APP);
		$this->assertEmpty($post[$this->config_name]);
		$this->assertGreaterThan($log_count, $this->get_log_count(), "Command ($command) did not log");
	}

}