<?php
/**
 * EGroupware API: sqlfs stream wrapper utilities: migration db-fs, fsck
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage vfs
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2008-14 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

require_once 'class.iface_stream_wrapper.inc.php';
require_once 'class.sqlfs_stream_wrapper.inc.php';

/**
 * sqlfs stream wrapper utilities: migration db-fs, fsck
 */
class sqlfs_utils extends sqlfs_stream_wrapper
{
	/**
	 * Migrate SQLFS content from DB to filesystem
	 *
	 * @param boolean $debug true to echo a message for each copied file
	 */
	static function migrate_db2fs($debug=false)
	{
		if (!is_object(self::$pdo))
		{
			self::_pdo();
		}
		$query = 'SELECT fs_id,fs_name,fs_size,fs_content'.
			' FROM '.self::TABLE.' WHERE fs_content IS NOT NULL';

		$fs_id = $fs_name = $fs_size = $fs_content = null;
		$stmt = self::$pdo->prepare($query);
		$stmt->bindColumn(1,$fs_id);
		$stmt->bindColumn(2,$fs_name);
		$stmt->bindColumn(3,$fs_size);
		$stmt->bindColumn(4,$fs_content,PDO::PARAM_LOB);

		if ($stmt->execute())
		{
			$n = 0;
			foreach($stmt as $row)
			{
				// hack to work around a current php bug (http://bugs.php.net/bug.php?id=40913)
				// PDOStatement::bindColumn(,,PDO::PARAM_LOB) is not working for MySQL, content is returned as string :-(
				if (is_string($fs_content))
				{
					$name = md5($fs_name.$fs_id);
					$GLOBALS[$name] =& $fs_content;
					require_once(EGW_API_INC.'/class.global_stream_wrapper.inc.php');
					$content = fopen('global://'.$name,'r');
					if (!$content) echo "fopen('global://$name','w' failed, strlen(\$GLOBALS['$name'])=".strlen($GLOBALS[$name]).", \$GLOBALS['$name']=".substr($GLOBALS['$name'],0,100)."...\n";
					unset($GLOBALS[$name]);	// unset it, so it does not use up memory, once the stream is closed
				}
				else
				{
					$content = $fs_content;
				}
				if (!is_resource($content))
				{
					throw new egw_exception_assertion_failed(__METHOD__."(): fs_id=$fs_id ($fs_name, $fs_size bytes) content is NO resource! ".array2string($content));
				}
				$filename = self::_fs_path($fs_id);
				if (!file_exists($fs_dir=egw_vfs::dirname($filename)))
				{
					self::mkdir_recursive($fs_dir,0700,true);
				}
				if (!($dest = fopen($filename,'w')))
				{
					throw new egw_exception_assertion_failed(__METHOD__."(): fopen($filename,'w') failed!");
				}
				if (($bytes = stream_copy_to_stream($content,$dest)) != $fs_size)
				{
					throw new egw_exception_assertion_failed(__METHOD__."(): fs_id=$fs_id ($fs_name) $bytes bytes copied != size of $fs_size bytes!");
				}
				if ($debug) echo "$fs_id: $fs_name: $bytes bytes copied to fs\n";
				fclose($dest);
				fclose($content); unset($content);

				++$n;
				unset($row);	// not used, as we access bound variables
			}
			unset($stmt);

			if ($n)	// delete all content in DB, if there was some AND no error (exception thrown!)
			{
				$query = 'UPDATE '.self::TABLE.' SET fs_content=NULL WHERE fs_content IS NOT NULL';
				$stmt = self::$pdo->prepare($query);
				$stmt->execute();
			}
		}
		return $n;
	}

	/**
	 * Check and optionaly fix corruption in sqlfs
	 *
	 * @param boolean $check_only=true
	 * @return array with messages / found problems
	 */
	public static function fsck($check_only=true)
	{
		if (!is_object(self::$pdo))
		{
			self::_pdo();
		}
		$msgs = array();
		foreach(array(
			self::fsck_fix_required_nodes($check_only),
			self::fsck_fix_multiple_active($check_only),
			self::fsck_fix_unconnected($check_only),
			self::fsck_fix_no_content($check_only),
		) as $check_msgs)
		{
			if ($check_msgs) $msgs = array_merge($msgs, $check_msgs);
		}

		foreach ($GLOBALS['egw']->hooks->process(array(
			'location' => 'fsck',
			'check_only' => $check_only)
		) as $app_msgs)
		{
			if ($app_msgs) $msgs = array_merge($msgs, $app_msgs);
		}
		return $msgs;
	}

	/**
	 * Check and optionally create required nodes: /, /home, /apps
	 *
	 * @param boolean $check_only=true
	 * @return array with messages / found problems
	 */
	private static function fsck_fix_required_nodes($check_only=true)
	{
		static $dirs = array(
			'/' => 1,
			'/home' => 2,
			'/apps' => 3,
		);
		$stmt = $delete_stmt = null;
		$msgs = array();
		foreach($dirs as $path => $id)
		{
			if (!($stat = self::url_stat($path, STREAM_URL_STAT_LINK)))
			{
				if ($check_only)
				{
					$msgs[] = lang('Required directory "%1" not found!', $path);
				}
				else
				{
					if (!isset($stmt))
					{
						$stmt = self::$pdo->prepare('INSERT INTO '.self::TABLE.' (fs_id,fs_name,fs_dir,fs_mode,fs_uid,fs_gid,fs_size,fs_mime,fs_created,fs_modified,fs_creator'.
							') VALUES (:fs_id,:fs_name,:fs_dir,:fs_mode,:fs_uid,:fs_gid,:fs_size,:fs_mime,:fs_created,:fs_modified,:fs_creator)');
					}
					try {
						$ok = $stmt->execute($data = array(
							'fs_id' => $id,
							'fs_name' => substr($path,1),
							'fs_dir'  => $path == '/' ? 0 : $dirs['/'],
							'fs_mode' => 05,
							'fs_uid' => 0,
							'fs_gid' => 0,
							'fs_size' => 0,
							'fs_mime' => 'httpd/unix-directory',
							'fs_created' => self::_pdo_timestamp(time()),
							'fs_modified' => self::_pdo_timestamp(time()),
							'fs_creator' => 0,
						));
					}
					catch (PDOException $e)
					{
						$ok = false;
						unset($e);	// ignore exception
					}
					if (!$ok)	// can not insert it, try deleting it first
					{
						if (!isset($delete_stmt))
						{
							$delete_stmt = self::$pdo->prepare('DELETE FROM '.self::TABLE.' WHERE fs_id=:fs_id');
						}
						try {
							$ok = $delete_stmt->execute(array('fs_id' => $id)) && $stmt->execute($data);
						}
						catch (PDOException $e)
						{
							unset($e);	// ignore exception
						}
					}
					$msgs[] = $ok ? lang('Required directory "%1" created.', $path) :
						lang('Failed to create required directory "%1"!', $path);
				}
			}
			// check if directory is at least world readable and executable (r-x), we allow more but not less
			elseif (($stat['mode'] & 05) != 05)
			{
				if ($check_only)
				{
					$msgs[] = lang('Required directory "%1" has wrong mode %2 instead of %3!',
						$path, egw_vfs::int2mode($stat['mode']), egw_vfs::int2mode(05|0x4000));
				}
				else
				{
					$stmt = self::$pdo->prepare('UPDATE '.self::TABLE.' SET fs_mode=:fs_mode WHERE fs_id=:fs_id');
					if (($ok = $stmt->execute(array(
						'fs_id' => $id,
						'fs_mode' => 05,
					))))
					{
						$msgs[] = lang('Mode of required directory "%1" changed to %2.', $path, egw_vfs::int2mode(05|0x4000));
					}
					else
					{
						$msgs[] = lang('Failed to change mode of required directory "%1" to %2!', $path, egw_vfs::int2mode(05|0x4000));
					}
				}
			}
		}
		if (!$check_only && $msgs)
		{
			global $oProc;
			if (!isset($oProc)) $oProc = new schema_proc();
			// PostgreSQL seems to require to update the sequenz, after manually inserting id's
			$oProc->UpdateSequence('egw_sqlfs', 'fs_id');
		}
		return $msgs;
	}

	/**
	 * Check and optionally remove files without content part in physical filesystem
	 *
	 * @param boolean $check_only=true
	 * @return array with messages / found problems
	 */
	private static function fsck_fix_no_content($check_only=true)
	{
		$stmt = null;
		$msgs = array();
		foreach(self::$pdo->query('SELECT fs_id FROM '.self::TABLE.
			" WHERE fs_mime!='httpd/unix-directory' AND fs_content IS NULL AND fs_link IS NULL") as $row)
		{
			if (!file_exists($phy_path=self::_fs_path($row['fs_id'])))
			{
				$path = self::id2path($row['fs_id']);
				if ($check_only)
				{
					$msgs[] = lang('File %1 has no content in physical filesystem %2!',
						$path.' (#'.$row['fs_id'].')',$phy_path);
				}
				else
				{
					if (!isset($stmt))
					{
						$stmt = self::$pdo->prepare('DELETE FROM '.self::TABLE.' WHERE fs_id=:fs_id');
						$stmt_props = self::$pdo->prepare('DELETE FROM '.self::PROPS_TABLE.' WHERE fs_id=:fs_id');
					}
					if ($stmt->execute(array('fs_id' => $row['fs_id'])) &&
						$stmt_props->execute(array('fs_id' => $row['fs_id'])))
					{
						$msgs[] = lang('File %1 has no content in physical filesystem %2 --> file removed!',$path,$phy_path);
					}
					else
					{
						$msgs[] = lang('File %1 has no content in physical filesystem %2 --> failed to remove file!',
							$path.' (#'.$row['fs_id'].')',$phy_path);
					}
				}
			}
		}
		if ($check_only && $msgs)
		{
			$msgs[] = lang('Files without content in physical filesystem will be removed.');
		}
		return $msgs;
	}

	/**
	 * Name of lost+found directory for unconnected nodes
	 */
	const LOST_N_FOUND = '/lost+found';
	const LOST_N_FOUND_MOD = 070;
	const LOST_N_FOUND_GRP = 'Admins';

	/**
	 * Check and optionally fix unconnected nodes - parent directory does not (longer) exists:
	 *
	 * SELECT fs.*
	 * FROM egw_sqlfs fs
	 * LEFT JOIN egw_sqlfs dir ON dir.fs_id=fs.fs_dir
	 * WHERE fs.fs_id > 1 && dir.fs_id IS NULL
	 *
	 * @param boolean $check_only=true
	 * @return array with messages / found problems
	 */
	private static function fsck_fix_unconnected($check_only=true)
	{
		$lostnfound = null;
		$msgs = array();
		foreach(self::$pdo->query('SELECT fs.* FROM '.self::TABLE.' fs'.
			' LEFT JOIN '.self::TABLE.' dir ON dir.fs_id=fs.fs_dir'.
			' WHERE fs.fs_id > 1 AND dir.fs_id IS NULL') as $row)
		{
			if ($check_only)
			{
				$msgs[] = lang('Found unconnected %1 %2!',
					mime_magic::mime2label($row['fs_mime']),
					egw_vfs::decodePath($row['fs_name']).' (#'.$row['fs_id'].')');
				continue;
			}
			if (!isset($lostnfound))
			{
				// check if we already have /lost+found, create it if not
				if (!($lostnfound = self::url_stat(self::LOST_N_FOUND, STREAM_URL_STAT_QUIET)))
				{
					egw_vfs::$is_root = true;
					if (!self::mkdir(self::LOST_N_FOUND, self::LOST_N_FOUND_MOD, 0) ||
						!(!($admins = $GLOBALS['egw']->accounts->name2id(self::LOST_N_FOUND_GRP)) ||
						   self::chgrp(self::LOST_N_FOUND, $admins) && self::chmod(self::LOST_N_FOUND,self::LOST_N_FOUND_MOD)) ||
						!($lostnfound = self::url_stat(self::LOST_N_FOUND, STREAM_URL_STAT_QUIET)))
					{
						$msgs[] = lang("Can't create directory %1 to connect found unconnected nodes to it!",self::LOST_N_FOUND);
					}
					else
					{
						$msgs[] = lang('Successful created new directory %1 for unconnected nods.',self::LOST_N_FOUND);
					}
					egw_vfs::$is_root = false;
					if (!$lostnfound) break;
				}
				$stmt = self::$pdo->prepare('UPDATE '.self::TABLE.' SET fs_dir=:fs_dir WHERE fs_id=:fs_id');
			}
			if ($stmt->execute(array(
				'fs_dir' => $lostnfound['ino'],
				'fs_id'  => $row['fs_id'],
			)))
			{
				$msgs[] = lang('Moved unconnected %1 %2 to %3.',
					mime_magic::mime2label($row['fs_mime']),
					egw_vfs::decodePath($row['fs_name']).' (#'.$row['fs_id'].')',
					self::LOST_N_FOUND);
			}
			else
			{
				$msgs[] = lang('Failed to move unconnected %1 %2 to %3!',
					mime_magic::mime2label($row['fs_mime']), egw_vfs::decodePath($row['fs_name']), self::LOST_N_FOUND);
			}
		}
		if ($check_only && $msgs)
		{
			$msgs[] = lang('Unconnected nodes will be moved to %1.',self::LOST_N_FOUND);
		}
		return $msgs;
	}

	/**
	 * Check and optionally fix multiple active files and directories with identical path
	 *
	 * @param boolean $check_only=true
	 * @return array with messages / found problems
	 */
	private static function fsck_fix_multiple_active($check_only=true)
	{
		$stmt = $inactivate_msg_added = null;
		$msgs = array();
		foreach(self::$pdo->query('SELECT fs_dir,fs_name,COUNT(*) FROM '.self::TABLE.
			' WHERE fs_active='.self::_pdo_boolean(true).
			' GROUP BY fs_dir,'.(self::$pdo_type == 'mysql' ? 'BINARY ' : '').'fs_name'.	// fs_name is casesensitive!
			' HAVING COUNT(*) > 1') as $row)
		{
			if (!isset($stmt))
			{
				$stmt = self::$pdo->prepare('SELECT *,(SELECT COUNT(*) FROM '.self::TABLE.' sub WHERE sub.fs_dir=fs.fs_id) AS children'.
					' FROM '.self::TABLE.' fs'.
					' WHERE fs.fs_dir=:fs_dir AND fs.fs_active='.self::_pdo_boolean(true).' AND fs.fs_name'.self::$case_sensitive_equal.':fs_name'.
					" ORDER BY fs.fs_mime='httpd/unix-directory' DESC,children DESC,fs.fs_modified DESC");
				$inactivate_stmt = self::$pdo->prepare('UPDATE '.self::TABLE.
					' SET fs_active='.self::_pdo_boolean(false).
					' WHERE fs_dir=:fs_dir AND fs_active='.self::_pdo_boolean(true).
						' AND fs_name'.self::$case_sensitive_equal.':fs_name AND fs_id!=:fs_id');
			}
			//$msgs[] = array2string($row);
			$cnt = 0;
			$stmt->execute(array(
				'fs_dir'  => $row['fs_dir'],
				'fs_name' => $row['fs_name'],
			));
			foreach($stmt as $n => $entry)
			{
				if ($entry['fs_mime'] == 'httpd/unix-directory')
				{
					if (!$n)
					{
						$dir = $entry;	// directory to keep
						$msgs[] = lang('%1 directories %2 found!', $row[2], self::id2path($entry['fs_id']));
						if ($check_only) break;
					}
					else
					{
						if ($entry['children'])
						{
							$msgs[] = lang('Moved %1 children from directory fs_id=%2 to %3',
								$children = self::$pdo->exec('UPDATE '.self::TABLE.' SET fs_dir='.(int)$dir['fs_id'].
									' WHERE fs_dir='.(int)$entry['fs_id']),
								$entry['fs_id'], $dir['fs_id']);

							$dir['children'] += $children;
						}
						self::$pdo->query('DELETE FROM '.self::TABLE.' WHERE fs_id='.(int)$entry['fs_id']);
						$msgs[] = lang('Removed (now) empty directory fs_id=%1',$entry['fs_id']);
					}
				}
				elseif (isset($dir))	// file and directory with same name exist!
				{
					if (!$check_only)
					{
						$inactivate_stmt->execute(array(
							'fs_dir'  => $row['fs_dir'],
							'fs_name' => $row['fs_name'],
							'fs_id'   => $dir['fs_id'],
						));
						$cnt = $inactivate_stmt->rowCount();
					}
					else
					{
						$cnt = ucfirst(lang('none of %1', $row[2]-1));
					}
					$msgs[] = lang('%1 active file(s) with same name as directory inactivated!',$cnt);
					break;
				}
				else	// newest file --> set for all other fs_active=false
				{
					if (!$check_only)
					{
						$inactivate_stmt->execute(array(
							'fs_dir'  => $row['fs_dir'],
							'fs_name' => $row['fs_name'],
							'fs_id'   => $entry['fs_id'],
						));
						$cnt = $inactivate_stmt->rowCount();
					}
					else
					{
						$cnt = lang('none of %1', $row[2]-1);
					}
					$msgs[] = lang('More then one active file %1 found, inactivating %2 older revisions!',
						self::id2path($entry['fs_id']), $cnt);
					break;
				}
			}
			unset($dir);
			if ($cnt && !isset($inactivate_msg_added))
			{
				$msgs[] = lang('To examine or reinstate inactived files, you might need to turn versioning on.');
				$inactivate_msg_added = true;
			}
		}
		return $msgs;
	}
}

// fsck testcode, if this file is called via it's URL (you need to uncomment it!)
/*if (isset($_SERVER['SCRIPT_FILENAME']) && $_SERVER['SCRIPT_FILENAME'] == __FILE__)
{
	$GLOBALS['egw_info'] = array(
		'flags' => array(
			'currentapp' => 'admin',
			'nonavbar' => true,
		),
	);
	include_once '../../header.inc.php';

	$msgs = sqlfs_utils::fsck(!isset($_GET['check_only']) || $_GET['check_only']);
	echo '<p>'.implode("</p>\n<p>", (array)$msgs)."</p>\n";
}*/