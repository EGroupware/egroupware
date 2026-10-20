<?php
/**
 * EGroupware Api: Api\Storage\Base's read()/delete() under Db::requireSqlFragmentObjects()
 *
 * @package api
 * @subpackage tests
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;
use PHPUnit\Framework\TestCase;

/**
 * read()/delete()/search()'s "copy direct SQL parts from $keys"-style int-key passthroughs take
 * the raw value from the CALLER's own $keys/$filter/$criteria parameter, unlike eg. parse_search()'s
 * "$db_col IN (...)" branch which Storage\Base builds itself from a validated column name. Those
 * passthrough points must NEVER wrap a plain string in Db\SqlFragment on the caller's behalf -
 * doing so would launder an attacker-controlled value into looking like a deliberately-built
 * fragment, making Db::requireSqlFragmentObjects() a no-op for exactly the bug class it exists to
 * catch (an earlier version of this conversion got this wrong - see
 * doc/ai/projects/sql-fragment-type-safety.md).
 *
 * Bootstrap mirrors SaveDeleteTest.php.
 */
class RequireSqlFragmentObjectsTest extends TestCase
{
	/** @var Api\Db */
	private static $db;

	/** @var int[] ids inserted by the current test, removed in tearDown() */
	private $created_ids = [];

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

		$default_domain = null;
		$domain = Api\Session::search_instance(null, $GLOBALS['EGW_DOMAIN'], $default_domain,
			array($_SERVER['HTTP_HOST'] ?? '', $_SERVER['SERVER_NAME'] ?? ''), $GLOBALS['egw_domain']);

		$GLOBALS['egw'] = new stdClass();
		$GLOBALS['egw']->db = self::$db = new Api\Db($GLOBALS['egw_domain'][$domain]);
		self::$db->connect();
	}

	protected function tearDown() : void
	{
		foreach($this->created_ids as $id)
		{
			self::$db->delete('egw_test', array('t_id' => $id), __LINE__, __FILE__, 'test');
		}
		$this->created_ids = [];
	}

	/**
	 * @return Api\Storage\Base a fresh instance with its OWN strict Db clone, not affecting self::$db
	 */
	private function strictStorage() : Api\Storage\Base
	{
		return new Api\Storage\Base('test', 'egw_test', (clone self::$db)->requireSqlFragmentObjects(true));
	}

	private function insertRow(array $data = []) : array
	{
		$storage = new Api\Storage\Base('test', 'egw_test', self::$db);
		$storage->data = array_merge(array(
			't_title' => 'RequireSqlFragmentObjectsTest-'.uniqid(),
			't_desc' => 'fixture row',
			't_modifier' => 1,
		), $data);
		$errno = $storage->save();
		$this->assertSame(0, $errno, 'save() failed inserting fixture row: db errno='.$errno);
		$this->created_ids[] = $storage->data['t_id'];
		return $storage->data;
	}

	public function testReadWithRawStringAtIntKeyThrowsWhenOptedIn()
	{
		$row = $this->insertRow();

		$this->expectException(Api\Db\Exception\InvalidArgument::class);
		$this->strictStorage()->read(array('t_id' => $row['t_id'], 't_modifier > 0'));
	}

	public function testReadWithSqlFragmentAtIntKeyWorksWhenOptedIn()
	{
		$row = $this->insertRow(array('t_modifier' => 10));

		$found = $this->strictStorage()->read(array('t_id' => $row['t_id'], Api\Db\SqlFragment::raw('t_modifier > 0')));

		$this->assertNotFalse($found, 'a deliberately-wrapped Db\SqlFragment at an integer key must still work when opted in');
	}

	public function testDeleteWithRawStringAtIntKeyThrowsWhenOptedIn()
	{
		$row = $this->insertRow();

		$this->expectException(Api\Db\Exception\InvalidArgument::class);
		$this->strictStorage()->delete(array('t_title' => $row['t_title'], 't_modifier > 0'));
	}

	public function testDeleteWithSqlFragmentAtIntKeyWorksWhenOptedIn()
	{
		$row = $this->insertRow(array('t_modifier' => 10));

		$affected = $this->strictStorage()->delete(array('t_title' => $row['t_title'], Api\Db\SqlFragment::raw('t_modifier > 5')));

		$this->assertEquals(1, $affected);
	}

	/**
	 * The opt-in must only affect the instance it was called on, never the shared self::$db it was
	 * cloned from - a plain string at an integer key must keep working there exactly as before.
	 */
	public function testOptingInOneInstanceDoesNotAffectTheSharedConnection()
	{
		$row = $this->insertRow(array('t_modifier' => 10));

		$this->strictStorage(); // opts in a clone, must not leak back to self::$db

		$storage = new Api\Storage\Base('test', 'egw_test', self::$db);
		$found = $storage->read(array('t_id' => $row['t_id'], 't_modifier > 0'));

		$this->assertNotFalse($found, 'the shared connection must be unaffected by another instance opting in');
	}
}
