<?php
/**
 * EGroupware Api: tests for detecting a changed DB password on restore of a backup and re-encrypting
 * everything encrypted with the DB password aka system secret (mail account credentials, password customfields, ...)
 *
 * @package api
 * @subpackage db
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Db;

use EGroupware\Api;
use EGroupware\Api\Mail\Credentials;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use ZipArchive;

/**
 * Backup subclass writing no schema and therefore no table data, to test the header of a backup
 */
class BackupWithoutTables extends Backup
{
	function schema_backup($f=False)
	{
		$this->schemas = [];
	}
}

/**
 * Backup subclass reimplementing restore() with its original parameters, like Stylite's S3\Backup does:
 * PHP refuses to load it, should restore() get an incompatible signature, as it did when it got an extra parameter
 */
class BackupWithOriginalRestoreSignature extends Backup
{
	public function restore($f, $convert_to_system_charset = true, $filename = '', $protect_system_config = true, $insert_n_rows = 500)
	{
		return parent::restore($f, $convert_to_system_charset, $filename, $protect_system_config, $insert_n_rows);
	}
}

/**
 * Needs a DB connection, but never changes existing data:
 * - backups are written to and read from a temporary directory
 * - re-encrypting runs against TEMPORARY tables, which shadow the real ones for our own connection and are dropped again
 * - restore() is only called, where it has to return before it drops a table
 */
class BackupDbPasswordTest extends TestCase
{
	/**
	 * @var Api\Db own connection, so TEMPORARY tables do not leak into the connection of other tests
	 */
	private static $db;

	/**
	 * Tables the re-encryption reads and writes
	 */
	const TABLES = ['egw_ea_credentials', 'egw_customfields', 'egw_addressbook_extra', 'egw_cal_extra', 'egw_sharing', 'egw_categories', 'egw_sqlfs_props'];

	private $tmp_dir;
	private $temp_tables = [];
	private $saved_egw;
	private $saved_egw_info;

	public static function setUpBeforeClass() : void
	{
		$_REQUEST['domain'] = $GLOBALS['EGW_DOMAIN'];
		$GLOBALS['egw_info'] = ['flags' => ['noheader' => true, 'nonavbar' => true, 'currentapp' => 'setup', 'noapi' => true]];
		require(__DIR__.'/../../../header.inc.php');

		$default_domain = null;
		$domain = Api\Session::search_instance(null, $GLOBALS['EGW_DOMAIN'], $default_domain,
			[$_SERVER['HTTP_HOST'] ?? '', $_SERVER['SERVER_NAME'] ?? ''], $GLOBALS['egw_domain']);

		self::$db = new Api\Db($GLOBALS['egw_domain'][$domain]);
		self::$db->connect();
	}

	protected function setUp() : void
	{
		$this->saved_egw = $GLOBALS['egw'] ?? null;
		$this->saved_egw_info = $GLOBALS['egw_info'] ?? null;

		$this->tmp_dir = sys_get_temp_dir().'/egw-backup-test-'.uniqid();
		mkdir($this->tmp_dir);

		$GLOBALS['egw'] = new \stdClass();
		$GLOBALS['egw']->db = self::$db;
		$GLOBALS['egw_info']['server']['backup_dir'] = $this->tmp_dir;
		$GLOBALS['egw_info']['server']['files_dir'] = $this->tmp_dir.'/files';
		$GLOBALS['egw_info']['server']['backup_mincount'] = 0;
		$GLOBALS['egw_info']['server']['backup_files'] = false;
		$GLOBALS['egw_info']['apps']['api']['version'] = '0';
	}

	protected function tearDown() : void
	{
		foreach($this->temp_tables as $table)
		{
			self::$db->query('DROP TEMPORARY TABLE IF EXISTS '.$table, __LINE__, __FILE__);
		}
		$this->temp_tables = [];

		foreach(glob($this->tmp_dir.'/*') as $file)
		{
			unlink($file);
		}
		rmdir($this->tmp_dir);

		$GLOBALS['egw'] = $this->saved_egw;
		$GLOBALS['egw_info'] = $this->saved_egw_info;
	}

	/**
	 * DB password of this installation
	 */
	private function dbPassword() : string
	{
		return self::$db->Password;
	}

	private function newBackup(string $class = Backup::class) : Backup
	{
		$backup = new $class();
		$backup->backup_dir = $this->tmp_dir;

		return $backup;
	}

	/**
	 * Content of an (old) backup with the egw_ea_credentials table, but without secret_check line
	 */
	private function legacyBackup(array $credentials) : string
	{
		$rows = '';
		foreach($credentials as $id => [$encrypted, $pw_enc])
		{
			// cred_password is a blob and therefore base64 encoded in the backup
			$rows .= $id.',1,1,0,"user'.$id.'",'.base64_encode($encrypted).','.$pw_enc."\n";
		}
		return "EGroupware backup from 2024-03-27 11:43:00\n\nversion: 24.1\n\ncharset: utf-8\n\n".
			"\t\$phpgw_baseline = array(\n\t'egw_ea_credentials' => array(),\n);\n\n".
			"schema: {}\n\n".
			"table: egw_addressbook\ncontact_id,n_family\n1,\"Test\"\n\n".
			"table: egw_ea_credentials\ncred_id,acc_id,cred_type,account_id,cred_username,cred_password,cred_pw_enc\n".$rows.
			"\ntable: egw_zzz\nid\n1\n";
	}

	/**
	 * Content of a backup with secret_check line
	 */
	private function backupContent(string $db_password) : string
	{
		return "EGroupware backup from 2026-10-23 11:43:00\n\nversion: 26.1\n\ncharset: utf-8\n\n".
			'secret_check: '.Credentials::encryptWithKey(Backup::SECRET_CHECK, $db_password)."\n\n".
			"schema: {}\n";
	}

	/**
	 * Write a backup in the requested format, return filename relative to backup dir
	 */
	private function writeBackup(string $content, string $format = 'plain') : string
	{
		switch($format)
		{
			case 'gz':
				file_put_contents('compress.zlib://'.$this->tmp_dir.'/db_backup-test.gz', $content);
				return 'db_backup-test.gz';
			case 'bz2':
				file_put_contents('compress.bzip2://'.$this->tmp_dir.'/db_backup-test.bz2', $content);
				return 'db_backup-test.bz2';
			case 'zip':
				$zip = new ZipArchive();
				$this->assertTrue($zip->open($this->tmp_dir.'/db_backup-test.zip', ZipArchive::CREATE));
				$zip->addFromString('database_backup/db_backup-test', $content);
				$zip->addFromString('some/other/file', 'not the backup');
				$zip->close();
				return 'db_backup-test.zip';
		}
		file_put_contents($this->tmp_dir.'/db_backup-test', $content);
		return 'db_backup-test';
	}

	#[DataProvider('formatProvider')]
	public function testUnchangedPassword(string $format)
	{
		$backup = $this->newBackup();
		$file = $this->writeBackup($this->backupContent($this->dbPassword()), $format);

		$this->assertTrue($backup->checkDbPassword($file, $this->dbPassword()));
		$this->assertFalse($backup->checkDbPassword($file, 'some other password'));
		$this->assertFalse($backup->dbPasswordChanged($file));
	}

	#[DataProvider('formatProvider')]
	public function testChangedPassword(string $format)
	{
		$backup = $this->newBackup();
		$file = $this->writeBackup($this->backupContent('the old DB password'), $format);

		$this->assertTrue($backup->dbPasswordChanged($file));
		$this->assertTrue($backup->checkDbPassword($file, 'the old DB password'));
		$this->assertFalse($backup->checkDbPassword($file, $this->dbPassword()));
		$this->assertFalse($backup->checkDbPassword($file, 'not the old password'));
	}

	public static function formatProvider() : array
	{
		return ['plain' => ['plain'], 'gzip' => ['gz'], 'bzip2' => ['bz2'], 'zip' => ['zip']];
	}

	/**
	 * Backups created before the secret_check line existed: probe with the first system encrypted mail credentials
	 */
	public function testLegacyBackupProbesMailCredentials()
	{
		$backup = $this->newBackup();
		$file = $this->writeBackup($this->legacyBackup([
			1 => [Credentials::encryptWithKey('user password', 'the old DB password'), Credentials::USER_AES],	// not with the DB password
			2 => [Credentials::encryptWithKey('imap password', 'the old DB password'), Credentials::SYSTEM_AES],
		]));
		$this->assertTrue($backup->dbPasswordChanged($file));
		$this->assertTrue($backup->checkDbPassword($file, 'the old DB password'));
		$this->assertFalse($backup->checkDbPassword($file, 'not the old password'));

		$file = $this->writeBackup($this->legacyBackup([
			1 => [Credentials::encryptWithKey('imap password', $this->dbPassword()), Credentials::SYSTEM_AES],
		]), 'gz');
		$this->assertFalse($backup->dbPasswordChanged($file));
	}

	/**
	 * Binary mail credentials (S/MIME keys) must not make the probe think the password changed
	 */
	public function testLegacyBackupProbeAcceptsBinaryCredentials()
	{
		$backup = $this->newBackup();
		$file = $this->writeBackup($this->legacyBackup([
			1 => [Credentials::encryptWithKey("\x00\x01\xff".random_bytes(50), $this->dbPassword()), Credentials::SYSTEM_AES],
		]));
		$this->assertFalse($backup->dbPasswordChanged($file));
	}

	/**
	 * Nothing to tell: no secret_check and no system encrypted credentials, or no backup at all
	 */
	public function testNothingToTell()
	{
		$backup = $this->newBackup();

		$file = $this->writeBackup($this->legacyBackup([1 => [Credentials::encryptWithKey('user password', 'x'), Credentials::USER_AES]]));
		$this->assertNull($backup->dbPasswordChanged($file));
		$this->assertNull($backup->checkDbPassword($file, 'whatever'));

		$this->assertNull($backup->dbPasswordChanged('does-not-exist.gz'));
		$this->assertNull($backup->dbPasswordChanged('does-not-exist.zip'));
	}

	/**
	 * A backup we create contains the check for the current DB password and nothing else is in the way of reading it
	 */
	public function testBackupWritesSecretCheck()
	{
		$backup = $this->newBackup(BackupWithoutTables::class);
		$f = fopen($this->tmp_dir.'/db_backup-test', 'wb');
		$this->assertTrue($backup->backup($f, false, true));

		$content = file_get_contents($this->tmp_dir.'/db_backup-test');
		$this->assertMatchesRegularExpression('/^secret_check: \S+$/m', $content);
		$this->assertStringNotContainsString($this->dbPassword(), $content, 'DB password must not be in the backup');

		$this->assertTrue($backup->checkDbPassword('db_backup-test', $this->dbPassword()));
		$this->assertFalse($backup->checkDbPassword('db_backup-test', 'not the DB password'));
		$this->assertFalse($backup->dbPasswordChanged('db_backup-test'));
	}

	/**
	 * A wrong old DB password has to be refused BEFORE restore() drops a single table
	 */
	public function testRestoreRefusesWrongOldPasswordBeforeDroppingAnything()
	{
		$backup = $this->newBackup(BackupWithOriginalRestoreSignature::class);
		// should restore() wrongly continue, the first table it tries to drop ends the test, instead of the database
		$schema_proc = new ReflectionProperty(Backup::class, 'schema_proc');
		$schema_proc->setValue($backup, new class {
			public function DropTable($table)
			{
				throw new \LogicException("restore() tried to drop table $table");
			}
		});
		$file = $this->writeBackup($this->backupContent('the old DB password'));
		$f = fopen($this->tmp_dir.'/'.$file, 'rb');

		$backup->old_db_password = 'not the old password';
		$msg = $backup->restore($f, true, $file);

		$this->assertNotSame('', $msg);
		$this->assertStringContainsString('old DB password', $msg);
		$this->assertSame([], $backup->reencrypted);
	}

	/**
	 * Create TEMPORARY copies of the tables the re-encryption touches, so we never touch the real ones
	 */
	private function createTempTables() : array
	{
		if (!in_array(self::$db->Type, ['mysql', 'mysqli']))
		{
			$this->markTestSkipped('TEMPORARY tables shadowing the real ones are only used with MariaDB/MySQL');
		}
		$defs = [];
		foreach(self::TABLES as $table)
		{
			if (!in_array($table, self::$db->table_names(true)))
			{
				$this->markTestSkipped("Table $table not installed");
			}
			// "CREATE TEMPORARY TABLE x LIKE x" fails, so create it under another name and rename the temporary table
			self::$db->query("CREATE TEMPORARY TABLE tmp_$table LIKE $table", __LINE__, __FILE__);
			$this->temp_tables[] = "tmp_$table";
			self::$db->query("ALTER TABLE tmp_$table RENAME TO $table", __LINE__, __FILE__);
			$this->temp_tables = array_diff($this->temp_tables, ["tmp_$table"]);
			$this->temp_tables[] = $table;
			// make sure our temporary table is what we are going to read and write
			$this->assertEquals(0, self::$db->query("SELECT COUNT(*) FROM $table", __LINE__, __FILE__)->fetchColumn(), "temporary $table is empty");
			$defs[$table] = self::$db->get_table_definitions(true, $table);
		}
		return $defs;
	}

	private function insert(array $defs, string $table, array $row)
	{
		self::$db->insert($table, $row, false, __LINE__, __FILE__, false, false, $defs[$table]);
	}

	private function value(string $table, string $column, array $where)
	{
		return self::$db->select($table, $column, $where, __LINE__, __FILE__)->fetchColumn();
	}

	/**
	 * Re-encrypting moves everything encrypted with the old DB password to the new one and leaves all other values alone
	 */
	public function testReencrypt()
	{
		$defs = $this->createTempTables();
		$old = 'the old DB password';
		$new = 'the new DB password';
		$other = 'a key we know nothing about';

		// mail account credentials: 2 encrypted with old DB password (one binary), 1 with other key, 1 user encrypted, 1 cleartext
		$binary = "\x00\x01\xff".random_bytes(50);
		$cred = ['acc_id' => 1, 'cred_type' => 1, 'account_id' => 0];
		$this->insert($defs, 'egw_ea_credentials', ['acc_id' => 1]+$cred+['cred_username' => 'imap', 'cred_pw_enc' => Credentials::SYSTEM_AES,
			'cred_password' => Credentials::encryptWithKey('imap password', $old)]);
		$this->insert($defs, 'egw_ea_credentials', ['acc_id' => 2]+$cred+['cred_username' => 'smime', 'cred_pw_enc' => Credentials::SYSTEM_AES,
			'cred_password' => Credentials::encryptWithKey($binary, $old)]);
		$this->insert($defs, 'egw_ea_credentials', ['acc_id' => 3]+$cred+['cred_username' => 'broken', 'cred_pw_enc' => Credentials::SYSTEM_AES,
			'cred_password' => Credentials::encryptWithKey('other password', $other)]);
		$this->insert($defs, 'egw_ea_credentials', ['acc_id' => 4]+$cred+['cred_username' => 'user', 'cred_pw_enc' => Credentials::USER_AES,
			'cred_password' => Credentials::encryptWithKey('user password', $old)]);
		$this->insert($defs, 'egw_ea_credentials', ['acc_id' => 5]+$cred+['cred_username' => 'clear', 'cred_pw_enc' => Credentials::CLEARTEXT,
			'cred_password' => base64_encode('cleartext')]);

		// password customfields of addressbook and calendar, and a text customfield looking like a password
		$this->insert($defs, 'egw_customfields', ['cf_app' => 'addressbook', 'cf_name' => 'pwtest', 'cf_label' => 'PW', 'cf_type' => 'passwd']);
		$this->insert($defs, 'egw_customfields', ['cf_app' => 'addressbook', 'cf_name' => 'texttest', 'cf_label' => 'Text', 'cf_type' => 'text']);
		$this->insert($defs, 'egw_customfields', ['cf_app' => 'calendar', 'cf_name' => 'calpw', 'cf_label' => 'PW', 'cf_type' => 'passwd']);
		$this->insert($defs, 'egw_addressbook_extra', ['contact_id' => 1, 'contact_name' => 'pwtest', 'contact_value' => Credentials::encryptWithKey('contact secret', $old)]);
		$this->insert($defs, 'egw_addressbook_extra', ['contact_id' => 2, 'contact_name' => 'pwtest', 'contact_value' => 'plaintext password']);
		$this->insert($defs, 'egw_addressbook_extra', ['contact_id' => 3, 'contact_name' => 'pwtest', 'contact_value' => '']);
		$this->insert($defs, 'egw_addressbook_extra', ['contact_id' => 4, 'contact_name' => 'texttest', 'contact_value' => Credentials::encryptWithKey('not a password cf', $old)]);
		$this->insert($defs, 'egw_cal_extra', ['cal_id' => 1, 'cal_extra_name' => 'calpw', 'cal_extra_value' => Credentials::encryptWithKey('event secret', $old)]);

		// filemanager customfields are properties of files, with a "#" prefix: password one and a normal property
		$ns = Api\Vfs::DEFAULT_PROP_NAMESPACE;
		$this->insert($defs, 'egw_customfields', ['cf_app' => 'filemanager', 'cf_name' => 'fmpw', 'cf_label' => 'PW', 'cf_type' => 'passwd']);
		$this->insert($defs, 'egw_sqlfs_props', ['fs_id' => 1, 'prop_namespace' => $ns, 'prop_name' => '#fmpw', 'prop_value' => Credentials::encryptWithKey('file secret', $old)]);
		$this->insert($defs, 'egw_sqlfs_props', ['fs_id' => 2, 'prop_namespace' => $ns, 'prop_name' => '#fmpw', 'prop_value' => Credentials::encryptWithKey('other file secret', $old)]);
		$this->insert($defs, 'egw_sqlfs_props', ['fs_id' => 1, 'prop_namespace' => $ns, 'prop_name' => 'comment', 'prop_value' => Credentials::encryptWithKey('comment', $old)]);
		$this->insert($defs, 'egw_sqlfs_props', ['fs_id' => 1, 'prop_namespace' => 'http://example.org/', 'prop_name' => '#fmpw', 'prop_value' => Credentials::encryptWithKey('other namespace', $old)]);

		// reversible share password
		$share = ['share_token' => 'token', 'share_path' => '/home/x', 'share_owner' => 1, 'share_modified' => '2026-10-23 10:00:00', 'share_created' => '2026-10-23 10:00:00'];
		$this->insert($defs, 'egw_sharing', $share+['share_pw_reversable' => Credentials::encryptWithKey('share secret', $old)]);
		$this->insert($defs, 'egw_sharing', array_merge($share, ['share_token' => 'token2', 'share_pw_reversable' => '']));

		// subscribed calendar and a normal category
		$subscription = ['type' => '*SUBSCRIBED-CALENDAR*', 'url' => 'https://example.org/cal.ics', 'user' => 'me', 'password' => Credentials::encryptWithKey('calendar secret', $old)];
		$cat = ['cat_appname' => 'calendar', 'cat_name' => 'Subscribed', 'cat_description' => '', 'cat_owner' => '1', 'last_mod' => time()];
		$this->insert($defs, 'egw_categories', $cat+['cat_data' => json_encode($subscription)]);
		$this->insert($defs, 'egw_categories', array_merge($cat, ['cat_name' => 'Normal', 'cat_data' => json_encode(['color' => '#ff0000'])]));

		$backup = $this->newBackup();
		(new ReflectionProperty(Backup::class, 'schemas'))->setValue($backup, $defs);
		$stats = (new \ReflectionMethod(Backup::class, 'reencrypt'))->invoke($backup, $old, $new);

		// skipped are the credential encrypted with another key and the plaintext password customfield
		$this->assertSame(['credentials' => 2, 'customfields' => 4, 'subscriptions' => 1, 'shares' => 1, 'skipped' => 2], $stats);

		$prop = fn($fs_id, $name, $ns = Api\Vfs::DEFAULT_PROP_NAMESPACE) => $this->value('egw_sqlfs_props', 'prop_value',
			['fs_id' => $fs_id, 'prop_namespace' => $ns, 'prop_name' => $name]);
		$this->assertSame('file secret', Credentials::decryptWithKey($prop(1, '#fmpw'), $new));
		$this->assertSame('other file secret', Credentials::decryptWithKey($prop(2, '#fmpw'), $new));
		$this->assertSame('comment', Credentials::decryptWithKey($prop(1, 'comment'), $old), 'other properties untouched');
		$this->assertSame('other namespace', Credentials::decryptWithKey($prop(1, '#fmpw', 'http://example.org/'), $old), 'other namespace untouched');

		$cred_where = fn($user) => ['cred_username' => $user];
		$this->assertSame('imap password', Credentials::decryptWithKey($this->value('egw_ea_credentials', 'cred_password', $cred_where('imap')), $new));
		$this->assertSame($binary, Credentials::decryptWithKey($this->value('egw_ea_credentials', 'cred_password', $cred_where('smime')), $new, false));
		$this->assertSame('other password', Credentials::decryptWithKey($this->value('egw_ea_credentials', 'cred_password', $cred_where('broken')), $other),
			'credential of other key untouched');
		$this->assertSame('user password', Credentials::decryptWithKey($this->value('egw_ea_credentials', 'cred_password', $cred_where('user')), $old),
			'user encrypted credential untouched');
		$this->assertSame(base64_encode('cleartext'), $this->value('egw_ea_credentials', 'cred_password', $cred_where('clear')));

		$this->assertSame('contact secret', Credentials::decryptWithKey($this->value('egw_addressbook_extra', 'contact_value', ['contact_id' => 1]), $new));
		$this->assertSame('plaintext password', $this->value('egw_addressbook_extra', 'contact_value', ['contact_id' => 2]));
		$this->assertSame('', $this->value('egw_addressbook_extra', 'contact_value', ['contact_id' => 3]));
		$this->assertSame('not a password cf', Credentials::decryptWithKey($this->value('egw_addressbook_extra', 'contact_value', ['contact_id' => 4]), $old),
			'customfields of other types untouched');
		$this->assertSame('event secret', Credentials::decryptWithKey($this->value('egw_cal_extra', 'cal_extra_value', ['cal_id' => 1]), $new));

		$this->assertSame('share secret', Credentials::decryptWithKey($this->value('egw_sharing', 'share_pw_reversable', ['share_token' => 'token']), $new));
		$this->assertSame('', $this->value('egw_sharing', 'share_pw_reversable', ['share_token' => 'token2']));

		$data = json_decode($this->value('egw_categories', 'cat_data', ['cat_name' => 'Subscribed']), true);
		$this->assertSame('calendar secret', Credentials::decryptWithKey($data['password'], $new));
		$this->assertSame(['type' => '*SUBSCRIBED-CALENDAR*', 'url' => 'https://example.org/cal.ics', 'user' => 'me'], array_diff_key($data, ['password' => 1]));
		$this->assertSame(['color' => '#ff0000'], json_decode($this->value('egw_categories', 'cat_data', ['cat_name' => 'Normal']), true));
	}
}
