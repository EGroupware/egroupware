<?php
/**
 * eGroupWare API: Database backups
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage db
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2003-8 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

/**
 * DB independent backup and restore of eGW's DB
 */
class db_backup
{
	/**
	 * replaces backslashes, used in cvs_split
	 */
	const BACKSLASH_TOKEN = '##!!**bAcKsLaSh**!!##';
	/**
	 * Reference to schema_proc
	 *
	 * @var schema_proc
	 */
	var $schema_proc;
	/**
	 * Reference to ADOdb (connection) object
	 *
	 * @var ADOConnection
	 */
	var $adodb;
	/**
	 * DB schemas, as array tablename => schema
	 *
	 * @var array
	 */
	var $schemas = array();
	/**
	 * Tables to exclude from the backup: sessions, diverse caches which get automatic rebuild
	 *
	 * @var array
	 */
	var $exclude_tables = array(
		'egw_sessions','egw_app_sessions','phpgw_sessions','phpgw_app_sessions',	// eGW's session-tables
		'phpgw_anglemail',	// email's cache
		'egw_felamimail_cache','egw_felamimail_folderstatus','phpgw_felamimail_cache','phpgw_felamimail_folderstatus',	// felamimail's cache
	);
	/**
	 * regular expression to identify system-tables => ignored for schema+backup
	 *
	 * @var string|boolean
	 */
	var $system_tables = false;
	/**
	 * regurar expression to identify eGW tables => if set only they are used
	 *
	 * @var string|boolean
	 */
	var $egw_tables = false;

	/**
	 * Constructor
	 */
	function __construct()
	{
		if (isset($GLOBALS['egw_setup']) && is_object($GLOBALS['egw_setup']) && !isset($GLOBALS['egw_setup']->db))
		{
			$GLOBALS['egw_setup']->loaddb();	// we run inside setup, but db object is not loaded
		}
		if (isset($GLOBALS['egw_setup']->oProc) && is_object($GLOBALS['egw_setup']->oProc))	// schema_proc already instanciated, use it
		{
			$this->schema_proc = $GLOBALS['egw_setup']->oProc;
		}
		else
		{
			$this->schema_proc = new schema_proc();
		}

		$this->db = $this->schema_proc->m_odb;
		$this->adodb = $this->db->Link_ID;
		if (isset($GLOBALS['egw_setup']) && is_object($GLOBALS['egw_setup']))		// called from setup
		{
			if ($GLOBALS['egw_setup']->config_table && $GLOBALS['egw_setup']->table_exist(array($GLOBALS['egw_setup']->config_table)))
			{
				$this->db->query("SELECT config_value FROM {$GLOBALS['egw_setup']->config_table} WHERE config_app='phpgwapi' AND config_name='backup_dir'",__LINE__,__FILE__);
				$this->db->next_record();
				if (!($this->backup_dir = $this->db->f(0)))
				{
					$this->db->query("SELECT config_value FROM {$GLOBALS['egw_setup']->config_table} WHERE config_app='phpgwapi' AND config_name='files_dir'",__LINE__,__FILE__);
					$this->db->next_record();
					$this->backup_dir = $this->db->f(0).'/db_backup';
				}
				$this->db->query("SELECT config_value FROM {$GLOBALS['egw_setup']->config_table} WHERE config_app='phpgwapi' AND config_name='system_charset'",__LINE__,__FILE__);
				$this->db->next_record();
				$this->charset = $this->db->f(0);
				if (!$this->charset)
				{
					$this->db->query("SELECT content FROM {$GLOBALS['egw_setup']->lang_table} WHERE message_id='charset' AND app_name='common' AND lang!='en'",__LINE__,__FILE__);
					$this->db->next_record();
					$this->charset = $this->db->f(0);
				}
				$this->db->select($GLOBALS['egw_setup']->applications_table,'app_version',array('app_name'=>'phpgwapi'),__LINE__,__FILE__);
				$this->api_version = $this->db->next_record() ? $this->db->f(0) : false;
			}
			if (!$this->charset) $this->charset = 'iso-8859-1';
		}
		else	// called from eGW
		{
			$this->schema_proc = CreateObject('phpgwapi.schema_proc');
			if (!($this->backup_dir = $GLOBALS['egw_info']['server']['backup_dir']))
			{
				$this->backup_dir = $GLOBALS['egw_info']['server']['files_dir'].'/db_backup';
			}
			$this->charset = $GLOBALS['egw']->translation->charset();

			$this->api_version = $GLOBALS['egw_info']['apps']['phpgwapi']['version'];
		}
		if (!is_dir($this->backup_dir) && is_writable(dirname($this->backup_dir)))
		{
			mkdir($this->backup_dir);
		}
		switch($this->db->Type)
		{
			case 'sapdb':
			case 'maxdb':
				//$this->system_tables = '/^(sql_cursor.*|session_roles|activeconfiguration|cachestatistics|commandcachestatistics|commandstatistics|datastatistics|datavolumes|hotstandbycomponent|hotstandbygroup|instance|logvolumes|machineconfiguration|machineutilization|memoryallocatorstatistics|memoryholders|omslocks|optimizerinformation|sessions|snapshots|spinlockstatistics|version)$/i';
				$this->egw_tables = '/^(egw_|phpgw_|g2_)/i';
				break;
		}
	}

	/**
	 * Opens the backup-file using the highest availible compression
	 *
	 * @param $name=false string/boolean filename to use, or false for the default one
	 * @param $reading=false opening for reading ('rb') or writing ('wb')
	 * @return string/resource error-msg of file-handle
	 */
	function fopen_backup($name=false,$reading=false)
	{
		if (!$name)
		{
			if (!$this->backup_dir || !is_writable($this->backup_dir))
			{
				return lang("backupdir '%1' is not writeable by the webserver",$this->backup_dir);
			}
			$name = $this->backup_dir.'/db_backup-'.date('YmdHi');
		}
		else	// remove the extension, to use the correct wrapper based on the extension
		{
			$name = preg_replace('/\.(bz2|gz)$/i','',$name);
		}
		$mode = $reading ? 'rb' : 'wb';

		if (!($f = @fopen($file = "compress.bzip2://$name.bz2",$mode)) &&
			!($f = @fopen($file = "compress.zlib://$name.gz",$mode)) &&
			!($f = @fopen($file = "zlib:$name.gz",$mode)) &&	// php < 4.3
			!($f = @fopen($file = $name,$mode)))
		{
			$lang_mode = $reading ? lang('reading') : lang('writing');
			return lang("Cant open '%1' for %2",$name,$lang_mode);
		}
		return $f;
	}

	/**
	 * Backup all data in the form of a (compressed) csv file
	 *
	 * @param resource $f file opened with fopen for reading
	 * @param boolean $convert_to_system_charset=false convert the restored data to the selected system-charset
	 */
	function restore($f,$convert_to_system_charset=false)
	{
		@set_time_limit(0);
		ini_set('auto_detect_line_endings',true);

		$this->db->transaction_begin();

		// drop all existing tables
		foreach($this->adodb->MetaTables('TABLES') as $table)
		{
			if ($this->system_tables && preg_match($this->system_tables,$table) ||
				$this->egw_tables && !preg_match($this->egw_tables,$table))
			{
				 continue;
			}
			$this->schema_proc->DropTable($table);
		}

		$table = False;
		$n = 0;
		while(!feof($f))
		{
			$line = trim(fgets($f)); ++$n;

			if (empty($line)) continue;

			if (substr($line,0,9) == 'version: ')
			{
				$api_version = trim(substr($line,9));
				continue;
			}
			if (substr($line,0,9) == 'charset: ')
			{
				$charset = trim(substr($line,9));
				// needed if mbstring.func_overload > 0, else eg. substr does not work with non ascii chars
				@ini_set('mbstring.internal_encoding',$charset);

				// check if we really need to convert the charset, as it's not perfect and can do some damage
				if ($convert_to_system_charset && !strcasecmp($convert_to_system_charset,$charset))
				{
					$convert_to_system_charset = false;	// no conversation necessary
				}
				// set the DB's client encoding (for mysql only if api_version >= 1.0.1.019)
				if ((!$convert_to_system_charset || $this->db->capabilities['client_encoding']) &&
					(substr($this->db->Type,0,5) != 'mysql' || !is_object($GLOBALS['egw_setup']) ||
					$api_version && !$GLOBALS['egw_setup']->alessthanb($api_version,'1.0.1.019')))
				{
					$this->db->Link_ID->SetCharSet($charset);
					if (!$convert_to_system_charset)
					{
						$this->schema_proc->system_charset = $charset;	// so schema_proc uses it for the creation of the tables
					}
				}
				continue;
			}
			if (substr($line,0,8) == 'schema: ')
			{
				// create the tables in the backup set
				$this->schemas = unserialize(trim(substr($line,8)));
				foreach($this->schemas as $table_name => $schema)
				{
					echo "<pre>$table_name => ".$this->write_array($schema,1)."</pre>\n";
					$this->schema_proc->CreateTable($table_name,$schema);
				}
				// make the schemas availible for the db-class
				$GLOBALS['egw_info']['apps']['all-apps']['table_defs'] = &$this->schemas;
				continue;
			}
			if (substr($line,0,7) == 'table: ')
			{
				$table = substr($line,7);

				$cols = $this->csv_split($line=fgets($f)); ++$n;

				if (feof($f)) break;
				continue;
			}
			if ($convert_to_system_charset && !$this->db->capabilities['client_encoding'])
			{
				if ($GLOBALS['egw_setup'])
				{
					if (!is_object($GLOBALS['egw_setup']->translation->sql))
					{
						$GLOBALS['egw_setup']->translation->setup_translation_sql();
					}
					$translation =& $GLOBALS['egw_setup']->translation->sql;
				}
				else
				{
					$translation =& $GLOBALS['egw']->translation;
				}
			}
			if ($table)	// do we already reached the data part
			{
				$import = true;
				$data = $this->csv_split($line,$cols);
				if ($table == 'egw_async' && in_array('##last-check-run##',$data)) {
					echo '<p>'.lang("Line %1: '%2'<br><b>csv data does contain ##last-check-run## of table %3 ==> ignored</b>",$n,$line,$table)."</p>\n";
					echo 'data=<pre>'.print_r($data,true)."</pre>\n";
					$import = false;
				}
				if ($import) {
					if (count($data) == count($cols))
					{
						if ($convert_to_system_charset && !$this->db->capabilities['client_encoding'])
						{
							$translation->convert($data,$charset);
						}
						$this->db->insert($table,$data,False,__LINE__,__FILE__,'all-apps',true);
					}
					else
					{
						echo '<p>'.lang("Line %1: '%2'<br><b>csv data does not match column-count of table %3 ==> ignored</b>",$n,$line,$table)."</p>\n";
						echo 'data=<pre>'.print_r($data,true)."</pre>\n";
					}
				}
			}
		}
		// updated the sequences, if the DB uses them
		foreach($this->schemas as $table => $schema)
		{
			foreach($schema['fd'] as $column => $definition)
			{
				if ($definition['type'] == 'auto')
				{
					$this->schema_proc->UpdateSequence($table,$column);
					break;	// max. one per table
				}
			}
		}

		if ($convert_to_system_charset)	// store the changed charset
		{
			$this->db->insert($GLOBALS['egw_setup']->config_table,array(
				'config_value' => $GLOBALS['egw_setup']->system_charset,
			),array(
				'config_app' => 'phpgwapi',
				'config_name' => 'system_charset',
			),__LINE__,__FILE__);
		}
		$this->db->transaction_commit();
	}

	/**
	 * Split one line of a csv file into an array and does all unescaping
	 */
	private function csv_split($line,$keys=False)
	{
		$fields = explode(',',trim($line));

		$str_pending = False;
		$n = 0;
		foreach($fields as $i => $field)
		{
			if ($str_pending !== False)
			{
				$field = $str_pending.','.$field;
				$str_pending = False;
			}
			$key = $keys ? $keys[$n] : $n;

			if ($field[0] == '"')
			{
				if (substr($field,-1) !== '"' || $field === '"' || !preg_match('/[^\\\\]+(\\\\\\\\)*"$/',$field))
				{
					$str_pending = $field;
					continue;
				}
				$arr[$key] = str_replace(self::BACKSLASH_TOKEN,'\\',str_replace(array('\\\\','\\n','\\r','\\"'),array(self::BACKSLASH_TOKEN,"\n","\r",'"'),substr($field,1,-1)));
			}
			elseif ($keys && strlen($field) > 24)
			{
				$arr[$key] = base64_decode($field);
			}
			else
			{
				$arr[$key] = $field == 'NULL' ? NULL : $field;
			}
			++$n;
		}
		return $arr;
	}

	/**
	 * escape data for csv
	 */
	private function escape_data(&$data,$col,$defs)
	{
		if (is_null($data))
		{
			$data = 'NULL';
		}
		else
		{
			switch($defs[$col]['type'])
			{
				case 'int':
				case 'auto':
				case 'decimal':
				case 'date':
				case 'timestamp':
					break;
				case 'blob':
					$data = base64_encode($data);
					break;
				default:
					$data = '"'.str_replace(array('\\',"\n","\r",'"'),array('\\\\','\\n','\\r','\\"'),$data).'"';
					break;
			}
		}
	}

	/**
	 * Backup all data in the form of a (compressed) csv file
	 *
	 * @param f resource file opened with fopen for writing
	 */
	function backup($f)
	{
		@set_time_limit(0);

		fwrite($f,"eGroupWare backup from ".date('Y-m-d H:i:s')."\n\n");

		fwrite($f,"version: $this->api_version\n\n");

		fwrite($f,"charset: $this->charset\n\n");

		$this->schema_backup($f);	// add the schema in a human readable form too

		/* not needed, already done by schema_backup
		foreach($this->adodb->MetaTables('TABLES') as $table)
		{
			if ($this->db->Type == 'sapdb' || $this->db->Type == 'maxdb')
			{
				$table = strtolower($table);
			}
			if (!($this->schemas[$table] = $this->schema_proc->GetTableDefinition($table)))
			{
				unset($this->schemas[$table]);
			}
		}
		*/
		fwrite($f,"\nschema: ".serialize($this->schemas)."\n");

		foreach($this->schemas as $table => $schema)
		{
			if (in_array($table,$this->exclude_tables)) continue;	// dont backup

			$first_row = true;
			$this->db->select($table,'*',false,__LINE__,__FILE__);
			while(($row = $this->db->row(true)))
			{
				if ($first_row)
				{
					fwrite($f,"\ntable: $table\n".implode(',',array_keys($row))."\n");
					$first_row = false;
				}
				array_walk($row,array('db_backup','escape_data'),$schema['fd']);
				fwrite($f,implode(',',$row)."\n");
			}
		}
		return true;
	}

	/**
	 * Backup all schemas in the form of a setup/tables_current.inc.php file
	 *
	 * @param resource|boolean $f
	 */
	function schema_backup($f=False)
	{
		foreach($this->adodb->MetaTables('TABLES') as $table)
		{
			if ($this->system_tables && preg_match($this->system_tables,$table) ||
				$this->egw_tables && !preg_match($this->egw_tables,$table))
			{
				continue;
			}
			if ($this->db->Type == 'sapdb' || $this->db->Type == 'maxdb')
			{
				$table = strtolower($table);
			}
			if (!($this->schemas[$table] = $this->schema_proc->GetTableDefinition($table)))
			{
				unset($this->schemas[$table]);
			}
			if (($this->db->Type == 'sapdb' || $this->db->Type == 'maxdb') && $table == 'phpgw_anglemail')
			{
				// sapdb does not differ between text and blob
				$this->schemas[$table]['fd']['content']['type'] = 'blob';
			}
		}
		$def = "\t\$phpgw_baseline = ";
		$def .= $this->write_array($this->schemas,1);
		$def .= ";\n";

		if ($f)
		{
			fwrite($f,$def);
		}
		else
		{
			if (!is_object($this->browser))
			{
				$this->browser = new browser();
			}
			$this->browser->content_header('schema-backup-'.date('YmdHi').'.inc.php','text/plain',bytes($def));
			echo "<?php\n\t/* eGroupWare schema-backup from ".date('Y-m-d H:i:s')." */\n\n".$def;
		}
	}

	/**
	 * Dump an array as php source
	 *
	 * copied from etemplate/inc/class.db_tools.inc.php
	 */
	private function write_array($arr,$depth,$parent='')
	{
		if (in_array($parent,array('pk','fk','ix','uc')))
		{
			$depth = 0;
		}
		if ($depth)
		{
			$tabs = "\n";
			for ($n = 0; $n < $depth; ++$n)
			{
				$tabs .= "\t";
			}
			++$depth;
		}
		$def = "array($tabs".($tabs ? "\t" : '');

		$n = 0;
		foreach($arr as $key => $val)
		{
			if (!is_int($key))
			{
				$def .= "'$key' => ";
			}
			if (is_array($val))
			{
				$def .= $this->write_array($val,$parent == 'fd' ? 0 : $depth,$key);
			}
			else
			{
				if (!$only_vals && $key === 'nullable')
				{
					$def .= $val ? 'True' : 'False';
				}
				else
				{
					$def .= "'$val'";
				}
			}
			if ($n < count($arr)-1)
			{
				$def .= ",$tabs".($tabs ? "\t" : '');
			}
			++$n;
		}
		$def .= "$tabs)";

		return $def;
	}
}
/*
$line = '"de","ranking","use \\"yes\\", or \\"no, prefession\\"","benützen Sie \\"yes\\" oder \\"no, Beruf\\""';

echo "<p>line='$line'</p>\n";
echo "<pre>".print_r(db_backup::csv_split($line),true)."</pre>\n";
*/
