<?php
/**
 * EGroupware Api: tests for Api\Storage\Base2's magic-property accessors
 *
 * @package api
 * @subpackage tests
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;
use PHPUnit\Framework\TestCase;

/**
 * Base2 is a small __get/__set/as_array() wrapper around Base's $data array - see
 * doc/ai/projects/storage-test-coverage.md. None of these tests write to the DB; construction
 * only needs a live connection to read the egw_test table definition via setup_table().
 */
class Base2Test extends TestCase
{
	/** @var Api\Db */
	private static $db;

	/** @var Api\Storage\Base2 */
	private $storage;

	public static function setUpBeforeClass() : void
	{
		if (ini_get('session.save_handler') == 'files' && !is_writable(ini_get('session.save_path')) && is_dir('/tmp') && is_writable('/tmp'))
		{
			ini_set('session.save_path','/tmp');
		}
		$_REQUEST['domain'] = $GLOBALS['EGW_DOMAIN'];

		$GLOBALS['egw_info'] = array(
			'flags' => array(
				'noheader' => True,
				'nonavbar' => True,
				'currentapp' => 'setup',
				'noapi' => true,
		));
		require(__DIR__.'/../../../header.inc.php');

		// see BaseTest.php for why this resolution is necessary instead of a direct
		// $GLOBALS['egw_domain'][$GLOBALS['EGW_DOMAIN']] index
		$default_domain = null;
		$domain = Api\Session::search_instance(null, $GLOBALS['EGW_DOMAIN'], $default_domain,
			array($_SERVER['HTTP_HOST'] ?? '', $_SERVER['SERVER_NAME'] ?? ''), $GLOBALS['egw_domain']);

		$GLOBALS['egw'] = new stdClass();
		$GLOBALS['egw']->db = self::$db = new Api\Db($GLOBALS['egw_domain'][$domain]);
		self::$db->connect();
	}

	protected function setUp() : void
	{
		$this->storage = new Api\Storage\Base2('test', 'egw_test', self::$db);
	}

	protected function assertPreConditions() : void
	{
		$tables = self::$db->table_names(true);
		if(!in_array('egw_test', $tables))
		{
			$this->markTestSkipped('No test app installed');
		}
	}

	/**
	 * Behavior: the magic property 'id' is an alias for the table's autoinc column (t_id for
	 * egw_test), in both directions.
	 */
	public function testIdAliasesAutoincColumn()
	{
		$this->storage->id = 42;
		$this->assertSame(42, $this->storage->id);
		$this->assertSame(42, $this->storage->t_id, "'id' must write through to the real autoinc column name");

		$this->storage->t_id = 99;
		$this->assertSame(99, $this->storage->id, "'id' must read through from the real autoinc column name");
	}

	/**
	 * Behavior: a real db column is readable/writable by its own name too (not just via 'id').
	 */
	public function testRealColumnReadWrite()
	{
		$this->storage->t_title = 'Base2Test title';
		$this->assertSame('Base2Test title', $this->storage->t_title);
	}

	/**
	 * Behavior: __get()/__set() silently ignore any property name that is neither 'id' nor a
	 * known db_cols/non_db_cols entry - no exception, no warning, just a no-op (get returns
	 * null, set does nothing). This is a real footgun for a typo'd property name in app code -
	 * it fails silently instead of loudly.
	 */
	public function testUnknownPropertyIsSilentlyIgnored()
	{
		$this->storage->this_property_does_not_exist = 'should be dropped';
		$this->assertNull($this->storage->this_property_does_not_exist);
		$this->assertArrayNotHasKey('this_property_does_not_exist', $this->storage->as_array());
	}

	/**
	 * Behavior: as_array() is a live reflection of $this->data - every property set via the
	 * magic setter shows up under its real column name.
	 */
	public function testAsArrayReflectsAllSetProperties()
	{
		$this->storage->id = 7;
		$this->storage->t_title = 'via as_array';
		$this->storage->t_modifier = 123;

		$array = $this->storage->as_array();
		$this->assertSame(7, $array['t_id']);
		$this->assertSame('via as_array', $array['t_title']);
		$this->assertSame(123, $array['t_modifier']);
	}
}
