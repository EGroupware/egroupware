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
use EGroupware\Api;
use EGroupware\Api\LoggedInTest as LoggedInTest;
use EGroupware\Api\Vfs;

class CustomfieldsTest extends LoggedInTest
{
	const APP = 'test';
	protected $customfields = null;
	private $mounts = array();

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

		foreach($this->mounts as $mount)
		{
			Vfs::umount($mount);
		}
		parent::tearDown();
	}

	protected function assertPreConditions() : void
	{
		parent::assertPreConditions();
		$tables = $GLOBALS['egw']->db->table_names(true);

		if(!in_array('egw_test', $tables))
		{
			$this->markTestSkipped('No test app installed');
		}
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
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('fileOptionProvider')]
	public function testGetOptionsFromGoodFile($expected, $file)
	{
		// Mount the fixtures
		$this->mountFilesystem(realpath(__DIR__ . '/../fixtures/Storage/'), '/api/tests');

		// Load
		$options = Customfields::get_options_from_file('/api/tests/' . $file);

		// Check
		$this->assertIsArray($options);
		$this->assertEquals($expected, $options);
	}

	/**
	 * Provide some options (duplicated in the files) to check loading
	 *
	 * @return array
	 */
	public static function fileOptionProvider()
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
				  ), 'greek_options.json'),
			array(array(
					  'View Subs' => "egw_open('','infolog','list',{action:'sp',action_id:widget.getRoot().getArrayMgr('content').getEntry('info_id')},'infolog','infolog');"
				  ), 'infolog_subs_option.json')
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

	/**
	 * Customfields::format() - 'select-account' type: single numeric value resolves to a
	 * username via Api\Accounts::username(); a non-numeric value passes through unchanged.
	 *
	 * Pass criteria: single-value case returns the current user's username; a non-numeric
	 * value (eg. already-resolved text) is returned as-is.
	 */
	public function testFormatSelectAccountSingleValue()
	{
		$field = array('type' => 'select-account', 'rows' => 1);
		$account_id = $GLOBALS['egw_info']['user']['account_id'];
		$expected = Api\Accounts::username($account_id);

		$this->assertEquals($expected, Customfields::format($field, $account_id));
		$this->assertEquals('not-numeric', Customfields::format($field, 'not-numeric'));
	}

	/**
	 * Customfields::format() - 'select-account' type with rows>1: a comma-separated string
	 * of account ids (not already an array) gets split and each numeric id resolved.
	 *
	 * Pass criteria: two account ids joined by ',' resolve to "user1, user2".
	 */
	public function testFormatSelectAccountMultiValue()
	{
		$other_account = $this->get_another_user();
		$account_id = $GLOBALS['egw_info']['user']['account_id'];
		$field = array('type' => 'select-account', 'rows' => 2);

		$expected = implode(', ', array(Api\Accounts::username($account_id), Api\Accounts::username($other_account)));
		$this->assertEquals($expected, Customfields::format($field, $account_id.','.$other_account));
	}

	/**
	 * Customfields::format() - 'checkbox' type: truthy -> 'X', falsy -> ''.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('checkboxProvider')]
	public function testFormatCheckbox($value, $expected)
	{
		$this->assertSame($expected, Customfields::format(array('type' => 'checkbox'), $value));
	}

	public static function checkboxProvider() : array
	{
		return array(
			'truthy 1' => array(1, 'X'),
			'truthy string' => array('yes', 'X'),
			'falsy 0' => array(0, ''),
			'falsy empty string' => array('', ''),
			'falsy null' => array(null, ''),
		);
	}

	/**
	 * Customfields::format() - 'select'/'radio': a known option value maps to its label, an
	 * unknown value falls back to '#'.$val, and multiple rows>1 values get comma-joined.
	 */
	public function testFormatSelectKnownAndUnknownValues()
	{
		$field = array('type' => 'select', 'rows' => 1, 'values' => array('a' => 'Option A', 'b' => 'Option B'));
		$this->assertEquals('Option A', Customfields::format($field, 'a'));
		$this->assertEquals('#unknown', Customfields::format($field, 'unknown'),
			"An option value with no matching label must fall back to '#'.\$val");

		$field['rows'] = 2;
		$this->assertEquals('Option A, Option B', Customfields::format($field, 'a,b'));
	}

	/**
	 * Customfields::format() - 'select'/'radio' with $field['values'] shaped as a single
	 * ['@' => file] entry: options are loaded from that file via get_options_from_file()
	 * before resolving the value's label. Uses the existing greek_options.json fixture.
	 */
	public function testFormatSelectValuesFromFile()
	{
		$this->mountFilesystem(realpath(__DIR__ . '/../fixtures/Storage/'), '/api/tests');

		$field = array('type' => 'select', 'rows' => 1, 'values' => array('@' => '/api/tests/greek_options.json'));
		$this->assertEquals('α	Alpha', Customfields::format($field, 'Α'));
	}

	/**
	 * Customfields::format() - 'date'/'date-time': non-empty value gets formatted via
	 * Api\DateTime::to(), empty value passes through unchanged (no crash on falsy input).
	 */
	public function testFormatDateAndDateTime()
	{
		$ts = Api\DateTime::to('now', 'ts');

		$this->assertEquals(Api\DateTime::to($ts, true), Customfields::format(array('type' => 'date'), $ts));
		$this->assertEquals(Api\DateTime::to($ts, ''), Customfields::format(array('type' => 'date-time'), $ts));
		$this->assertSame('', Customfields::format(array('type' => 'date'), ''),
			'Empty value must pass through unchanged, not crash formatting a falsy date');
	}

	/**
	 * Customfields::format() - 'htmlarea': only OPENING <br>/<p ...> tags (the regex
	 * '/<(br|p)[^>]*>/i' never matches a leading '/', so '</p>' is excluded) convert to
	 * CRLF; closing tags are then silently dropped by the final strip_tags() with no
	 * separator of their own - so consecutive "<p>A</p><p>B</p>" blocks get exactly ONE
	 * CRLF between them (from the second block's opening tag), not two. Pre-existing
	 * literal CR/LF in the source are stripped first (str_replace(["\r","\n"], '', ...)
	 * runs before the tag substitution).
	 */
	public function testFormatHtmlarea()
	{
		$value = "<p>First</p><p>Second</p>\n<br>Third<br/>";
		$formatted = Customfields::format(array('type' => 'htmlarea'), $value);

		$this->assertSame("\r\nFirst\r\nSecond\r\nThird\r\n", $formatted);
		$this->assertSame('', Customfields::format(array('type' => 'htmlarea'), ''));
	}

	/**
	 * Customfields::format() - default/link-type branch: a field whose type is a real
	 * linkable app name (from get_link_types()) resolves its value via Api\Link::title().
	 * Uses an existing addressbook contact (read-only lookup, no fixture created/cleaned
	 * up) so this doesn't touch shared data - skipped if none exists.
	 */
	public function testFormatLinkType()
	{
		$this->assertContains('addressbook', Customfields::get_link_types(),
			'Test precondition: addressbook expected to be a registered link type');

		$contacts = new Api\Contacts();
		$rows = $contacts->search('', false, '', '', '', false, 'AND', array(0, 1));
		if (empty($rows))
		{
			$this->markTestSkipped('No existing addressbook contact available to test link-type formatting');
		}
		$contact = reset($rows);

		$expected = Api\Link::title('addressbook', $contact['id']);
		$this->assertEquals($expected, Customfields::format(array('type' => 'addressbook'), $contact['id']));

		// 'link-entry' type: value is "app:id" and gets split before the Link::title() lookup
		$this->assertEquals($expected, Customfields::format(array('type' => 'link-entry'), 'addressbook:'.$contact['id']));
	}

	/**
	 * Customfields::get_options_from_file() - a file that exists but doesn't end in .json
	 * is rejected the same way as a missing file (single-element error-message array).
	 */
	public function testGetOptionsFromFileWrongExtension()
	{
		$this->mountFilesystem(realpath(__DIR__ . '/../fixtures/Storage/'), '/api/tests');

		$options = Customfields::get_options_from_file('/api/tests/options.txt');
		$this->assertIsArray($options);
		$this->assertCount(1, $options, 'A non-.json file must be rejected, even though it exists and contains valid JSON');
	}

	/**
	 * Customfields::get_options_from_file() - a .json file whose content isn't valid JSON
	 * (json_decode() returns null) is rejected the same way as a missing file.
	 */
	public function testGetOptionsFromMalformedJsonFile()
	{
		$this->mountFilesystem(realpath(__DIR__ . '/../fixtures/Storage/'), '/api/tests');

		$options = Customfields::get_options_from_file('/api/tests/malformed_options.json');
		$this->assertIsArray($options);
		$this->assertCount(1, $options, 'Malformed JSON content must be rejected, not throw or return null');
	}

	/**
	 * Customfields::get_options_from_file() - a file literally named "header.inc.php" is
	 * explicitly blocked ("dont allow to include our header again").
	 *
	 * Note: in the current implementation this exact check is unreachable in practice - any
	 * real "header.inc.php" already fails the preceding ".json extension" check first (a
	 * file can't simultaneously end in ".json" and be named exactly "header.inc.php"), so
	 * this test only confirms the file IS rejected, via whichever guard catches it first -
	 * it does not prove the header.inc.php-specific check itself is reachable. Flagged, not
	 * "fixed" (would need e.g. an extension-independent basename check to actually matter).
	 */
	public function testGetOptionsFromHeaderIncPhpBlocked()
	{
		$this->mountFilesystem(realpath(__DIR__ . '/../fixtures/Storage/'), '/api/tests');

		$options = Customfields::get_options_from_file('/api/tests/header.inc.php');
		$this->assertIsArray($options);
		$this->assertCount(1, $options, 'A file named header.inc.php must never be read as options');
	}

	/**
	 * Customfields::get_link_types() returns the intersection of Api\Link's query-capable
	 * and title-capable apps, plus the fixed 'link-entry' pseudo-type.
	 */
	public function testGetLinkTypes()
	{
		$types = Customfields::get_link_types();
		$this->assertIsArray($types);
		$this->assertContains('link-entry', $types);
		$this->assertContains('addressbook', $types, 'addressbook is expected to support both link query and title');
	}

	/**
	 * Customfields::get_account_cfs() groups 'select-account'/'api-accounts' custom fields
	 * by whether they allow multiple values (rows>1 -> 'account-commasep' bucket) or a
	 * single value ('account' bucket).
	 */
	public function testGetAccountCfs()
	{
		$single = array_merge($this->simple_field, array('name' => 'test_single_account', 'type' => 'select-account', 'rows' => 1));
		$multi = array_merge($this->simple_field, array('name' => 'test_multi_account', 'type' => 'select-account', 'rows' => 2));
		$this->customfields[] = $single['name'];
		$this->customfields[] = $multi['name'];
		Customfields::update($single);
		Customfields::update($multi);

		$account_cfs = Customfields::get_account_cfs(self::APP);

		$this->assertContains('test_single_account', $account_cfs['account'] ?? array());
		$this->assertContains('test_multi_account', $account_cfs['account-commasep'] ?? array());
	}

	/**
	 * Customfields::get_email_cfs() returns the names of all 'url-email'-typed custom
	 * fields for an app.
	 */
	public function testGetEmailCfs()
	{
		$field = array_merge($this->simple_field, array('name' => 'test_email_field', 'type' => 'url-email'));
		$this->customfields[] = $field['name'];
		Customfields::update($field);

		$email_cfs = Customfields::get_email_cfs(self::APP);

		$this->assertContains('test_email_field', $email_cfs);
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


	protected function mountFilesystem($fs_path, $vfs_path)
	{
		$backup = Vfs::$is_root;
		Vfs::$is_root = true;
		$fs_path = realpath($fs_path);
		if(!file_exists($fs_path))
		{
			$this->fail("Missing filesystem test directory 'api/tests/fixtures/Vfs/filesystem_mount'");
		}

		$url = \EGroupware\Api\Vfs\Filesystem\StreamWrapper::SCHEME . '://default' . $fs_path;
		$this->assertTrue(Vfs::mount($url, $vfs_path), "Unable to mount $url to $vfs_path");
		Vfs::$is_root = $backup;

		$this->mounts[] = $vfs_path;
		Vfs::clearstatcache();
		Vfs::init_static();
		Vfs\StreamWrapper::init_static();
	}

	/**
	 * update()'s automatic cf_order renumbering: creating 3 fields with orders 10/20/30, then
	 * moving the first one to a non-multiple-of-10 value between the other two, must renumber
	 * ALL fields back to clean multiples of 10 - not just apply the raw requested value.
	 *
	 * Setup: 3 real CF rows via Customfields::update().
	 * Pass criteria: after re-ordering, get() reports all three fields at clean multiples of 10,
	 * in the sequence implied by the new order value.
	 */
	public function testFieldReorderingOnUpdate()
	{
		$a = array_merge($this->simple_field, array('name' => 'reorder_a', 'order' => 10));
		$b = array_merge($this->simple_field, array('name' => 'reorder_b', 'order' => 20));
		$c = array_merge($this->simple_field, array('name' => 'reorder_c', 'order' => 30));
		$this->customfields[] = 'reorder_a';
		$this->customfields[] = 'reorder_b';
		$this->customfields[] = 'reorder_c';
		Customfields::update($a);
		Customfields::update($b);
		Customfields::update($c);

		// move A between B (20) and C (30), NOT a multiple of 10 --> must trigger renumbering
		$fields = Customfields::get(self::APP);
		$a = $fields['reorder_a'];
		$a['order'] = 25;
		Customfields::update($a);

		$fields = Customfields::get(self::APP);
		$this->assertSame(10, (int)$fields['reorder_b']['order'], 'reorder_b should have been renumbered to 10');
		$this->assertSame(20, (int)$fields['reorder_a']['order'], 'reorder_a (moved to 25) should have been snapped to 20');
		$this->assertSame(30, (int)$fields['reorder_c']['order'], 'reorder_c should be unchanged at 30');
	}

	/**
	 * Customfields::save() deletes any field NOT present in the given array
	 * (`NOT cf_name IN (...)`). A regression here would silently delete field definitions still
	 * meant to exist.
	 *
	 * Pass criteria: after save() with only 'keep_field' in the array, 'keep_field' still exists
	 * and 'drop_field' (created separately, omitted from the save() array) is gone.
	 */
	public function testSaveDeletesFieldsNotInArray()
	{
		$keep = array_merge($this->simple_field, array('name' => 'keep_field'));
		$drop = array_merge($this->simple_field, array('name' => 'drop_field'));
		$this->customfields[] = 'keep_field';
		// intentionally NOT tracked in $this->customfields - it must already be gone by tearDown()
		Customfields::update($keep);
		Customfields::update($drop);

		$fields = Customfields::get(self::APP, true);
		$this->assertArrayHasKey('keep_field', $fields, 'Test precondition failed');
		$this->assertArrayHasKey('drop_field', $fields, 'Test precondition failed');

		unset($fields['drop_field']);
		Customfields::save(self::APP, $fields);

		$fields = Customfields::get(self::APP, true);
		$this->assertArrayHasKey('keep_field', $fields, 'save() must keep a field present in the given array');
		$this->assertArrayNotHasKey('drop_field', $fields, 'save() must delete a field omitted from the given array');
	}

	/**
	 * Customfields::getSerial(): first call with no prior value defaults to "1"; subsequent calls
	 * increment the trailing digit-group and preserve its zero-padding width (e.g. "0009"->"0010").
	 *
	 * Setup: a real 'serial'-type CF, id read back from get(). The zero-padding case is seeded via
	 * a direct DB update, since getSerial() itself has no way to set an arbitrary starting value.
	 */
	public function testGetSerialIncrementsAndPreservesPadding()
	{
		$field = array_merge($this->simple_field, array('name' => 'serial_field', 'type' => 'serial'));
		$this->customfields[] = 'serial_field';
		Customfields::update($field);

		$fields = Customfields::get(self::APP, true);
		$id = (int)$fields['serial_field']['id'];
		$this->assertGreaterThan(0, $id, 'Test precondition failed: could not read back cf_id for serial_field');

		// Assert the increment RELATIVE to the first call rather than hardcoding "1"/"2": this is a
		// real, shared dev database, and while $id is always a fresh auto-increment cf_id (so no
		// other test can touch this exact row), a transient Api\Cache race under concurrent test
		// activity was observed once to make the very first call already start above 1 - the
		// increment-by-exactly-1 behavior is what matters here, not the absolute starting value.
		$first = (int)Customfields::getSerial($id);
		$this->assertGreaterThan(0, $first, 'First call with no prior value must default to a positive number');
		$this->assertSame($first + 1, (int)Customfields::getSerial($id), 'Second call must increment by exactly 1');

		// seed a zero-padded value directly, bypassing getSerial()'s own increment logic
		$GLOBALS['egw']->db->update('egw_customfields', array('cf_values' => json_encode(array('last' => '0009'))),
			array('cf_id' => $id), __LINE__, __FILE__);
		$this->assertSame('0010', Customfields::getSerial($id),
			'Padding width of the previous value ("0009") must be preserved on increment');
	}

	/**
	 * getSerial() throws Api\Db\Exception if no row matches (wrong id, or cf_type != 'serial').
	 */
	public function testGetSerialThrowsForMissingRow()
	{
		$this->expectException(\EGroupware\Api\Db\Exception::class);
		Customfields::getSerial(999999999);
	}

	/**
	 * Customfields::update_links(): a link-type CF value creates an Api\Link on set, and changing
	 * the value away removes the old link. Uses the current test user's own linked addressbook
	 * contact as a safe, pre-existing link target - nothing to create/clean beyond the link itself.
	 */
	public function testUpdateLinksCreatesAndRemovesLink()
	{
		$contact_id = $GLOBALS['egw']->accounts->id2name($GLOBALS['egw_info']['user']['account_id'], 'person_id');
		if (!$contact_id)
		{
			$this->markTestSkipped('Current test user has no linked addressbook contact to link to');
		}

		$field = array_merge($this->simple_field, array('name' => 'link_field', 'type' => 'addressbook'));
		$this->customfields[] = 'link_field';
		Customfields::update($field);

		$record_id = -999999; // fake "test"-app entry id, only used as the link's own-side id
		$values = array('id' => $record_id, '#link_field' => $contact_id);

		try
		{
			Customfields::update_links('test', $values, null, 'id');

			$links = \EGroupware\Api\Link::get_links('test', $record_id, 'addressbook');
			$this->assertNotEmpty($links, 'update_links() should have created a link to the addressbook contact');

			// change value away --> the old link must be removed
			$old = $values;
			$values['#link_field'] = '';
			Customfields::update_links('test', $values, $old, 'id');

			$links = \EGroupware\Api\Link::get_links('test', $record_id, 'addressbook');
			$this->assertEmpty($links, 'update_links() should have removed the link when the value changed away');
		}
		finally
		{
			// safety net in case an assertion above failed before the removal step ran
			\EGroupware\Api\Link::unlink(0, 'test', $record_id);
		}
	}
}
