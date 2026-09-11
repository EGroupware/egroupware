<?php
/**
 * EGroupware test app to test eg. Api\Storage\Base
 *
 * @package api
 * @subpackage tests
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2017RalfBecker@outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;
use PHPUnit\Framework\TestCase;

class BaseTest extends TestCase
{
	/**
	 *
	 * @var Api\Db
	 */
	private static $db;

	/**
	 *
	 * @var Api\Storage\Base
	 */
	private $storage;

	public static function setUpBeforeClass() : void
	{
		if (ini_get('session.save_handler') == 'files' && !is_writable(ini_get('session.save_path')) && is_dir('/tmp') && is_writable('/tmp'))
		{
			ini_set('session.save_path','/tmp');	// regular users may have no rights to apache's session dir
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

		// $GLOBALS['EGW_DOMAIN'] is literally "default" (doc/phpunit.xml), which is never a real
		// key in $GLOBALS['egw_domain'] - resolve it the same way LoggedInTest::load_egw() /
		// Api/src/loader.php do, via Session::search_instance()'s fallback-to-first-domain logic,
		// instead of indexing $GLOBALS['egw_domain'] directly (which throws "No DB host set!" on
		// any install where the domain isn't literally named "default").
		$default_domain = null;
		$domain = Api\Session::search_instance(null, $GLOBALS['EGW_DOMAIN'], $default_domain,
			array($_SERVER['HTTP_HOST'] ?? '', $_SERVER['SERVER_NAME'] ?? ''), $GLOBALS['egw_domain']);

		$GLOBALS['egw'] = new stdClass();
		$GLOBALS['egw']->db = self::$db = new Api\Db($GLOBALS['egw_domain'][$domain]);
		self::$db->connect();
	}

	protected function setUp() : void
	{
		$this->storage = new Api\Storage\Base('test', 'egw_test', self::$db);
	}

	protected function assertPreConditions() : void
	{
		$tables = self::$db->table_names(true);
		if(!in_array('egw_test', $tables))
		{
			$this->markTestSkipped('No test app installed');
		}
	}

	public function testSaveInternalState()
	{
		$this->storage->data = $data = array(
			't_title' => 'Test',
				't_desc' => "First Line\nSecond Line\n\n...",
				't_start' => $start = Api\DateTime::to('now', 'ts'),
				't_end' => $end = Api\DateTime::to('now', 'ts'),
				't_modifier' => 123,
		);
		$this->storage->Save();
		$this->assertGreaterThan(0, $this->storage->data['t_id']);

		$row = self::$db->select('egw_test', '*', array('t_id' => $this->storage->data['t_id']),
				__LINE__, __FILE__, false, '', 'test')->fetch();
		$this->assertIsArray($row);
		$this->assertEquals($data['t_title'], $row['t_title']);
		$this->assertEquals($data['t_desc'], $row['t_desc']);
		$this->assertEquals($data['t_modifier'], $row['t_modifier']);
		$this->assertEquals(Api\DateTime::user2server($start), $row['t_start']);
		$this->assertEquals(Api\DateTime::user2server($end, Api\DateTime::DATABASE), $row['t_end']);
		// Compare against the DB server's OWN clock (via a fresh NOW() query), not PHP's - the DB
		// connection's session timezone is whatever the DB server defaults to (Db::setTimeZone()
		// is unused dead code, called nowhere), which can differ from the PHP process's timezone
		// by whole hours on a dev box where the db/app containers have different host timezones.
		// t_modified is a raw DB default (current_timestamp), so it's only meaningfully comparable
		// against another value from that same DB connection/clock.
		$db_now = new DateTime(self::$db->query('SELECT NOW() AS now', __LINE__, __FILE__)->fetch()['now']);
		$this->assertEqualsWithDelta($db_now, new DateTime($row['t_modified']), 5);

		return $this->storage->data;
	}

	/**
	 *
	 * @param array $data
	 */
	#[\PHPUnit\Framework\Attributes\Depends('testSaveInternalState')]
	public function testReadFromDb(array $data)
	{
		$read = $this->storage->read($data['t_id']);
		// not set above, just be DB or read method
		unset($read['t_modified'], $read['user_timezone_read']);
		// set as ts, but read as is in DB
		$data['t_end'] = Api\DateTime::to($data['t_end'], Api\DateTime::DATABASE);
		// not set in testSaveInternalState()'s $data - insert() must apply the column defaults
		// (t_uniq/t_json are nullable with no default => null, t_active defaults to true)
		$data['t_uniq'] = null;
		$data['t_active'] = true;
		$data['t_json'] = null;
		$this->assertEquals($data, $read);
	}

	/*public function testSaveGivenState()
	{

	}*/
}
