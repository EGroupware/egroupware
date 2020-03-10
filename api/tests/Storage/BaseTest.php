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

		$GLOBALS['egw'] = new stdClass();
		$GLOBALS['egw']->db = self::$db = new Api\Db($GLOBALS['egw_domain'][$GLOBALS['EGW_DOMAIN']]);
		self::$db->connect();
	}

	protected function setUp() : void
	{
		$this->storage = new Api\Storage\Base('test', 'egw_test', self::$db);
	}

	protected function assertPreConditions() : void
	{
		$tables = self::$db->table_names(true);
		$this->assertContains('egw_test', $tables, 'Could not find DB table "egw_test", make sure test app is installed');
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
		$this->assertEqualsWithDelta(new DateTime('now'), new DateTime($row['t_modified']), 1);

		return $this->storage->data;
	}

	/**
	 *
	 * @param array $data
	 * @depends testSaveInternalState
	 */
	public function testReadFromDb(array $data)
	{
		$read = $this->storage->read($data['t_id']);
		// not set above, just be DB or read method
		unset($read['t_modified'], $read['user_timezone_read']);
		// set as ts, but read as is in DB
		$data['t_end'] = Api\DateTime::to($data['t_end'], Api\DateTime::DATABASE);
		$this->assertEquals($data, $read);
	}

	/*public function testSaveGivenState()
	{

	}*/
}
