<?php
/**
 * EGroupware - Setup - db-schema-processor - unit tests
 *
 * Written by Ralf Becker <RalfBecker@outdoor-training.de>
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage db
 * @version $Id$
 */

namespace Egroupware\Api;

use EGroupware\Api\Db;
use EGroupware\Api\Db\Schema;

// test base providing Egw environment
require_once realpath(__DIR__.'/../LoggedInTest.php');

// For security reasons we exit by default if called via the webserver
if (php_sapi_name() !== 'cli')
{
	die ('Access denied !!!');
}

/**
 * Testing the Schema processor
 *
 */
class SchemaTest extends LoggedInTest {

	protected static $adodb;
	protected static $db;
	protected static $schema_proc;
	protected static $test_app = 'login';

	// define a test-table to create
	protected static $test_tables = array(
		'schema_proc_test' => array(
			'fd' => array(
				'test_auto' => array('type' => 'auto'),
				'test_int4' => array('type' => 'int','precision' => '4'),
				'test_varchar' => array('type' => 'varchar','precision' => '128'),
				'test_char' => array('type' => 'char','precision' => '10'),
				'test_timestamp' => array('type' => 'timestamp','default'=>'current_timestamp'),
				'test_text' => array('type' => 'text'),
				'test_blob' => array('type' => 'blob'),
			),
			'pk' => array('test_auto'),
			'fk' => array(),
			'ix' => array(array('test_char','test_varchar'),'test_varchar',array('test_text','options'=>array('mysql'=>'FULLTEXT','sapdb'=>false,'maxdb'=>false,'pgsql'=>false,'mssql'=>false))),
			'uc' => array('test_char')
		),
	);

	/**
	 * Get a database connection
	 */
	public static function setUpBeforeClass() : void
	{
		parent::setUpBeforeClass();

		// now we should have a valid db-connection
		self::$adodb = $GLOBALS['egw']->db->Link_ID;
		self::$db = $GLOBALS['egw']->db;

		// Show lots of debug
		//self::$db->query_log = 'php://stdout';

		Db::set_table_definitions(self::$test_app, 'schema_proc_test', self::$test_tables['schema_proc_test']);

		// dropping test-tables, if they are there from a previous failed run
		self::$schema_proc = new Schema(self::$db->Type);
		foreach(self::$adodb->MetaTables() as $table)
		{
			$table = strtolower($table);
			if (strstr($table,'schema_proc'))
			{
				$aSql = self::$schema_proc->dict->DropTableSQL($table);
				self::$schema_proc->ExecuteSqlArray($aSql,1,"DropTableSQL('%1') = %2",$table,$aSql);
			}
		}
	}

	/**
	 * Try to create test tables, check to see if it worked
	 */
	public function testCreateTable()
	{
		foreach(self::$test_tables as $name => $definition)
		{
			self::$schema_proc->CreateTable($name,$definition);

			$columns = self::$adodb->MetaColumns($name);
			$this->assertNotFalse($columns);
			$this->assertGreaterThan(0, count($columns));

			// check if all columns are there
			foreach($definition['fd'] as $column => $data)
			{
				$this->check_column($column,$columns);
			}

			// check if all indexes are there
			$indexes = self::$adodb->MetaIndexes($name,true);
			$this->assertNotFalse($indexes);
			if ($indexes !== False)
			{
				foreach(array('ix','uc') as $kind)
				{
					foreach($definition[$kind] as $key => $idx)
					{
						$this->check_index($idx,$kind=='uc',$indexes);
					}
				}
				if (count($definition['pk'])) $this->check_index($definition['pk'],True,$indexes);
			}
		}
	}

	/**
	 * Try to insert some content into the created table(s)
	 *
	 * @depends testCreateTable
	 */
	public function testInsertContent()
	{
		self::$adodb->Execute("INSERT INTO schema_proc_test (test_int4,test_varchar,test_char) VALUES (1,'Hallo Ralf','0123456789')");

		self::$db->insert('schema_proc_test',array(
				'test_int4'		=> 2,
				'test_varchar'	=> 'Hallo wer noch?',
				'test_char'		=> '9876543210',
				'test_text'	=> 'This is a test-value for the text-column, insert-value',
				'test_blob'	=> 'This is a test-value for the blob-column, insert-value',
			), False, __LINE__, __FILE__, self::$test_app);

		$this->check_content(
			self::$adodb->GetAll("SELECT * FROM schema_proc_test"), array(
				array(
					'test_auto' => 1, 'test_int4' => 1, 'test_varchar' => 'Hallo Ralf','test_char' => '0123456789',
				),
				array(
					'test_auto' => 2, 'test_int4' => 2, 'test_varchar' => 'Hallo wer noch?','test_char' => '9876543210',
					'test_text'	=> 'This is a test-value for the text-column, insert-value',
					'test_blob'	=> 'This is a test-value for the blob-column, insert-value',
				),
			)
		);
	}

	/**
	 * Try to update existing content
	 *
	 * @depends testInsertContent
	 */
	public function testUpdateContent()
	{
		// updating blob's and other columns
		self::$db->update('schema_proc_test', array(
				'test_int4'	=> 99,
				'test_char' => 'abcdefghij',
				'test_text'	=> 'This is a test-value for the text-column',
				'test_blob'	=> 'This is a test-value for the blob-column',
			), array('test_auto'=>1), __LINE__, __FILE__, self::$test_app);

		// updating only the blob's
		self::$db->update('schema_proc_test',array(
				'test_text'	=> 'This is a test-value for the text-column, 2.row',
				'test_blob'	=> 'This is a test-value for the blob-column, 2.row',
			), array('test_auto'=>2), __LINE__, __FILE__, self::$test_app);

		// db::update uses UpdateBlob only for MaxDB at the moment, it works for MySql too, but fails for postgres with text / CLOB's
		// $adodb->UpdateBlob('schema_proc_test','test_text','This is a test-value for the text-column, 2.row','test_auto=2','CLOB');
		// $adodb->UpdateBlob('schema_proc_test','test_blob','This is a test-value for the blob-column, 2.row','test_auto=2','BLOB');

		$this->check_content(self::$adodb->GetAll("SELECT * FROM schema_proc_test"),array(
			array(
				'test_auto' => 1, 'test_int4' => 99, 'test_varchar' => 'Hallo Ralf','test_char' => 'abcdefghij',
				'test_text' => 'This is a test-value for the text-column',
				'test_blob'=>'This is a test-value for the blob-column',
			),
			array(
				'test_auto' => 2, 'test_int4' => 2, 'test_varchar' => 'Hallo wer noch?','test_char' => '9876543210',
				'test_text' => 'This is a test-value for the text-column, 2.row',
				'test_blob'=>'This is a test-value for the blob-column, 2.row',
			),
		));
	}

	/**
	 * Drop the test_blob column
	 *
	 * @depends testCreateTable
	 */
	public function testDropColumn()
	{
		$new_table_def = $test_tables['schema_proc_test'];
		unset($new_table_def['fd']['test_blob']);
		self::$schema_proc->DropColumn('schema_proc_test',$new_table_def,'test_blob');
		$this->check_column('test_blob',self::$adodb->MetaColumns('schema_proc_test'),False);
	}

	/**
	 * Alter the test_char column
	 *
	 * @depends testCreateTable
	 */
	public function testAlterColumn()
	{
		self::$schema_proc->AlterColumn('schema_proc_test','test_char',array('type' => 'varchar','precision' => 32));
		$this->check_column_type('test_char','varchar',32,self::$adodb->MetaColumns('schema_proc_test'));
	}

	/**
	 * Add a column
	 *
	 * @depends testCreateTable
	 */
	public function testAddColumn()
	{
		self::$schema_proc->AddColumn('schema_proc_test','test_bool',array('type' => 'bool'));
		$this->check_column('test_bool',self::$adodb->MetaColumns('schema_proc_test'));
	}

	/**
	 * Rename a column
	 *
	 * @depends testCreateTable
	 */
	public function testRenameColumn()
	{
		self::$schema_proc->RenameColumn('schema_proc_test','test_timestamp','test_time');
		$this->check_column('test_timestamp',self::$adodb->MetaColumns('schema_proc_test'),false);
		$this->check_column('test_time',self::$adodb->MetaColumns('schema_proc_test'));
	}

	/**
	 * Rename a table
	 *
	 * @depends testCreateTable
	 */
	public function testRenameTable()
	{
		self::$schema_proc->RenameTable('schema_proc_test','schema_proc_renamed');
		$tables = self::$adodb->MetaTables();
		$this->check_table('schema_proc_test',$tables,False);
		$this->check_table('schema_proc_renamed',$tables);
	}

	/**
	 * @depends testRenameTable
	 */
	public function testRenameColumnWithIndex()
	{
		self::$schema_proc->RenameColumn('schema_proc_renamed','test_varchar','test_varchar_renamed');
		$columns = self::$adodb->MetaColumns('schema_proc_renamed');
		$this->check_column('test_varchar',$columns,False);
		$this->check_column('test_varchar_renamed',$columns);
		$indexes = self::$adodb->MetaIndexes('schema_proc_renamed');
		if ($indexes !== False)
		{
			$this->check_index('test_varchar',False,$indexes,False);
			$this->check_index('test_varchar_renamed',False,$indexes);
		}
		else
		{
			$this->markTestIncomplete();
		}
	}

	/**
	 * @depends testRenameColumnWithIndex
	 */
	public function testDropIndex()
	{
		self::$schema_proc->DropIndex('schema_proc_renamed',array('test_char','test_varchar_renamed'));
		$indexes = self::$adodb->MetaIndexes('schema_proc_renamed');
		if ($indexes !== False)
		{
			$this->check_index(array('test_char','test_varchar_renamed'),False,$indexes,False);
		}
		else
		{
			$this->markTestIncomplete();
		}
	}

	/**
	 * @depends testDropIndex
	 */
	public function testInsertMoreContent()
	{
		self::$db->query("INSERT INTO schema_proc_renamed (test_int4,test_varchar_renamed,test_char) VALUES (10,'Hallo Hallo Hallo ...','12345678901234567890123456789012')");
		$this->check_content(self::$adodb->GetAll("SELECT * FROM schema_proc_renamed"),array(
			array('test_auto' => 1, 'test_int4' => 99, 'test_varchar_renamed' => 'Hallo Ralf','test_char' => 'abcdefghij'),
			array('test_auto' => 2, 'test_int4' => 2, 'test_varchar_renamed' => 'Hallo wer noch?','test_char' => '9876543210'),
			array('test_auto' => 3, 'test_int4' => 10, 'test_varchar_renamed' => 'Hallo Hallo Hallo ...','test_char' => '12345678901234567890123456789012'),
		));
	}

	/**
	 * @depends testInsertMoreContent
	 */
	public function testDropTable()
	{
		foreach(self::$adodb->MetaTables() as $table)
		{
			$table = strtolower($table);
			if (strstr($table,'schema_proc'))
			{
				$aSql = self::$schema_proc->dict->DropTableSQL($table);
				self::$schema_proc->ExecuteSqlArray($aSql,1,"DropTableSQL('%1') = %2",$table,$aSql);
			}
		}
	}


	/**
	 * Checks if table $table exists or not
	 *
	 * @param string $table table-name
	 * @param array $tables array of table-names from call to MetaTables()
	 * @param boolean $existence =true should we check for existence or non-existence, default existence
	 */
	protected function check_table($table,$tables,$existence=True)
	{
		$exist = in_array($table,$tables) || in_array(strtoupper($table),$tables);

		$this->assertEquals($existence, $exist, "Checking for $table");
	}

	/**
	 * Checks if $column exists or not
	 *
	 * @param string $column column-name
	 * @param array $columns array of adodb field objects from MetaColumns($table)
	 * @param boolean $existence =true should we check for existence or non-existence, default existence
	 */
	protected function check_column($column,$columns,$existence=True)
	{
		$exist = isset($columns[$column]) || isset($columns[strtoupper($column)]);

		$this->assertEquals($existence, $exist, "Checking for $column");
	}

	/**
	 * Checks the type of a column
	 *
	 * @param string $column column-name
	 * @param string $type column-type as the DB uses it, no eGW type !!!
	 * @param int $precision precision
	 * @param array $columns array of adodb field objects from MetaColumns($table)
	 */
	protected function check_column_type($column,$type,$precision,$columns)
	{
		static $alternate_types = array(
			'varchar'	=> array('C'),
			'int'		=> array('I'),
		);

		$data = isset($columns[$column]) ? $columns[$column] : $columns[strtoupper($column)];
		$this->assertInstanceOf('ADOFieldObject', $data, "Column '$column' does not exist.");
		$data->type = strtolower($data->type);

		$this->assertFalse($data->type != $type && !in_array($data->type,$alternate_types[$type]),
			"Column '$column' is NOT of type '$type', but '$data->type'"
		);

		if ($precision)
		{
			$this->assertEquals($precision, $data->max_length,
				"Precision of column '$column' is NOT $precision, but $data->precision"
			);
		}
	}

	/**
	 * Checks if $idx exists or not
	 *
	 * @param array $columns array of strings with column-names of that index
	 * @param boolean $unique unique index or not
	 * @param array $indexes array of index-describtions from call to MetaIndexes($table)
	 * @param boolean $_existence =true should we check for existence or none-existence, default existence
	 */
	protected function check_index($columns,$unique,$indexes,$_existence=True)
	{
		if (!is_array($columns)) $columns = array($columns);
		$existence = $_existence && $columns['options'][$GLOBALS['db']->Type] !== False;
		unset($columns['options']);

		$exist = False;
		$idx_data = array();
		foreach($indexes as $idx_data)
		{
			if (implode(':',$columns) == strtolower(implode(':',$idx_data['columns'])))
			{
				$exist = true;
				break;
			}
		}
		$this->assertEquals($existence, $exist,
				"Index (".implode(', ',$columns).") is ".($existence ? 'missing' : 'still there')
		);

		if ($existence)
		{
			$this->assertEquals($unique, !!$idx_data['unique'],
				"Index (".implode(', ',$columns).") is ".($unique ? 'NOT ' : '')."unique"
			);
		}
	}

	/**
	 * Checks the content written to the table
	 *
	 * @param array $is content read from the database via GetAll()
	 * @param array $should content against which we test
	 */
	protected function check_content($is,$should)
	{
		foreach($should as $key => $val)
		{
			if (!isset($is[$key]) && isset($is[strtoupper($key)]))
			{
				$key = strtoupper($key);
			}
			if (!is_array($val))
			{
				$this->assertEquals($val, $is[$key], 'Content read back from table is not as expected');
			}
			if (is_array($val) && !$this->check_content($is[$key],$val,True) || !is_array($val) && $is[$key] != $val)
			{
				$this->fail('Content read back from table is not as expected');
				return False;
			}
		}
		return True;
	}
}