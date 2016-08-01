#!/usr/bin/env php -q
<?php
/**
 * EGroupware - Setup - db-schema-processor - unit tests
 *
 * Usage: api/src/Db/test/schema_proc.php [instance]
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage db
 * @version $Id$
 */

use EGroupware\Api\Db;
use EGroupware\Api\Db\Schema;

// For security reasons we exit by default if called via the webserver
if (php_sapi_name() !== 'cli')
{
	die ('Access denied !!!');
}
// the used domain has to be given as first parameter if called on the commandline or as domain= on the url
if (!isset($_REQUEST['domain']))
{
	$_REQUEST['domain'] = $_SERVER['argc'] > 1 ? $_SERVER['argv'][1] : 'default';
}
$path_to_egroupware = realpath(__DIR__.'/../../../..');	//  need to be adapted if this script is moved somewhere else

$egw_info = array(
	'flags' => array(
		'disable_Template_class' => True,
		'login'                  => True,
		'currentapp'             => 'login',
		'noheader'               => True,
	)
);
include ($path_to_egroupware.'/header.inc.php');
$GLOBALS['egw_info']['server']['asyncservice'] = 'off';

// now we should have a valid db-connection
$adodb = $GLOBALS['egw']->ADOdb;
$db = $GLOBALS['egw']->db;
// uncomment to log SQL statesments
//$db->query_log = 'php://stdout';

if (isset($_SERVER['HTTP_HOST'])) echo "<pre>\n";
echo "Serverinfo: Domain $_GET[domain]: $db->Type($db->Database)\n";
print_r($adodb->ServerInfo());

// creating a schema_proc instance
$schema_proc = new Schema($db->Type);
$schema_proc->debug = isset($_GET['debug']) ? $_GET['debug'] : ($_SERVER['argc'] > 2 ? $_SERVER['argv'][2] :  0);
if ($schema_proc->debug > 1) $adodb->debug = true;

// define a test-table to create
$test_tables = array(
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

Db::set_table_definitions($app='login', 'schema_proc_test', $test_tables['schema_proc_test']);

// droping test-tables, if they are there from a previous failed run
foreach($adodb->MetaTables() as $table)
{
	$table = strtolower($table);
	if (strstr($table,'schema_proc'))
	{
		$aSql = $schema_proc->dict->DropTableSQL($table);
		$schema_proc->ExecuteSqlArray($aSql,1,"DropTableSQL('%1') = %2",$table,$aSql);
	}
}

echo "Creating table(s):\n";
foreach($test_tables as $name => $definition)
{
	echo "$name:\n";
	$schema_proc->CreateTable($name,$definition);

	$columns = $adodb->MetaColumns($name);
	if (!$columns || count($columns) <= 0)
	{
		die("\n\n!!! Table '$name' has NOT been created !!!\n\n");
	}
	else
	{
		// check if all columns are there
		foreach($definition['fd'] as $column => $data)
		{
			check_column($column,$columns);
			if (!isset($columns[$column]) && !isset($columns[strtoupper($column)]))
			{
				print_r($columns);
				die ("\n\n!!! Column '$column' is missing !!!\n\n");
			}
		}
		// check if all indexes are there
		$indexes = $adodb->MetaIndexes($name,true);
		if ($indexes !== False)
		{
			foreach(array('ix','uc') as $kind)
			{
				foreach($definition[$kind] as $key => $idx)
				{
					check_index($idx,$kind=='uc',$indexes);
				}
			}
			if (count($definition['pk'])) check_index($definition['pk'],True,$indexes);
		}
	}
	echo $indexes !== False ? "==> SUCCESS\n" : "==> unchecked\n";
}
echo "Inserting some content:\n";
$adodb->Execute("INSERT INTO schema_proc_test (test_int4,test_varchar,test_char) VALUES (1,'Hallo Ralf','0123456789')");
//$adodb->Execute("INSERT INTO schema_proc_test (test_int4,test_varchar,test_char) VALUES (2,'Hallo wer noch?','9876543210')");
$db->insert('schema_proc_test',array(
		'test_int4'		=> 2,
		'test_varchar'	=> 'Hallo wer noch?',
		'test_char'		=> '9876543210',
		'test_text'	=> 'This is a test-value for the text-column, insert-value',
		'test_blob'	=> 'This is a test-value for the blob-column, insert-value',
	), False, __LINE__, __FILE__, $app);
check_content($adodb->GetAll("SELECT * FROM schema_proc_test"), array(
		array(
			'test_auto' => 1, 'test_int4' => 1, 'test_varchar' => 'Hallo Ralf','test_char' => '0123456789',
		),
		array(
			'test_auto' => 2, 'test_int4' => 2, 'test_varchar' => 'Hallo wer noch?','test_char' => '9876543210',
			'test_text'	=> 'This is a test-value for the text-column, insert-value',
			'test_blob'	=> 'This is a test-value for the blob-column, insert-value',
		),
	));
echo "==> SUCCESS\n";

echo "Updating the text- and blob-columns\n";
// updating blob's and other columns
$db->update('schema_proc_test', array(
		'test_int4'	=> 99,
		'test_char' => 'abcdefghij',
		'test_text'	=> 'This is a test-value for the text-column',
		'test_blob'	=> 'This is a test-value for the blob-column',
	), array('test_auto'=>1), __LINE__, __FILE__, $app);
// updating only the blob's
$db->update('schema_proc_test',array(
		'test_text'	=> 'This is a test-value for the text-column, 2.row',
		'test_blob'	=> 'This is a test-value for the blob-column, 2.row',
	), array('test_auto'=>2), __LINE__, __FILE__, $app);
// db::update uses UpdateBlob only for MaxDB at the moment, it works for MySql too, but fails for postgres with text / CLOB's
// $adodb->UpdateBlob('schema_proc_test','test_text','This is a test-value for the text-column, 2.row','test_auto=2','CLOB');
// $adodb->UpdateBlob('schema_proc_test','test_blob','This is a test-value for the blob-column, 2.row','test_auto=2','BLOB');
check_content($adodb->GetAll("SELECT * FROM schema_proc_test"),array(
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
echo "==> SUCCESS\n";

echo "Droping column test_blob:\n";
$new_table_def = $test_tables['schema_proc_test'];
unset($new_table_def['fd']['test_blob']);
$schema_proc->DropColumn('schema_proc_test',$new_table_def,'test_blob');
check_column('test_blob',$adodb->MetaColumns('schema_proc_test'),False);
echo "==> SUCCESS\n";

echo "Altering column test_char to varchar(32):\n";
$schema_proc->AlterColumn('schema_proc_test','test_char',array('type' => 'varchar','precision' => 32));
check_column_type('test_char','varchar',32,$adodb->MetaColumns('schema_proc_test'));
echo "==> SUCCESS\n";

echo "Adding column test_bool bool:\n";
$schema_proc->AddColumn('schema_proc_test','test_bool',array('type' => 'bool'));
check_column('test_bool',$adodb->MetaColumns('schema_proc_test'));
echo "==> SUCCESS\n";

echo "Renaming column test_timestamp to test_time:\n";
$schema_proc->RenameColumn('schema_proc_test','test_timestamp','test_time');
check_column('test_timestamp',$adodb->MetaColumns('schema_proc_test'),false);
check_column('test_time',$adodb->MetaColumns('schema_proc_test'));
echo "==> SUCCESS\n";

echo "Renaming table schema_proc_test to schema_proc_renamed:\n";
$schema_proc->RenameTable('schema_proc_test','schema_proc_renamed');
$tables = $adodb->MetaTables();
check_table('schema_proc_test',$tables,False);
check_table('schema_proc_renamed',$tables);
echo "==> SUCCESS\n";

echo "Renaming column (with index) test_varchar to test_varchar_renamed:\n";
$schema_proc->RenameColumn('schema_proc_renamed','test_varchar','test_varchar_renamed');
$columns = $adodb->MetaColumns('schema_proc_renamed');
check_column('test_varchar',$columns,False);
check_column('test_varchar_renamed',$columns);
$indexes = $adodb->MetaIndexes('schema_proc_renamed');
if ($indexes !== False)
{
	check_index('test_varchar',False,$indexes,False);
	check_index('test_varchar_renamed',False,$indexes);
}
echo $indexes !== False ? "==> SUCCESS\n" : "==> unchecked\n";

echo "Droping index from renamed column (test_char,test_varchar_renamed):\n";
$schema_proc->DropIndex('schema_proc_renamed',array('test_char','test_varchar_renamed'));
$indexes2 = $adodb->MetaIndexes('schema_proc_renamed');
if ($indexes2 !== False) check_index(array('test_char','test_varchar_renamed'),False,$indexes2,False);
echo $indexes2 !== False ? "==> SUCCESS\n" : "==> unchecked\n";

	//print_r($adodb->MetaColumns('schema_proc_renamed'));
	//print_r($adodb->MetaIndexes('schema_proc_renamed'));

echo "Inserting some more content\n";
$db->query("INSERT INTO schema_proc_renamed (test_int4,test_varchar_renamed,test_char) VALUES (10,'Hallo Hallo Hallo ...','12345678901234567890123456789012')");
check_content($adodb->GetAll("SELECT * FROM schema_proc_renamed"),array(
		array('test_auto' => 1, 'test_int4' => 99, 'test_varchar_renamed' => 'Hallo Ralf','test_char' => 'abcdefghij'),
		array('test_auto' => 2, 'test_int4' => 2, 'test_varchar_renamed' => 'Hallo wer noch?','test_char' => '9876543210'),
		array('test_auto' => 3, 'test_int4' => 10, 'test_varchar_renamed' => 'Hallo Hallo Hallo ...','test_char' => '12345678901234567890123456789012'),
	));
echo "==> SUCCESS\n";

//echo "Reading back the content:\n";
//$all = $adodb->GetAll("SELECT * FROM schema_proc_renamed");
//print_r($all);

echo "\nDroping the test-tables again\n";
foreach($adodb->MetaTables() as $table)
{
	$table = strtolower($table);
	if (strstr($table,'schema_proc'))
	{
		$aSql = $schema_proc->dict->DropTableSQL($table);
		$schema_proc->ExecuteSqlArray($aSql,1,"DropTableSQL('%1') = %2",$table,$aSql);
	}
}
echo "\n********************\n";
echo "*** FULL SUCCESS ***\n";
echo "********************\n";
echo "\nbye ...\n";


/**
 * Checks if table $table exists or not, die's with an error-message if the check went wrong
 *
 * @param string $table table-name
 * @param array $tables array of table-names from call to MetaTables()
 * @param boolean $existence =true should we check for existence or none-existence, default existence
 */
function check_table($table,$tables,$existence=True)
{
	$exist = in_array($table,$tables) || in_array(strtoupper($table),$tables);

	if ($exist != $existence)
	{
		print_r($tables);
		die ("\n\n!!! Table '$table' is ".($existence ? 'missing' : 'still there')." !!!\n\n");
	}
}

/**
 * Checks if $column exists or not, die's with an error-message if the check went wrong
 *
 * @param string $column column-name
 * @param array $columns array of adodb field objects from MetaColumns($table)
 * @param boolean $existence =true should we check for existence or none-existence, default existence
 */
function check_column($column,$columns,$existence=True)
{
	$exist = isset($columns[$column]) || isset($columns[strtoupper($column)]);

	if ($exist != $existence)
	{
		print_r($columns);
		die ("\n\n!!! Column '$column' is ".($existence ? 'missing' : 'still there')." !!!\n\n");
	}
}

/**
 * Checks the type of a column
 *
 * @param string $column column-name
 * @param string $type column-type as the DB uses it, no eGW type !!!
 * @param int $precision precision
 * @param array $columns array of adodb field objects from MetaColumns($table)
 */
function check_column_type($column,$type,$precision,$columns)
{
	static $alternate_types = array(
		'varchar'	=> array('C'),
		'int'		=> array('I'),
	);

	$data = isset($columns[$column]) ? $columns[$column] : $columns[strtoupper($column)];

	if (!is_object($data))
	{
		print_r($columns);
		die ("\n\n!!! Column '$column' does NOT exist !!!\n\n");
	}
	$data->type = strtolower($data->type);
	if ($data->type != $type && !in_array($data->type,$alternate_types[$type]))
	{
		print_r($columns);
		die ("\n\n!!! Column '$column' is NOT of type '$type', but '$data->type' !!!\n\n");
	}
	if ($precision && $data->max_length != $precision)
	{
		print_r($columns);
		die ("\n\n!!! Precision of column '$column' is NOT $precision, but $data->precision !!!\n\n");
	}
}

/**
 * Checks if $idx exists or not, die's with an error-message if the check went wrong
 *
 * @param array $columns array of strings with column-names of that index
 * @param boolean $unique unique index or not
 * @param array $indexes array of index-describtions from call to MetaIndexes($table)
 * @param boolean $_existence =true should we check for existence or none-existence, default existence
 */
function check_index($columns,$unique,$indexes,$_existence=True)
{
	if (!is_array($columns)) $columns = array($columns);
	$existence = $_existence && $columns['options'][$GLOBALS['db']->Type] !== False;
	unset($columns['options']);

	$exist = False;
	foreach($indexes as $idx_data)
	{
		if (implode(':',$columns) == strtolower(implode(':',$idx_data['columns'])))
		{
			$exist = true;
			break;
		}
	}
	if ($exist != $existence)
	{
		print_r($indexes);
		die ("\n\n!!! Index (".implode(', ',$columns).") is ".($existence ? 'missing' : 'still there')." !!!\n\n");
	}
	if ($existence && $unique != !!$idx_data['unique'])
	{
		print_r($indexes);
		die ("\n\n!!! Index (".implode(', ',$columns).") is ".($unique ? 'NOT ' : '')."unique !!!\n\n");
	}
}

/**
 * Checks the content written to the table
 *
 * @param array $is content read from the database via GetAll()
 * @param array $should content against which we test
 * @param boolean $return_false =false return false if the check fails or die with an error-msg, default die
 */
function check_content($is,$should,$return_false=false)
{
	foreach($should as $key => $val)
	{
		if (!isset($is[$key]) && isset($is[strtoupper($key)])) $key = strtoupper($key);
		if (is_array($val) && !check_content($is[$key],$val,True) || !is_array($val) && $is[$key] != $val)
		{
			echo "key='$key', is="; print_r($is[$key]); echo ", should="; print_r($val); echo "\n";
			if ($return_false) return False;

			print_r($is);
			die("\n\n!!! Content read back from table is not as expected !!!\n\n");
		}
	}
	return True;
}
