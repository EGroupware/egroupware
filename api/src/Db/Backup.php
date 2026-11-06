<?php
/**
 * EGroupware API: Database backups
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage db
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2003-22 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 */

namespace EGroupware\Api\Db;

use EGroupware\Api;
use EGroupware\Api\Mail\Credentials;
use ZipArchive;

/**
 * DB independent backup and restore of EGroupware database
 *
 * Backing up bool columns now for all databases as 1 or 0, but understanding PostgreSQL 't' or 'f' too.
 */
class Backup
{
	/**
	 * Configuration table.
	 */
	const TABLE = 'egw_config';
	/**
	 * Reference to schema_proc
	 *
	 * @var Api\Db\Schema
	 */
	protected $schema_proc;
	/**
	 * Reference to ADOdb (connection) object
	 *
	 * @var ADOConnection
	 */
	protected $adodb;
	/**
	 * DB schemas, as array tablename => schema
	 *
	 * @var array
	 */
	protected $schemas = array();
	/**
	 * Tables to exclude from the backup: sessions, diverse caches which get automatic rebuild
	 *
	 * @var array
	 */
	public $exclude_tables = array(
		'egw_sessions','egw_app_sessions','phpgw_sessions','phpgw_app_sessions',	// eGW's session-tables
		'phpgw_anglemail',	// email's cache
		'egw_felamimail_cache','egw_felamimail_folderstatus','phpgw_felamimail_cache','phpgw_felamimail_folderstatus',	// felamimail's cache
		'egw_phpfreechat', // as of the fieldnames of the table a restore would fail within egroupware, and chatcontent is of no particular interest
	);
	/**
	 * regular expression to identify system-tables => ignored for schema+backup
	 *
	 * @var string|boolean
	 */
	public $system_tables = false;
	/**
	 * Regular expression to identify eGW tables => if set only they are used
	 *
	 * @var string|boolean
	 */
	public $egw_tables = false;
	/**
	 * Regular expression to identify a Guacamole table OR view
	 */
	const GUACAMOLE_REGEXP = '/^guacamole_/';
	/**
	 * Cleartext of the "secret_check" line of a backup
	 *
	 * The backup contains it encrypted with the DB password aka system secret (see Api\Mail\Credentials::SYSTEM_AES)
	 * used by the backed up instance. That allows to tell on restore, if it changed, and if a given old DB password is the right one.
	 */
	const SECRET_CHECK = 'egw-db-password-check';
	/**
	 * DB password of the system the backup to restore was created on, set it before calling restore()
	 *
	 * It is a property and not a parameter of restore(), to not break classes reimplementing restore() like Stylite's S3\Backup.
	 *
	 * @var ?string
	 */
	public $old_db_password = null;
	/**
	 * Number of values re-encrypted by the last restore() with an old_db_password, by type (credentials, customfields, subscriptions, shares)
	 * and "skipped" for values which could not be decrypted with the old DB password
	 *
	 * @var int[]
	 */
	public $reencrypted = [];
	/**
	 * Files directory
	 *
	 * @var string
	 */
	public $files_dir;
	/**
	 * Backup directory.
	 *
	 * @var string
	 */
	public $backup_dir;
	/**
	 * Minimum number of backup files to keep. Zero for: Disable cleanup.
	 *
	 * @var integer
	 */
	public $backup_mincount;
	/**
	 * Backup Files config value, will be overwritten by the availability of the ZibArchive libraries
	 *
	 * @var boolean
	 */
	public $backup_files = false ;
	public $charset = 'utf-8';
	public $api_version;
	/**
	 * Reference to schema_proc's Api\Db object
	 *
	 * @var Api\Db
	 */
	protected $db;

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
			$this->schema_proc = new Api\Db\Schema();
		}

		$this->db = $this->schema_proc->m_odb;
		if (!$this->db->Link_ID) $this->db->connect();
		$this->adodb = $this->db->Link_ID;
		if (isset($GLOBALS['egw_setup']) && is_object($GLOBALS['egw_setup']))		// called from setup
		{
			if ($GLOBALS['egw_setup']->config_table && $GLOBALS['egw_setup']->table_exist(array($GLOBALS['egw_setup']->config_table)))
			{
				if (!($this->files_dir = $this->db->query("SELECT config_value FROM {$GLOBALS['egw_setup']->config_table} WHERE config_app='phpgwapi' AND config_name='files_dir'",__LINE__,__FILE__)->fetchColumn()))
				{
					error_log(__METHOD__."->"."No files Directory set/found");
				}
				if (!($this->backup_dir = $this->db->query("SELECT config_value FROM {$GLOBALS['egw_setup']->config_table} WHERE config_app='phpgwapi' AND config_name='backup_dir'",__LINE__,__FILE__)->fetchColumn()))
				{
					$this->backup_dir = dirname($this->files_dir).'/backup';
				}
				$this->charset = $this->db->query("SELECT config_value FROM {$GLOBALS['egw_setup']->config_table} WHERE config_app='phpgwapi' AND config_name='system_charset'",__LINE__,__FILE__)->fetchColumn();
				$this->api_version = $this->db->select($GLOBALS['egw_setup']->applications_table,'app_version',array('app_name'=>array('api','phpgwapi')),
					__LINE__,__FILE__,0,'ORDER BY app_name ASC')->fetchColumn();
				// Backup settings
				$this->backup_mincount = $this->db->query("SELECT config_value FROM {$GLOBALS['egw_setup']->config_table} WHERE config_app='phpgwapi' AND config_name='backup_mincount'",__LINE__,__FILE__)->fetchColumn();
				// backup files too
				$this->backup_files = (bool)$this->db->query("SELECT config_value FROM {$GLOBALS['egw_setup']->config_table} WHERE config_app='phpgwapi' AND config_name='backup_files'",__LINE__,__FILE__)->fetchColumn();
			}
			if (!$this->charset) $this->charset = 'utf-8';
		}
		else	// called from eGW
		{
			if (!($this->backup_dir = $GLOBALS['egw_info']['server']['backup_dir']))
			{
				$this->backup_dir = dirname($GLOBALS['egw_info']['server']['files_dir']).'/backup';
			}
			$this->files_dir = $GLOBALS['egw_info']['server']['files_dir'];
			$this->backup_mincount = $GLOBALS['egw_info']['server']['backup_mincount'];
			$this->backup_files = $GLOBALS['egw_info']['server']['backup_files'];
			$this->charset = Api\Translation::charset();

			$this->api_version = $GLOBALS['egw_info']['apps'][isset($GLOBALS['egw_info']['apps']['api'])?'api':'phpgwapi']['version'];
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
	 * @param ?string $name =null filename relative to backup-dir to use, or null for the default one
	 * @param bool $reading =false opening for reading ('rb') or writing ('wb')
	 * @param bool $un_compress true: opening file with (un)compression wrapper, false: opening as it is for download
	 * @return resource file-handle
	 * @throws \Exception on error
	 */
	public function fopen_backup(?string $name=null, bool $reading=false, bool $un_compress=true)
	{
		if ($name)
		{
			$name = $this->backup_dir.'/'.basename($name);
		}
		if (!$name)
		{
			$name = $this->backup_dir.'/db_backup-'.date('YmdHi');
			if (empty($this->backup_dir) || !is_writable($this->backup_dir))
			{
				$this->log($name, $reading, null, lang("backupdir '%1' is not writeable by the webserver", $this->backup_dir));
				throw new Exception(lang("backupdir '%1' is not writeable by the webserver", $this->backup_dir));
			}
		}
		// remove the extension, to use the correct wrapper based on the extension
		elseif ($un_compress)
		{
			$name = preg_replace('/\.(bz2|gz)$/i','',$name);
		}
		$mode = $reading ? 'rb' : 'wb';
		$lang_mode = $reading ? lang("reading") : lang("writing");
		[, $type] = explode('.', basename($name));
		if($un_compress && $type == 'zip' && $reading && $this->backup_files)
		{
			//echo '-> $type == "zip" && $reading<br>';	// !
			if(!class_exists('ZipArchive', false))
			{
				$this->backup_files = false;
				$this->log($name, $reading, null, lang("Cant open %1, needs ZipArchive", $name));
				throw new \Exception(lang("Cant open %1, needs ZipArchive", $name));
			}
			if(!($f = fopen($path=$name, $mode)))
			{
				$this->log($name, $reading, null, lang("Cant open '%1' for %2", $name, $lang_mode));
				throw new \Exception(lang("Cant open '%1' for %2", $name, $lang_mode));
			}
		}
		elseif ($un_compress && class_exists('ZipArchive', false) && !$reading && $this->backup_files)
		{
			//echo '-> (new ZipArchive) != NULL && !$reading; '.$name.'<br>';	// !
			if (!($f = fopen($path=$name, $mode)))
			{
				$this->log($name, $reading, null, lang("Cant open '%1' for %2", $name, $lang_mode));
				throw new \Exception(lang("Cant open '%1' for %2", $name, $lang_mode));
			}
		}
		elseif (!($un_compress && (
				($f = fopen('compress.bzip2://'.($path=$name.'.bz2'), $mode)) ||
	 		    ($f = fopen('compress.zlib://'.($path=$name.'.gz'),$mode)))) &&
 		 	!($f = fopen($path=$name,$mode)))
		{
			$this->log($name, $reading, null, lang("Cant open '%1' for %2", $name, $lang_mode));
			throw new \Exception(lang("Cant open '%1' for %2", $name, $lang_mode));
		}

		// Log start of backup/restore
		$this->log($path, $reading, true);

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
		foreach($files as $file)
		{
			if ($count >= $this->backup_mincount)//
			{
				$this->log($file, lang('Housekeeping removed'));
				$ret = unlink($this->backup_dir.'/'.$file);
				if (!$ret) $this->log($file, 'remove', null, "Failed to remove $file");
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
	 * @param int $minCount Minimum number of backups to keep.
	 * @param boolean $backupFiles include files in backup or not, default dont change!
	 */
	function saveConfig($minCount,$backupFiles=null)
	{
		Api\Config::save_value('backup_mincount',$this->backup_mincount=(int)$minCount,'phpgwapi');

		if (!is_null($backupFiles))
		{
			Api\Config::save_value('backup_files',$this->backup_files=(bool)$backupFiles,'phpgwapi');
		}
	}

	/**
	 * Certain config settings NOT to restore (because they break a working system)
	 *
	 * @var array
	 */
	static $system_config = array(
		'files_dir',
		'temp_dir',
		'backup_dir',
		'backup_files',
		'webserver_url',
		'aspell_path',
		'hostname',
		'httpproxy_server',
		'httpproxy_port',
		'httpproxy_server_username',
		'httpproxy_server_password',
		'system_charset',
		'usecookies',
		'install_id',	// do not restore install_id, as that would give two systems with identical install_id
	);

	/**
	 * Restore a backup
	 *
	 * @param resource $f file opened with fopen for reading
	 * @param boolean $convert_to_system_charset =true obsolet, it's now allways done
	 * @param string $filename ='' gives the file name which is used in case of a zip archive.
	 * @param boolean $protect_system_config =true should above system_config values be protected (NOT overwritten)
	 * @param int $insert_n_rows =500 how many rows to insert in one sql statement
	 *
	 * If $old_db_password is set, passwords encrypted with the DB password are re-encrypted with the current one,
	 * see $reencrypted for the result.
	 *
	 * @returns string An empty string or an error message in case of failure.
	 */
	function restore($f,$convert_to_system_charset=true,$filename='',$protect_system_config=true, $insert_n_rows=500)
	{
		@set_time_limit(0);
		ini_set('auto_detect_line_endings',true);

		// check old DB password BEFORE we drop anything
		$old_key = null;
		$this->reencrypted = [];
		if ((string)$this->old_db_password !== '' && (string)$this->db->Password !== '' && $this->old_db_password !== $this->db->Password)
		{
			if ($this->checkDbPassword($filename, $this->old_db_password) === false)
			{
				return lang('The old DB password does not match the one the backup was created with, nothing was restored!');
			}
			$old_key = $this->old_db_password;
		}

		if (true) $convert_to_system_charset = true;	// enforce now utf-8 as system charset restores of old backups

		if ($protect_system_config)
		{
			$system_config = array();
			foreach($this->db->select(self::TABLE,'*',array(
				'config_app' => 'phpgwapi',
				'config_name' => self::$system_config,
			),__LINE__,__FILE__) as $row)
			{
				$system_config[] = $row;
			}
		}

		// as MySQL 5.7+ has sql_mode STRICT_(ALL|TRANS)_TABLES enabled by default,
		// it will no longer restore '0000-00-00 00:00:00' in timestamps it created before,
		// so switching strict-mode off temporary for the restore (we dont create these!)
		if (substr($this->db->Type,0,5) == 'mysql')
		{
			$this->db->query("SET SESSION sql_mode=(SELECT REPLACE(REPLACE(@@sql_mode,'STRICT_ALL_TABLES',''),'STRICT_TRANS_TABLES',''))", __LINE__, __FILE__);
			// disable foreign key checks, in case Guacamole is installed
			$this->db->query('SET FOREIGN_KEY_CHECKS = 0');
		}
		else
		{
			$this->db->transaction_begin();
		}


		// drop all existing tables
		foreach($this->adodb->MetaTables('TABLES') as $table)
		{
			if ($this->system_tables && preg_match($this->system_tables,$table) ||
				$this->egw_tables && !preg_match($this->egw_tables,$table) ||
				// do NOT drop Guacamole tables and views
				preg_match(self::GUACAMOLE_REGEXP, $table))
			{
				 continue;
			}
			$this->schema_proc->DropTable($table);
		}
		// it could be an old backup
		list( , $type) = explode('.', basename($filename));
		$dir = $this->files_dir; // $GLOBALS['egw_info']['server']['files_dir'];
		// we may have to clean up old backup - leftovers
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
				$this->log($name, true, null, lang("Cant open '%1' for %2", $filename, lang("reading")));
				return lang("Cant open '%1' for %2", $filename, lang("reading"))."<br>\n";
			}
		}
		$this->db_restore($f, $insert_n_rows);

		if ($convert_to_system_charset)	// store the changed charset
		{
			$this->db->insert(Api\Config::TABLE, array(
				'config_value' => $this->schema_proc->system_charset,
			),array(
				'config_app' => 'phpgwapi',
				'config_name' => 'system_charset',
			),__LINE__,__FILE__);
		}
		// restore protected system config
		if ($protect_system_config)
		{
			foreach($system_config as $row)
			{
				$this->db->insert(self::TABLE,array('config_value'=>$row['config_value']),array(
					'config_name' => $row['config_name'],
					'config_app'  => $row['config_app'],
				),__LINE__,__FILE__);
			}
			// check and reset cookie configuration, if it does not match current enviroment
			// if $_SERVER[HTTP_HOST] does not end with cookiedomain --> delete cookiedomain
			if (($cookiedomain = $this->db->select(self::TABLE,'config_value',array(
					'config_app' => 'phpgwapi',
					'config_name' => 'cookiedomain',
				),__LINE__,__FILE__)->fetchColumn()) && isset($_SERVER['HTTP_HOST']) &&
				(list($hostname) = explode(':',$_SERVER['HTTP_HOST'])) &&
				substr($hostname,-strlen($cookiedomain) !== $cookiedomain))
			{
				$this->db->delete(self::TABLE,array(
					'config_app' => 'phpgwapi',
					'config_name' => 'cookiedomain',
				),__LINE__,__FILE__);
			}
			// if configured webserver_url does NOT start with cookiepath --> delete cookiepath
			if (($cookiepath = $this->db->select(self::TABLE,'config_value',array(
					'config_app' => 'phpgwapi',
					'config_name' => 'cookiepath',
				),__LINE__,__FILE__)->fetchColumn()) &&
				substr(parse_url($system_config['webserver_url'], PHP_URL_PATH),0,strlen($cookiepath) !== $cookiepath))
			{
				$this->db->delete(self::TABLE,array(
					'config_app' => 'phpgwapi',
					'config_name' => 'cookiepath',
				),__LINE__,__FILE__);
			}
		}
		// DB password changed: re-encrypt everything encrypted with it
		if (isset($old_key))
		{
			$this->reencrypted = $this->reencrypt($old_key, $this->db->Password);
		}
		// zip?
		if($type == 'zip')
		{
			fclose($f);
			unlink($name);
			rmdir($dir.'/database_backup');
		}
		if (substr($this->db->Type,0,5) != 'mysql')
		{
			if (!$this->db->transaction_commit())
			{
				$this->log($filename, true, false, lang('Restore failed'));
				return lang('Restore failed');
			}
		}
		// generate an install_id if we dont have one (it breaks Api\Cache::flush() stalling the upgrade)
		unset($GLOBALS['egw_info']['server']['install_id']);
		if (!($GLOBALS['egw_info']['server']['install_id'] = Api\Cache::get_system_config('install_id', false)))
		{
			$GLOBALS['egw_info']['server']['install_id'] = md5(microtime(true).$_SERVER['HTTP_HOST']);
			$this->db->insert('egw_config', array(
				'config_value' => $GLOBALS['egw_info']['server']['install_id'],
			), array(
				'config_name' => 'install_id',
				'config_app'  => 'phpgwapi',
			), __LINE__, __FILE__);
		}
		// flush instance cache
		Api\Cache::flush(Api\Cache::INSTANCE);

		// search-and-register-hooks
		Api\Hooks::read(true);

		// log end of successful restore
		$this->log($filename, true, false);

		return '';
	}

	/**
	 * Read what is needed to check the DB password from the start of a backup
	 *
	 * @param string $filename backup file, relative to the backup directory (any directory part is ignored)
	 * @return array with values for keys:
	 *	- "secret_check" SECRET_CHECK encrypted with the DB password of the backed up system, or null for older backups
	 *	- "probe" for older backups the first value encrypted with the DB password found in the mail credentials, or null if none
	 */
	protected function readBackupInfo(string $filename) : array
	{
		$info = ['secret_check' => null, 'probe' => null];
		$name = $this->backup_dir.'/'.basename($filename);
		$zip = null;
		if (strtolower(substr($name, -4)) === '.zip')
		{
			if (!class_exists(ZipArchive::class, false) || ($zip = new ZipArchive)->open($name) !== true)
			{
				return $info;
			}
			$f = false;
			for ($i = 0; $i < $zip->numFiles; ++$i)
			{
				if (preg_match('#^database_backup/[^/]+$#', $zip->getNameIndex($i)))
				{
					$f = $zip->getStream($zip->getNameIndex($i));
					break;
				}
			}
		}
		else
		{
			$name = preg_replace('/\.(bz2|gz)$/i', '', $name);
			$f = @fopen('compress.bzip2://'.$name.'.bz2', 'rb') ?: @fopen('compress.zlib://'.$name.'.gz', 'rb') ?: @fopen($name, 'rb');
		}
		if (!is_resource($f))
		{
			return $info;
		}
		$table = $cols = null;
		while (($line = fgets($f)) !== false)
		{
			if (substr($line, 0, 14) === 'secret_check: ')
			{
				$info['secret_check'] = trim(substr($line, 14));
				break;
			}
			if (substr($line, 0, 7) === 'table: ')
			{
				if ($table === 'egw_ea_credentials')	// credentials table is over and had no value to probe with
				{
					break;
				}
				$table = trim(substr($line, 7));
				$cols = $table === 'egw_ea_credentials' ? self::csv_split(trim(fgets($f))) : null;
			}
			elseif ($table === 'egw_ea_credentials' && $cols && count($data = self::csv_split(trim($line))) === count($cols))
			{
				$data = array_combine($cols, $data);
				if ((int)$data['cred_pw_enc'] === Credentials::SYSTEM_AES && !empty($data['cred_password']))
				{
					// cred_password is a blob, which is base64 encoded in the backup
					$info['probe'] = base64_decode($data['cred_password'], true) ?: $data['cred_password'];
					break;
				}
			}
		}
		fclose($f);
		unset($zip);

		return $info;
	}

	/**
	 * Check if a backup was encrypted with the given DB password
	 *
	 * @param string $filename backup file, relative to the backup directory
	 * @param string $password DB password to check
	 * @return ?bool true: it was created with $password, false: it was NOT, null: backup contains nothing to tell
	 */
	public function checkDbPassword(string $filename, string $password) : ?bool
	{
		$info = $this->readBackupInfo($filename);

		if (isset($info['secret_check']))
		{
			return Credentials::decryptWithKey($info['secret_check'], $password) === self::SECRET_CHECK;
		}
		if (isset($info['probe']))
		{
			// mail credentials can be binary (S/MIME keys)
			return Credentials::decryptWithKey($info['probe'], $password, false) !== null;
		}
		return null;
	}

	/**
	 * Check if the DB password of the backup differs from the current one, which makes encrypted passwords unreadable
	 *
	 * @param string $filename backup file, relative to the backup directory
	 * @return ?bool true: it differs, false: it's the same, null: backup contains nothing to tell
	 */
	public function dbPasswordChanged(string $filename) : ?bool
	{
		$same = (string)$this->db->Password === '' ? null : $this->checkDbPassword($filename, $this->db->Password);

		return isset($same) ? !$same : null;
	}

	/**
	 * Re-encrypt everything in the (restored) database encrypted with the old DB password with the new one
	 *
	 * That are:
	 * - mail account credentials (egw_ea_credentials), only the AES ones, which were encrypted with the DB password
	 * - password customfields in the egw_*_extra tables and, for filemanager, in the file properties (egw_sqlfs_props)
	 * - passwords of subscribed calendars in egw_categories
	 * - reversible passwords of shares in egw_sharing
	 * Values, which can not be decrypted with the old password (eg. encrypted with a user password) are left untouched.
	 *
	 * @param string $old_key DB password the backup was created with
	 * @param string $new_key current DB password
	 * @return int[] number of re-encrypted values by "credentials", "customfields", "subscriptions", "shares" and number of "skipped" ones
	 */
	protected function reencrypt(string $old_key, string $new_key) : array
	{
		$stats = ['credentials' => 0, 'customfields' => 0, 'subscriptions' => 0, 'shares' => 0, 'skipped' => 0];

		// mail account credentials, SSO password, 2FA secrets, ...
		$stats['credentials'] = $this->reencryptRows('egw_ea_credentials', ['cred_id'], 'cred_password',
			['cred_pw_enc' => Credentials::SYSTEM_AES], $old_key, $new_key, $stats['skipped'], false);

		// password customfields
		$cfs = [];
		if (isset($this->schemas['egw_customfields']))
		{
			foreach($this->db->select('egw_customfields', 'cf_app,cf_name', ['cf_type' => 'passwd'], __LINE__, __FILE__,
				false, '', false, 0, '', $this->schemas['egw_customfields']) as $row)
			{
				$cfs[$row['cf_app']][] = $row['cf_name'];
			}
		}
		foreach($cfs as $app => $names)
		{
			if ($app === 'filemanager')	// stored as properties of the files, with a "#" prefix
			{
				$stats['customfields'] += $this->reencryptRows('egw_sqlfs_props', ['prop_id'], 'prop_value', [
					'prop_namespace' => Api\Vfs::DEFAULT_PROP_NAMESPACE,
					'prop_name' => array_map(static fn($name) => '#'.$name, $names),
				], $old_key, $new_key, $stats['skipped']);
				continue;
			}
			$table = $app === 'calendar' ? 'egw_cal_extra' : 'egw_'.$app.'_extra';
			if (!isset($this->schemas[$table])) continue;

			$columns = array_keys($this->schemas[$table]['fd']);
			$name_col = current(preg_grep('/_name$/', $columns));
			$value_col = current(preg_grep('/_value$/', $columns));
			if (!$name_col || !$value_col || !$this->schemas[$table]['pk']) continue;

			$stats['customfields'] += $this->reencryptRows($table, $this->schemas[$table]['pk'], $value_col,
				[$name_col => $names], $old_key, $new_key, $stats['skipped']);
		}

		// reversible share passwords
		$stats['shares'] = $this->reencryptRows('egw_sharing', ['share_id'], 'share_pw_reversable',
			"share_pw_reversable != ''", $old_key, $new_key, $stats['skipped']);

		// subscribed calendars: password is stored encrypted inside the JSON in cat_data
		if (isset($this->schemas['egw_categories']))
		{
			foreach($this->db->select('egw_categories', 'cat_id,cat_data', 'cat_data LIKE '.$this->db->quote('%SUBSCRIBED-CALENDAR%'),
				__LINE__, __FILE__, false, '', false, 0, '', $this->schemas['egw_categories']) as $row)
			{
				$data = json_php_unserialize($row['cat_data'], true);
				if (!is_array($data) || empty($data['password']) || strlen($data['password']) <= 32) continue;

				if (($new = Credentials::reencrypt($data['password'], $old_key, $new_key)) === null)
				{
					++$stats['skipped'];
					continue;
				}
				$data['password'] = $new;
				$this->db->update('egw_categories', ['cat_data' => json_encode($data)], ['cat_id' => $row['cat_id']],
					__LINE__, __FILE__, false, false, $this->schemas['egw_categories']);
				++$stats['subscriptions'];
			}
		}
		return $stats;
	}

	/**
	 * Re-encrypt one column of a table
	 *
	 * @param string $table
	 * @param string[] $pk columns identifying a row
	 * @param string $column column with the encrypted value
	 * @param array|string $where which rows to re-encrypt
	 * @param string $old_key
	 * @param string $new_key
	 * @param int& $skipped increased by the number of values not decryptable with $old_key
	 * @param bool $printable =true false: values can be binary, eg. S/MIME keys, see Credentials::decryptWithKey()
	 * @return int number of re-encrypted values
	 */
	protected function reencryptRows(string $table, array $pk, string $column, $where, string $old_key, string $new_key, int &$skipped, bool $printable=true) : int
	{
		if (!isset($this->schemas[$table]) || array_diff(array_merge($pk, [$column]), array_keys($this->schemas[$table]['fd'])))
		{
			return 0;
		}
		$rows = [];
		foreach($this->db->select($table, implode(',', array_merge($pk, [$column])), $where, __LINE__, __FILE__,
			false, '', false, 0, '', $this->schemas[$table]) as $row)
		{
			$rows[] = $row;
		}
		$done = 0;
		foreach($rows as $row)
		{
			if ((string)$row[$column] === '')	// nothing to encrypt
			{
				continue;
			}
			if (!is_string($row[$column]) || ($new = Credentials::reencrypt($row[$column], $old_key, $new_key, $printable)) === null)
			{
				++$skipped;
				continue;
			}
			$this->db->update($table, [$column => $new], array_intersect_key($row, array_flip($pk)),
				__LINE__, __FILE__, false, false, $this->schemas[$table]);
			++$done;
		}
		return $done;
	}

	/**
	 * Restore data from a (compressed) csv file
	 *
	 * @param resource $f file opened with fopen for reading
	 * @param int|string $insert_n_rows =500 how many rows to insert in one sql statement, or string with column-name used as unique key for insert
	 * @param bool $only_egw=false true: only restore egw_* tables, setting this to true often helps to import/update ancient EGroupware dumps
	 * @returns int number of rows read from csv file
	 */
	function db_restore($f, $insert_n_rows=500, bool $only_egw=false)
	{
		$convert_to_system_charset = true;
		$table = null;
		$n = 0;
		$rows = array();
		while(!feof($f))
		{
			$line = trim(fgets($f)); ++$n;

			if (empty($line)) continue;

			if (substr($line,0,9) == 'version: ')
			{
				// currently not used: $api_version = trim(substr($line,9));
				continue;
			}
			if (substr($line,0,9) == 'charset: ')
			{
				$charset = trim(substr($line,9));
				// needed if mbstring.func_overload > 0, else eg. substr does not work with non ascii chars
				$ini_default_charset = version_compare(PHP_VERSION, '5.6', '<') ? 'mbstring.internal_encoding' : 'default_charset';
				@ini_set($ini_default_charset, $charset);

				// check if we really need to convert the charset, as it's not perfect and can do some damage
				if ($convert_to_system_charset && !strcasecmp($this->schema_proc->system_charset, $charset))
				{
					$convert_to_system_charset = false;	// no conversation necessary
				}
				continue;
			}
			if (substr($line,0,8) == 'schema: ')
			{
				// create the tables in the backup set
				$this->schemas = json_php_unserialize(trim(substr($line,8)));
				foreach($this->schemas as $table_name => $schema)
				{
					// do NOT create GUACAMOLE tables, just truncate them (as we have no abstraction to create the foreign keys)
					if (preg_match(self::GUACAMOLE_REGEXP, $table_name))
					{
						try {
							$this->db->query('TRUNCATE TABLE '.$this->db->name_quote($table_name));
						}
						catch (Exception $e) {
							echo "<p><b>Failed to TRUNCATE TABLE $table_name: ".$e->getMessage()."</b></p>\n";
						}
						continue;
					}
					// if column is longtext in current schema, convert text to longtext, in case user already updated column
					foreach($schema['fd'] as $col => &$def)
					{
						if ($def['type'] == 'text' && $this->db->get_column_attribute($col, $table_name, true, 'type') == 'longtext')
						{
							$def['type'] = 'longtext';
						}
					}
					unset($def);
					//echo "<pre>$table_name => ".self::write_array($schema,1)."</pre>\n";
					if (!$only_egw || preg_match('/^egw_/', $table_name))
					{
						$this->schema_proc->CreateTable($table_name, $schema);
					}
				}
				continue;
			}
			if (substr($line,0,7) == 'table: ')
			{
				if ($rows)	// flush pending rows of last table
				{
					$this->insert_multiple($table, $rows, $this->schemas[$table]);
				}
				$rows = array();
				$table = substr($line,7);
				if (!isset($this->schemas[$table])) $this->schemas[$table] = $this->db->get_table_definitions(true, $table);
				$auto_id = count($this->schemas[$table]['pk']) == 1 ? $this->schemas[$table]['pk'][0] : null;

				$cols = self::csv_split($line=fgets($f)); ++$n;
				$blobs = array();
				foreach($this->schemas[$table]['fd'] as $col => $data)
				{
					if (in_array($data['type'], ['blob', 'binary', 'vector']))
					{
						$blobs[] = $col;
					}
				}
				// check if we have an old PostgreSQL backup using 't'/'f' for bool values
				// --> convert them to MySQL and our new PostgreSQL format of 1/0
				$bools = array();
				foreach($this->schemas[$table]['fd'] as $col => $def)
				{
					if ($def['type'] === 'bool') $bools[] = $col;
				}

				if (feof($f)) break;
				continue;
			}
			if ($table)	// do we already reached the data part
			{
				$import = !$only_egw || preg_match('/^egw_/', $table);
				$data = self::csv_split($line, $cols, $blobs, $bools);

				if ($table == 'egw_async' && in_array('##last-check-run##',$data))
				{
					//echo '<p>'.lang("Line %1: '%2'<br><b>csv data does contain ##last-check-run## of table %3 ==> ignored</b>",$n,$line,$table)."</p>\n";
					//echo 'data=<pre>'.print_r($data,true)."</pre>\n";
					$import = false;
				}
				if (in_array($table,$this->exclude_tables))
				{
					echo '<p><b>'.lang("Table %1 is excluded from backup and restore. Data will not be restored.",$table)."</b></p>\n";
					$import = false; // dont restore data of excluded tables
				}
				if ($import)
				{
					if (count($data) == count($cols))
					{
						if ($convert_to_system_charset)
						{
							$data = Api\Translation::convert($data,$charset);
						}
						if ($insert_n_rows > 1)
						{
							$rows[] = $data;
							if (count($rows) == $insert_n_rows ||
								// check every 50 rows, if we might reach MySQLs max_allowed_packet=1MB
								(!(count($rows) % 50) && strlen(json_encode($rows)) > 500000))
							{
								$this->insert_multiple($table, $rows, $this->schemas[$table]);
								$rows = array();
							}
						}
						// update existing table using given unique key in $insert_n_rows (also removing auto-id/sequence)
						elseif(!is_numeric($insert_n_rows))
						{
							$where = array($insert_n_rows => $data[$insert_n_rows]);
							unset($data[$insert_n_rows]);
							if ($auto_id) unset($data[$auto_id]);
							$this->db->insert($table,$data,$where,__LINE__,__FILE__,false,false,$this->schemas[$table]);
						}
						else
						{
							try {
								$this->db->insert($table,$data,False,__LINE__,__FILE__,false,false,$this->schemas[$table]);
							}
							catch(Exception\InvalidSql $e) {
								echo "<p>".$e->getMessage()."</p>\n";
							}
						}
					}
					else
					{
						echo '<p>'.lang("Line %1: '%2'<br><b>csv data does not match column-count of table %3 ==> ignored</b>",$n,$line,$table)."</p>\n";
						echo 'data=<pre>'.print_r($data,true)."</pre>\n";
					}
				}
			}
		}
		if ($rows)	// flush pending rows
		{
			$this->insert_multiple($table, $rows, $this->schemas[$table]);
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

		// check if backup contained all indexes and create missing ones
		$this->schema_proc->CheckCreateIndexes();

		return $n;
	}

	/**
	 * Insert multiple rows ignoring duplicate entries
	 *
	 * @param string $table
	 * @param array $rows
	 * @param array $schema
	 */
	private function insert_multiple($table, array $rows, array $schema)
	{
		try {
			$this->db->insert($table, $rows, False, __LINE__, __FILE__, false, false, $schema);
		}
		catch(Exception\InvalidSql $e)
		{
			// try inserting them one by one, ignoring duplicates
			foreach($rows as $data)
			{
				try {
					$this->db->insert($table, $data, False, __LINE__, __FILE__, false, false, $schema);
				}
				catch(Exception\InvalidSql $e) {
					echo "<p>$table: ".$e->getMessage()." ignored</p>\n";
				}
			}
		}
	}

	/**
	 * Removes a dir, no matter whether it is empty or full
	 *
	 * @param string $dir
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
	 * temp. replaces backslashes
	 */
	const BACKSLASH_TOKEN = '##!!**bAcKsLaSh**!!##';
	/**
	 * temp. replaces NULL
	 */
	const NULL_TOKEN = '##!!**NuLl**!!##';

	/**
	 * Split one line of a csv file into an array and does all unescaping
	 *
	 * @param string $line line to split
	 * @param array $keys =null keys to use or null to use numeric ones
	 * @param array $blobs =array() blob columns
	 * @param array $bools =array() bool columns, values might be 't'/'f' for old PostgreSQL backups
	 * @return array
	 */
	public static function csv_split($line, $keys=null, $blobs=array(), $bools=array())
	{
		if (function_exists('str_getcsv'))	// php5.3+
		{
			// we need to take care of literal "NULL" values, replacing them with a special token as str_getcsv removes enclosures around strings
			// str_getcsv uses '""' for '"' instead of '\\"' and does not unescape '\\n', '\\r' or '\\\\' (two backslashes)
			$fields = str_getcsv(strtr($line, array(
				'"NULL"' => self::NULL_TOKEN,
				'\\\\'   => self::BACKSLASH_TOKEN,
				'\\"'    => '""',
				'\\n'    => "\n",
				'\\r'    => "\r")), ',', '"');
			// replace NULL-token again with 'NULL', 'NULL' with null and BACKSLASH-token with a backslash
			foreach($fields as &$field)
			{
				switch($field)
				{
					case self::NULL_TOKEN:
						$field = 'NULL';
						break;
					case 'NULL':
						$field = null;
						break;
					default:
						$field = str_replace(self::BACKSLASH_TOKEN, '\\', $field);
						break;
				}
			}
			unset($field);
			if ($keys)	// if string keys are to be used --> combine keys and values
			{
				$fields = array_combine($keys, $fields);
				// base64-decode blob columns, if they are base64 encoded
				foreach($blobs as $key)
				{
					if (!is_null($fields[$key]) && ($tmp = base64_decode($fields[$key], true)) !== false)
					{
						$fields[$key] = $tmp;
					}
				}
				// decode bool columns, they might be 't'/'f' for old PostgreSQL backups
				foreach($bools as $key)
				{
					if (isset($fields[$key]))	// do NOT replace NULL with false/0
					{
						$fields[$key] = Api\Db::from_bool($fields[$key]);
					}
				}
			}
			return $fields;
		}
		// pre 5.3 implementation
		$fields = explode(',',trim($line));

		$str_pending = False;
		$n = 0;
		foreach($fields as $field)
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
			elseif ($keys && strlen($field) > 26)
			{
				$arr[$key] = base64_decode($field);
			}
			elseif ($field !== 'NULL' && in_array($key, $bools))
			{
				$arr[$key] = Api\Db::from_bool($field);
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
				case 'binary':
				case 'vector':
					$data = base64_encode($data);
					break;
				case 'bool':	// we use MySQL 0, 1 in csv, not PostgreSQL 't', 'f'
					$data = (int)Api\Db::from_bool($data);
					break;
				default:
					$data = '"'.str_replace(array('\\',"\n","\r",'"'),array('\\\\','\\n','\\r','\\"'),$data).'"';
					break;
			}
		}
	}

	/**
	 * Number of rows to select per chunk, to not run into memory limit on huge tables
	 */
	const ROW_CHUNK = 2000;

	/**
	 * Get the biggest possible chunk-size for given table to NOT run into memory-limit
	 *
	 * We use the average row size reported from the DB for that given table and half of the memory limit.
	 *
	 * @param string $table
	 * @param ?int &$rows on return number of rows in table
	 * @return float|int
	 */
	protected function rowChunk(string $table, ?int &$rows=null)
	{
		static $tables = null;
		static $memory_limit;
		if (!isset($tables))
		{
			try {
				$memory_limit = ini_get('memory_limit');
				$memory_limit = function_exists('ini_parse_quantity') ?
					ini_parse_quantity($memory_limit) : 1024*1024*min(128, (int)$memory_limit);
				foreach($this->db->query("SELECT TABLE_NAME,TABLE_ROWS,AVG_ROW_LENGTH FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=".$this->db->quote($this->db->Database)) as $row)
				{
					$tables[$row['TABLE_NAME']] = [
						'rows' => $row['TABLE_ROWS'],
						'avg_length' => $row['AVG_ROW_LENGTH'],
					];
				}
			}
			catch (\Exception $e) {
				$tables = false;
				_egw_log_exception($e);
			}
		}
		$rows = $tables[$table]['rows'] ?? null;
		if ($tables === false || empty($tables[$table]['avg_length']))
		{
			return self::ROW_CHUNK;    // use current default chunk-size
		}
		return 1000*round($memory_limit / $tables[$table]['avg_length'] / 2 / 1000) ?: 1000;
	}

	/**
	 * Backup all data in the form of a (compressed) csv file
	 *
	 * @param resource $f file opened with fopen for writing
	 * @param boolean $lock_table =null true: allways, false: never, null: if no primary key
	 *	default of null limits memory usage if there is no primary key, by locking table and use ROW_CHUCK
	 * @param bool $skip_files_backup =false true: do not backup files, even if config / $this->backup_files is set (used by upgrade)
	 * @return string|true true: on success, string with error-message on failure
	 * @todo use https://github.com/maennchen/ZipStream-PHP to not assemble all files in memmory
	 */
	function backup($f, $lock_table=null, bool $skip_files_backup=false)
	{
		@set_time_limit(0);
		$dir = $this->files_dir; // $GLOBALS['egw_info']['server']['files_dir'];
		// we may have to clean up old backup - leftovers
		if (is_dir($dir.'/database_backup'))
		{
			self::remove_dir_content($dir.'/database_backup/');
			rmdir($dir.'/database_backup');
		}

		$file_list = array();
		$name = $this->backup_dir.'/db_backup-'.date('YmdHi');
		$filename = $name.'.zip';
		$zippresent = false;
		if(class_exists('ZipArchive') && $this->backup_files && !$skip_files_backup)
		{
			$zip = new ZipArchive;
			if(is_object($zip))
			{
				$zippresent = true;
				//echo '-> is_object($zip); '.$filename.'<br>';	// !
				$res = $zip->open($filename, ZipArchive::CREATE);
				if($res !== TRUE)
				{
					$this->log($filename, false, null, lang("Cant open '%1' for %2", $filename, lang("writing")));
					return lang("Cant open '%1' for %2", $filename, lang("writing"))."<br>\n";
				}
				$file_list = $this->get_file_list($dir);
			}
		}
		fwrite($f,"EGroupware backup from ".date('Y-m-d H:i:s')."\n\n");

		fwrite($f,"version: $this->api_version\n\n");

		fwrite($f,"charset: $this->charset\n\n");

		// allows to detect on restore, if DB password (used to encrypt passwords in the DB) changed
		if ((string)$this->db->Password !== '')
		{
			fwrite($f, 'secret_check: '.Credentials::encryptWithKey(self::SECRET_CHECK, $this->db->Password)."\n\n");
		}
		$this->schema_backup($f);	// add the schema in a human-readable form too

		fwrite($f,"\nschema: ".json_encode($this->schemas)."\n");

		// let apps know that backup is about to start
		Api\Hooks::process('backup_starts', array(), true);

		$t = 0;
		foreach($this->schemas as $table => $schema)
		{
			if (in_array($table,$this->exclude_tables)) continue;	// dont backup
			$t++;

			// do we have a primary key?
			// --> use it to order and limit rows, to cope with rows being added during backup
			// otherwise new rows can cause rows being backed up twice and
			// backups don't restore because of deduplicate keys
			$pk = $schema['pk'] && count($schema['pk']) == 1 ? $schema['pk'][0] : null;

			if ($lock_table || empty($pk) && is_null($lock_table))
			{
				$this->db->row_lock($table);
			}
			$total = $max = 0;
			$row_chunk = $this->rowChunk($table, $db_rows);
			do {
				$num_rows = 0;
				// querying only chunks of 2000 rows, to not run into memory limit on huge tables
				foreach($this->db->select($table, '*',
					// limit by maximum primary key already received
					empty($pk) || !$max ? false : $pk.' > '.$this->db->quote($max, $schema['fd'][$pk]['type']),
					__LINE__, __FILE__,
					// if no primary key, either lock table, or query all rows
					empty($pk) ? ($lock_table !== false ? $total : false) : 0,
					// if we have a primary key, order by it to ensure rows are not read double
					empty($pk) ? '' : 'ORDER BY '.$this->db->name_quote($pk).' ASC',
					false, $row_chunk) as $row)
				{
					if (!empty($pk)) $max = $row[$pk];
					if ($total === 0) fwrite($f,"\ntable: $table\n".implode(',',array_keys($row))."\n");

					array_walk($row, array(__CLASS__, 'escape_data'), $schema['fd']);
					fwrite($f,implode(',',$row)."\n");
					++$total;
					++$num_rows;
				}
				//$this->log($filename, false, null, "#$t. table $table: rowChunk=$row_chunk, total-rows=$db_rows, backed-up=$total");
			}
			while((!empty($pk) || $lock_table !== false) && !($total % $row_chunk) && $num_rows);

			if (!$pk && $lock_table !== false) $this->db->rollback_lock($table);
		}

		// let apps know that backup is finished
		Api\Hooks::process('backup_finished', array(), true);

		if(!$zippresent)  // save without files
		{
			if ($this->backup_files && !$skip_files_backup)
			{
				$this->log($name, false, null, lang("Cant open %1, needs ZipArchive", $name));
				echo '<center>'.lang("Cant open %1, needs ZipArchive", $name)."<br>\n".'</center>';
			}

			// get actual filename from stream
			$filename_parts = explode('://', stream_get_meta_data($f)['uri'] ?? $name);

			fclose($f);
		    //if (file_exists($name)) unlink($name);

			// log successful end of backup
			$this->log(array_pop($filename_parts), false, false);

			return TRUE;
		}
		// save files ....
		//echo $name.'<br>';
		$zip->addFile($name, 'database_backup/'.basename($name));
		$count = 1;
		foreach($file_list as $file)
		{
			//echo substr($file,strlen($dir)+1).'<br>';
			//echo $file.'<br>';
			$zip->addFile($file,substr($file,strlen($dir)+1));//,substr($file);
			if(($count++) == 100) { // the file descriptor limit
				$zip->close();
				if(($zip = new ZipArchive())) {
					$zip->open($filename);
					$count =0;
				}
			}
		}
		$zip->close();
		fclose($f);
		unlink($name);

		// log successful end of backup
		$this->log($filename, false, false);

		return true;
	}

	/**
	 * gets a list of all files on $f
	 *
	 * @param string file $f
	 * @param int $cnt =0
	 * @param string $path_name =''
	 *
	 * @return array (list of files)
	 */
	function get_file_list($f, $cnt = 0, $path_name = '')
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
			elseif ($file == 'debug.txt' && stripos($f,'activesync')!==false)
			{
				// skip activesync debug.txt on backupFiles
				//error_log(__METHOD__.__LINE__.'->'.$f.'/'.$file);
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
			// check if indexed column is a text column: add MariaDB/MySQL specific index type (our DB abstraction does NOT report it)
			foreach($this->schemas[$table]['ix'] as $key => $index)
			{
				foreach((array)$index as $k => $col)
				{
					if (is_int($k) && in_array($this->schemas[$table]['fd'][$col]['type'], array('text','longtext')) && !isset($index['options']))
					{
						if (!is_array($index))
						{
							$this->schemas[$table]['ix'][$key] = [$index];
						}
						$this->schemas[$table]['ix'][$key]['options'] = ['mysql' => 'FULLTEXT'];
					}
				}
			}
		}
		ksort($this->schemas, SORT_STRING);
		$def = "\t\$phpgw_baseline = ";
		$def .= self::write_array($this->schemas,1);
		$def .= ";\n";

		if ($f)
		{
			fwrite($f,$def);
		}
		else
		{
			$def = "<?php\n\t/* EGroupware schema-backup from ".date('Y-m-d H:i:s')." */\n\n".$def;
			Api\Header\Content::type('schema-backup-'.date('YmdHi').'.inc.php','text/plain',bytes($def));
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
				if ($key === 'nullable')
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

	const LOG_FILE = 'db_backup.log';

	/**
	 * Log backup and restore start- and stop-time, plus file-name, -size and sha1 hash
	 *
	 * @param string $file filename
	 * @param bool|string $restore true: 'Restore', false: 'Backup', string with custom label
	 * @param bool $start true: start of operation, false: end, null: neither
	 * @param ?string $error_msg optional error-msg to log (prefix with # to not log as error)
	 */
	public function log(string $file, $restore, ?bool $start=null, ?string $error_msg=null)
	{
		$msg = (is_string($restore) ? $restore : ($restore ? 'Restore' : 'Backup')).' ';
		if (isset($start))
		{
			$msg .= $start ? 'started ' : 'finished ';
		}
		$msg .= 'at '.date('Y-m-d H:i:s e').' ';
		if (!empty($GLOBALS['egw_setup']))
		{
			$msg .= 'from setup: ';
		}
		elseif (!empty($GLOBALS['egw_info']['user']['account_lid']))
		{
			$msg .= 'by user '.$GLOBALS['egw_info']['user']['account_lid'].': ';
		}
		$msg .= ($path = $file[0] === '/' ? $file : $this->backup_dir.'/'.$file).' ';
		if (empty($this->backup_dir) || !file_exists($path))
		{
			if (empty($error_msg) && (!$start || $restore))
			{
				$msg .= 'NOT existing!';
			}
		}
		else
		{
			$msg .= sprintf('%3.1f MB (%d)',filesize($path)/(1024*1024), filesize($path)).' sha1: '.sha1_file($path);
		}
		if (!empty($error_msg))
		{
			if ($error_msg[0] === '#')
			{
				$msg .= ' '.substr($error_msg,1);
			}
			else
			{
				$msg .= ' ERROR: '.$error_msg;
			}
		}

		// try opening log-file in backup-dir, or /var/lib/egroupware
		if (!empty($this->backup_dir) && ($f = fopen($this->backup_dir.'/'.self::LOG_FILE, 'a')) ||
			($f = fopen('/var/lib/egroupware/'.self::LOG_FILE, 'a')))
		{
			fwrite($f, $msg."\n".($start === false ? "\n" : ''));
			fclose($f);
		}
		else
		{
			error_log("Could NOT open ".self::LOG_FILE.': '.$msg);
		}
	}

	/**
	 * Move uploaded file to backup-directory
	 *
	 * @param array $file values for keys "tmp_name", "name", "size"
	 * @return string success or error message
	 */
	public function upload(array $file) : string
	{
		if (is_uploaded_file($file['tmp_name']) && ($src=fopen($file['tmp_name'], 'r')) &&
			($dst=fopen($filename = $this->backup_dir . '/' . basename($file['name']), 'w')) &&
			stream_copy_to_stream($src, $dst) !== false && fclose($dst))
		{
			$msg = lang("succesfully uploaded file %1", $filename . ', ' .
					sprintf('%3.1f MB (%d)', $file['size'] / (1024 * 1024), $file['size'])) .
				', md5=' . md5_file($file['tmp_name']) . ', sha1=' . sha1_file($file['tmp_name']);
		}
		else
		{
			$msg = lang('Error uploading your backup!');
		}

		if (isset($src))
		{
			fclose($src);
		}
		$this->log($filename, $msg);

		return $msg;
	}

	/**
	 * Delete a backup
	 *
	 * @param string $name filename
	 * @return string|null success message or null on error
	 */
	public function delete(string $name) : ?string
	{
		if (unlink($file = $this->backup_dir.'/'.basename($name)))	// basename to not allow to change the dir
		{
			$this->log($file, $msg = lang("backup '%1' deleted", $file));
		}
		return $msg ?? null;
	}

	/**
	 * Rename a backup
	 * @param string $file
	 * @param string $new_name
	 * @return string|null success message or null on error
	 */
	public function rename(string $file, string $new_name) : ?string
	{
		[$ending] = array_reverse(explode('.', $file));
		[$new_ending, $has_ending] = array_reverse(explode('.', $new_name));
		if (!$has_ending || $new_ending != $ending)
		{
			$new_name .= '.' . $ending;
		}
		$file = $this->backup_dir.'/'.basename($file);	// basename to not allow to change the dir
		$ext = preg_match('/(\.gz|\.bz2)+$/i',$file,$matches) ? $matches[1] : '';
		$new_file = $this->backup_dir.'/'.preg_replace('/(\.gz|\.bz2)+$/i','',basename($new_name)).$ext;
		if (rename($file, $new_file))
		{
			$this->log($new_file, $msg=lang("backup '%1' renamed to '%2'", basename($file), basename($new_file)));
		}
		return $msg ?? null;
	}

	/**
	 * List available backups for restore
	 *
	 * @return array filename => [$ctime, $size] pairs
	 */
	public function index() : array
	{
		$files = [];
		if (($handle = opendir($this->backup_dir)))
		{
			while(($file = readdir($handle)))
			{
				if ($file != '.' && $file != '..')
				{
					$files[$file] = [
						'ctime' => filectime($this->backup_dir.'/'.$file),
						'size' => filesize($this->backup_dir.'/'.$file)
					];
				}
			}
			closedir($handle);
		}
		arsort($files);
		return $files;
	}
}

/*
$line = '"de","NULL","ranking",NULL,NULL,"one backslash: \\\\ here","\\\\","use \\"yes\\", or \\"no, prefession\\"","benützen Sie \\"yes\\" oder \\"no, Beruf\\"",NULL';

echo "<p>line='$line'</p>\n";
$fields = Backup::csv_split($line);
echo "<pre>".print_r($fields,true)."</pre>\n";
//echo count($fields)." fields\n";
*/