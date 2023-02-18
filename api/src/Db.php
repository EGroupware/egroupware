<?php
/**
 * EGroupware API: Database abstraction library
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage db
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2003-19 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 */

namespace EGroupware\Api;

if(empty($GLOBALS['egw_info']['server']['db_type']))
{
	$GLOBALS['egw_info']['server']['db_type'] = 'mysql';
}

/**
 * Database abstraction library
 *
 * This allows eGroupWare to use multiple database backends via ADOdb or in future with PDO
 *
 * You only need to clone the global database object $GLOBALS['egw']->db if:
 * - you access an application table (non phpgwapi) and you want to call set_app()
 *
 * Otherwise you can simply use $GLOBALS['egw']->db or a reference to it.
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
 * Api\Db allows to use exceptions to catch sql-erros, not existing tables or failure to connect to the database, eg.:
 *		try {
 *			$this->db->connect();
 *			$num_config = $this->db->select(config::TABLE,'COUNT(config_name)',false,__LINE__,__FILE__)->fetchColumn();
 *		}
 *		catch(Exception $e) {
 *			echo "Connection to DB failed (".$e->getMessage().")!\n";
 *		}
 */
class Db
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
	 * @var boolean $readonly only allow readonly access to database
	 */
	var $readonly = false;

	/**
	* @var int $Debug enable debuging - 0 no, 1 yes
	*/
	var $Debug         = 0;

	/**
	 * Log update queries to error_log or file given below
	 *
	 * @var boolean|string|string[] true: all tables, table-name(s) or false: disabled
	 */
	var $log_updates = false;
	/**
	 * Only log update from given table(s)
	 *
	 * @var string|null string with filename eg. /var/lib/egroupware/sql-update.log or null to use error_log
	 */
	var $log_updates_to;

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

	/**
	 * @var array with values for keys "version" and "description"
	 */
	public $ServerInfo;

	/**
	 * ADOdb connection
	 *
	 * @var \ADOConnection
	 */
	var $Link_ID = 0;
	/**
	 * ADOdb connection is private / not the global one
	 *
	 * @var boolean
	 */
	var $privat_Link_ID = False;	// do we use a privat Link_ID or a reference to the global ADOdb object
	/**
	 * Global ADOdb connection
	 * @var \ADOConnection
	 */
	static public $ADOdb = null;

	/**
	 * Can be used to transparently convert tablenames, eg. 'mytable' => 'otherdb.othertable'
	 *
	 * Can be set eg. at the *end* of header.inc.php.
	 * Only works with new Api\Db methods (select, insert, update, delete) not query!
	 *
	 * @var array
	 */
	static $tablealiases = array();

	/**
	 * Callback to check if selected node is healty / should be used
	 *
	 * @var callback throwing Db\Exception\Connection, if connected node should NOT be used
	 */
	static $health_check;

	/**
	 * db allows sub-queries, true for everything but mysql < 4.1
	 *
	 * use like: if ($db->capabilities[self::CAPABILITY_SUB_QUERIES]) ...
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
	 * case insensitiv like statement (in $db->capabilities[self::CAPABILITY_CASE_INSENSITIV_LIKE]), default LIKE, ILIKE for postgres
	 */
	const CAPABILITY_CASE_INSENSITIV_LIKE = 'case_insensitive_like';
	/**
	 * DB requires varchar columns to be truncated to the max. size (eg. Postgres)
	 */
	const CAPABILITY_REQUIRE_TRUNCATE_VARCHAR = 'require_truncate_varchar';
	/**
	 * How to cast a column to varchar: CAST(%s AS varchar)
	 *
	 * MySQL requires to use CAST(%s AS char)!
	 *
	 * Use as: $sql = sprintf($GLOBALS['egw']->db->capabilities[self::CAPABILITY_CAST_AS_VARCHAR],$expression);
	 */
	const CAPABILITY_CAST_AS_VARCHAR = 'cast_as_varchar';
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
		self::CAPABILITY_REQUIRE_TRUNCATE_VARCHAR => true,
		self::CAPABILITY_CAST_AS_VARCHAR   => 'CAST(%s AS varchar)',
	);

	var $prepared_sql = array();	// sql is the index

	/**
	 * Constructor
	 *
	 * @param array $db_data =null values for keys 'db_name', 'db_host', 'db_port', 'db_user', 'db_pass', 'db_type', 'db_readonly'
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
				'readonly' => 'db_readonly',
			) as $var => $key)
			{
				$this->$var = $db_data[$key];
			}
		}
//if ($GLOBALS['egw_info']['server']['default_domain'] == 'ralfsmacbook.local') $this->query_log = '/tmp/query.log';
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
	 * Open a connection to a database
	 *
	 * @param string $Database name of database to use (optional)
	 * @param string $Host database host to connect to (optional)
	 * @param string $Port database port to connect to (optional)
	 * @param string $User name of database user (optional)
	 * @param string $Password password for database user (optional)
	 * @param string $Type type of database (optional)
	 * @throws Db\Exception\Connection
	 * @return \ADOConnection
	 */
	function connect($Database = NULL, $Host = NULL, $Port = NULL, $User = NULL, $Password = NULL, $Type = NULL)
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
		// on connection failure re-try with an other host
		// remembering in session which host we used last time
		$use_host_from_session = true;
		while(($host = $this->get_host(!$use_host_from_session)))
		{
			try {
				//error_log(__METHOD__."() this->Host(s)=$this->Host, n=$n --> host=$host");
				$new_connection = !$this->Link_ID || !$this->Link_ID->IsConnected();
				$this->_connect($host);
				// check if connected node is healthy
				if ($new_connection && self::$health_check)
				{
					call_user_func(self::$health_check, $this);
				}
				//error_log(__METHOD__."() host=$host, new_connection=$new_connection, this->Type=$this->Type, this->Host=$this->Host, wsrep_local_state=".array2string($state));
				return $this->Link_ID;
			}
			catch(Db\Exception\Connection $e) {
				//_egw_log_exception($e);
				$this->disconnect();	// force a new connect
				$this->Type = $this->setupType;	// get set to "mysql" for "mysqli"
				$use_host_from_session = false;	// re-try with next host from list
			}
		}
		if (!isset($e))
		{
			$e = new Db\Exception\Connection('No DB host set!');
		}
		throw $e;
	}

	/**
	 * Check if just connected Galera cluster node is healthy / fully operational
	 *
	 * A node in state "Donor/Desynced" will block updates at the end of a SST.
	 * Therefore we try to avoid that node, if we have an alternative.
	 *
	 * To enable this check add the following to your header.inc.php:
	 *
	 * require_once(EGW_INCLUDE_ROOT.'/api/src/Db.php');
	 * EGroupware\Api\Db::$health_check = array('EGroupware\Api\Db', 'galera_cluster_health');
	 *
	 * @param Db $db already connected Db instance to check
	 * @throws Db\Exception\Connection if node should NOT be used
	 */
	static function galera_cluster_health(Db $db)
	{
		if (($state = $db->query("SHOW STATUS WHERE Variable_name in ('wsrep_cluster_size','wsrep_local_state','wsrep_local_state_comment')",
			// GetAssoc in ADOdb 5.20 does not work with our default self::FETCH_BOTH
			__LINE__, __FILE__, 0, -1, false, self::FETCH_ASSOC)->GetAssoc()))
		{
			if ($state['wsrep_local_state_comment'] == 'Synced' ||
				// if we have only 2 nodes (2. one starting), we can only use the donor
				$state['wsrep_local_state_comment'] == 'Donor/Desynced' &&
					$state['wsrep_cluster_size'] == 2) return;

			throw new Db\Exception\Connection('Node is NOT Synced! '.array2string($state));
		}
	}

	/**
	 * Get one of multiple (semicolon-separated) DB-hosts to use
	 *
	 * Which host to use is cached in session, default is first one.
	 *
	 * @param boolean $next =false	true: move to next host
	 * @return boolean|string hostname or false, if already number-of-hosts plus 2 times called with $next == true
	 */
	public function get_host($next = false)
	{
		$hosts = explode(';', $this->Host[0] == '@' ? getenv(substr($this->Host, 1)) : $this->Host);
		$num_hosts = count($hosts);
		$n =& Cache::getSession(__CLASS__, $this->Host);
		if (!isset($n)) $n = 0;

		if ($next && ++$n >= $num_hosts+2)
		{
			$n = 0;	// start search again with default on next request
			$ret = false;
		}
		else
		{
			$ret = $hosts[$n % $num_hosts];
		}
		//error_log(__METHOD__."(next=".array2string($next).") n=$n returning ".array2string($ret));
		return $ret;
	}

	/**
	 * Connect to given host
	 *
	 * @param string $Host host to connect to
	 * @return \ADOConnection
	 * @throws Db\Exception\Connection
	 */
	protected function _connect($Host)
	{
		if (!$this->Link_ID || $Host != $this->Link_ID->host)
		{
			$Database = $User = $Password = $Port = $Type = '';
			foreach(array('Database','User','Password','Port','Type') as $name)
			{
				$$name = $this->$name;
				if (${$name}[0] == '@' && $name != 'Password') $$name = getenv(substr($$name, 1));
			}
			$this->setupType = $php_extension = $Type;

			switch($Type)	// convert to ADO db-type-names
			{
				case 'pgsql':
					$Type = 'postgres'; // name in ADOdb
					// create our own pgsql connection-string, to allow unix domain soccets if !$Host
					$Host = "dbname=$Database".($Host ? " host=$Host".($Port ? " port=$Port" : '') : '').
						" user=$User".($Password ? " password='".addslashes($Password)."'" : '');
					$User = $Password = $Database = '';	// to indicate $Host is a connection-string
					break;

				case 'odbc_mssql':
					$php_extension = 'odbc';
					$Type = 'mssql';
					// fall through
				case 'mssql':
					if ($Port) $Host .= ','.$Port;
					break;

				case 'odbc_oracle':
					$php_extension = 'odbc';
					$Type = 'oracle';
					break;
				case 'oracle':
					$php_extension = $Type = 'oci8';
					break;

				case 'sapdb':
					$Type = 'maxdb';
					// fall through
				case 'maxdb':
					$Type ='sapdb';	// name in ADOdb
					$php_extension = 'odbc';
					break;

				case 'mysqlt':
				case 'mysql':
					// if mysqli is available silently switch to it, mysql extension is deprecated and no longer available in php7+
					if (check_load_extension('mysqli'))
					{
						$php_extension = $Type = 'mysqli';
					}
					else
					{
						$php_extension = 'mysql';	// you can use $this->setupType to determine if it's mysqlt or mysql
					}
					// fall through
				case 'mysqli':
					$this->Type = 'mysql';		// need to be "mysql", so apps can check just for "mysql"!
					// fall through
				default:
					if ($Port) $Host .= ':'.$Port;
					break;
			}
			if (!isset(self::$ADOdb) ||	// we have no connection so far
				(is_object($GLOBALS['egw']->db) &&	// we connect to a different db, then the global one
					($this->Type != $GLOBALS['egw']->db->Type ||
					$this->Database != $GLOBALS['egw']->db->Database ||
					$this->User != $GLOBALS['egw']->db->User ||
					$this->Host != $GLOBALS['egw']->db->Host ||
					$this->Port != $GLOBALS['egw']->db->Port)))
			{
				if (!check_load_extension($php_extension))
				{
					throw new Db\Exception\Connection("Necessary php database support for $this->Type (".PHP_SHLIB_PREFIX.$php_extension.'.'.PHP_SHLIB_SUFFIX.") not loaded and can't be loaded, exiting !!!");
				}
				$this->Link_ID = ADONewConnection($Type);
				if (!isset(self::$ADOdb))	// use the global object to store the connection
				{
					self::$ADOdb = $this->Link_ID;
				}
				else
				{
					$this->privat_Link_ID = True;	// remember that we use a privat Link_ID for disconnect
				}
				if (!$this->Link_ID)
				{
					throw new Db\Exception\Connection("No ADOdb support for '$Type' ($this->Type) !!!");
				}
				if ($Type == 'mysqli')
				{
					// set a connection timeout of 1 second, to allow quicker failover to other db-nodes (default is 20s)
					$this->Link_ID->setConnectionParameter(MYSQLI_OPT_CONNECT_TIMEOUT, 1);
				}
				$connect = $GLOBALS['egw_info']['server']['db_persistent'] &&
					// do NOT attempt persistent connection, if it is switched off in php.ini (it will only cause a warning)
					($Type !== 'mysqli' || ini_get('mysqli.allow_persistent')) ?
					'PConnect' : 'Connect';

				try
				{
					if (($Ok = $this->Link_ID->$connect($Host, $User, $Password, $Database)))
					{
						$this->ServerInfo = $this->Link_ID->ServerInfo();
						$this->set_capabilities($Type, $this->ServerInfo['version']);

						// switch off MySQL 5.7+ ONLY_FULL_GROUP_BY sql_mode
						if (substr($this->Type, 0, 5) == 'mysql' && (float)$this->ServerInfo['version'] >= 5.7 && (float)$this->ServerInfo['version'] < 10.0)
						{
							$this->query("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))", __LINE__, __FILE__);
						}
					}
				}
				catch (\mysqli_sql_exception $e) {
					_egw_log_exception($e);
					$Ok = false;
				}
				if (!$Ok)
				{
					$Host = preg_replace('/password=[^ ]+/','password=$Password',$Host);	// eg. postgres dsn contains password
					throw new Db\Exception\Connection("ADOdb::$connect($Host, $User, \$Password, $Database) failed.");
				}
				if ($this->Debug)
				{
					echo function_backtrace();
					echo "<p>new ADOdb connection to $Type://$Host/$Database: Link_ID".($this->Link_ID === self::$ADOdb ? '===' : '!==')."self::\$ADOdb</p>";
					//echo "<p>".print_r($this->Link_ID->ServerInfo(),true)."</p>\n";
					_debug_array($this);
					echo "\$GLOBALS[egw]->db="; _debug_array($GLOBALS[egw]->db);
				}
				if ($Type == 'mssql')
				{
					// this is the format ADOdb expects
					$this->Link_ID->Execute('SET DATEFORMAT ymd');
					// sets the limit to the maximum
					ini_set('mssql.textlimit',2147483647);
					ini_set('mssql.sizelimit',2147483647);
				}
				// set our default charset
				$this->Link_ID->SetCharSet($this->Type == 'mysql' ? 'utf8' : 'utf-8');

				$new_connection = true;
			}
			else
			{
				$this->Link_ID = self::$ADOdb;
			}
		}
		if (!$this->Link_ID->isConnected() && !$this->Link_ID->Connect())
		{
			$Host = preg_replace('/password=[^ ]+/','password=$Password',$Host);	// eg. postgres dsn contains password
			throw new Db\Exception\Connection("ADOdb::$connect($Host, $User, \$Password, $Database) reconnect failed.");
		}
		// fix due to caching and reusing of connection not correctly set $this->Type == 'mysql'
		if ($this->Type == 'mysqli')
		{
			$this->setupType = $this->Type;
			$this->Type = 'mysql';
		}
		if (!empty($new_connection))
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
	 * Magic method called when object get's serialized
	 *
	 * We do NOT store Link_ID and private_Link_ID, as we need to reconnect anyway.
	 * This also ensures reevaluating environment-data or multiple hosts in connection-data!
	 *
	 * @return array
	 */
	function __sleep()
	{
		if (!empty($this->setupType)) $this->Type = $this->setupType;	// restore Type eg. to mysqli

		$vars = get_object_vars($this);
		unset($vars['Link_ID'], $vars['Query_ID'], $vars['privat_Link_ID']);
		return array_keys($vars);
	}

	/**
	 * changes defaults set in class-var $capabilities depending on db-type and -version
	 *
	 * @param string $adodb_driver mysql, postgres, mssql, sapdb, oci8
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
				$this->capabilities[self::CAPABILITY_CAST_AS_VARCHAR] = 'CAST(%s AS char)';
				break;

			case 'postgres':
				$this->capabilities[self::CAPABILITY_NAME_CASE] = 'lower';
				$this->capabilities[self::CAPABILITY_CLIENT_ENCODING] = (float) $db_version >= 7.4;
				$this->capabilities[self::CAPABILITY_OUTER_JOIN] = true;
				$this->capabilities[self::CAPABILITY_CASE_INSENSITIV_LIKE] = '::text ILIKE';
				$this->capabilities[self::CAPABILITY_REQUIRE_TRUNCATE_VARCHAR] = true;
				break;

			case 'mssql':
				$this->capabilities[self::CAPABILITY_DISTINCT_ON_TEXT] = false;
				$this->capabilities[self::CAPABILITY_ORDER_ON_TEXT] = 'CAST (%s AS varchar)';
				break;

			case 'maxdb':	// if Lim ever changes it to maxdb ;-)
			case 'sapdb':
				$this->capabilities[self::CAPABILITY_DISTINCT_ON_TEXT] = false;
				$this->capabilities[self::CAPABILITY_LIKE_ON_TEXT] = (float)$db_version >= 7.6;
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
			self::$ADOdb = null;
		}
		unset($this->Link_ID);
		$this->Link_ID = 0;

		if (!empty($this->setupType)) $this->Type = $this->setupType;
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
	public static function from_bool($val)
	{
		return $val && $val[0] !== 'f';	// everthing other then 0 or f[alse] is returned as true
	}

	/**
	* Execute a query
	*
	* @param string $Query_String the query to be executed
	* @param int $line the line method was called from - use __LINE__
	* @param string $file the file method was called from - use __FILE__
	* @param int $offset row to start from, default 0
	* @param int $num_rows number of rows to return (optional), default -1 = all, 0 will use $GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs']
	* @param array|boolean $inputarr array for binding variables to parameters or false (default)
	* @param int $fetchmode =self::FETCH_BOTH self::FETCH_BOTH (default), self::FETCH_ASSOC or self::FETCH_NUM
	* @param boolean $reconnect =true true: try reconnecting if server closes connection, false: dont (mysql only!)
	* @return ADORecordSet or false, if the query fails
	* @throws Db\Exception\InvalidSql with $this->Link_ID->ErrorNo() as code
	*/
	function query($Query_String, $line = '', $file = '', $offset=0, $num_rows=-1, $inputarr=false, $fetchmode=self::FETCH_BOTH, $reconnect=true)
	{
		if ($Query_String == '')
		{
			return 0;
		}
		if (!$this->Link_ID && !$this->connect())
		{
			return False;
		}

		if ($this->Link_ID->fetchMode != $fetchmode)
		{
			$this->Link_ID->SetFetchMode($fetchmode);
		}
		if (!$num_rows)
		{
			$num_rows = $GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs'];
		}
		if (($this->readonly || $this->log_updates === true) && !preg_match('/^\(?(SELECT|SET|SHOW)/i', $Query_String))
		{
			if ($this->log_updates === true)
			{
				$msg = $Query_String."\n".implode("\n", array_map(static function($level)
					{
						$args = substr(json_encode($level['args'], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE), 1, -1);
						if (strlen($args) > 120) $args = substr($args, 0, 120).'...';
						return str_replace(EGW_SERVER_ROOT.'/', '', $level['file']).'('.$level['line'].'): '.
							(empty($level['class']) ? '' : str_replace('EGroupware\\', '', $level['class']).$level['type']).$level['function'].'('.$args.')';
					}, debug_backtrace()));

				if (!empty($this->log_updates_to))
				{
					$msg = date('Y-m-d H:i:s: ').$_SERVER['REQUEST_METHOD'].' '.Framework::getUrl($_SERVER['REQUEST_URI'])."\n".$msg."\n".
						'User: '.$GLOBALS['egw_info']['user']['account_lid'].', User-agent: '.$_SERVER['HTTP_USER_AGENT']."\n\n";
				}
				error_log($msg, empty($this->log_updates_to) ? 0 : 3, $this->log_updates_to);
			}
			if ($this->readonly)
			{
				$this->Error = 'Database is readonly';
				$this->Errno = -2;
				return 0;
			}
		}
		try
		{
			if ($num_rows > 0)
			{
				$rs = $this->Link_ID->SelectLimit($Query_String, $num_rows, (int)$offset, $inputarr);
			}
			else
			{
				$rs = $this->Link_ID->Execute($Query_String, $inputarr);
			}
			$this->Errno  = $this->Link_ID->ErrorNo();
			$this->Error  = $this->Link_ID->ErrorMsg();
		}
		// PHP 8.1 mysqli throws its own exception
		catch(\mysqli_sql_exception $e) {
			if (!($reconnect && $this->Type == 'mysql' && ($e->getCode() == 2006 || $e->getMessage() === 'MySQL server has gone away')))
			{
				throw new Db\Exception($e->getMessage(), $e->getCode(), $e);
			}
			$this->Errno  = 2006;
			$this->Error  = $e->getMessage();
		}
		$this->Row = 0;

		if ($this->query_log && ($f = @fopen($this->query_log,'a+')))
		{
			fwrite($f,'['.(isset($GLOBALS['egw_setup']) ? $GLOBALS['egw_setup']->ConfigDomain : $GLOBALS['egw_info']['user']['domain']).'] ');
			fwrite($f,date('Y-m-d H:i:s ').$Query_String.($inputarr ? "\n".print_r($inputarr,true) : '')."\n");
			fwrite($f, function_backtrace()."\n");
			if (!$rs)
			{
				fwrite($f,"*** Error $this->Errno: $this->Error\n".function_backtrace()."\n");
			}
			fclose($f);
		}
		if (!$rs)
		{
			if ($reconnect && $this->Type == 'mysql' && $this->Errno == 2006)	// Server has gone away
			{
				$this->disconnect();
				return $this->query($Query_String, $line, $file, $offset, $num_rows, $inputarr, $fetchmode, false);
			}
			throw new Db\Exception\InvalidSql("Invalid SQL: ".(is_array($Query_String)?$Query_String[0]:$Query_String).
				"\n$this->Error ($this->Errno)".
				($inputarr ? "\nParameters: '".implode("','",$inputarr)."'":''), $this->Errno);
		}
		elseif(empty($rs->sql)) $rs->sql = $Query_String;
		return $rs;
	}

	/**
	* Execute a query with limited result set
	*
	* @param string $Query_String the query to be executed
	* @param int $offset row to start from, default 0
	* @param int $line the line method was called from - use __LINE__
	* @param string $file the file method was called from - use __FILE__
	* @param int $num_rows number of rows to return (optional), default -1 = all, 0 will use $GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs']
	* @param array|boolean $inputarr array for binding variables to parameters or false (default)
	* @return ADORecordSet or false, if the query fails
	*/
	function limit_query($Query_String, $offset, $line = '', $file = '', $num_rows = '',$inputarr=false)
	{
		return $this->query($Query_String,$line,$file,$offset,$num_rows,$inputarr);
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
	 * Lock a rows in table
	 *
	 * Will escalate and lock the table if row locking not supported.
	 * Will normally free the lock at the end of the transaction.
	 *
	 * @param string $table name of table to lock
	 * @param string $where ='true' where clause to use, eg: "WHERE row=12". Defaults to lock whole table.
	 * @param string $col ='1 as adodbignore'
	 */
	function row_lock($table, $where='true', $col='1 as adodbignore')
	{
		if (!$this->Link_ID && !$this->connect())
		{
			return False;
		}
		if (self::$tablealiases && isset(self::$tablealiases[$table]))
		{
			$table = self::$tablealiases[$table];
		}

		return $this->Link_ID->RowLock($table, $where, $col);
	}

	/**
	 * Commit changed rows in table
	 *
	 * @param string $table
	 * @return boolean
	 */
	function commit_lock($table)
	{
		if (!$this->Link_ID && !$this->connect())
		{
			return False;
		}
		if (self::$tablealiases && isset(self::$tablealiases[$table]))
		{
			$table = self::$tablealiases[$table];
		}

		return $this->Link_ID->CommitLock($table);
	}

	/**
	 * Unlock rows in table
	 *
	 * @param string $table
	 * @return boolean
	 */
	function rollback_lock($table)
	{
		if (!$this->Link_ID && !$this->connect())
		{
			return False;
		}
		if (self::$tablealiases && isset(self::$tablealiases[$table]))
		{
			$table = self::$tablealiases[$table];
		}

		return $this->Link_ID->RollbackLock($table);
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
			echo '<p>'.function_backtrace()."</p>\n";
			return -1;
		}
		return $id;
	}

	/**
	* Get the number of rows affected by last update or delete
	*
	* @return int number of rows
	*/
	function affected_rows()
	{
		if ($this->readonly) return 0;

		if (!$this->Link_ID && !$this->connect())
		{
			return False;
		}
		return $this->Link_ID->Affected_Rows();
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
			$flags = null;
			if($column->auto_increment) $flags .= "auto_increment ";
			if($column->primary_key) $flags .= "primary_key ";
			if($column->binary) $flags .= "binary ";

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
	 * @param boolean $just_name =false true return array of table-names, false return old format
	 * @return array list of the tables
	 */
	function table_names($just_name=false)
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
				$result[] = $just_name ? $table : array(
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
		foreach($this->query("SELECT relname FROM pg_class WHERE NOT relname ~ 'pg_.*' AND relkind ='i' ORDER BY relname") as $row)
		{
			$indices[] = array(
				'index_name'      => $row[0],
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
	* @param string $grant_host ='localhost' host/ip of the webserver
	*/
	function create_database($adminname = '', $adminpasswd = '', $charset='', $grant_host='localhost')
	{
		$currentUser = $this->User;
		$currentPassword = $this->Password;
		$currentDatabase = $this->Database;

		if ($adminname != '')
		{
			$this->User = $adminname;
			$this->Password = $adminpasswd;
			$this->Database = $this->Type == 'pgsql' ? 'template1' : 'mysql';
		}
		$this->disconnect();

		$sqls = array();
		switch ($this->Type)
		{
			case 'pgsql':
				$sqls[] = "CREATE DATABASE $currentDatabase";
				break;
			case 'mysql':
			case 'mysqli':
			case 'mysqlt':
				$create = "CREATE DATABASE `$currentDatabase`";
				if ($charset && isset($this->Link_ID->charset2mysql[$charset]) && (float) $this->ServerInfo['version'] >= 4.1)
				{
					$create .= ' DEFAULT CHARACTER SET '.$this->Link_ID->charset2mysql[$charset].';';
				}
				$sqls[] = $create;
				$sqls[] = "CREATE USER $currentUser@'$grant_host' IDENTIFIED BY ".$this->quote($currentPassword);
				$sqls[] = "GRANT ALL PRIVILEGES ON `$currentDatabase`.* TO $currentUser@'$grant_host'";
				break;
			default:
				throw new Exception\WrongParameter(__METHOD__."(user=$adminname, \$pw) not yet implemented for DB-type '$this->Type'");
		}
		//error_log(__METHOD__."() this->Type=$this->Type: sqls=".array2string($sqls));
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
	 * Set session timezone, to get automatic timestamps to be in our configured timezone
	 *
	 * @param string $timezone
	 * @return ?boolean
	 */
	public function setTimeZone($timezone)
	{
		if (!$this->Link_ID && !$this->connect())
		{
			return False;
		}
		switch ($this->Type)
		{
			case 'pgsql':
				$sql = 'SET TIME ZONE ' . $this->quote($timezone);
				break;
			case 'mysql':
			case 'mysqli':
				$sql = 'SET time_zone=' . $this->quote($timezone);
				break;
		}
		if (!empty($timezone) && !empty($sql))
		{
			try {
				$this->Link_ID->Execute($sql);
			}
			catch (\Exception $e) {
				// do NOT stall because DB does not know the TZ, report once per session
				if (empty($_SESSION[Session::EGW_APPSESSION_VAR][__CLASS__]['SQL-error-TZ']))
				{
					_egw_log_exception($e);
					$_SESSION[Session::EGW_APPSESSION_VAR][__CLASS__]['SQL-error-TZ'] = 'reported';
				}
				return false;
			}
			return true;
		}
	}

	/**
	 * concat a variable number of strings together, to be used in a query
	 *
	 * Example: $db->concat($db->quote('Hallo '),'username') would return
	 *	for mysql "concat('Hallo ',username)" or "'Hallo ' || username" for postgres
	 * @param string $str1 already quoted stringliteral or column-name, variable number of arguments
	 * @return string to be used in a query
	 */
	function concat(/*$str1, ...*/)
	{
		$args = func_get_args();

		if (!$this->Link_ID && !$this->connect())
		{
			return False;
		}
		return call_user_func_array(array(&$this->Link_ID,'concat'),$args);
	}

	/**
	 * Concat grouped values of an expression with optional order and separator
	 *
	 * @param string $expr column-name or expression optional prefixed with "DISTINCT"
	 * @param string $order_by ='' optional order
	 * @param string $separator =',' optional separator, default is comma
	 * @return string|boolean false if not supported by dbms
	 */
	function group_concat($expr, $order_by='', $separator=',')
	{
		switch($this->Type)
		{
			case 'mysqli':
			case 'mysql':
				$sql = 'GROUP_CONCAT('.$expr;
				if ($order_by) $sql .= ' ORDER BY '.$order_by;
				if ($separator != ',') $sql .= ' SEPARATOR '.$this->quote($separator);
				$sql .= ')';
				break;

			case 'pgsql':	// requires for Postgresql < 8.4 to have a custom ARRAY_AGG method installed!
				if ($this->Type == 'pgsql' && (float)$this->ServerInfo['version'] < 8.4)
				{
					return false;
				}
				$sql = 'ARRAY_TO_STRING(ARRAY_AGG('.$expr;
				if ($order_by) $sql .= ' ORDER BY '.$order_by;
				$sql .= '), '.$this->quote($separator).')';
				break;

			default:	// probably gives an sql error anyway
				return false;
		}
		return $sql;
	}

	/**
	 * Calls REGEXP_REPLACE if available for the DB otherwise returns just $expr
	 *
	 * Example: REGEXP_REPLACE('tel_work', '[^0-9]', "''") to remove non-numbers from tel_work column
	 *
	 * @param string $expr SQL expression, must be quoted for strings, eg. "'string'"
	 * @param string $regexp no quotes neccessary will be run through quotes()
	 * @param string $with SQL expression, must be quoted for strings, eg. "'string'"
	 * @return string SQL REGEXP_REPLACE() function or $expr, if not supported!
	 */
	function regexp_replace($expr, $regexp, $with)
	{
		switch($this->Type)
		{
			case 'mysqli':
			case 'mysql':
				if ((float)$this->ServerInfo['version'] < 8.0) break;	// MySQL 8.0 or MariaDB 10.0 required
				// fall through
			case 'pgsql':
				return 'REGEXP_REPLACE('.$expr.','.$this->quote($regexp).','.$with.')';
		}
		return $expr;
	}

	/**
	 * SQL returning character (not byte!) positions for $substr in $str
	 *
	 * @param string $str
	 * @param string $substr
	 * @return string SQL returning character (not byte!) positions for $substr in $str
	 */
	function strpos($str, $substr)
	{
		switch($this->Type)
		{
			case 'mysql':
				return "LOCATE($substr,$str)";
			case 'pgsql':
				return "STRPOS($str,$substr)";
			case 'mssql':
				return "CHARINDEX($substr,$str)";
		}
		die(__METHOD__." not implemented for DB type '$this->Type'!");
	}

	/**
	 * Convert a DB specific timestamp in a unix timestamp stored as integer, like MySQL: UNIX_TIMESTAMP(ts)
	 *
	 * @param string $expr name of an integer column or integer expression
	 * @return string SQL expression of type timestamp
	 */
	function unix_timestamp($expr)
	{
		switch($this->Type)
		{
			case 'mysql':
				return "UNIX_TIMESTAMP($expr)";

			case 'pgsql':
				return "EXTRACT(EPOCH FROM CAST($expr AS TIMESTAMP))";

			case 'mssql':
				return "DATEDIFF(second,'1970-01-01',($expr))";
		}
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
				return "(TIMESTAMP WITH TIME ZONE 'epoch' + ($expr) * INTERVAL '1 sec')";

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
	 * Cast a column or sql expression to integer, necessary at least for postgreSQL or MySQL for sorting
	 *
	 * @param string $expr
	 * @return string
	 */
	function to_double($expr)
	{
		switch($this->Type)
		{
			case 'pgsql':
				return $expr.'::double';
			case 'mysql':
				return 'CAST('.$expr.' AS DECIMAL(24,3))';
		}
		return $expr;
	}

	/**
	 * Cast a column or sql expression to integer, necessary at least for postgreSQL
	 *
	 * @param string $expr
	 * @return string
	 */
	function to_int($expr)
	{
		switch($this->Type)
		{
			case 'pgsql':
				return $expr.'::integer';
			case 'mysql':
				return 'CAST('.$expr.' AS SIGNED)';
		}
		return $expr;
	}

	/**
	 * Cast a column or sql expression to varchar, necessary at least for postgreSQL
	 *
	 * @param string $expr
	 * @return string
	 */
	function to_varchar($expr)
	{
		switch($this->Type)
		{
			case 'pgsql':
				return 'CAST('.$expr.' AS varchar)';
		}
		return $expr;
	}

	/**
	* Correctly Quote Identifiers like table- or colmnnames for use in SQL-statements
	*
	* This is mostly copy & paste from adodb's datadict class
	* @param string $_name
	* @return string quoted string
	*/
	function name_quote($_name = NULL)
	{
		if (!is_string($_name))
		{
			return false;
		}

		$name = trim($_name);

		if (!$this->Link_ID && !$this->connect())
		{
			return false;
		}

		$quote = $this->Link_ID->nameQuote;
		$type = $this->Type;

		// if name is of the form `name`, remove MySQL quotes and leave it to automatic below
		if ($name[0] === '`' && substr($name, -1) === '`')
		{
			$name = substr($name, 1, -1);
		}

		$quoted = array_map(function($name) use ($quote, $type)
		{
			// if name contains special characters, quote it
			// always quote for postgreSQL, as this is the only way to support mixed case names
			if (preg_match('/\W/', $name) || $type == 'pgsql' && preg_match('/[A-Z]+/', $name) || $name == 'index')
			{
				return $quote . $name . $quote;
			}
			return $name;
		}, explode('.', $name));

		return implode('.', $quoted);
	}

	/**
	* Escape values before sending them to the database - prevents SQL injection and SQL errors ;-)
	*
	* Please note that the quote function already returns necessary quotes: quote('Hello') === "'Hello'".
	* Int and Auto types are casted to int: quote('1','int') === 1, quote('','int') === 0, quote('Hello','int') === 0
	* Arrays of id's stored in strings: quote(array(1,2,3),'string') === "'1,2,3'"
	*
	* @param mixed $value the value to be escaped
	* @param string|boolean $type =false string the type of the db-column, default False === varchar
	* @param boolean $not_null =true is column NOT NULL, default true, else php null values are written as SQL NULL
	* @param int $length =null length of the varchar column, to truncate it if the database requires it (eg. Postgres)
	* @param string $glue =',' used to glue array values together for the string type
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
				// if DateTime object given, set server-timezone and format it as EGroupware timestamp with offset
				if (is_object($value) && ($value instanceof \DateTime))
				{
					return DateTime::user2server($value,'ts');
				}
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
				// if DateTime object given, set server-timezone and format it as string
				if (is_object($value) && ($value instanceof \DateTime))
				{
					return $this->Link_ID->qstr(DateTime::user2server($value,'Y-m-d'));
				}
				return $this->Link_ID->DBDate($value);
			case 'timestamp':
				// if DateTime object given, set server-timezone and format it as string
				if (is_object($value) && ($value instanceof \DateTime))
				{
					return $this->Link_ID->qstr(DateTime::user2server($value,'Y-m-d H:i:s'));
				}
				return $this->Link_ID->DBTimeStamp($value);
		}
		if (is_array($value))
		{
			$value = implode($glue,$value);
		}
		// timestamp given das DateTime object stored in string column, e.g. in history-log
		if (is_object($value) && ($value instanceof \DateTime))
		{
			$value = DateTime::user2server($value,'string');
		}
		// truncate to long strings for varchar(X) columns as PostgreSQL and newer MySQL/MariaDB given an error otherwise
		if (isset($length) && isset($value) && mb_strlen($value) > $length)
		{
			$value = mb_substr($value, 0, $length);
		}
		// casting boolean explicitly to string, as ADODB_postgres64::qstr() has an unwanted special handling
		// for boolean types, causing it to return "true" or "false" and not a quoted string like "'1'"!
		if (is_bool($value)) $value = (string)$value;

		// MySQL and MariaDB not 10.1 need 4-byte utf8 chars replaced with our default utf8 charset
		// (MariaDB 10.1 does the replacement automatic, 10.0 cuts everything off behind and MySQL gives an error)
		// (MariaDB 10.3 gives an error too: Incorrect string value: '\xF0\x9F\x98\x8A\x0AW...')
		// Changing charset to utf8mb4 requires schema update, shortening of some indexes and probably have negative impact on performace!
		if (isset($value) && substr($this->Type, 0, 5) === 'mysql')
		{
			$value = preg_replace('/[\x{10000}-\x{10FFFF}]/u', "\xEF\xBF\xBD", $value);
		}

		// need to cast to string, as ADOdb 5.20 would return NULL instead of '' for NULL, causing us to write that into NOT NULL columns
		return $this->Link_ID->qstr((string)$value);
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
	* @param boolean|string $use_key If $use_key===True a "$key=" prefix each value (default), typically set to False
	*	or 'VALUES' for insert querys, on 'VALUES' "(key1,key2,...) VALUES (val1,val2,...)" is returned
	* @param array|boolean $only if set to an array only colums which are set (as data !!!) are written
	*	typicaly used to form a WHERE-clause from the primary keys.
	*	If set to True, only columns from the colum_definitons are written.
	* @param array|boolean $column_definitions this can be set to the column-definitions-array
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
		if (empty($column_definitions))
		{
			$column_definitions = $this->column_definitions ?? null;
		}
		if ($this->Debug) echo "<p>db::column_data_implode('$glue',".print_r($array,True).",'$use_key',".print_r($only,True).",<pre>".print_r($column_definitions,True)."</pre>\n";

		// do we need to truncate varchars to their max length (INSERT and UPDATE on Postgres)
		$truncate_varchar = $glue == ',' && $this->capabilities[self::CAPABILITY_REQUIRE_TRUNCATE_VARCHAR];

		$keys = $values = array();
		foreach($array as $key => $data)
		{
			if (is_int($key) && $use_key !== 'VALUES' || !$only || $only === True && isset($column_definitions[$key]) ||
				is_array($only) && in_array($key,$only))
			{
				$keys[] = $this->name_quote($key);

				$col = $key;
				// fix "table.column" expressions, to not trigger exception, if column alone would work
				if (!is_int($key) && is_array($column_definitions) && !isset($column_definitions[$key]))
				{
					if (strpos($key, '.') !== false) list(, $col) = explode('.', $key);
					if (!isset($column_definitions[$col]))
					{
						throw new Db\Exception\InvalidSql("db::column_data_implode('$glue',".print_r($array,True).",'$use_key',".print_r($only,True).",<pre>".print_r($column_definitions,True)."</pre><b>nothing known about column '$key'!</b>");
					}
				}
				$column_type = is_array($column_definitions) ? ($column_definitions[$col]['type'] ?? false) : False;
				$not_null = is_array($column_definitions) && isset($column_definitions[$col]['nullable']) ? !$column_definitions[$col]['nullable'] : false;

				$maxlength = null;
				if ($truncate_varchar && !is_int($col) && isset($column_definitions[$col]) &&
					in_array($column_definitions[$col]['type'], ['varchar','ascii']))
				{
					$maxlength = $column_definitions[$col]['precision'];
				}
				// dont use IN ( ), if there's only one value, it's slower for MySQL
				if (is_array($data) && count($data) <= 1)
				{
					$data = array_shift($data);
				}
				// array for SET or VALUES, not WHERE --> automatic store comma-separated
				if (is_array($data) && $glue === ',' && in_array($column_type, ['varchar','ascii']))
				{
					$data = implode(',', $data);
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
					$values[] = ($or_null?'(':'').(!count($data) ?
						// empty array on insert/update, store as NULL, or if not allowed whatever value NULL is casted to
						$this->quote(null, $column_type, $not_null) :
						($use_key===True ? $this->name_quote($key).' IN ' : '') .
						'('.implode(',',$data).')'.($or_null ? ' OR ' : '')).$or_null;
				}
				elseif (is_int($key) && $use_key===True)
				{
					if (empty($data)) continue;	// would give SQL error
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
	* @param array|boolean $column_definitions this can be set to the column-definitions-array
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
	const API_APPNAME = 'api';
	/**
	 * Default app, if no app specified in select, insert, delete, ...
	 *
	 * @var string
	 */
	protected $app=self::API_APPNAME;

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
		// ease the transition to api
		if ($app == 'phpgwapi') $app = 'api';

		if ($this === $GLOBALS['egw']->db && $app != self::API_APPNAME)
		{
			// prevent that anyone switches the global db object to an other app
			throw new Exception\WrongParameter('You are not allowed to call set_app for $GLOBALS[egw]->db or a refence to it, you have to clone it!');
		}
		$this->app = $app;
	}

	/**
	 * Data used by (get|set)_table_defintion and get_column_attribute
	 *
	 * @var array
	 */
	protected static $all_app_data = array();

	/**
	 * Set/changes definition of one table
	 *
	 * If you set or change defition of a single table of an app, other tables
	 * are not loaded from $app/setup/tables_current.inc.php!
	 *
	 * @param string $app name of the app $table belongs too
	 * @param string $table table name
	 * @param array $definition table definition
	 */
	public static function set_table_definitions($app, $table, array $definition)
	{
		self::$all_app_data[$app][$table] = $definition;
	}

	/**
	* reads the table-definitions from the app's setup/tables_current.inc.php file
	*
	* The already read table-definitions are shared between all db-instances via a static var.
	*
	* @author RalfBecker<at>outdoor-training.de
	*
	* @param bool|string $app name of the app or default False to use the app set by db::set_app or the current app,
	*	true to search the already loaded table-definitions for $table and then search all existing apps for it
	* @param bool|string $table if set return only defintions of that table, else return all defintions
	* @return mixed array with table-defintions or False if file not found
	*/
	function get_table_definitions($app=False,$table=False)
	{
		// ease the transition to api
		if ($app === 'phpgwapi') $app = 'api';

		if ($app === true && $table)
		{
			foreach(self::$all_app_data as $app => &$app_data)
			{
				if (isset($app_data[$table]))
				{
					return $app_data[$table];
				}
			}
			// $table not found in loaded apps, check not yet loaded ones
			foreach(scandir(EGW_INCLUDE_ROOT) as $app)
			{
				if ($app[0] == '.' || !is_dir(EGW_INCLUDE_ROOT.'/'.$app) || isset(self::$all_app_data[$app]))
				{
					continue;
				}
				$tables_current = EGW_INCLUDE_ROOT . "/$app/setup/tables_current.inc.php";
				if (!@file_exists($tables_current))
				{
					self::$all_app_data[$app] = False;
				}
				else
				{
					$phpgw_baseline = null;
					include($tables_current);
					self::$all_app_data[$app] =& $phpgw_baseline;
					unset($phpgw_baseline);

					if (isset(self::$all_app_data[$app][$table]))
					{
						return self::$all_app_data[$app][$table];
					}
				}
			}
			$app = false;
		}
		if (!$app)
		{
			$app = $this->app ? $this->app : $GLOBALS['egw_info']['flags']['currentapp'];
		}
		$app_data =& self::$all_app_data[$app];

		if (!isset($app_data))
		{
			$tables_current = EGW_INCLUDE_ROOT . "/$app/setup/tables_current.inc.php";
			if (!@file_exists($tables_current))
			{
				return $app_data = False;
			}
			include($tables_current);
			$app_data =& $phpgw_baseline;
			unset($phpgw_baseline);
		}
		if ($table && (!$app_data || !isset($app_data[$table])))
		{
			if ($this->Debug) echo "<p>!!!get_table_definitions($app,$table) failed!!!</p>\n";
			return False;
		}
		if ($this->Debug) echo "<p>get_table_definitions($app,$table) succeeded</p>\n";
		return $table ? $app_data[$table] : $app_data;
	}

	/**
	 * Get specified attribute (default comment) of a colum or whole definition (if $attribute === null)
	 *
	 * Can be used static, in which case the global db object is used ($GLOBALS['egw']->db) and $app should be specified
	 *
	 * @param string $column name of column
	 * @param string $table name of table
	 * @param string $app=null app name or NULL to use $this->app, set via self::set_app()
	 * @param string $attribute='comment' what field to return, NULL for array with all fields, default 'comment' to return the comment
	 * @return string|array NULL if table or column or attribute not found
	 */
	/* static */ function get_column_attribute($column,$table,$app=null,$attribute='comment')
	{
		static $cached_columns=null,$cached_table=null;	// some caching

		if ($cached_table !== $table || is_null($cached_columns))
		{
			$db = isset($this) && is_a($this, __CLASS__) ? $this : $GLOBALS['egw']->db;
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
	* @param string|boolean $app string with name of app or False to use the current-app
	* @param bool $use_prepared_statement use a prepared statement
	* @param array|bool $table_def use this table definition. If False, the table definition will be read from tables_baseline
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
					if ($this->select($table,'count(*)',$where,$line,$file)->fetchColumn())
					{
						return !!$this->update($table,$data,$where,$line,$file,$app,$use_prepared_statement,$table_def);
					}
					break;
			}
			// the checked values need to be inserted too, value in data has precedence, also cant insert sql strings (numerical id)
			foreach($where as $column => $value)
			{
				if (!is_numeric($column) && !isset($data[$column]) &&
					// skip auto-id of 0 or NULL, as PostgreSQL does NOT create an auto-id, if they are given
					!(!$value && count($table_def['pk']) == 1 && $column == $table_def['pk'][0]))
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
		if (isset($data[0]) && is_array($data[0]))	// multiple data rows
		{
			if ($where) throw new Exception\WrongParameter('Can NOT use $where together with multiple data rows in $data!');

			$sql = "$cmd INTO $table ";
			foreach($data as $k => $d)
			{
				if (!$k)
				{
					$sql .= $this->column_data_implode(',',$d,'VALUES',true,$table_def['fd']);
				}
				else
				{
					$sql .= ",\n(".$this->column_data_implode(',',$d,false,true,$table_def['fd']).')';
				}
			}
			$sql .= $sql_append;
		}
		elseif ($use_prepared_statement && $this->Link_ID->_bindInputArray)	// eg. MaxDB
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

		if (!is_bool($this->log_updates) && in_array($table, (array)$this->log_updates))
		{
			$backup = $this->log_updates;
			$this->log_updates = true;
			$ret = $this->query($sql,$line,$file,0,-1,$inputarr);
			$this->log_updates = $backup;
			return $ret;
		}
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
	* @param string|boolean $app string with name of app or False to use the current-app
	* @param bool $use_prepared_statement use a prepared statement
	* @param array|bool $table_def use this table definition. If False, the table definition will be read from tables_baseline
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
		$where_str = $this->column_data_implode(' AND ',$where,True,true,$table_def['fd']);

		if (self::$tablealiases && isset(self::$tablealiases[$table]))
		{
			$table = self::$tablealiases[$table];
		}
		if (!empty($data))
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
				$sql = "UPDATE $table SET ".implode(',',$params).' WHERE '.$where_str;
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
					$this->column_data_implode(',',$data,True,true,$table_def['fd']).' WHERE '.$where_str;
			}
			if (!is_bool($this->log_updates) && in_array($table, (array)$this->log_updates))
			{
				$backup = $this->log_updates;
				$this->log_updates = true;
				$ret = $this->query($sql,$line,$file,0,-1,$inputarr);
				$this->log_updates = $backup;
			}
			else
			{
				$ret = $this->query($sql,$line,$file,0,-1,$inputarr);
			}
			if ($this->Debug) echo "<p>db::query('$sql',$line,$file)</p>\n";
		}
		// if we have any blobs to update, we do so now
		if (($ret || !count($data)) && count($blobs2update))
		{
			foreach($blobs2update as $col => $val)
			{
				$ret = $this->Link_ID->UpdateBlob($table,$col,$val,$where_str,$table_def['fd'][$col]['type'] == 'blob' ? 'BLOB' : 'CLOB');
				if ($this->Debug) echo "<p>adodb::UpdateBlob('$table','$col','$val','$where_str') = '$ret'</p>\n";
				if (!$ret) throw new Db\Exception\InvalidSql("Error in UpdateBlob($table,$col,\$val,$where_str)",$line,$file);
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
	* @param string|boolean $app string with name of app or False to use the current-app
	* @param array|bool $table_def use this table definition. If False, the table definition will be read from tables_baseline
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

		if (!is_bool($this->log_updates) && in_array($table, (array)$this->log_updates))
		{
			$backup = $this->log_updates;
			$this->log_updates = true;
			$ret = $this->query($sql,$line,$file);
			$this->log_updates = $backup;
			return $ret;
		}
		return $this->query($sql,$line,$file);
	}

	/**
	 * Formats and quotes a sql expression to be used eg. as where-clause
	 *
	 * The function has a variable number of arguments, from which the expession gets constructed
	 * eg. db::expression('my_table','(',array('name'=>"test'ed",'lang'=>'en'),') OR ',array('owner'=>array('',4,10)))
	 * gives "(name='test\'ed' AND lang='en') OR 'owner' IN (0,4,5,6,10)" if name,lang are strings and owner is an integer
	 *
	 * @param string|array $table_def table-name or definition array
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
	function expression($table_def/*,$args, ...*/)
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
		return $sql;
	}

	/**
	* Selects one or more rows in table depending on where, all data is quoted according to it's type
	*
	* @author RalfBecker<at>outdoor-training.de
	*
	* @param string $table name of the table
	* @param array|string $cols string or array of column-names / select-expressions
	* @param array|string $where string or array with column-name / values pairs AND'ed together for the where clause
	* @param int $line line-number to pass to query
	* @param string $file file-name to pass to query
	* @param int|bool $offset offset for a limited query or False (default)
	* @param string $append string to append to the end of the query, eg. ORDER BY ...
	* @param string|boolean $app string with name of app or False to use the current-app
	* @param int $num_rows number of rows to return if offset set, default 0 = use default in user prefs
	* @param string $join =null sql to do a join, added as is after the table-name, eg. ", table2 WHERE x=y" or
	*	"LEFT JOIN table2 ON (x=y)", Note: there's no quoting done on $join!
	* @param array|bool $table_def use this table definition. If False, the table definition will be read from tables_baseline
	* @param int $fetchmode =self::FETCH_ASSOC self::FETCH_ASSOC (default), self::FETCH_BOTH or self::FETCH_NUM
	* @return ADORecordSet or false, if the query fails
	*/
	function select($table,$cols,$where,$line,$file,$offset=False,$append='',$app=False,$num_rows=0,$join='',$table_def=False,$fetchmode=self::FETCH_ASSOC)
	{
		if ($this->Debug) echo "<p>db::select('$table',".print_r($cols,True).",".print_r($where,True).",$line,$file,$offset,'$app',$num_rows,'$join')</p>\n";

		if (!$table_def) $table_def = $this->get_table_definitions($app,$table);
		if (is_array($cols))
		{
			$cols = implode(',',$cols);
		}
		if (is_array($where))
		{
			$where = $this->column_data_implode(' AND ',$where,True,False, $table_def ? $table_def['fd'] : null);
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
	* @param int|bool $offset offset for a limited query or False (default)
	* @param int $num_rows number of rows to return if offset set, default 0 = use default in user prefs
	* @param int $fetchmode =self::FETCH_ASSOC self::FETCH_ASSOC (default), self::FETCH_BOTH or self::FETCH_NUM
	* @return ADORecordSet or false, if the query fails
	*/
	function union($selects,$line,$file,$order_by='',$offset=false,$num_rows=0,$fetchmode=self::FETCH_ASSOC)
	{
		if ($this->Debug) echo "<p>db::union(".print_r($selects,True).",$line,$file,$order_by,$offset,$num_rows)</p>\n";

		$union = array();
		foreach($selects as $select)
		{
			$union[] = call_user_func_array(array($this,'select'),array(
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
		$sql = count($union) > 1 ? '(' . implode(")\nUNION\n(",$union).')' : 'SELECT DISTINCT'.substr($union[0],6);

		if ($order_by) $sql .=  (!stristr($order_by,'ORDER BY') ? "\nORDER BY " : '').$order_by;

		if ($this->Debug) echo "<p>sql='$sql'</p>";

		return $this->query($sql,$line,$file,$offset,$offset===False ? -1 : (int)$num_rows,false,$fetchmode);
	}

	/**
	 * Strip eg. a prefix from the keys of an array
	 *
	 * @param array $arr
	 * @param string|array $strip
	 * @return array
	 */
	static function strip_array_keys($arr,$strip)
	{
		$keys = array_keys($arr);

		return array_walk($keys, function(&$v, $k, $strip)
		{
			unset($k);	// not used, but required by function signature
			$v = str_replace($strip, '', $v);
		}, $strip) ?
			array_combine($keys,$arr) : $arr;
	}
}