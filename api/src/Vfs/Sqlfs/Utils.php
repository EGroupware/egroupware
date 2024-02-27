<?php
/**
 * EGroupware API: sqlfs stream wrapper utilities: migration db-fs, fsck
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage vfs
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2008-17 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 */

namespace EGroupware\Api\Vfs\Sqlfs;

use EGroupware\Api\Vfs;
use EGroupware\Api;
use PDO, PDOException;

/**
 * sqlfs stream wrapper utilities: migration db-fs, fsck
 */
class Utils extends StreamWrapper
{
	/**
	 * Migrate SQLFS content from DB to filesystem
	 *
	 * @param boolean $debug true to echo a message for each copied file
	 */
	static function migrate_db2fs($debug=true)
	{
		if (!is_object(self::$pdo))
		{
			self::_pdo();
		}
		$query = 'SELECT fs_id,fs_name,fs_size,fs_content'.
			' FROM '.self::TABLE.' WHERE fs_content IS NOT NULL'.
			' ORDER BY fs_id LIMIT 500 OFFSET :offset';

		$fs_id = $fs_name = $fs_size = $fs_content = null;
		$n = 0;
		$stmt = self::$pdo->prepare($query);
		$stmt->bindColumn(1,$fs_id);
		$stmt->bindColumn(2,$fs_name);
		$stmt->bindColumn(3,$fs_size);
		$stmt->bindColumn(4,$fs_content,PDO::PARAM_LOB);
		$stmt->bindValue(':offset', $n, PDO::PARAM_INT);

		while ($stmt->execute())	// && $stmt->rowCount() does not work for all dbs :(
		{
			$start = $n;
			foreach($stmt as $row)
			{
				// hack to work around a current php bug (http://bugs.php.net/bug.php?id=40913)
				// PDOStatement::bindColumn(,,PDO::PARAM_LOB) is not working for MySQL, content is returned as string :-(
				if (is_string($fs_content))
				{
					$content = fopen('php://temp', 'wb');
					fwrite($content, $fs_content);
					fseek($content, 0, SEEK_SET);
					$fs_content = '';	// can NOT unset it, at least in PHP 7 it looses bind to column!
				}
				else
				{
					$content = $fs_content;
				}
				if (!is_resource($content))
				{
					throw new Api\Exception\AssertionFailed(__METHOD__."(): fs_id=$fs_id ($fs_name, $fs_size bytes) content is NO resource! ".array2string($content));
				}
				$filename = self::_fs_path($fs_id);
				if (!file_exists($fs_dir=Vfs::dirname($filename)))
				{
					self::mkdir_recursive($fs_dir,0700,true);
				}
				if (!($dest = fopen($filename,'w')))
				{
					throw new Api\Exception\AssertionFailed(__METHOD__."(): fopen($filename,'w') failed!");
				}
				if (($bytes = stream_copy_to_stream($content,$dest)) != $fs_size)
				{
					throw new Api\Exception\AssertionFailed(__METHOD__."(): fs_id=$fs_id ($fs_name) $bytes bytes copied != size of $fs_size bytes!");
				}
				if ($debug) error_log("$fs_id: $fs_name: $bytes bytes copied to fs");
				fclose($dest);
				fclose($content); unset($content);

				++$n;
			}
			if (!$n || $n == $start) break;	// just in case nothing is found, statement will execute just fine

			$stmt->bindValue(':offset', $n, PDO::PARAM_INT);
		}
		unset($row);	// not used, as we access bound variables
		unset($stmt);

		if ($n)	// delete all content in DB, if there was some AND no error (exception thrown!)
		{
			$query = 'UPDATE '.self::TABLE.' SET fs_content=NULL';
			$stmt = self::$pdo->prepare($query);
			$stmt->execute();
		}
		return $n;
	}

	/**
	 * Check and optionally fix corruption in sqlfs
	 *
	 * @param boolean $check_only =true
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

		foreach (Api\Hooks::process(array(
				'location' => 'fsck',
				'check_only' => $check_only)
		) as $app_msgs)
		{
			if ($app_msgs && is_array($app_msgs[0]))
			{
				$msgs = array_merge($msgs, ...$app_msgs);
			}
			elseif ($app_msgs)
			{
				$msgs = array_merge($msgs, $app_msgs);
			}
		}

		// also run quota recalc as fsck might have (re-)moved files
		if (!$check_only && $msgs)
		{
			list($dirs, $iterations, $time) = Vfs\Sqlfs\Utils::quotaRecalc();
			$msgs[] = lang("Recalculated %1 directories in %2 iterations and %3 seconds", $dirs, $iterations, number_format($time, 1));
		}
		return $msgs;
	}

	/**
	 * Check and optionally create required nodes: /, /home, /apps
	 *
	 * @param boolean $check_only =true
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
		$sqlfs = new StreamWrapper();
		foreach($dirs as $path => $id)
		{
			if (!($stat = $sqlfs->url_stat($path, STREAM_URL_STAT_LINK)) || $stat['ino'] != $id)
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
						$path, Vfs::int2mode($stat['mode']), Vfs::int2mode(05|0x4000));
				}
				else
				{
					$stmt = self::$pdo->prepare('UPDATE '.self::TABLE.' SET fs_mode=:fs_mode WHERE fs_id=:fs_id');
					if (($ok = $stmt->execute(array(
						'fs_id' => $id,
						'fs_mode' => 05,
					))))
					{
						$msgs[] = lang('Mode of required directory "%1" changed to %2.', $path, Vfs::int2mode(05|0x4000));
					}
					else
					{
						$msgs[] = lang('Failed to change mode of required directory "%1" to %2!', $path, Vfs::int2mode(05|0x4000));
					}
				}
			}
		}
		if (!$check_only && $msgs)
		{
			global $oProc;
			if (!isset($oProc)) $oProc = new Api\Db\Schema();
			// PostgreSQL seems to require to update the sequenz, after manually inserting id's
			$oProc->UpdateSequence('egw_sqlfs', 'fs_id');
		}
		return $msgs;
	}

	/**
	 * Check and optionally remove files without content part in physical filesystem
	 *
	 * @param boolean $check_only =true
	 * @return array with messages / found problems
	 */
	private static function fsck_fix_no_content($check_only=true)
	{
		$stmt = null;
		$msgs = array();
		$limit = 500;
		$offset = 0;
		$select_stmt = self::$pdo->prepare('SELECT fs_id,fs_size FROM '.self::TABLE.
			" WHERE fs_mime!='httpd/unix-directory' AND fs_content IS NULL AND fs_link IS NULL AND (fs_s3_flags&7)=0".
			" LIMIT $limit OFFSET :offset");
		$select_stmt->setFetchMode(PDO::FETCH_ASSOC);
		do {
			$num = 0;
			$select_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
			$select_stmt->execute();
			foreach($select_stmt as $row)
			{
				++$num;
				if (!file_exists($phy_path=self::_fs_path($row['fs_id'])))
				{
					$path = self::id2path($row['fs_id']);
					if ($check_only)
					{
						++$offset;
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
							++$offset;
							$msgs[] = lang('File %1 has no content in physical filesystem %2 --> failed to remove file!',
								$path.' (#'.$row['fs_id'].')',$phy_path);
						}
					}
				}
				// file is empty and NOT in /templates/ or /etemplates/ which use 0-byte files as delete marker
				elseif (!$row['fs_size'] && !filesize($phy_path) && !preg_match('#^/e?templates/#', $path))
				{
					if ($check_only)
					{
						++$offset;
						$msgs[] = lang('File %1 is empty %2!',
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
							$msgs[] = lang('File %1 is empty %2 --> file removed!',$path,$phy_path);
							unlink($phy_path);
						}
						else
						{
							++$offset;
							$msgs[] = lang('File %1 is empty %2 --> failed to remove file!',
								$path.' (#'.$row['fs_id'].')',$phy_path);
						}
					}

				}
			}
		}
		while ($num >= $limit);

		if ($check_only && $msgs)
		{
			$msgs[] = lang('Files without content in physical filesystem or empty files will be removed.');
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
	 * Check and optionally fix unconnected nodes - parent directory does no (longer) exists or connected to itself:
	 *
	 * SELECT fs.*
	 * FROM egw_sqlfs fs
	 * LEFT JOIN egw_sqlfs dir ON dir.fs_id=fs.fs_dir
	 * WHERE fs.fs_id > 1 AND (dir.fs_id IS NULL OR fs.fs_id=fs.fs_dir)
	 *
	 * @param boolean $check_only =true
	 * @return array with messages / found problems
	 */
	private static function fsck_fix_unconnected($check_only=true)
	{
		$lostnfound = null;
		$msgs = array();
		$sqlfs = new StreamWrapper();
		$limit = 500;
		$offset = 0;
		$stmt = self::$pdo->prepare('SELECT fs.* FROM ' . self::TABLE . ' fs' .
			' LEFT JOIN ' . self::TABLE . ' dir ON dir.fs_id=fs.fs_dir' .
			" WHERE fs.fs_id > 1 AND (dir.fs_id IS NULL OR fs.fs_id=fs.fs_dir) LIMIT $limit OFFSET :offset");
		$stmt->setFetchMode(PDO::FETCH_ASSOC);
		do
		{
			$num = 0;
			$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
			$stmt->execute();
			foreach ($stmt as $row)
			{
				++$num;
				if ($check_only)
				{
					++$offset;
					$msgs[] = lang('Found unconnected %1 %2!',
						Api\MimeMagic::mime2label($row['fs_mime']),
						Vfs::decodePath($row['fs_name']) . ' (#' . $row['fs_id'] . ')');
					continue;
				}
				if (!isset($lostnfound))
				{
					// check if we already have /lost+found, create it if not
					if (!($lostnfound = $sqlfs->url_stat(self::LOST_N_FOUND, STREAM_URL_STAT_QUIET)))
					{
						Vfs::$is_root = true;
						if (!$sqlfs->mkdir(self::LOST_N_FOUND, self::LOST_N_FOUND_MOD, 0) ||
							!(!($admins = $GLOBALS['egw']->accounts->name2id(self::LOST_N_FOUND_GRP)) ||
								self::chgrp(self::LOST_N_FOUND, $admins) && self::chmod(self::LOST_N_FOUND, self::LOST_N_FOUND_MOD)) ||
							!($lostnfound = $sqlfs->url_stat(self::LOST_N_FOUND, STREAM_URL_STAT_QUIET)))
						{
							$msgs[] = lang("Can't create directory %1 to connect found unconnected nodes to it!", self::LOST_N_FOUND);
						}
						else
						{
							$msgs[] = lang('Successful created new directory %1 for unconnected nods.', self::LOST_N_FOUND);
						}
						Vfs::$is_root = false;
						if (!$lostnfound) break;
					}
					$stmt = self::$pdo->prepare('UPDATE ' . self::TABLE . ' SET fs_dir=:fs_dir WHERE fs_id=:fs_id');
				}
				if ($stmt->execute(array(
					'fs_dir' => $lostnfound['ino'],
					'fs_id' => $row['fs_id'],
				)))
				{
					$msgs[] = lang('Moved unconnected %1 %2 to %3.',
						Api\MimeMagic::mime2label($row['fs_mime']),
						Vfs::decodePath($row['fs_name']) . ' (#' . $row['fs_id'] . ')',
						self::LOST_N_FOUND);
				}
				else
				{
					++$offset;
					$msgs[] = lang('Failed to move unconnected %1 %2 to %3!',
						Api\MimeMagic::mime2label($row['fs_mime']), Vfs::decodePath($row['fs_name']), self::LOST_N_FOUND);
				}
			}
		}
		while ($num >= $limit);

		if ($check_only && $msgs)
		{
			$msgs[] = lang('Unconnected nodes will be moved to %1.',self::LOST_N_FOUND);
		}
		return $msgs;
	}

	/**
	 * Check and optionally fix multiple active files and multiple active or inactive directories with identical path
	 *
	 * There are never multiple directories with same name, unlike versioned files!
	 *
	 * @param boolean $check_only =true
	 * @return array with messages / found problems
	 */
	private static function fsck_fix_multiple_active($check_only=true)
	{
		$stmt = $inactivate_msg_added = null;
		$msgs = array();
		foreach(self::$pdo->query('SELECT fs_dir,fs_name,COUNT(*) FROM '.self::TABLE.
			' WHERE fs_active='.self::_pdo_boolean(true)." OR fs_mime='httpd/unix-directory'".
			' GROUP BY fs_dir,'.(self::$pdo_type == 'mysql' ? 'BINARY ' : '').'fs_name'.	// fs_name is casesensitive!
			' HAVING COUNT(*) > 1') as $row)
		{
			if (!isset($stmt))
			{
				$stmt = self::$pdo->prepare('SELECT *,(SELECT COUNT(*) FROM '.self::TABLE.' sub WHERE sub.fs_dir=fs.fs_id) AS children'.
					' FROM '.self::TABLE.' fs'.
					' WHERE fs.fs_dir=:fs_dir AND (fs.fs_active='.self::_pdo_boolean(true)." OR fs_mime='httpd/unix-directory')" .
					' AND fs.fs_name'.self::$case_sensitive_equal.':fs_name'.
					" ORDER BY fs.fs_mime='httpd/unix-directory' DESC,fs.fs_active DESC,children DESC,fs.fs_modified DESC");
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
					// by sorting active directores first (fs.fs_active DESC), we make sure active one is kept
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

	/**
	 * Recalculate directory sizes
	 *
	 * @return int[] directories recalculated, iterations necessary, time
	 */
	static function quotaRecalc()
	{
		if (!is_object(self::$pdo))
		{
			self::_pdo();
		}
		$start = microtime(true);
		$table = self::TABLE;
		self::$pdo->query("UPDATE $table SET fs_size=NULL WHERE fs_mime='".self::DIR_MIME_TYPE."' AND fs_active");
		self::$pdo->query("UPDATE $table SET fs_size=0 WHERE fs_mime='".self::SYMLINK_MIME_TYPE."'");

		$stmt = self::$pdo->prepare("SELECT $table.fs_id, SUM(child.fs_size) as total
FROM $table
LEFT JOIN $table child ON $table.fs_id=child.fs_dir AND child.fs_active
WHERE $table.fs_active AND $table.fs_mime='httpd/unix-directory' AND $table.fs_size IS NULL
GROUP BY $table.fs_id
HAVING COUNT(child.fs_id)=0 OR COUNT(CASE WHEN child.fs_size IS NULL THEN 1 ELSE NULL END)=0
ORDER BY $table.fs_id DESC
LIMIT 500");
		$stmt->setFetchMode(PDO::FETCH_ASSOC);
		$update = self::$pdo->prepare("UPDATE $table SET fs_size=:fs_size WHERE fs_id=:fs_id");
		$iterations = $dirs = 0;
		do
		{
			$rows = 0;
			$stmt->execute();
			foreach ($stmt as $row)
			{
				$update->execute([
					'fs_size' => $row['total'] ?? 0,
					'fs_id' => $row['fs_id'],
				]);
				++$rows;
			}
			$dirs += $rows;
		}
		while ($rows > 0 && ++$iterations < 100);

		return [$dirs, $iterations, microtime(true)-$start];
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

	$msgs = Utils::fsck(!isset($_GET['check_only']) || $_GET['check_only']);
	echo '<p>'.implode("</p>\n<p>", (array)$msgs)."</p>\n";
}*/