<?php
/**
 * EGroupware Api: tests for Api\Storage\Json / Api\Storage\JsonCF / Api\Storage\JsonTrait
 *
 * Part of the Api\Storage test-coverage project, Phase 5 (doc/ai/projects/storage-test-coverage.md).
 * Json/JsonCF/JsonTrait are genuinely used by the invoices app (present on disk, but .gitignore'd/
 * untracked in this checkout - not a hypothetical case), despite an earlier incorrect "zero usage"
 * finding in this same project's mapping phase.
 *
 * @package api
 * @subpackage tests
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;
use PHPUnit\Framework\TestCase;

class JsonTest extends TestCase
{
	/**
	 * @var Api\Db
	 */
	private static $db;

	/**
	 * @var Api\Storage\Json
	 */
	private $json;

	/**
	 * ids of rows created by a test, cleaned up in tearDown()
	 *
	 * @var int[]
	 */
	private $created = [];

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

		// see BaseTest.php for why this indirection is needed instead of a direct
		// $GLOBALS['egw_domain'][$GLOBALS['EGW_DOMAIN']] lookup
		$default_domain = null;
		$domain = Api\Session::search_instance(null, $GLOBALS['EGW_DOMAIN'], $default_domain,
			array($_SERVER['HTTP_HOST'] ?? '', $_SERVER['SERVER_NAME'] ?? ''), $GLOBALS['egw_domain']);

		$GLOBALS['egw'] = new stdClass();
		$GLOBALS['egw']->db = self::$db = new Api\Db($GLOBALS['egw_domain'][$domain]);
		self::$db->connect();
	}

	protected function setUp() : void
	{
		$this->json = new Api\Storage\Json('test', 'egw_test', 't_json', self::$db);
	}

	protected function assertPreConditions() : void
	{
		$tables = self::$db->table_names(true);
		if (!in_array('egw_test', $tables))
		{
			$this->markTestSkipped('No test app installed');
		}
		if (!in_array('t_json', array_keys(self::$db->get_table_definitions('test', 'egw_test')['fd'])))
		{
			$this->markTestSkipped('egw_test.t_json column not (yet) installed');
		}
	}

	protected function tearDown() : void
	{
		foreach ($this->created as $id)
		{
			self::$db->delete('egw_test', ['t_id' => $id], __LINE__, __FILE__, 'test');
		}
		$this->created = [];
	}

	// ---- pure data2db()/db2data() logic, no DB writes (called directly with an explicit $data array) ----

	/**
	 * Behaviour: data2db() moves every key that is NOT a real db-column (and not an int key, not
	 * USER_TIMEZONE_READ) into a single json-encoded blob under $json_column, leaving real columns
	 * untouched as separate array entries.
	 * Pass criteria: the schema column stays a plain value, the extra keys end up json_encode()d
	 * under 't_json', and are gone as top-level keys.
	 */
	public function testDataToDbEncodesExtraKeysIntoJsonBlob()
	{
		$result = $this->json->data2db([
			't_title' => 'JsonTest-title',
			'extra_foo' => 'bar',
			'extra_num' => 42,
		]);

		$this->assertSame('JsonTest-title', $result['t_title'], 'real schema column must stay untouched');
		$this->assertArrayNotHasKey('extra_foo', $result, 'extra key must be moved into the json blob, not stay top-level');
		$this->assertArrayHasKey('t_json', $result);
		$decoded = json_decode($result['t_json'], true);
		$this->assertSame(['extra_foo' => 'bar', 'extra_num' => 42], $decoded);
	}

	/**
	 * Behaviour: null values are excluded from the json blob (isset() check in the array_filter).
	 */
	public function testDataToDbExcludesNullValues()
	{
		$result = $this->json->data2db([
			't_title' => 'JsonTest-title',
			'extra_null' => null,
			'extra_kept' => 'value',
		]);

		$decoded = json_decode($result['t_json'] ?? '{}', true);
		$this->assertArrayNotHasKey('extra_null', $decoded, 'null values must not appear in the json blob');
		$this->assertSame('value', $decoded['extra_kept']);
	}

	/**
	 * Behaviour: empty-string values are excluded too, by default (empty_on_write='NULL' is
	 * truthy, so the (string)$value!=='' branch of the filter applies).
	 */
	public function testDataToDbExcludesEmptyStringsByDefault()
	{
		$this->assertNotEmpty($this->json->empty_on_write, 'precondition: default empty_on_write must be truthy for this test to be meaningful');

		$result = $this->json->data2db([
			't_title' => 'JsonTest-title',
			'extra_empty' => '',
			'extra_kept' => 'value',
		]);

		$decoded = json_decode($result['t_json'] ?? '{}', true);
		$this->assertArrayNotHasKey('extra_empty', $decoded);
		$this->assertSame('value', $decoded['extra_kept']);
	}

	/**
	 * Behaviour: integer-keyed array entries (eg. raw SQL fragments used elsewhere in Storage\Base)
	 * are excluded from the json blob via the !is_int($key) check.
	 */
	public function testDataToDbExcludesIntegerKeys()
	{
		$result = $this->json->data2db([
			't_title' => 'JsonTest-title',
			0 => "t_desc=t_desc+1",
			'extra_kept' => 'value',
		]);

		$decoded = json_decode($result['t_json'] ?? '{}', true);
		$this->assertArrayNotHasKey('0', $decoded, 'int-keyed entries must never end up in the json blob');
		$this->assertSame('value', $decoded['extra_kept']);
		// the int-keyed raw-sql-fragment entry must survive as-is outside the blob (data2db must not swallow it)
		$this->assertSame("t_desc=t_desc+1", $result[0]);
	}

	/**
	 * Behaviour: Base::USER_TIMEZONE_READ is explicitly excluded from the json blob.
	 */
	public function testDataToDbExcludesUserTimezoneRead()
	{
		$result = $this->json->data2db([
			't_title' => 'JsonTest-title',
			Api\Storage\Base::USER_TIMEZONE_READ => 'Europe/Berlin',
		]);

		$decoded = json_decode($result['t_json'] ?? '{}', true);
		$this->assertArrayNotHasKey(Api\Storage\Base::USER_TIMEZONE_READ, $decoded);
	}

	/**
	 * Behaviour: when $column_preg is set, only extra keys matching it are written to the blob -
	 * everything else is silently dropped from data2db()'s output entirely (not kept top-level
	 * either, since it's neither a real db column nor a matching extra key).
	 */
	public function testDataToDbRespectsColumnPreg()
	{
		$json = new Api\Storage\Json('test', 'egw_test', 't_json', self::$db, '', true, 'object', '/^allowed_/');

		$result = $json->data2db([
			't_title' => 'JsonTest-title',
			'allowed_one' => 'yes',
			'blocked_one' => 'no',
		]);

		$decoded = json_decode($result['t_json'] ?? '{}', true);
		$this->assertArrayHasKey('allowed_one', $decoded);
		$this->assertArrayNotHasKey('blocked_one', $decoded, 'a key not matching column_preg must not reach the json blob');
	}

	/**
	 * Behaviour: db2data() json_decode()s a string blob and merges its keys into $data via +=,
	 * then removes the blob column itself.
	 */
	public function testDb2DataDecodesJsonStringBlobIntoData()
	{
		$result = $this->json->db2data([
			't_title' => 'JsonTest-title',
			't_json' => json_encode(['extra_foo' => 'bar', 'extra_num' => 42]),
		]);

		$this->assertSame('bar', $result['extra_foo']);
		$this->assertSame(42, $result['extra_num']);
		$this->assertArrayNotHasKey('t_json', $result, 'the blob column itself must be removed after decoding');
	}

	/**
	 * Behaviour: db2data() also accepts the blob column already being a plain array (not a JSON
	 * string) and merges it directly, per the elseif is_array() branch.
	 */
	public function testDb2DataAcceptsAlreadyDecodedArrayBlob()
	{
		$result = $this->json->db2data([
			't_title' => 'JsonTest-title',
			't_json' => ['extra_foo' => 'already-an-array'],
		]);

		$this->assertSame('already-an-array', $result['extra_foo']);
		$this->assertArrayNotHasKey('t_json', $result);
	}

	/**
	 * Behaviour: db2data() merges the blob via $data += (array)json_decode(...) - array union
	 * keeps the LEFT-HAND (already-present, ie. real column) value when both sides have the same
	 * key, so a genuine schema column always wins over a same-named key that also ended up in the
	 * blob (which shouldn't normally happen, but the precedence is worth locking down).
	 */
	public function testDb2DataExistingKeyWinsOverBlobKey()
	{
		$result = $this->json->db2data([
			't_title' => 'real-column-value',
			't_json' => json_encode(['t_title' => 'blob-value-should-be-ignored']),
		]);

		$this->assertSame('real-column-value', $result['t_title']);
	}

	/**
	 * Behaviour: an empty/null blob column is a no-op - db2data() must not error and must still
	 * remove the (empty) blob column key.
	 */
	public function testDb2DataEmptyBlobIsNoop()
	{
		$result = $this->json->db2data([
			't_title' => 'JsonTest-title',
			't_json' => null,
		]);

		$this->assertSame('JsonTest-title', $result['t_title']);
		$this->assertArrayNotHasKey('t_json', $result);
	}

	// ---- magic accessors ----

	public function testMagicGetSetIssetUnset()
	{
		$this->assertFalse(isset($this->json->extra_foo));
		$this->json->extra_foo = 'bar';
		$this->assertTrue(isset($this->json->extra_foo));
		$this->assertSame('bar', $this->json->extra_foo);
		unset($this->json->extra_foo);
		$this->assertFalse(isset($this->json->extra_foo));
		$this->assertNull($this->json->extra_foo, 'reading an unset property must return null, not error');
	}

	/**
	 * Behaviour: the special property name 'id' is aliased to autoinc_id (t_id for egw_test).
	 */
	public function testMagicIdPropertyAliasesAutoincId()
	{
		$this->json->id = 123;
		$this->assertSame(123, $this->json->t_id);
		$this->assertSame(123, $this->json->id);
		$this->assertTrue(isset($this->json->id));

		unset($this->json->id);
		$this->assertFalse(isset($this->json->t_id));
	}

	/**
	 * Behaviour: __set('data', $value) is special-cased to replace $this->data wholesale instead
	 * of recursing back into __set() (which would otherwise infinite-loop / store it under a
	 * literal 'data' key).
	 */
	public function testMagicSetDataReplacesWholeArray()
	{
		$this->json->data = ['t_title' => 'replaced', 'extra_x' => 1];

		$this->assertSame('replaced', $this->json->t_title);
		$this->assertSame(1, $this->json->extra_x);
		$this->assertSame(['t_title' => 'replaced', 'extra_x' => 1], $this->json->as_array());
	}

	public function testAsArrayReflectsData()
	{
		$this->json->data = ['t_title' => 'x'];
		$this->json->extra_y = 2;

		$this->assertSame(['t_title' => 'x', 'extra_y' => 2], $this->json->as_array());
	}

	// ---- live-DB round trip via the real Json class against egw_test.t_json ----

	/**
	 * Behaviour under test: a real save()/read() round trip through Api\Storage\Json - schema
	 * columns persist as real columns, everything else persists via the t_json JSON blob column
	 * and comes back out correctly on read(), including a nested array value.
	 * Setup: fresh Json instance, set one real schema column + several extra (non-schema) keys,
	 * save(), then read() back via a SEPARATE fresh instance (so nothing is served from in-memory
	 * state) and via a raw SQL select (to confirm the blob column actually contains valid JSON on
	 * disk, not just that read() round-trips correctly).
	 * Pass criteria: read()'s extra keys equal what was set; the raw DB row's t_json column
	 * contains valid JSON with exactly those extra keys (not the schema column).
	 */
	public function testLiveRoundTripSavesAndReadsExtraKeysThroughJsonBlob()
	{
		$this->json->data = [
			't_title' => 'JsonTest-roundtrip',
			'extra_string' => 'hello',
			'extra_number' => 7,
			'extra_nested' => ['a' => 1, 'b' => [2, 3]],
		];
		$this->json->save();
		$id = $this->json->t_id;
		$this->assertGreaterThan(0, $id, 'save() must populate the autoinc id');
		$this->created[] = $id;

		// raw row: confirm the blob column really holds JSON on disk, and the extra keys are NOT
		// separate real columns
		$row = self::$db->select('egw_test', '*', ['t_id' => $id], __LINE__, __FILE__, false, '', 'test')->fetch();
		$this->assertNotEmpty($row['t_json'], 'the json blob column must actually be populated in the db');
		$blob = json_decode($row['t_json'], true);
		$this->assertSame(['extra_string' => 'hello', 'extra_number' => 7, 'extra_nested' => ['a' => 1, 'b' => [2, 3]]], $blob);

		// fresh instance read-back
		$fresh = new Api\Storage\Json('test', 'egw_test', 't_json', self::$db);
		$read = $fresh->read($id);
		$this->assertSame('JsonTest-roundtrip', $read['t_title']);
		$this->assertSame('hello', $read['extra_string']);
		$this->assertSame(7, $read['extra_number']);
		$this->assertSame(['a' => 1, 'b' => [2, 3]], $read['extra_nested']);
		$this->assertArrayNotHasKey('t_json', $read, 'the raw blob column must not leak into read() output');
	}

	// ---- JsonCF: verify the trait composes correctly through the deeper Base->Customfields/
	// Api\Storage class hierarchy, without needing a live extra_table (the "test" app has no
	// customfields fixture table - egw_test_extra doesn't exist, so a real JsonCF construction+
	// save()/read() round trip is deferred; see doc/ai/projects/storage-test-coverage.md) ----

	/**
	 * Behaviour: JsonCF (Base->Customfields/Api\Storage, + JsonTrait) must resolve data2db()/
	 * db2data() to JsonTrait's implementation (not silently shadowed by a same-named method
	 * somewhere in the Api\Storage/Customfields chain), proven by constructing a real JsonCF
	 * instance WITHOUT running its constructor (which would require a live customfields
	 * extra_table this fixture app doesn't have) via reflection, then manually seeding just the
	 * properties data2db()/db2data() actually read.
	 */
	public function testJsonCFComposesJsonTraitCorrectly()
	{
		$ref = new ReflectionClass(Api\Storage\JsonCF::class);
		$jsonCF = $ref->newInstanceWithoutConstructor();

		$prop = function(string $name, $value) use ($ref, $jsonCF)
		{
			$p = $ref->getProperty($name);
			$p->setAccessible(true);
			$p->setValue($jsonCF, $value);
		};
		$prop('json_column', 'extra_json');
		$prop('db_cols', ['real_col' => 'real_col']);
		$prop('empty_on_write', 'NULL');
		$prop('data', []);
		$prop('timestamps', []);

		$result = $jsonCF->data2db(['real_col' => 'kept', 'extra_one' => 'blobbed']);

		$this->assertSame('kept', $result['real_col']);
		$this->assertArrayNotHasKey('extra_one', $result);
		$this->assertSame(['extra_one' => 'blobbed'], json_decode($result['extra_json'], true));

		$decoded = $jsonCF->db2data(['real_col' => 'kept', 'extra_json' => json_encode(['extra_one' => 'blobbed'])]);
		$this->assertSame('blobbed', $decoded['extra_one']);
	}
}
