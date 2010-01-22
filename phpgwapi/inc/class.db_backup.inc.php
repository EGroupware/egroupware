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
	 * Configuration table.
	 */
	const TABLE = 'egw_config';
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
		'egw_phpfreechat', // as of the fieldnames of the table a restore would fail within egroupware, and chatcontent is of no particular intrest
	);
	/**
	 * regular expression to identify system-tables => ignored for schema+backup
	 *
	 * @var string|boolean
	 */
	var $system_tables = false;
	/**
	 * Regular expression to identify eGW tables => if set only they are used
	 *
	 * @var string|boolean
	 */
	var $egw_tables = false;
	/**
	 * Backup directory.
	 *
	 * @var string
	 */
	var $backup_dir;
	/**
	 * Minimum number of backup files to keep. Zero for: Disable cleanup.
	 *
	 * @var integer
	 */
	var $backup_mincount;
	/**
	 * Backup Files config value, will be overwritten by the availability of the ZibArchive libraries
	 *
	 * @var boolean
	 */
	var $backup_files = false ;

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
				$this->db->query("SELECT config_value FROM {$GLOBALS['egw_setup']->config_table} WHERE config_app='phpgwapi' AND config_name='files_dir'",__LINE__,__FILE__);
				$this->db->next_record();
				if (!($this->files_dir = $this->db->f(0)))
				{
					error_log(__METHOD__."->"."No files Directory set/found");
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
				/* Backup settings */
				$this->db->query("SELECT config_value FROM {$GLOBALS['egw_setup']->config_table} WHERE config_app='phpgwapi' AND config_name='backup_mincount'",__LINE__,__FILE__);
				$this->db->next_record();
				$this->backup_mincount = $this->db->f(0);
				// backup files too
				$this->db->query("SELECT config_value FROM {$GLOBALS['egw_setup']->config_table} WHERE config_app='phpgwapi' AND config_name='backup_files'",__LINE__,__FILE__);
				$this->db->next_record();
				$this->backup_files = (bool)$this->db->f(0);
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
			$this->files_dir = $GLOBALS['egw_info']['server']['files_dir'];
			$this->backup_mincount = $GLOBALS['egw_info']['server']['backup_mincount'];
			$this->backup_files = $GLOBALS['egw_info']['server']['backup_files'];
			$this->charset = $GLOBALS['egw']->translation->charset();

			$this->api_version = $GLOBALS['egw_info']['apps']['phpgwapi']['version'];
		}
		// Set a default value if not set.
		if (!isset($this->backup_mincount))
		{
			$this->backup_mincount = 0; // Disabled if not set
		}
		if (!isset($this->backup_files))
		{
			$this->backup_files = false; // Disabled if not set
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
	 * Opens the backup-file using the highest available compression
	 *
	 * @param $name=false string/boolean filename to use, or false for the default one
	 * @param $reading=false opening for reading ('rb') or writing ('wb')
	 * @return string/resource/zip error-msg of file-handle
	 */
	function fopen_backup($name=false,$reading=false)
	{
		//echo "function fopen_backup($name,$reading)<br>";	// !
		if (!$name)
		{
			//echo '-> !$name<br>';	// !
			if (!$this->backup_dir || !is_writable($this->backup_dir))
			{
				//echo '   -> !$this->backup_dir || !is_writable($this->backup_dir)<br>';	// !
				return lang("backupdir '%1' is not writeable by the webserver",$this->backup_dir);
			}
			$name = $this->backup_dir.'/db_backup-'.date('YmdHi');
		}
		else	// remove the extension, to use the correct wrapper based on the extension
		{
			//echo '-> else<br>';	// !
			$name = preg_replace('/\.(bz2|gz)$/i','',$name);
		}
		$mode = $reading ? 'rb' : 'wb';
		list( , $type) = explode('.', basename($name));
		if($type == 'zip' && $reading && $this->backup_files)
		{
			//echo '-> $type == "zip" && $reading<br>';	// !
			if(!class_exists('ZipArchive', false))
			{
				$this->backup_files = false;
				//echo '   -> (new ZipArchive) == NULL<br>';	// !
				return lang("Cant open %1, needs ZipArchive", $name)."<br>\n";
			}
			if(!($f = fopen($name, $mode)))
			{
				//echo '   -> !($f = fopen($name, $mode))<br>';	// !
				$lang_mode = $reading ? lang("reading") : lang("writing");
				return lang("Cant open '%1' for %2", $name, $lang_mode)."<br>";
			}
			return $f;
		}
		if(class_exists('ZipArchive', false) && !$reading && $this->backup_files)
		{
			//echo '-> (new ZipArchive) != NULL && !$reading; '.$name.'<br>';	// !
			if(!($f = fopen($name, $mode)))
			{
				//echo '   -> !($f = fopen($name, $mode))<br>';	// !
				$lang_mode = $reading ? lang("reading") : lang("writing");
				return lang("Cant open '%1' for %2", $name, $lang_mode)."<br>";
			}
			return $f;
		}
		if(!($f = fopen("compress.bzip2://$name.bz2", $mode)) &&
	 		!($f = fopen("compress.zlib://$name.gz",$mode)) &&
 		 	!($f = fopen($name,$mode))
		)
		{
			//echo '-> !($f = fopen("compress.bzip2://$name.bz2", $mode))<br>';	// !
			$lang_mode = $reading ? lang("reading") : lang("writing");
			return lang("Cant open '%1' for %2", $name, $lang_mode)."<br>";
		}
		return $f;
	}

	/**
	 * Remove old backups, leaving at least
	 * backup_mincount backup files in place. Only backup files with
	 * the regular name scheme are taken into account.
	 *
	 * @param files_return Fills a given array of file names to display (if given).
	 */
	function housekeeping(&$files_return = false)
	{
		/* Stop housekeeping in case it is disabled. */
		if ($this->backup_mincount == 0)
		{
			return;
		}
		/* Search the backup directory for matching files. */
		$handle = @opendir($this->backup_dir);
		$files = array();
		while($handle && ($file = readdir($handle)))
		{
			/* Filter for only the files with the regular name (un-renamed).
			 * Leave special backup files (renamed) in place.
			 * Note that this also excludes "." and "..".
			 */
			if (preg_match("/^db_backup-[0-9]{12}(\.bz2|\.gz|\.zip|)$/",$file))
			{
				$files[filectime($this->backup_dir.'/'.$file)] = $file;
			}
		}
		if ($handle) closedir($handle);

		/* Sort the files by ctime. */
		krsort($files);
		$count = 0;
		foreach($files as $ctime => $file)
		{
			if ($count >= $this->backup_mincount)//
			{
				$ret = unlink($this->backup_dir.'/'.$file);
				if (($ret) && (is_array($files_return)))
				{
					array_push($files_return, $file);
				}
			}
			$count ++;
		}
	}

	/**
	 * Save the housekeeping configuration in the database and update the local variables.
	 *
	 * @param mincount Minimum number of backups to keep.
	 */
	function saveConfig($config_values)
	{
		if (!is_array($config_values))
		{
			error_log(__METHOD__." unable to save backup config values, wrong type of parameter passed.");
			return false;
		}
		//_debug_array($config_values);
		$minCount = $config_values['backup_mincount'];
		$backupFiles = (int)$config_values['backup_files'];
		/* minCount */
		$this->db->insert(
			self::TABLE,
			array(
				'config_value' => $minCount,
			),
			array(
				'config_app' => 'phpgwapi',
				'config_name' => 'backup_mincount',
			),
			__LINE__,
			__FILE__);
		/* backup files flag */
		$this->db->insert(self::TABLE,array('config_value' => $backupFiles),array('config_app' => 'phpgwapi','config_name' => 'backup_files'),__LINE__,__FILE__);
		$this->backup_mincount = $minCount;
		$this->backup_files = (bool)$backupFiles;
		// Update session cache
		$GLOBALS['egw']->invalidate_session_cache();
	}

	/**
	 * Backup all data in the form of a (compressed) csv file
	 *
	 * @param resource $f file opened with fopen for reading
	 * @param boolean $convert_to_system_charset=false convert the restored data to the selected system-charset
	 * @param string $filename='' gives the file name which is used in case of a zip archive.
	 *
	 * @returns An empty string or an error message in case of failure.
	 */
	function restore($f,$convert_to_system_charset=false,$filename='')
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
		// it could be an old backup
		list( , $type) = explode('.', basename($filename));
		$dir = $this->files_dir; // $GLOBALS['egw_info']['server']['files_dir'];
		// we may have to clean up old backup - left overs
		if (is_dir($dir.'/database_backup'))
		{
			self::remove_dir_content($dir.'/database_backup/');
			rmdir($dir.'/database_backup');
		}

		$list = array();
		$name = "";
		$zip = NULL;
		$_f = NULL;
		if($type == 'zip')
	    {
			// has already been verified to be available in fopen_backup
			$zip = new ZipArchive;
			if(($zip->open($filename)) !== TRUE)
			{
				return lang("Cant open '%1' for %2", $filename, lang("reading"))."<br>\n";
			}
			self::remove_dir_content($dir);  // removes the files-dir
			$zip->extractTo($dir);
			$_f = $f;
			$list = $this->get_file_list($dir.'/database_backup/');
			$name = $dir.'/database_backup/'.basename($list[0]);
			if(!($f = fopen($name, 'rb')))
			{
				return lang("Cant open '%1' for %2", $filename, lang("reading"))."<br>\n";
			}
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
					echo "<pre>$table_name => ".self::write_array($schema,1)."</pre>\n";
					$this->schema_proc->CreateTable($table_name,$schema);
				}
				// make the schemas availible for the db-class
				$GLOBALS['egw_info']['apps']['all-apps']['table_defs'] = &$this->schemas;
				continue;
			}
			if (substr($line,0,7) == 'table: ')
			{
				$table = substr($line,7);

				$cols = self::csv_split($line=fgets($f)); ++$n;

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
				$data = self::csv_split($line,$cols);
				if ($table == 'egw_async' && in_array('##last-check-run##',$data)) {
					echo '<p>'.lang("Line %1: '%2'<br><b>csv data does contain ##last-check-run## of table %3 ==> ignored</b>",$n,$line,$table)."</p>\n";
					echo 'data=<pre>'.print_r($data,true)."</pre>\n";
					$import = false;
				}
				if (in_array($table,$this->exclude_tables))
				{
					echo '<p><b>'.lang("Table %1 is excluded from backup and restore. Data will not be restored.",$table)."</b></p>\n";
					$import = false; // dont restore data of excluded tables
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
		// zip?
		if($type == 'zip')
		{
			fclose($f);
			$f = $save_f;
			unlink($name);
			rmdir($dir.'/database_backup');
		}
		if (!$this->db->transaction_commit())
		{
			return lang('Restore failed');
		}
		return '';
	}

	/**
	 * Removes a dir, no matter whether it is empty or full
	 *
	 * @param strin $dir
	 */
	private static function remove_dir_content($dir)
	{
		$list = scandir($dir);
		while($file = $list[0])
		{
			if(is_dir($file) && $file != '.' && $file != '..')
			    self::remove_dir_content($dir.'/'.$file);
			if(is_file($file) && $file != '.' && $file != '..')
			    unlink($dir.'/'.$file);
			array_shift($list);
		}
		//rmdir($dir);  // dont remove own dir
	}

	/**
	 * Split one line of a csv file into an array and does all unescaping
	 *
	 * @param string $line line to split
	 * @param array $keys=null keys to use or null to use numeric ones
	 * @return array
	 */
	public static function csv_split($line,$keys=null)
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
	public static function escape_data(&$data,$col,$defs)
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
		//echo "function backup($f)<br>";	// !
		@set_time_limit(0);
		$dir = $this->files_dir; // $GLOBALS['egw_info']['server']['files_dir'];
		// we may have to clean up old backup - left overs
		if (is_dir($dir.'/database_backup'))
		{
			self::remove_dir_content($dir.'/database_backup/');
			rmdir($dir.'/database_backup');
		}

		$file_list = array();
		$name = $this->backup_dir.'/db_backup-'.date('YmdHi');
		$filename = $name.'.zip';
		$zippresent = false;
		if(class_exists('ZipArchive') && $this->backup_files)
		{
			$zip = new ZipArchive;
			if(is_object($zip))
			{
				$zippresent = true;
				//echo '-> is_object($zip); '.$filename.'<br>';	// !
				$res = $zip->open($filename, ZIPARCHIVE::CREATE);
				if($res !== TRUE)
				{
					//echo '   -> !$res<br>';	// !
					return lang("Cant open '%1' for %2", $filename, lang("writing"))."<br>\n";
				}
				$file_list = $this->get_file_list($dir);
			}
		}
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
			while($row = $this->db->row(true))
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
		if(!$zippresent)  // save without files 
		{
			if ($this->backup_files)
			{
				echo '<center>'.lang("Cant open %1, needs ZipArchive", $name)."<br>\n".'</center>';
			}

		    fclose($f);
		    if (file_exists($name)) unlink($name);
			return TRUE;
		}
		// save files ....
		//echo $name.'<br>';
		$zip->addFile($name, 'database_backup/'.basename($name));
		$count = 1;
		foreach($file_list as $num => $file)
		{
			//echo substr($file,strlen($dir)+1).'<br>';
			//echo $file.'<br>';
			$zip->addFile($file,substr($file,strlen($dir)+1));//,substr($file);
			if(($count++) == 100) { // the file descriptor limit
				$zip->close();
				if($zip = new ZipArchive()) {
					$zip->open($filename);
					$count =0;
				}
			}
		}
		$zip->close();
		fclose($f);
		unlink($name);
		return true;
	}

	/**
	 * gets a list of all files on $f
	 *
	 * @param string file $f
	 * [@param int $cnt]
	 * [@param string $path_name]
	 *
	 * @return array (list of files)
	 */
	function get_file_list($f, $cnt = 0, $path_name = "")
	{
		//chdir($f);
		//echo "Processing $f <br>";
		if ($path_name =='') $path_name = $f;
		$tlist = scandir($f);
		$list = array();
		$i = $cnt;
		while($file = $tlist[0]) // remove all '.' and '..' and transfer to $list
		{
			if($file == '.' || $file == '..')
			{
				array_shift($tlist);
			}
			else
			{
				if(is_dir($f.'/'.$file))
				{
					$nlist = $this->get_file_list($f.'/'.$file, $i);
					$list += $nlist;
					$i += count($nlist);
					array_shift($tlist);
				}
				else
				{
					$list[$i++] = $path_name.'/'.array_shift($tlist);
				}
			}
		}
		return $list;
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
		$def .= self::write_array($this->schemas,1);
		$def .= ";\n";

		if ($f)
		{
			fwrite($f,$def);
		}
		else
		{
			$def = "<?php\n\t/* eGroupWare schema-backup from ".date('Y-m-d H:i:s')." */\n\n".$def;
			html::content_header('schema-backup-'.date('YmdHi').'.inc.php','text/plain',bytes($def));
			echo $def;
		}
	}

	/**
	 * Dump an array as php source
	 *
	 * copied from etemplate/inc/class.db_tools.inc.php
	 */
	private static function write_array($arr,$depth,$parent='')
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
				$def .= self::write_array($val,$parent == 'fd' ? 0 : $depth,$key);
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
