<?php
/**
 * Tests for customfields
 *
 * @package api
 * @subpackage tests
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2018 Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Storage;

use EGroupware\Api;
use EGroupware\Api\LoggedInTest as LoggedInTest;

class CustomfieldsTest extends LoggedInTest
{
	const APP = 'test';
	protected $customfields = null;

	protected $simple_field = array(
			'app'         => self::APP,
			'name'        => 'test_field',
			'label'       => 'Custom field',
			'type'        => 'text',
			'type2'       => array(),
			'help'        => 'Custom field created for automated testing by CustomfieldsTest',
			'values'      => null,
			'len'         => null,
			'rows'        => null,
			'order'       => null,
			'needed'      => null,
			'private'     => array()
		);

	public function assertPreConditions()
	{
		parent::assertPreConditions();
		$tables = $GLOBALS['egw']->db->table_names(true);
		$this->assertContains('egw_test', $tables, 'Could not find DB table "egw_test", make sure test app is installed');
	}

	/**
	 * Check to make sure we can create a custom field
	 */
	public function testCreateField()
	{
		// Create
		$field = $this->simple_field;

		Customfields::update($field);

		// Check
		$fields = Customfields::get(self::APP);

		$this->assertArrayHasKey($field['name'], $fields);

		$saved_field = $fields[$field['name']];

		foreach(array('app','label','type','type2','help','values','len','rows','needed','private') as $key)
		{
			$this->assertEquals($field[$key], $saved_field[$key], "Load of $key did not match save");
		}

		// Clean
		unset($fields[$field['name']]);
		Customfields::save(self::APP, $fields);
	}

	/**
	 * Test the access control on private custom fields
	 */
	public function testPrivate()
	{
		// Create field
		$field = array_merge(
			$this->simple_field,
			array(
				'private' => array($GLOBALS['egw_info']['user']['account_id'])
			)
		);

		Customfields::update($field);
		$fields = Customfields::get(self::APP);

		// Get another user
		$accounts = $GLOBALS['egw']->accounts->search(array(
			'type'    => 'accounts'
		));
		unset($accounts[$GLOBALS['egw_info']['user']['account_id']]);
		if(count($accounts) == 0)
		{
			$this->markTestSkipped('Need more than one user to check private');
		}
		$other_account = key($accounts);

		// Try to read - should not be there
		$fields = Customfields::get(self::APP,$other_account);
		$this->assertArrayNotHasKey($field['name'], $fields);

		// Give access & check again
		$field['private'][] = $other_account;
		Customfields::update($field);

		$fields = Customfields::get(self::APP,$other_account);
		$this->assertArrayHasKey($field['name'], $fields);

		// Remove access, check its gone
		$field['private'] = array($GLOBALS['egw_info']['user']['account_id']);
		Customfields::update($field);

		$fields = Customfields::get(self::APP,$other_account);
		$this->assertArrayNotHasKey($field['name'], $fields);

		// Clean up
		unset($fields[$field['name']]);
		Customfields::save(self::APP, $fields);
	}
}
