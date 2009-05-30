<?php
/**
 * eGroupWare API: Database abstraction library
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage db
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2003-9 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

/**
 * Database abstraction library
 *
 * This allows eGroupWare to use multiple database backends via ADOdb or in future with PDO
 *
 * You only need to clone the global database object $GLOBALS['egw']->db if:
 * - you use the old methods f(), next_record(), row(), num_fields(), num_rows()
 * - you access an application table (non phpgwapi) and you want to call set_app()
 *
 * Otherwise you can simply use $GLOBALS['egw']->db or a reference to it.
 *
 * Avoiding next_record() or row() can be done by looping with the recordset returned by query() or select():
 *
 * a) foreach($db->query("SELECT * FROM $table",__LINE__,__FILE__) as $row)
 *
 * b) foreach($db->select($api_table,'*',$where,__LINE__,__FILE__) as $row)
 *
 * c) foreach($db->select($table,'*',$where,__LINE__,__FILE__,false,'',$app) as $row)
 *
 * To fetch only a single column (of the next row):
 *		$cnt = $db->query("SELECT COUNT(*) FROM ...")->fetchColumn($column_num=0);
 *
 * To fetch a next (single) row, you can use:
 *		$row = $db->query("SELECT COUNT(*) FROM ...")->fetch($fetchmod=null);
 *
 * egw_db allows to use exceptions to catch sql-erros, not existing tables or failure to connect to the database, eg.:
 *		try {
 *			$this->db->connect();
 *			$num_config = $this->db->select(config::TABLE,'COUNT(config_name)',false,__LINE__,__FILE__)->fetchSingle();
 *		}
 *		catch(Exception $e) {
 *			echo "Connection to DB failed (".$e->getMessage().")!\n";
 *		}
 */

if(empty($GLOBALS['egw_info']['server']['db_type']))
{
	$GLOBALS['egw_info']['server']['db_type'] = 'mysql';
}
include_once(EGW_API_INC.'/adodb/adodb.inc.php');

class egw_db
{
	/**
	 * Fetchmode to fetch only as associative array with $colname => $value pairs
	 *
	 * Use the FETCH_* constants to be compatible, if we replace ADOdb ...
	 */
	const FETCH_ASSOC = ADODB_FETCH_ASSOC;
	/**
	 * Fetchmode to fetch only as (numeric indexed) array: array($val1,$val2,...)
	 */
	const FETCH_NUM = ADODB_FETCH_NUM;
	/**
	 * Fetchmode to have both numeric and column-name indexes
	 */
	const FETCH_BOTH = ADODB_FETCH_BOTH;
	/**
	* @var string $type translated database type: mysqlt+mysqli ==> mysql, same for odbc-types
	*/
	var $Type     = '';

	/**
	* @var string $type database type as defined in the header.inc.php, eg. mysqlt
	*/
	var $setupType     = '';

	/**
	* @var string $Host database host to connect to
	*/
	var $Host     = '';

	/**
	* @var string $Port port number of database to connect to
	*/
	var $Port     = '';

	/**
	* @var string $Database name of database to use
	*/
	var $Database = '';

	/**
	* @var string $User name of database user
	*/
	var $User     = '';

	/**
	* @var string $Password password for database user
	*/
	var $Password = '';

	/**
	* @var int $Auto_Free automatically free results - 0 no, 1 yes
	*/
	var $Auto_Free     = 0;

	/**
	* @var int $Debug enable debuging - 0 no, 1 yes
	*/
	var $Debug         = 0;

	/**
	* @deprecated use exceptions (try/catch block) to handle failed connections or sql errors
	* @var string $Halt_On_Error "yes" (halt with message), "no" (ignore errors quietly), "report" (ignore errror, but spit a warning)
	*/
	var $Halt_On_Error = 'yes';

	/**
	* @var array $Record current record
	*/
	var $Record   = array();

	/**
	* @var int row number for current record
	*/
	var $Row;

	/**
	* @var int $Errno internal rdms error number for last error
	*/
	var $Errno    = 0;

	/**
	* @var string descriptive text from last error
	*/
	var $Error    = '';

	/**
	 * eGW's own query log, independent of the db-type, eg. /tmp/query.log
	 *
	 * @var string
	 */
	var $query_log;

	//i am not documenting private vars - skwashd :)
    var $xmlrpc = False;
	var $soap   = False;
	/**
	 * ADOdb connection
	 *
	 * @var ADOConnection
	 */
	var $Link_ID = 0;
	/**
	 * ADOdb connection
	 *
	 * @var ADOConnection
	 */
	var $privat_Link_ID = False;	// do we use a privat Link_ID or a reference to the global ADOdb object
	/**
	 * ADOdb record set of the current query
	 *
	 * @var ADORecordSet
	 */
	var $Query_ID = 0;

	/**
	 * Can be used to transparently convert tablenames, eg. 'mytable' => 'otherdb.othertable'
	 *
	 * Can be set eg. at the *end* of header.inc.php.
	 * Only works with new egw_db methods (select, insert, update, delete) not query!
	 *
	 * @var array
	 */
	static $tablealiases = array();

	/**
	 * db allows sub-queries, true for everything but mysql < 4.1
	 *
	 * use like: if ($db->capabilities[egw_db::CAPABILITY_SUB_QUERIES]) ...
	 */
	const CAPABILITY_SUB_QUERIES = 'sub_queries';
	/**
	 * db allows union queries, true for everything but mysql < 4.0
	 */
	const CAPABILITY_UNION = 'union';
	/**
	 * db allows an outer join, will be set eg. for postgres
	 */
	const CAPABILITY_OUTER_JOIN = 'outer_join';
	/**
	 * db is able to use DISTINCT on text or blob columns
	 */
	const CAPABILITY_DISTINCT_ON_TEXT =	'distinct_on_text';
	/**
	 * DB is able to use LIKE on text columns
	 */
	const CAPABILITY_LIKE_ON_TEXT =	'like_on_text';
	/**
	 * DB allows ORDER on text columns
	 *
	 * boolean or string for sprintf for a cast (eg. 'CAST(%s AS varchar)
	 */
	const CAPABILITY_ORDER_ON_TEXT = 'order_on_text';
	/**
	 * case of returned column- and table-names: upper, lower(pgSql), preserv(MySQL)
	 */
	const CAPABILITY_NAME_CASE = 'name_case';
	/**
	 * does DB supports a changeable client-encoding
	 */
	const CAPABILITY_CLIENT_ENCODING = 'client_encoding';
	/**
	 * case insensitiv like statement (in $db->capabilities[egw_db::CAPABILITY_CASE_INSENSITIV_LIKE]), default LIKE, ILIKE for postgres
	 */
	const CAPABILITY_CASE_INSENSITIV_LIKE = 'case_insensitive_like';
	/**
	 * DB requires varchar columns to be truncated to the max. size (eg. Postgres)
	 */
	const CAPABILITY_REQUIRE_TRUNCATE_VARCHAR = 'require_truncate_varchar';
	/**
	 * default capabilities will be changed by method set_capabilities($ado_driver,$db_version)
	 *
	 * should be used with the CAPABILITY_* constants as key
	 *
	 * @var array
	 */
	var $capabilities = array(
		self::CAPABILITY_SUB_QUERIES      => true,
		self::CAPABILITY_UNION            => true,
		self::CAPABILITY_OUTER_JOIN       => false,
		self::CAPABILITY_DISTINCT_ON_TEXT => true,
		self::CAPABILITY_LIKE_ON_TEXT     => true,
		self::CAPABILITY_ORDER_ON_TEXT    => true,
		self::CAPABILITY_NAME_CASE        => 'upper',
		self::CAPABILITY_CLIENT_ENCODING  => false,
		self::CAPABILITY_CASE_INSENSITIV_LIKE => 'LIKE',
		self::CAPABILITY_REQUIRE_TRUNCATE_VARCHAR => false,
	);

	var $prepared_sql = array();	// sql is the index

	/**
	 * Constructor
	 *
	 * @param array $db_data=null values for keys 'db_name', 'db_host', 'db_port', 'db_user', 'db_pass', 'db_type'
	 */
	function __construct(array $db_data=null)
	{
		if (!is_null($db_data))
		{
			foreach(array(
				'Database' => 'db_name',
				'Host'     => 'db_host',
				'Port'     => 'db_port',
				'User'     => 'db_user',
				'Password' => 'db_pass',
				'Type'     => 'db_type',
			) as $var => $key)
			{
				$this->$var = $db_data[$key];
			}
		}
	}

	/**
	* @param string $query query to be executed (optional)
	*/

	function db($query = '')
	{
		$this->query($query);
	}

	/**
	* @return int current connection id
	*/
	function link_id()
	{
		return $this->Link_ID;
	}

	/**
	 * Return the result-object of the last query
	 *
	 * @deprecated use the result-object returned by query() or select() direct, so you can use the global db-object and not a clone
	 * @return ADORecordSet
	 */
	function query_id()
	{
		return $this->Query_ID;
	}

	/**
	* Open a connection to a database
	*
	* @param string $Database name of database to use (optional)
	* @param string $Host database host to connect to (optional)
	* @param string $Port database port to connect to (optional)
	* @param string $User name of database user (optional)
	* @param string $Password password for database user (optional)
	* @param string $Type type of database (optional)
	* @return ADONewConnection
	*/
	function connect($Database = NULL, $Host = NULL, $Port = NULL, $User = NULL, $Password = NULL,$Type = NULL)
	{
		/* Handle defaults */
		if (!is_null($Database) && $Database)
		{
			$this->Database = $Database;
		}
		if (!is_null($Host) && $Host)
		{
			$this->Host     = $Host;
		}
		if (!is_null($Port) && $Port)
		{
			$this->Port     = $Port;
		}
		if (!is_null($User) && $User)
		{
			$this->User     = $User;
		}
		if (!is_null($Password) && $Password)
		{
			$this->Password = $Password;
		}
		if (!is_null($Type) && $Type)
		{
			$this->Type = $Type;
		}
		elseif (!$this->Type)
		{
			$this->Type = $GLOBALS['egw_info']['server']['db_type'];
		}
		if (!$this->Link_ID)
		{
			foreach(array('Host','Database','User','Password') as $name)
			{
				$$name = $this->$name;
			}
			$this->setupType = $php_extension = $type = $this->Type;

			switch($this->Type)	// convert to ADO db-type-names
			{
				case 'pgsql':
					$type = 'postgres'; // name in ADOdb
					// create our own pgsql connection-string, to allow unix domain soccets if !$Host
					$Host = "dbname=$this->Database".($this->Host ? " host=$this->Host".($this->Port ? " port=$this->Port" : '') : '').
						" user=$this->User".($this->Password ? " password='".addslashes($this->Password)."'" : '');
					$User = $Password = $Database = '';	// to indicate $Host is a connection-string
					break;

				case 'odbc_mssql':
					$php_extension = 'odbc';
					$this->Type = 'mssql';
					// fall through
				case 'mssql':
					if ($this->Port) $Host .= ','.$this->Port;
					break;

				case 'odbc_oracle':
					$php_extension = 'odbc';
					$this->Type = 'oracle';
					break;
				case 'oracle':
					$php_extension = $type = 'oci8';
					break;

				case 'sapdb':
					$this->Type = 'maxdb';
					// fall through
				case 'maxdb':
					$type ='sapdb';	// name in ADOdb
					$php_extension = 'odbc';
					break;

				case 'mysqlt':
					$php_extension = 'mysql';	// you can use $this->setupType to determine if it's mysqlt or mysql
					// fall through
				case 'mysqli':
					$this->Type = 'mysql';
					// fall through
				default:
					if ($this->Port) $Host .= ':'.$this->Port;
					break;
			}
			if (!isset($GLOBALS['egw']->ADOdb) ||	// we have no connection so far
				(is_object($GLOBALS['egw']->db) &&	// we connect to a different db, then the global one
					($this->Type != $GLOBALS['egw']->db->Type ||
					$this->Database != $GLOBALS['egw']->db->Database ||
					$this->User != $GLOBALS['egw']->db->User ||
					$this->Host != $GLOBALS['egw']->db->Host ||
					$this->Port != $GLOBALS['egw']->db->Port)))
			{
				if (!check_load_extension($php_extension))
				{
					$this->halt("Necessary php database support for $this->Type (".PHP_SHLIB_PREFIX.$php_extension.'.'.PHP_SHLIB_SUFFIX.") not loaded and can't be loaded, exiting !!!");
					return null;	// in case error-reporting = 'no'
				}
				if (!isset($GLOBALS['egw']->ADOdb))	// use the global object to store the connection
				{
					$this->Link_ID =& $GLOBALS['egw']->ADOdb;
				}
				else
				{
					$this->privat_Link_ID = True;	// remember that we use a privat Link_ID for disconnect
				}
				$this->Link_ID = ADONewConnection($type);
				if (!$this->Link_ID)
				{
					$this->halt("No ADOdb support for '$type' ($this->Type) !!!");
					return null;	// in case error-reporting = 'no'
				}
				$connect = $GLOBALS['egw_info']['server']['db_persistent'] ? 'PConnect' : 'Connect';
				if (($Ok = $this->Link_ID->$connect($Host, $User, $Password)))
				{
					$this->ServerInfo = $this->Link_ID->ServerInfo();
					$this->set_capabilities($type,$this->ServerInfo['version']);
					if($Database)
					{
					   $Ok = $this->Link_ID->SelectDB($Database);
					}
				}
				if (!$Ok)
				{
					$Host = preg_replace('/password=[^ ]+/','password=$Password',$Host);	// eg. postgres dsn contains password
					$this->halt("ADOdb::$connect($Host, $User, \$Password, $Database) failed.");
					return null;	// in case error-reporting = 'no'
				}
				if ($this->Debug)
				{
					echo function_backtrace();
					echo "<p>new ADOdb connection to $this->Type://$this->Host/$this->Database: Link_ID".($this->Link_ID === $GLOBALS['egw']->ADOdb ? '===' : '!==')."\$GLOBALS[egw]->ADOdb</p>";
					//echo "<p>".print_r($this->Link_ID->ServerInfo(),true)."</p>\n";
					_debug_array($this);
					echo "\$GLOBALS[egw]->db="; _debug_array($GLOBALS[egw]->db);
				}
				if ($this->Type == 'mssql')
				{
					// this is the format ADOdb expects
					$this->Link_ID->Execute('SET DATEFORMAT ymd');
					// sets the limit to the maximum
					ini_set('mssql.textlimit',2147483647);
					ini_set('mssql.sizelimit',2147483647);
				}
				$new_connection = true;
			}
			else
			{
				$this->Link_ID =& $GLOBALS['egw']->ADOdb;
			}
		}
		// next ADOdb version: if (!$this->Link_ID->isConnected()) $this->Link_ID->Connect();
		if (!$this->Link_ID->_connectionID) $this->Link_ID->Connect();

		if ($new_connection)
		{
			foreach(get_included_files() as $file)
			{
				if (strpos($file,'adodb') !== false && !in_array($file,(array)$_SESSION['egw_required_files']))
				{
					$_SESSION['egw_required_files'][] = $file;
					//error_log(__METHOD__."() egw_required_files[] = $file");
				}
			}
		}
		//echo "<p>".print_r($this->Link_ID->ServerInfo(),true)."</p>\n";
		return $this->Link_ID;
	}

	/**
	 * Magic method to re-connect with the database, if the object get's restored from the session
	 */
	function __wakeup()
	{
		$this->connect();	// we need to re-connect
	}

	/**
	 * changes defaults set in class-var $capabilities depending on db-type and -version
	 *
	 * @param string $ado_driver mysql, postgres, mssql, sapdb, oci8
	 * @param string $db_version version-number of connected db-server, as reported by ServerInfo
	 */
	function set_capabilities($adodb_driver,$db_version)
	{
		switch($adodb_driver)
		{
			case 'mysql':
			case 'mysqlt':
			case 'mysqli':
				$this->capabilities[self::CAPABILITY_SUB_QUERIES] = (float) $db_version >= 4.1;
				$this->capabilities[self::CAPABILITY_UNION] = (float) $db_version >= 4.0;
				$this->capabilities[self::CAPABILITY_NAME_CASE] = 'preserv';
				$this->capabilities[self::CAPABILITY_CLIENT_ENCODING] = (float) $db_version >= 4.1;
				break;

			case 'postgres':
				$this->capabilities[self::CAPABILITY_NAME_CASE] = 'lower';
				$this->capabilities[self::CAPABILITY_CLIENT_ENCODING] = (float) $db_version >= 7.4;
				$this->capabilities[self::CAPABILITY_OUTER_JOIN] = true;
				$this->capabilities[self::CAPABILITY_CASE_INSENSITIV_LIKE] = 'ILIKE';
				$this->capabilities[self::CAPABILITY_REQUIRE_TRUNCATE_VARCHAR] = true;
				break;

			case 'mssql':
				$this->capabilities[self::CAPABILITY_DISTINCT_ON_TEXT] = false;
				$this->capabilities[self::CAPABILITY_ORDER_ON_TEXT] = 'CAST (%s AS varchar)';
				break;

			case 'maxdb':	// if Lim ever changes it to maxdb ;-)
			case 'sapdb':
				$this->capabilities[self::CAPABILITY_DISTINCT_ON_TEXT] = false;
				$this->capabilities[self::CAPABILITY_LIKE_ON_TEXT] = $db_version >= 7.6;
				$this->capabilities[self::CAPABILITY_ORDER_ON_TEXT] = false;
				break;
		}
		//echo "db::set_capabilities('$adodb_driver',$db_version)"; _debug_array($this->capabilities);
	}

	/**
	* Close a connection to a database
	*/
	function disconnect()
	{
		if (!$this->privat_Link_ID)
		{
			unset($GLOBALS['egw']->ADOdb);
		}
		unset($this->Link_ID);
		$this->Link_ID = 0;
	}

	/**
	* Escape strings before sending them to the database
	*
	* @deprecated use quote($value,$type='') instead
	* @param string $str the string to be escaped
	* @return string escaped sting
	*/
	function db_addslashes($str)
	{
		if (!isset($str) || $str == '')
		{
			return '';
		}
		if (!$this->Link_ID && !$this->connect())
		{
			return False;
		}
		return $this->Link_ID->addq($str);
	}

	/**
	* Convert a unix timestamp to a rdms specific timestamp
	*
	* @param int unix timestamp
	* @return string rdms specific timestamp
	*/
	function to_timestamp($epoch)
	{
		if (!$this->Link_ID && !$this->connect())
		{
			return False;
		}
		// the substring is needed as the string is already in quotes
		return substr($this->Link_ID->DBTimeStamp($epoch),1,-1);
	}

	/**
	* Convert a rdms specific timestamp to a unix timestamp
	*
	* @param string rdms specific timestamp
	* @return int unix timestamp
	*/
	function from_timestamp($timestamp)
	{
		if (!$this->Link_ID && !$this->connect())
		{
			return False;
		}
		return $this->Link_ID->UnixTimeStamp($timestamp);
	}

	/**
	 * convert a rdbms specific boolean value
	 *
	 * @param string $val boolean value in db-specfic notation
	 * @return boolean
	 */
	function from_bool($val)
	{
		return $val && $val[0] !== 'f';	// everthing other then 0 or f[alse] is returned as true
	}

	/**
	 * Discard the current query result
	 *
	 * @deprecated use the result-object returned by query() or select() direct, so you can use the global db-object and not a clone
	 */
	function free()
	{
		unset($this->Query_ID);	// else copying of the db-object does not work
		$this->Query_ID = 0;
	}

	/**
	* Execute a query
	*
	* @param string $Query_String the query to be executed
	* @param int $line the line method was called from - use __LINE__
	* @param string $file the file method was called from - use __FILE__
	* @param int $offset row to start from, default 0
	* @param int $num_rows number of rows to return (optional), default -1 = all, 0 will use $GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs']
	* @param array/boolean $inputarr array for binding variables to parameters or false (default)
	* @param int $fetchmode=egw_db::FETCH_BOTH egw_db::FETCH_BOTH (default), egw_db::FETCH_ASSOC or egw_db::FETCH_NUM
	* @return ADORecordSet or false, if the query fails
	*/
	function query($Query_String, $line = '', $file = '', $offset=0, $num_rows=-1,$inputarr=false,$fetchmode=egw_db::FETCH_BOTH)
	{
		if ($Query_String == '')
		{
			return 0;
		}
		if (!$this->Link_ID && !$this->connect())
		{
			return False;
		}

		# New query, discard previous result.
		if ($this->Query_ID)
		{
			$this->free();
		}
		if ($this->Link_ID->fetchMode != $fetchmode)
		{
			$this->Link_ID->SetFetchMode($fetchmode);
		}
		if (!$num_rows)
		{
			$num_rows = $GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs'];
		}
		if ($num_rows > 0)
		{
			$this->Query_ID = $this->Link_ID->SelectLimit($Query_String,$num_rows,(int)$offset,$inputarr);
		}
		else
		{
			$this->Query_ID = $this->Link_ID->Execute($Query_String,$inputarr);
		}
		$this->Row = 0;
		$this->Errno  = $this->Link_ID->ErrorNo();
		$this->Error  = $this->Link_ID->ErrorMsg();

		if ($this->query_log && ($f = @fopen($this->query_log,'a+')))
		{
			fwrite($f,'['.(isset($GLOBALS['egw_setup']) ? $GLOBALS['egw_setup']->ConfigDomain : $GLOBALS['egw_info']['user']['domain']).'] ');
			fwrite($f,date('Y-m-d H:i:s ').$Query_String.($inputarr ? "\n".print_r($inputarr,true) : '')."\n");
			if (!$this->Query_ID)
			{
				fwrite($f,"*** Error $this->Errno: $this->Error\n".function_backtrace()."\n");
			}
			fclose($f);
		}
		if (!$this->Query_ID)
		{
			$this->halt("Invalid SQL: ".(is_array($Query_String)?$Query_String[0]:$Query_String).
				($inputarr ? "<br>Parameters: '".implode("','",$inputarr)."'":''),
				$line, $file);
		}
		return $this->Query_ID;
	}

	/**
	* Execute a query with limited result set
	*
	* @param string $Query_String the query to be executed
	* @param int $offset row to start from, default 0
	* @param int $line the line method was called from - use __LINE__
	* @param string $file the file method was called from - use __FILE__
	* @param int $num_rows number of rows to return (optional), default -1 = all, 0 will use $GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs']
	* @param array/boolean $inputarr array for binding variables to parameters or false (default)
	* @return ADORecordSet or false, if the query fails
	*/
	function limit_query($Query_String, $offset, $line = '', $file = '', $num_rows = '',$inputarr=false)
	{
		return $this->query($Query_String,$line,$file,$offset,$num_rows,$inputarr);
	}

	/**
	* Move to the next row in the results set
	*
	* Specifying a fetch_mode only works for newly fetched rows, the first row always gets fetched by query!!!
	*
	* @deprecated use foreach(query() or foreach(select() to loop over the query using the global db object
	* @param int $fetch_mode egw_db::FETCH_BOTH = numerical+assoc keys (eGW default), egw_db::FETCH_ASSOC or egw_db::FETCH_NUM
	* @return bool was another row found?
	*/
	function next_record($fetch_mode=egw_db::FETCH_BOTH)
	{
		if (!$this->Query_ID)
		{
			$this->halt('next_record called with no query pending.');
			return 0;
		}
		if ($this->Row)	// first row is already fetched
		{
			$this->Query_ID->MoveNext();
		}
		++$this->Row;

		$this->Record = $this->Query_ID->fields;

		if ($this->Query_ID->EOF || !$this->Query_ID->RecordCount() || !is_array($this->Record))
		{
			return False;
		}
		if ($this->capabilities[self::CAPABILITY_NAME_CASE] == 'upper')	// maxdb, oracle, ...
		{
			switch($fetch_mode)
			{
				case egw_db::FETCH_ASSOC:
					$this->Record = array_change_key_case($this->Record);
					break;
				case egw_db::FETCH_NUM:
					$this->Record = array_values($this->Record);
					break;
				default:
					$this->Record = array_change_key_case($this->Record);
					if (!isset($this->Record[0]))
					{
						$this->Record += array_values($this->Record);
					}
					break;
			}
		}
		// fix the result if it was fetched ASSOC and now NUM OR BOTH is required, as default for select() is now ASSOC
		elseif ($this->Link_ID->fetchMode != $fetch_mode)
		{
			if (!isset($this->Record[0]))
			{
				$this->Record += array_values($this->Record);
			}
		}
		return True;
	}

	/**
	* Move to position in result set
	*
	* @deprecated use the result-object returned by query() or select() direct, so you can use the global db-object and not a clone
	* @param int $pos required row (optional), default first row
	* @return boolean true if sucessful or false if not found
	*/
	function seek($pos = 0)
	{
		if (!$this->Query_ID  || !$this->Query_ID->Move($this->Row = $pos))
		{
			$this->halt("seek($pos) failed: resultset has " . $this->num_rows() . " rows");
			$this->Query_ID->Move( $this->num_rows() );
			$this->Row = $this->num_rows();
			return False;
		}
		return True;
	}

	/**
	* Begin Transaction
	*
	* @return int/boolean current transaction-id, of false if no connection
	*/
	function transaction_begin()
	{
		if (!$this->Link_ID && !$this->connect())
		{
			return False;
		}
		//return $this->Link_ID->BeginTrans();
		return $this->Link_ID->StartTrans();
	}

	/**
	* Complete the transaction
	*
	* @return bool True if sucessful, False if fails
	*/
	function transaction_commit()
	{
		if (!$this->Link_ID && !$this->connect())
		{
			return False;
		}
		//return $this->Link_ID->CommitTrans();
		return $this->Link_ID->CompleteTrans();
	}

	/**
	* Rollback the current transaction
	*
	* @return bool True if sucessful, False if fails
	*/
	function transaction_abort()
	{
		if (!$this->Link_ID && !$this->connect())
		{
			return False;
		}
		//return $this->Link_ID->RollbackTrans();
		return $this->Link_ID->FailTrans();
	}

	/**
	* Find the primary key of the last insertion on the current db connection
	*
	* @param string $table name of table the insert was performed on
	* @param string $field the autoincrement primary key of the table
	* @return int the id, -1 if fails
	*/
	function get_last_insert_id($table, $field)
	{
		if (!$this->Link_ID && !$this->connect())
		{
			return False;
		}
		if (self::$tablealiases && isset(self::$tablealiases[$table]))
		{
			$table = self::$tablealiases[$table];
		}
		$id = $this->Link_ID->PO_Insert_ID($table,$field);	// simulates Insert_ID with "SELECT MAX($field) FROM $table" if not native availible

		if ($id === False)	// function not supported
		{
			echo "<p>db::get_last_insert_id(table='$table',field='$field') not yet implemented for db-type '$this->Type' OR no insert operation before</p>\n";
			function_backtrace();
			return -1;
		}
		return $id;
	}

	/**
	* Lock a table
	*
	* @deprecated not used anymore as it costs to much performance, use transactions if needed
	* @param string $table name of table to lock
	* @param string $mode type of lock required (optional), default write
	* @return bool True if sucessful, False if fails
	*/
	function lock($table, $mode='write')
	{}

	/**
	* Unlock a table
	*
	* @deprecated not used anymore as it costs to much performance, use transactions if needed
	* @return bool True if sucessful, False if fails
	*/
	function unlock()
	{}

	/**
	* Get the number of rows affected by last update or delete
	*
	* @return int number of rows
	*/
	function affected_rows()
	{
		if (!$this->Link_ID && !$this->connect())
		{
			return False;
		}
		return $this->Link_ID->Affected_Rows();
	}

	/**
	* Number of rows in current result set
	*
	* @deprecated use the result-object returned by query/select()->NumRows(), so you can use the global db-object and not a clone
	* @return int number of rows
	*/
	function num_rows()
	{
		return $this->Query_ID ? $this->Query_ID->RecordCount() : False;
	}

	/**
	* Number of fields in current row
	*
	* @deprecated use the result-object returned by query() or select() direct, so you can use the global db-object and not a clone
	* @return int number of fields
	*/
	function num_fields()
	{
		return $this->Query_ID ? $this->Query_ID->FieldCount() : False;
	}

	/**
	* @deprecated use num_rows()
	*/
	function nf()
	{
		return $this->num_rows();
	}

	/**
	* @deprecated use print num_rows()
	*/
	function np()
	{
		print $this->num_rows();
	}

	/**
	* Return the value of a column
	*
	* @deprecated use the result-object returned by query() or select() direct, so you can use the global db-object and not a clone
	* @param string/integer $Name name of field or positional index starting from 0
	* @param bool $strip_slashes string escape chars from field(optional), default false
	*	depricated param, as correctly quoted values dont need any stripslashes!
	* @return string the field value
	*/
	function f($Name, $strip_slashes = False)
	{
		if ($strip_slashes)
		{
			return stripslashes($this->Record[$Name]);
		}
		return $this->Record[$Name];
	}

	/**
	* Print the value of a field
	*
	* @param string $Name name of field to print
	* @param bool $strip_slashes string escape chars from field(optional), default false
	*	depricated param, as correctly quoted values dont need any stripslashes!
	*/
	function p($Name, $strip_slashes = True)
	{
		print $this->f($Name, $strip_slashes);
	}

	/**
	* Returns a query-result-row as an associative array (no numerical keys !!!)
	*
	* @deprecated use foreach(query() or foreach(select() to loop over the query using the global db object
	* @param bool $do_next_record should next_record() be called or not (default not)
	* @param string $strip='' string to strip of the column-name, default ''
	* @return array/bool the associative array or False if no (more) result-row is availible
	*/
	function row($do_next_record=False,$strip='')
	{
		if ($do_next_record && !$this->next_record(egw_db::FETCH_ASSOC) || !is_array($this->Record))
		{
			return False;
		}
		return $strip ? self::strip_array_keys($this->Record,$strip) : $this->Record;
	}

	/**
	* Error handler
	*
	* @param string $msg error message
	* @param int $line line of calling method/function (optional)
	* @param string $file file of calling method/function (optional)
	*/
	function halt($msg, $line = '', $file = '')
	{
		if ($this->Link_ID)		// only if we have a link, else infinite loop
		{
			$this->Error = $this->Link_ID->ErrorMsg();	// need to be BEFORE unlock,
			$this->Errno = $this->Link_ID->ErrorNo();	// else we get its error or none

			$this->unlock();	/* Just in case there is a table currently locked */
		}
		if ($this->Halt_On_Error == "no")
		{
			return;
		}
		if ($this->Halt_On_Error == 'yes')
		{
			throw new egw_exception_db($msg.($this->Error?":\n".$this->Error:''),$this->Errno);
		}
		$this->haltmsg($msg);

		if ($file)
		{
			printf("<br /><b>File:</b> %s",$file);
		}
		if ($line)
		{
			printf("<br /><b>Line:</b> %s",$line);
		}
		printf("<br /><b>Function:</b> %s</p>\n",function_backtrace(2));

		if ($this->Halt_On_Error != "report")
		{
			echo "<p><b>Session halted.</b></p>";
			if (is_object($GLOBALS['egw']->common))
			{
				$GLOBALS['egw']->common->egw_exit(True);
			}
			else	// happens eg. in setup
			{
				exit();
			}
		}
	}

	function haltmsg($msg)
	{
		printf("<p><b>Database error:</b> %s<br>\n", $msg);
		if (($this->Errno || $this->Error) && $this->Error != "()")
		{
			printf("<b>$this->Type Error</b>: %s (%s)<br>\n",$this->Errno,$this->Error);
		}
	}

	/**
	* Get description of a table
	*
	* Beside the column-name all other data depends on the db-type !!!
	*
	* @param string $table name of table to describe
	* @param bool $full optional, default False summary information, True full information
	* @return array table meta data
	*/
	function metadata($table='',$full=false)
	{
		if (!$this->Link_ID && !$this->connect())
		{
			return False;
		}
		$columns = $this->Link_ID->MetaColumns($table);
		//$columns = $this->Link_ID->MetaColumnsSQL($table);
		//echo "<b>metadata</b>('$table')=<pre>\n".print_r($columns,True)."</pre>\n";

		$metadata = array();
		$i = 0;
		foreach($columns as $column)
		{
			// for backwards compatibilty (depreciated)
			unset($flags);
			if($column->auto_increment) $flags .= "auto_increment ";
			if($column->primary_key) $flags .= "primary_key ";
			if($column->binary) $flags .= "binary ";

//				_debug_array($column);
			$metadata[$i] = array(
				'table' => $table,
				'name'  => $column->name,
				'type'  => $column->type,
				'len'   => $column->max_length,
				'flags' => $flags, // for backwards compatibilty (depreciated) used by JiNN atm
				'not_null' => $column->not_null,
				'auto_increment' => $column->auto_increment,
				'primary_key' => $column->primary_key,
				'binary' => $column->binary,
				'has_default' => $column->has_default,
				'default'  => $column->default_value,
			);
			$metadata[$i]['table'] = $table;
			if ($full)
			{
				$metadata['meta'][$column->name] = $i;
			}
			++$i;
		}
		if ($full)
		{
			$metadata['num_fields'] = $i;
		}
		return $metadata;
	}

	/**
	* Get a list of table names in the current database
	*
	* @return array list of the tables
	*/
	function table_names()
	{
		if (!$this->Link_ID) $this->connect();
		if (!$this->Link_ID)
		{
			return False;
		}
		$result = array();
		$tables = $this->Link_ID->MetaTables('TABLES');
		if (is_array($tables))
		{
			foreach($tables as $table)
			{
				if ($this->capabilities[self::CAPABILITY_NAME_CASE] == 'upper')
				{
					$table = strtolower($table);
				}
				$result[] = array(
					'table_name'      => $table,
					'tablespace_name' => $this->Database,
					'database'        => $this->Database
				);
			}
		}
		return $result;
	}

	/**
	* Return a list of indexes in current database
	*
	* @return array list of indexes
	*/
	function index_names()
	{
		$indices = array();
		if ($this->Type != 'pgsql')
		{
			echo "<p>db::index_names() not yet implemented for db-type '$this->Type'</p>\n";
			return $indices;
		}
		$this->query("SELECT relname FROM pg_class WHERE NOT relname ~ 'pg_.*' AND relkind ='i' ORDER BY relname");
		while ($this->next_record())
		{
			$indices[] = array(
				'index_name'      => $this->f(0),
				'tablespace_name' => $this->Database,
				'database'        => $this->Database,
			);
		}
		return $indices;
	}

	/**
	* Returns an array containing column names that are the primary keys of $tablename.
	*
	* @return array of columns
	*/
	function pkey_columns($tablename)
	{
		if (!$this->Link_ID && !$this->connect())
		{
			return False;
		}
		return $this->Link_ID->MetaPrimaryKeys($tablename);
	}

	/**
	* Create a new database
	*
	* @param string $adminname name of database administrator user (optional)
	* @param string $adminpasswd password for the database administrator user (optional)
	* @param string $charset default charset for the database
	* @param string $grant_host='localhost' host/ip of the webserver
	*/
	function create_database($adminname = '', $adminpasswd = '', $charset='', $grant_host='localhost')
	{
		$currentUser = $this->User;
		$currentPassword = $this->Password;
		$currentDatabase = $this->Database;

		$sqls = array();
		$set_charset = '';
		switch ($this->Type)
		{
			case 'pgsql':
				$meta_db = 'template1';
				$sqls[] = "CREATE DATABASE $currentDatabase";
				break;
			case 'mysql':
				$create = "CREATE DATABASE `$currentDatabase`";
				if ($charset && isset($this->Link_ID->charset2mysql[$charset]) && (float) $this->ServerInfo['version'] >= 4.1)
				{
					$create .= ' DEFAULT CHARACTER SET '.$this->Link_ID->charset2mysql[$charset].';';
				}
				$sqls[] = $create;
				$sqls[] = "GRANT ALL ON `$currentDatabase`.* TO $currentUser@'$grant_host' IDENTIFIED BY ".$this->quote($currentPassword);
				$meta_db = 'mysql';
				break;
			default:
				echo "<p>db::create_database(user='$adminname',\$pw) not yet implemented for DB-type '$this->Type'</p>\n";
				break;
		}
		if ($adminname != '')
		{
			$this->User = $adminname;
			$this->Password = $adminpasswd;
			$this->Database = $meta_db;
		}
		$this->disconnect();
		foreach($sqls as $sql)
		{
			$this->query($sql,__LINE__,__FILE__);
		}
		$this->disconnect();

		$this->User = $currentUser;
		$this->Password = $currentPassword;
		$this->Database = $currentDatabase;
		$this->connect();
	}

	/**
	 * concat a variable number of strings together, to be used in a query
	 *
	 * Example: $db->concat($db->quote('Hallo '),'username') would return
	 *	for mysql "concat('Hallo ',username)" or "'Hallo ' || username" for postgres
	 * @param string $str1 already quoted stringliteral or column-name, variable number of arguments
	 * @return string to be used in a query
	 */
	function concat($str1)
	{
		$args = func_get_args();

		if (!$this->Link_ID && !$this->connect())
		{
			return False;
		}
		return call_user_func_array(array(&$this->Link_ID,'concat'),$args);
	}

	/**
	 * Convert a unix timestamp stored as integer in the db into a db timestamp, like MySQL: FROM_UNIXTIME(ts)
	 *
	 * @param string $expr name of an integer column or integer expression
	 * @return string SQL expression of type timestamp
	 */
	function from_unixtime($expr)
	{
		switch($this->Type)
		{
			case 'mysql':
				return "FROM_UNIXTIME($expr)";

			case 'pgsql':
				return "(timestamp with time zone 'epoch' + ($expr) * interval '1 sec')";

			case 'mssql':	// we use date(,0) as we store server-time
				return "DATEADD(second,($expr),'".date('Y-m-d H:i:s',0)."')";
		}
		return false;
	}

	/**
	 * format a timestamp as string, like MySQL: DATE_FORMAT(ts)
	 *
	 * Please note: only a subset of the MySQL formats are implemented
	 *
	 * @param string $expr name of a timestamp column or timestamp expression
	 * @param string $format format specifier like '%Y-%m-%d %H:%i:%s' or '%V%X' ('%v%x') weeknumber & year with Sunday (Monday) as first day
	 * @return string SQL expression of type timestamp
	 */
	function date_format($expr,$format)
	{
		switch($this->Type)
		{
			case 'mysql':
				return "DATE_FORMAT($expr,'$format')";

			case 'pgsql':
				$format = str_replace(
					array('%Y',  '%y','%m','%d','%H',  '%h','%i','%s','%V','%v','%X',  '%x'),
					array('YYYY','YY','MM','DD','HH24','HH','MI','SS','IW','IW','YYYY','YYYY'),
					$format);
				return "TO_CHAR($expr,'$format')";

			case 'mssql':
				$from = $to = array();
				foreach(array('%Y'=>'yyyy','%y'=>'yy','%m'=>'mm','%d'=>'dd','%H'=>'hh','%i'=>'mi','%s'=>'ss','%V'=>'wk','%v'=>'wk','%X'=>'yyyy','%x'=>'yyyy') as $f => $t)
				{
					$from[] = $f;
					$to[] = "'+DATEPART($t,($expr))+'";
				}
				$from[] = "''+"; $to[] = '';
				$from[] = "+''"; $to[] = '';
				return str_replace($from,$to,$format);
		}
		return false;
	}

	/**
	* Correctly Quote Identifiers like table- or colmnnames for use in SQL-statements
	*
	* This is mostly copy & paste from adodb's datadict class
	* @param $name string
	* @return string quoted string
	*/
	function name_quote($name = NULL)
	{
		if (!is_string($name)) {
			return FALSE;
		}

		$name = trim($name);

		if (!$this->Link_ID && !$this->connect())
		{
			return False;
		}

		$quote = $this->Link_ID->nameQuote;

		// if name is of the form `name`, quote it
		if ( preg_match('/^`(.+)`$/', $name, $matches) ) {
			return $quote . $matches[1] . $quote;
		}

		// if name contains special characters, quote it
		if ( preg_match('/\W/', $name) ) {
			return $quote . $name . $quote;
		}

		return $name;
	}

	/**
	* Escape values before sending them to the database - prevents SQL injection and SQL errors ;-)
	*
	* Please note that the quote function already returns necessary quotes: quote('Hello') === "'Hello'".
	* Int and Auto types are casted to int: quote('1','int') === 1, quote('','int') === 0, quote('Hello','int') === 0
	* Arrays of id's stored in strings: quote(array(1,2,3),'string') === "'1,2,3'"
	*
	* @param mixed $value the value to be escaped
	* @param string/boolean $type=false string the type of the db-column, default False === varchar
	* @param boolean $not_null=true is column NOT NULL, default true, else php null values are written as SQL NULL
	* @param int $length=null length of the varchar column, to truncate it if the database requires it (eg. Postgres)
	* @param string $glue=',' used to glue array values together for the string type
	* @return string escaped sting
	*/
	function quote($value,$type=False,$not_null=true,$length=null,$glue=',')
	{
		if ($this->Debug) echo "<p>db::quote(".(is_null($value)?'NULL':"'$value'").",'$type','$not_null')</p>\n";

		if (!$not_null && is_null($value))	// writing unset php-variables and those set to NULL now as SQL NULL
		{
			return 'NULL';
		}
		switch($type)
		{
			case 'int':
			case 'auto':
				// atm. (php5.2) php has only 32bit integers, it converts everything else to float.
				// Casting it to int gives a negative number instead of the big 64bit integer!
				// There for we have to keep it as float by using round instead the int cast.
				return is_float($value) ? round($value) : (int) $value;
			case 'bool':
				if ($this->Type == 'mysql')		// maybe it's not longer necessary with mysql5
				{
					return $value ? 1 : 0;
				}
				return $value ? 'true' : 'false';
			case 'float':
			case 'decimal':
				return (double) $value;
		}
		if (!$this->Link_ID && !$this->connect())
		{
			return False;
		}
		switch($type)
		{
			case 'blob':
				switch ($this->Link_ID->blobEncodeType)
				{
					case 'C':	// eg. postgres
						return "'" . $this->Link_ID->BlobEncode($value) . "'";
					case 'I':
						return $this->Link_ID->BlobEncode($value);
				}
				break;	// handled like strings
			case 'date':
				return $this->Link_ID->DBDate($value);
			case 'timestamp':
				return $this->Link_ID->DBTimeStamp($value);
		}
		if (is_array($value))
		{
			$value = implode($glue,$value);
		}
		if (!is_null($length) && strlen($value) > $length)
		{
			$value = substr($value,0,$length);
		}
		return $this->Link_ID->qstr($value);
	}

	/**
	* Implodes an array of column-value pairs for the use in sql-querys.
	* All data is run through quote (does either addslashes() or (int)) - prevents SQL injunction and SQL errors ;-).
	*
	* @author RalfBecker<at>outdoor-training.de
	*
	* @param string $glue in most cases this will be either ',' or ' AND ', depending you your query
	* @param array $array column-name / value pairs, if the value is an array all its array-values will be quoted
	*	according to the type of the column, and the whole array with be formatted like (val1,val2,...)
	*	If $use_key == True, an ' IN ' instead a '=' is used. Good for category- or user-lists.
	*	If the key is numerical (no key given in the array-definition) the value is used as is, eg.
	*	array('visits=visits+1') gives just "visits=visits+1" (no quoting at all !!!)
	* @param boolean/string $use_key If $use_key===True a "$key=" prefix each value (default), typically set to False
	*	or 'VALUES' for insert querys, on 'VALUES' "(key1,key2,...) VALUES (val1,val2,...)" is returned
	* @param array/boolean $only if set to an array only colums which are set (as data !!!) are written
	*	typicaly used to form a WHERE-clause from the primary keys.
	*	If set to True, only columns from the colum_definitons are written.
	* @param array/boolean $column_definitions this can be set to the column-definitions-array
	*	of your table ($tables_baseline[$table]['fd'] of the setup/tables_current.inc.php file).
	*	If its set, the column-type-data determinates if (int) or addslashes is used.
	* @return string SQL
	*/
	function column_data_implode($glue,$array,$use_key=True,$only=False,$column_definitions=False)
	{
		if (!is_array($array))	// this allows to give an SQL-string for delete or update
		{
			return $array;
		}
		if (!$column_definitions)
		{
			$column_definitions = $this->column_definitions;
		}
		if ($this->Debug) echo "<p>db::column_data_implode('$glue',".print_r($array,True).",'$use_key',".print_r($only,True).",<pre>".print_r($column_definitions,True)."</pre>\n";

		// do we need to truncate varchars to their max length (INSERT and UPDATE on Postgres)
		$truncate_varchar = $glue == ',' && $this->capabilities[self::CAPABILITY_REQUIRE_TRUNCATE_VARCHAR];

		$keys = $values = array();
		foreach($array as $key => $data)
		{
			if (is_int($key) || !$only || $only === True && isset($column_definitions[$key]) ||
				is_array($only) && in_array($key,$only))
			{
				$keys[] = $this->name_quote($key);

				if (!is_int($key) && is_array($column_definitions) && !isset($column_definitions[$key]))
				{
					// give a warning that we have no column-type
					$this->halt("db::column_data_implode('$glue',".print_r($array,True).",'$use_key',".print_r($only,True).",<pre>".print_r($column_definitions,True)."</pre><b>nothing known about column '$key'!</b>");
				}
				$column_type = is_array($column_definitions) ? @$column_definitions[$key]['type'] : False;
				$not_null = is_array($column_definitions) && isset($column_definitions[$key]['nullable']) ? !$column_definitions[$key]['nullable'] : false;

				if ($truncate_varchar)
				{
					$maxlength = $column_definitions[$key]['type'] == 'varchar' ? $column_definitions[$key]['precision'] : null;
				}
				if (is_array($data))
				{
					$or_null = '';
					foreach($data as $k => $v)
					{
						if (!$not_null && $use_key===True && is_null($v))
						{
							$or_null = $this->name_quote($key).' IS NULL)';
							unset($data[$k]);
							continue;
						}
						$data[$k] = $this->quote($v,$column_type,$not_null,$maxlength);
					}
					$values[] = ($or_null?'(':'').(!count($data) ? '' :
						($use_key===True ? $this->name_quote($key).' IN ' : '') .
						'('.implode(',',$data).')'.($or_null ? ' OR ' : '')).$or_null;
				}
				elseif (is_int($key) && $use_key===True)
				{
					$values[] = $data;
				}
				elseif ($glue != ',' && $use_key === True && !$not_null && is_null($data))
				{
					$values[] = $this->name_quote($key) .' IS NULL';
				}
				else
				{
					$values[] = ($use_key===True ? $this->name_quote($key) . '=' : '') . $this->quote($data,$column_type,$not_null,$maxlength);
				}
			}
		}
		return ($use_key==='VALUES' ? '('.implode(',',$keys).') VALUES (' : '').
			implode($glue,$values) . ($use_key==='VALUES' ? ')' : '');
	}

	/**
	* Sets the default column-definitions for use with column_data_implode()
	*
	* @author RalfBecker<at>outdoor-training.de
	*
	* @param array/boolean $column_definitions this can be set to the column-definitions-array
	*	of your table ($tables_baseline[$table]['fd'] of the setup/tables_current.inc.php file).
	*	If its set, the column-type-data determinates if (int) or addslashes is used.
	*/
	function set_column_definitions($column_definitions=False)
	{
		$this->column_definitions=$column_definitions;
	}

	/**
	 * Application name used by the API
	 *
	 */
	const API_APPNAME = 'phpgwapi';
	/**
	 * Default app, if no app specified in select, insert, delete, ...
	 *
	 * @var string
	 */
	private $app=self::API_APPNAME;

	/**
	 * Sets the application in which the db-class looks for table-defintions
	 *
	 * Used by table_definitions, insert, update, select, expression and delete. If the app is not set via set_app,
	 * it need to be set for these functions on every call
	 *
	 * @param string $app the app-name
	 */
	function set_app($app)
	{
		if ($this === $GLOBALS['egw']->db && $app != self::API_APPNAME)
		{
			// prevent that anyone switches the global db object to an other app
			throw new egw_exception_wrong_parameter('You are not allowed to call set_app for $GLOBALS[egw]->db or a refence to it, you have to clone it!');
		}
		$this->app = $app;
	}

	/**
	* reads the table-definitions from the app's setup/tables_current.inc.php file
	*
	* The already read table-definitions are shared between all db-instances via $GLOBALS['egw_info']['apps'][$app]['table_defs']
	*
	* @author RalfBecker<at>outdoor-training.de
	*
	* @param bool/string $app name of the app or default False to use the app set by db::set_app or the current app,
	*	true to search the already loaded table-definitions for $table
	* @param bool/string $table if set return only defintions of that table, else return all defintions
	* @return mixed array with table-defintions or False if file not found
	*/
	function get_table_definitions($app=False,$table=False)
	{
		if ($app === true && $table && isset($GLOBALS['egw_info']['apps']))
		{
			foreach($GLOBALS['egw_info']['apps'] as $app => &$app_data)
			{
				if (isset($app_data['table_defs'][$table]))
				{
					return $app_data['table_defs'][$table];
				}
			}
			$app = false;
		}
		if (!$app)
		{
			$app = $this->app ? $this->app : $GLOBALS['egw_info']['flags']['currentapp'];
		}
		if (isset($GLOBALS['egw_info']['apps']))	// dont set it, if it does not exist!!!
		{
			$this->app_data = &$GLOBALS['egw_info']['apps'][$app];
		}
		// this happens during the eGW startup or in setup
		else
		{
			$this->app_data =& $this->all_app_data[$app];
		}
		if (!isset($this->app_data['table_defs']))
		{
			$tables_current = EGW_INCLUDE_ROOT . "/$app/setup/tables_current.inc.php";
			if (!@file_exists($tables_current))
			{
				return $this->app_data['table_defs'] = False;
			}
			include($tables_current);
			$this->app_data['table_defs'] =& $phpgw_baseline;
			unset($phpgw_baseline);
		}
		if ($table && (!$this->app_data['table_defs'] || !isset($this->app_data['table_defs'][$table])))
		{
			if ($this->Debug) echo "<p>!!!get_table_definitions($app,$table) failed!!!</p>\n";
			return False;
		}
		if ($this->Debug) echo "<p>get_table_definitions($app,$table) succeeded</p>\n";
		return $table ? $this->app_data['table_defs'][$table] : $this->app_data['table_defs'];
	}

	/**
	 * Get specified attribute (default comment) of a colum or whole definition (if $attribute === null)
	 *
	 * Can be used static, in which case the global db object is used ($GLOBALS['egw']->db) and $app should be specified
	 *
	 * @param string $column name of column
	 * @param string $table name of table
	 * @param string $app=null app name or NULL to use $this->app, set via egw_db::set_app()
	 * @param string $attribute='comment' what field to return, NULL for array with all fields, default 'comment' to return the comment
	 * @return string|array NULL if table or column or attribute not found
	 */
	/* static */ function get_column_attribute($column,$table,$app=null,$attribute='comment')
	{
		static $cached_columns,$cached_table;	// some caching

		if ($cached_table !== $table || is_null($cached_columns))
		{
			$db = isset($this) ? $this : $GLOBALS['egw']->db;
			$table_def = $db->get_table_definitions($app,$table);
			$cached_columns = is_array($table_def) ? $table_def['fd'] : false;
		}
		if ($cached_columns === false) return null;

		return is_null($attribute) ? $cached_columns[$column] : $cached_columns[$column][$attribute];
	}

	/**
	* Insert a row of data into a table or updates it if $where is given, all data is quoted according to it's type
	*
	* @author RalfBecker<at>outdoor-training.de
	*
	* @param string $table name of the table
	* @param array $data with column-name / value pairs
	* @param mixed $where string with where clause or array with column-name / values pairs to check if a row with that keys already exists, or false for an unconditional insert
	*	if the row exists db::update is called else a new row with $date merged with $where gets inserted (data has precedence)
	* @param int $line line-number to pass to query
	* @param string $file file-name to pass to query
	* @param string/boolean $app string with name of app or False to use the current-app
	* @param bool $use_prepared_statement use a prepared statement
	* @param array/bool $table_def use this table definition. If False, the table definition will be read from tables_baseline
	* @return ADORecordSet or false, if the query fails
	*/
	function insert($table,$data,$where,$line,$file,$app=False,$use_prepared_statement=false,$table_def=False)
	{
		if ($this->Debug) echo "<p>db::insert('$table',".print_r($data,True).",".print_r($where,True).",$line,$file,'$app')</p>\n";

		if (!$table_def) $table_def = $this->get_table_definitions($app,$table);

		$sql_append = '';
		$cmd = 'INSERT';
		if (is_array($where) && count($where))
		{
			switch($this->Type)
			{
				case 'sapdb': case 'maxdb':
					$sql_append = ' UPDATE DUPLICATES';
					break;
				case 'mysql':
					// use replace if primary keys are included
					if (count(array_intersect(array_keys($where),(array)$table_def['pk'])) == count($table_def['pk']))
					{
						$cmd = 'REPLACE';
						break;
					}
					// fall through !!!
				default:
					$this->select($table,'count(*)',$where,$line,$file);
					if ($this->next_record() && $this->f(0))
					{
						return !!$this->update($table,$data,$where,$line,$file,$app);
					}
					break;
			}
			// the checked values need to be inserted too, value in data has precedence, also cant insert sql strings (numerical id)
			foreach($where as $column => $value)
			{
				if (!is_numeric($column) && !isset($data[$column]))
				{
					$data[$column] = $value;
				}
			}
		}
		if (self::$tablealiases && isset(self::$tablealiases[$table]))
		{
			$table = self::$tablealiases[$table];
		}
		$inputarr = false;
		if ($use_prepared_statement && $this->Link_ID->_bindInputArray)	// eg. MaxDB
		{
			$this->Link_ID->Param(false);	// reset param-counter
			$cols = array_keys($data);
			foreach($cols as $k => $col)
			{
				if (!isset($table_def['fd'][$col]))	// ignore columns not in this table
				{
					unset($cols[$k]);
					continue;
				}
				$params[] = $this->Link_ID->Param($col);
			}
			$sql = "$cmd INTO $table (".implode(',',$cols).') VALUES ('.implode(',',$params).')'.$sql_append;
			// check if we already prepared that statement
			if (!isset($this->prepared_sql[$sql]))
			{
				$this->prepared_sql[$sql] = $this->Link_ID->Prepare($sql);
			}
			$sql = $this->prepared_sql[$sql];
			$inputarr = &$data;
		}
		else
		{
			$sql = "$cmd INTO $table ".$this->column_data_implode(',',$data,'VALUES',true,$table_def['fd']).$sql_append;
		}
		if ($this->Debug) echo "<p>db::insert('$table',".print_r($data,True).",".print_r($where,True).",$line,$file,'$app') sql='$sql'</p>\n";
		return $this->query($sql,$line,$file,0,-1,$inputarr);
	}

	/**
	* Updates the data of one or more rows in a table, all data is quoted according to it's type
	*
	* @author RalfBecker<at>outdoor-training.de
	*
	* @param string $table name of the table
	* @param array $data with column-name / value pairs
	* @param array $where column-name / values pairs and'ed together for the where clause
	* @param int $line line-number to pass to query
	* @param string $file file-name to pass to query
	* @param string/boolean $app string with name of app or False to use the current-app
	* @param bool $use_prepared_statement use a prepared statement
	* @param array/bool $table_def use this table definition. If False, the table definition will be read from tables_baseline
	* @return ADORecordSet or false, if the query fails
	*/
	function update($table,$data,$where,$line,$file,$app=False,$use_prepared_statement=false,$table_def=False)
	{
		if ($this->Debug) echo "<p>db::update('$table',".print_r($data,true).','.print_r($where,true).",$line,$file,'$app')</p>\n";
		if (!$table_def) $table_def = $this->get_table_definitions($app,$table);

		$blobs2update = array();
		// SapDB/MaxDB cant update LONG columns / blob's: if a blob-column is included in the update we remember it in $blobs2update
		// and remove it from $data
		switch ($this->Type)
		{
			case 'sapdb':
			case 'maxdb':
				if ($use_prepared_statement) break;
				// check if data contains any LONG columns
				foreach($data as $col => $val)
				{
					switch ($table_def['fd'][$col]['type'])
					{
						case 'text':
						case 'longtext':
						case 'blob':
							$blobs2update[$col] = &$data[$col];
							unset($data[$col]);
							break;
					}
				}
				break;
		}
		$where = $this->column_data_implode(' AND ',$where,True,true,$table_def['fd']);

		if (self::$tablealiases && isset(self::$tablealiases[$table]))
		{
			$table = self::$tablealiases[$table];
		}
		if (count($data))
		{
			$inputarr = false;
			if ($use_prepared_statement && $this->Link_ID->_bindInputArray)	// eg. MaxDB
			{
				$this->Link_ID->Param(false);	// reset param-counter
				foreach($data as $col => $val)
				{
					if (!isset($table_def['fd'][$col])) continue;	// ignore columns not in this table
					$params[] = $this->name_quote($col).'='.$this->Link_ID->Param($col);
				}
				$sql = "UPDATE $table SET ".implode(',',$params).' WHERE '.$where;
				// check if we already prepared that statement
				if (!isset($this->prepared_sql[$sql]))
				{
					$this->prepared_sql[$sql] = $this->Link_ID->Prepare($sql);
				}
				$sql = $this->prepared_sql[$sql];
				$inputarr = &$data;
			}
			else
			{
				$sql = "UPDATE $table SET ".
					$this->column_data_implode(',',$data,True,true,$table_def['fd']).' WHERE '.$where;
			}
			$ret = $this->query($sql,$line,$file,0,-1,$inputarr);
			if ($this->Debug) echo "<p>db::query('$sql',$line,$file) = '$ret'</p>\n";
		}
		// if we have any blobs to update, we do so now
		if (($ret || !count($data)) && count($blobs2update))
		{
			foreach($blobs2update as $col => $val)
			{
				$ret = $this->Link_ID->UpdateBlob($table,$col,$val,$where,$table_def['fd'][$col]['type'] == 'blob' ? 'BLOB' : 'CLOB');
				if ($this->Debug) echo "<p>adodb::UpdateBlob('$table','$col','$val','$where') = '$ret'</p>\n";
				if (!$ret) $this->halt("Error in UpdateBlob($table,$col,\$val,$where)",$line,$file);
			}
		}
		return $ret;
	}

	/**
	* Deletes one or more rows in table, all data is quoted according to it's type
	*
	* @author RalfBecker<at>outdoor-training.de
	*
	* @param string $table name of the table
	* @param array $where column-name / values pairs and'ed together for the where clause
	* @param int $line line-number to pass to query
	* @param string $file file-name to pass to query
	* @param string/boolean $app string with name of app or False to use the current-app
	* @param array/bool $table_def use this table definition. If False, the table definition will be read from tables_baseline
	* @return ADORecordSet or false, if the query fails
	*/
	function delete($table,$where,$line,$file,$app=False,$table_def=False)
	{
		if (!$table_def) $table_def = $this->get_table_definitions($app,$table);

		if (self::$tablealiases && isset(self::$tablealiases[$table]))
		{
			$table = self::$tablealiases[$table];
		}
		$sql = "DELETE FROM $table WHERE ".
			$this->column_data_implode(' AND ',$where,True,False,$table_def['fd']);

		return $this->query($sql,$line,$file);
	}

	/**
	 * Formats and quotes a sql expression to be used eg. as where-clause
	 *
	 * The function has a variable number of arguments, from which the expession gets constructed
	 * eg. db::expression('my_table','(',array('name'=>"test'ed",'lang'=>'en'),') OR ',array('owner'=>array('',4,10)))
	 * gives "(name='test\'ed' AND lang='en') OR 'owner' IN (0,4,5,6,10)" if name,lang are strings and owner is an integer
	 *
	 * @param string/array $table_def table-name or definition array
	 * @param mixed $args variable number of arguments of the following types:
	 *	string: get's as is into the result
	 *	array:	column-name / value pairs: the value gets quoted according to the type of the column and prefixed
	 *		with column-name=, multiple pairs are AND'ed together, see db::column_data_implode
	 *	bool: If False or is_null($arg): the next 2 (!) arguments gets ignored
	 *
	 * Please note: As the function has a variable number of arguments, you CAN NOT add further parameters !!!
	 *
	 * @return string the expression generated from the arguments
	 */
	function expression($table_def,$args)
	{
		if (!is_array($table_def)) $table_def = $this->get_table_definitions(true,$table_def);
		$sql = '';
		$ignore_next = 0;
		foreach(func_get_args() as $n => $arg)
		{
			if ($n < 1) continue;	// table-name

			if ($ignore_next)
			{
				--$ignore_next;
				continue;
			}
			if (is_null($arg)) $arg = False;

			switch(gettype($arg))
			{
				case 'string':
					$sql .= $arg;
					break;
				case 'boolean':
					$ignore_next += !$arg ? 2 : 0;
					break;
				case 'array':
					$sql .= $this->column_data_implode(' AND ',$arg,True,False,$table_def['fd']);
					break;
			}
		}
		if ($this->Debug) echo "<p>db::expression($table,<pre>".print_r(func_get_args(),True)."</pre>) ='$sql'</p>\n";
		return $sql;
	}

	/**
	* Selects one or more rows in table depending on where, all data is quoted according to it's type
	*
	* @author RalfBecker<at>outdoor-training.de
	*
	* @param string $table name of the table
	* @param array/string $cols string or array of column-names / select-expressions
	* @param array/string $where string or array with column-name / values pairs AND'ed together for the where clause
	* @param int $line line-number to pass to query
	* @param string $file file-name to pass to query
	* @param int/bool $offset offset for a limited query or False (default)
	* @param string $append string to append to the end of the query, eg. ORDER BY ...
	* @param string/boolean $app string with name of app or False to use the current-app
	* @param int $num_rows number of rows to return if offset set, default 0 = use default in user prefs
	* @param string $join=null sql to do a join, added as is after the table-name, eg. ", table2 WHERE x=y" or
	*	"LEFT JOIN table2 ON (x=y)", Note: there's no quoting done on $join!
	* @param array/bool $table_def use this table definition. If False, the table definition will be read from tables_baseline
	* @param int $fetchmode=egw_db::FETCH_ASSOC egw_db::FETCH_BOTH (default), egw_db::FETCH_ASSOC or egw_db::FETCH_NUM
	* @return ADORecordSet or false, if the query fails
	*/
	function select($table,$cols,$where,$line,$file,$offset=False,$append='',$app=False,$num_rows=0,$join='',$table_def=False,$fetchmode=egw_db::FETCH_ASSOC)
	{
		if ($this->Debug) echo "<p>db::select('$table',".print_r($cols,True).",".print_r($where,True).",$line,$file,$offset,'$app',$num_rows,'$join')</p>\n";

		if (!$table_def) $table_def = $this->get_table_definitions($app,$table);
		if (is_array($cols))
		{
			$cols = implode(',',$cols);
		}
		if (is_array($where))
		{
			$where = $this->column_data_implode(' AND ',$where,True,False,$table_def['fd']);
		}
		if (self::$tablealiases && isset(self::$tablealiases[$table]))
		{
			$table = self::$tablealiases[$table];
		}
		$sql = "SELECT $cols FROM $table $join";

		// if we have a where clause, we need to add it together with the WHERE statement, if thats not in the join
		if ($where) $sql .= (strpos($join,"WHERE")!==false) ? ' AND ('.$where.')' : ' WHERE '.$where;

		if ($append) $sql .= ' '.$append;

		if ($this->Debug) echo "<p>sql='$sql'</p>";

		if ($line === false && $file === false)	// call by union, to return the sql rather then run the query
		{
			return $sql;
		}
		return $this->query($sql,$line,$file,$offset,$offset===False ? -1 : (int)$num_rows,false,$fetchmode);
	}

	/**
	* Does a union over multiple selects
	*
	* @author RalfBecker<at>outdoor-training.de
	*
	* @param array $selects array of selects, each select is an array with the possible keys/parameters: table, cols, where, append, app, join, table_def
	*	For further info about parameters see the definition of the select function, beside table, cols and where all other params are optional
	* @param int $line line-number to pass to query
	* @param string $file file-name to pass to query
	* @param string $order_by ORDER BY statement for the union
	* @param int/bool $offset offset for a limited query or False (default)
	* @param int $num_rows number of rows to return if offset set, default 0 = use default in user prefs
	* @param int $fetchmode=egw_db::FETCH_ASSOC egw_db::FETCH_BOTH (default), egw_db::FETCH_ASSOC or egw_db::FETCH_NUM
	* @return ADORecordSet or false, if the query fails
	*/
	function union($selects,$line,$file,$order_by='',$offset=false,$num_rows=0,$fetchmode=egw_db::FETCH_ASSOC)
	{
		if ($this->Debug) echo "<p>db::union(".print_r($selects,True).",$line,$file,$order_by,$offset,$num_rows)</p>\n";

		$sql = array();
		foreach($selects as $select)
		{
			$sql[] = call_user_func_array(array($this,'select'),array(
				$select['table'],
				$select['cols'],
				$select['where'],
				false,	// line
				false,	// file
				false,	// offset
				$select['append'],
				$select['app'],
				0,		// num_rows,
				$select['join'],
				$select['table_def'],
			));
		}
		$sql = count($sql) > 1 ? '(' . implode(")\nUNION\n(",$sql).')' : $sql[0];

		if ($order_by) $sql .=  (!stristr($order_by,'ORDER BY') ? "\nORDER BY " : '').$order_by;

		if ($this->Debug) echo "<p>sql='$sql'</p>";

		return $this->query($sql,$line,$file,$offset,$offset===False ? -1 : (int)$num_rows,false,$fetchmode);
	}

	/**
	 * Strip eg. a prefix from the keys of an array
	 *
	 * @param array $arr
	 * @param string/array $strip
	 * @return array
	 */
	static function strip_array_keys($arr,$strip)
	{
		$keys = array_keys($arr);

		return array_walk($keys,create_function('&$v,$k,$strip','$v = str_replace($strip,\'\',$v);'),$strip) ?
			array_combine($keys,$arr) : $arr;
	}
}
