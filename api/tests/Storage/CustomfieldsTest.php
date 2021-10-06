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

require_once __DIR__ . '/../LoggedInTest.php';
use EGroupware\Api\LoggedInTest as LoggedInTest;

class CustomfieldsTest extends LoggedInTest
{
	const APP = 'test';
	protected $customfields = null;

	protected $simple_field = array(
		'app'     => self::APP,
		'name'    => 'test_field',
		'label'   => 'Custom field',
		'type'    => 'text',
		'type2'   => array(),
		'help'    => 'Custom field created for automated testing by CustomfieldsTest',
		'values'  => null,
		'len'     => null,
		'rows'    => null,
		'order'   => null,
		'needed'  => null,
		'private' => array()
	);

	public function tearDown(): void
	{
		// Clean
		$fields = Customfields::get(self::APP);
		foreach($this->customfields as $field_name)
		{
			unset($fields[$field_name]);
		}
		Customfields::save(self::APP, $fields);
		parent::tearDown();
	}

	protected function assertPreConditions() : void
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
		$this->customfields[] = $field['name'];

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
	public function testPrivateCannotBeReadWithoutPermission()
	{
		$field = $this->create_private_field();

		// Get another user
		$other_account = $this->get_another_user();

		// Try to read - should not be there
		$fields = Customfields::get(self::APP,$other_account);
		$this->assertArrayNotHasKey($field['name'], $fields);

		// Switch the users
		$field['private'] = array($other_account);
		Customfields::update($field);

		// Try to read - should not be there
		$fields = Customfields::get(self::APP,false);
		$this->assertArrayNotHasKey($field['name'], $fields);

		// Clean up
		unset($fields[$field['name']]);
		Customfields::save(self::APP, $fields);
	}

	/**
	 * Test that giving access allows access
	 */
	public function testGivingAccess()
	{
		$field = $this->create_private_field();

		$fields = Customfields::get(self::APP);

		// Get another user
		$other_account = $this->get_another_user();

		// Give access & check
		$field['private'][] = $other_account;
		Customfields::update($field);

		$fields = Customfields::get(self::APP,$other_account);
		$this->assertArrayHasKey($field['name'], $fields);

		// Clean up
		unset($fields[$field['name']]);
		Customfields::save(self::APP, $fields);
	}

	/**
	 * Test that removing access disallows access
	 */
	public function testRemovingAccess()
	{
		$field = $this->create_private_field();

		$fields = Customfields::get(self::APP);

		// Get another user
		$other_account = $this->get_another_user();

		// Give access
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

	/**
	 * Test getting all fields ignores any access restrictions
	 */
	public function testGetAllFields()
	{
		$field = $this->create_private_field();

		// Get another user
		$other_account = $this->get_another_user();

		// Change access so current user can't read it
		$field['private'] = array($other_account);
		Customfields::update($field);

		$fields = Customfields::get(self::APP,true);
		$this->assertEquals(1, count($fields));
		$this->assertArrayHasKey($field['name'], $fields);

		// Clean up
		unset($fields[$field['name']]);
		Customfields::save(self::APP, $fields);
	}

	/**
	 * Test getting options from a file
	 *
	 * @dataProvider fileOptionProvider
	 */
	public function testGetOptionsFromGoodFile($expected, $file)
	{
		// Load
		$options = Customfields::get_options_from_file('api/tests/fixtures/Storage/' . $file);

		// Check
		$this->assertIsArray($options);
		$this->assertEquals($expected, $options);
	}

	/**
	 * Provide some options (duplicated in the files) to check loading
	 *
	 * @return array
	 */
	public function fileOptionProvider()
	{
		// Expected options, file
		return array(
			array(array(
					  ''  =>	'Select',
					  'Α' =>	'α	Alpha',
					  'Β' =>	'β	Beta',
					  'Γ' =>	'γ	Gamma',
					  'Δ' =>	'δ	Delta',
					  'Ε' =>	'ε	Epsilon',
					  'Ζ' =>	'ζ	Zeta',
					  'Η' =>	'η	Eta',
					  'Θ' =>	'θ	Theta',
					  'Ι' =>	'ι	Iota',
					  'Κ' =>	'κ	Kappa',
					  'Λ' =>	'λ	Lambda',
					  'Μ' =>	'μ	Mu',
					  'Ν' =>	'ν	Nu',
					  'Ξ' =>	'ξ	Xi',
					  'Ο' =>	'ο	Omicron',
					  'Π' =>	'π	Pi',
					  'Ρ' =>	'ρ	Rho',
					  'Σ' =>	'σ	Sigma',
					  'Τ' =>	'τ	Tau',
					  'Υ' =>	'υ	Upsilon',
					  'Φ' =>	'φ	Phi',
					  'Χ' =>	'χ	Chi',
					  'Ψ' =>	'ψ	Psi',
					  'Ω' =>	'ω	Omega'
				  ), 'greek_options.php'),
			array(array(
					  'View Subs' => "egw_open('','infolog','list',{action:'sp',action_id:widget.getRoot().getArrayMgr('content').getEntry('info_id')},'infolog','infolog');"
				  ), 'infolog_subs_option.php')
		);
	}

	/**
	 * A file that is not found or cannot be read should return an array
	 * with an error message, and not error.  It's impossible to deal with an
	 * actual invalid file though, they just cause Fatal Errors.
	 */
	public function testGetOptionsFromMissingFile()
	{
		$options = Customfields::get_options_from_file('totally invalid');
		$this->assertIsArray($options);
		$this->assertCount(1, $options);
	}

	/**
	 * Certain characters (&, ", etc.) will break our XML templates.
	 * Make sure they get stripped out.
	 */
	public function testInvalidName()
	{
		// Create
		$field = $this->simple_field;
		$this->customfields[] = $invalid_name = $field['name'] = '<Invalid> & "TEST"';
		$this->customfields[] = $valid_name = 'Invalid  TEST';

		Customfields::update($field);

		// Check
		$fields = Customfields::get(self::APP);

		$this->assertArrayNotHasKey($invalid_name, $fields, "Invalid customfield name was allowed");
		$this->assertArrayHasKey($valid_name, $fields, "Invalid customfield name was not corrected");

		$saved_field = $fields[$valid_name];

		$this->assertEquals($valid_name, $saved_field['name'], "Invalid customfield name was allowed");

		// Clean
		unset($fields[$invalid_name], $fields[$saved_field['name']]);
		Customfields::save(self::APP, $fields);
	}

	protected function create_private_field()
	{
		// Create field
		$field = array_merge(
			$this->simple_field,
			array(
				'private' => array($GLOBALS['egw_info']['user']['account_id'])
			)
		);
		$this->customfields[] = $field['name'];
		Customfields::update($field);

		return $field;
	}

	/**
	 * Get another user that we can use to test
	 */
	protected function get_another_user()
	{
		$accounts = $GLOBALS['egw']->accounts->search(array(
														  'type' => 'accounts'
													  ));
		unset($accounts[$GLOBALS['egw_info']['user']['account_id']]);
		if(count($accounts) == 0)
		{
			$this->markTestSkipped('Need more than one user to check private');
		}
		$other_account = key($accounts);

		if(!$other_account)
		{
			$this->markTestSkipped('Need more than one user to check private');
		}
		return $other_account;
	}
}
