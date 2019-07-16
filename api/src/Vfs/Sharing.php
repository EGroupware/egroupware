<?php
/**
 * EGroupware API: VFS sharing
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage Vfs
 * @author Ralf Becker <rb@stylite.de>
 * @copyright (c) 2014-16 by Ralf Becker <rb@stylite.de>
 * @version $Id$
 */

namespace EGroupware\Api\Vfs;

use EGroupware\Api;
use EGroupware\Api\Vfs;
use EGroupware\Collabora\Wopi;

use filemanager_ui;

/**
 * VFS sharing
 *
 * Token generation uses openssl_random_pseudo_bytes, if available, otherwise
 * mt_rand based Api\Auth::randomstring is used.
 *
 * Existing user sessions are kept whenever possible by an additional mount into regular VFS:
 * - share owner is current user (no problems with rights, they simply match)
 * - share owner has owner-right for share: we create a temp. eACL for current user
 * --> in all other cases session will be replaced with one of the anonymous user,
 *     as we dont support mounting with rights of share owner (VFS uses Vfs::$user!)
 *
 * @todo handle mounts of an entry directory /apps/$app/$id
 * @todo handle mounts inside shared directory (they get currently lost)
 * @todo handle absolute symlinks (wont work as we use share as root)
 */
class Sharing extends \EGroupware\Api\Sharing
{

	/**
	 * Modes ATTACH is NOT a sharing mode, but it is traditional mode in email
	 */
	const ATTACH = 'attach';
	const LINK = 'link';
	const READONLY = 'share_ro';
	const WRITABLE = 'share_rw';

 	/**
	 * Modes for sharing files
	 *
	 * @var array
	 */
	static $modes = array(
		self::ATTACH => array(
			'label' => 'Attachment',
			'title' => 'Works reliable for total size up to 1-2 MB, might work for 5-10 MB, most likely to fail for >10MB',
		),
		self::LINK => array(
			'label' => 'Download link',
			'title' => 'Link is appended to mail allowing recipients to download currently attached version of files',
		),
		self::READONLY => array(
			'label' => 'Readonly share',
			'title' => 'Link is appended to mail allowing recipients to download up to date version of files',
		),
		self::WRITABLE => array(
			'label' => 'Writable share',
			'title' => 'Link is appended to mail allowing recipients to download or modify up to date version of files (EPL only)'
		),
	);

	/**
	 * Create sharing session
	 *
	 * Certain cases:
	 * a) there is not session $keep_session === null
	 *    --> create new anon session with just filemanager rights and share as fstab
	 * b) there is a session $keep_session === true
	 *  b1) current user is share owner (eg. checking the link)
	 *      --> mount share under token additionally
	 *  b2) current user not share owner
	 *  b2a) need/use filemanager UI (eg. directory)
	 *       --> destroy current session and continue with a)
	 *  b2b) single file or WebDAV
	 *       --> modify EGroupware enviroment for that request only, no change in session
	 *
	 * @param boolean $keep_session =null null: create a new session, true: try mounting it into existing (already verified) session
	 * @return string with sessionid, does NOT return if no session created
	 */
	public static function setup_share($keep_session, &$share)
	{

		// need to reset fs_tab, as resolve_url does NOT work with just share mounted
		if (count($GLOBALS['egw_info']['server']['vfs_fstab']) <= 1)
		{
			unset($GLOBALS['egw_info']['server']['vfs_fstab']);	// triggers reset of fstab in mount()
			$GLOBALS['egw_info']['server']['vfs_fstab'] = Vfs::mount();
			Vfs::clearstatcache();
		}
		$share['resolve_url'] = Vfs::resolve_url($share['share_path'], true, true, true, true);	// true = fix evtl. contained url parameter
		// if share not writable append ro=1 to mount url to make it readonly
		if (!($share['share_writable'] & 1))
		{
			$share['resolve_url'] .= (strpos($share['resolve_url'], '?') ? '&' : '?').'ro=1';
		}
		//_debug_array($share);

		if ($keep_session)	// add share to existing session
		{
			$share['share_root'] = '/'.$share['share_token'];

			// if current user is not the share owner, we cant just mount share
			if (Vfs::$user != $share['share_owner'])
			{
				$keep_session = false;
			}
		}
		if (!$keep_session)	// do NOT change to else, as we might have set $keep_session=false!
		{
			// only allow filemanager app
			$GLOBALS['egw_info']['user']['apps'] = array(
				'filemanager' => $GLOBALS['egw_info']['apps']['filemanager']
			);

			$share['share_root'] = '/';
			Vfs::$user = $share['share_owner'];

			// Need to re-init stream wrapper, as some of them look at
			// preferences or permissions
			$scheme = Vfs\StreamWrapper::scheme2class(Vfs::parse_url($share['resolve_url'],PHP_URL_SCHEME));
			if($scheme && method_exists($scheme, 'init_static'))
			{
				$scheme::init_static();
			}
		}

		// mounting share
		Vfs::$is_root = true;
		if (!Vfs::mount($share['resolve_url'], $share['share_root'], false, false, !$keep_session))
		{
			sleep(1);
			return static::share_fail(
				'404 Not Found',
				"Requested resource '/".htmlspecialchars($share['share_token'])."' does NOT exist!\n"
			);
		}
		Vfs::$is_root = false;
		Vfs::clearstatcache();
		// clear link-cache and load link registry without permission check to access /apps
		Api\Link::init_static(true);
	}

	protected function after_login()
	{
		// only allow filemanager app (gets overwritten by session::create)
		$GLOBALS['egw_info']['user']['apps'] = array(
			'filemanager' => $GLOBALS['egw_info']['apps']['filemanager']
		);
		// check if sharee has Collabora run rights --> give is to share too
		$apps = $GLOBALS['egw']->acl->get_user_applications($this->share['share_owner']);
		if (!empty($apps['collabora']))
		{
			$GLOBALS['egw_info']['user']['apps']['collabora'] = $GLOBALS['egw_info']['apps']['collabora'];
		}
	}

	/**
	 * Server a request on a share specified in REQUEST_URI
	 */
	public function get_ui()
	{
		// run full eTemplate2 UI for directories
		$_GET['path'] = $this->share['share_root'];
		$GLOBALS['egw_info']['user']['preferences']['filemanager']['nm_view'] = 'tile';
		$_GET['cd'] = 'no';
		$GLOBALS['egw_info']['flags']['js_link_registry'] = true;
		$GLOBALS['egw_info']['flags']['currentapp'] = 'filemanager';
		Api\Framework::includeCSS('filemanager', 'sharing');
		$ui = new SharingUi();
		$ui->index();
	}

	/**
	 * Create a new share
	 *
	 * @param string $path either path in temp_dir or vfs with optional vfs scheme
	 * @param string $mode self::LINK: copy file in users tmp-dir or self::READABLE share given vfs file,
	 *	if no vfs behave as self::LINK
	 * @param string $name filename to use for $mode==self::LINK, default basename of $path
	 * @param string|array $recipients one or more recipient email addresses
	 * @param array $extra =array() extra data to store
	 * @throw Api\Exception\NotFound if $path not found
	 * @throw Api\Exception\AssertionFailed if user temp. directory does not exist and can not be created
	 * @return array with share data, eg. value for key 'share_token'
	 */
	public static function create($path, $mode, $name, $recipients, $extra=array())
	{
		if (!isset(self::$db)) self::$db = $GLOBALS['egw']->db;

		// Parent puts the application as a prefix.  If we're coming from there, pull it off
		if(strpos($path, 'filemanager::') === 0)
		{
			list(,$path) = explode('::', $path);
		}
		if (empty($name)) $name = $path;

		$path2tmp =& Api\Cache::getSession(__CLASS__, 'path2tmp');

		// allow filesystem path only for temp_dir
		$temp_dir = $GLOBALS['egw_info']['server']['temp_dir'].'/';
		if (substr($path, 0, strlen($temp_dir)) == $temp_dir)
		{
			$mode = self::LINK;
			$exists = file_exists($path) && is_readable($path);
		}
		else
		{
			if(parse_url($path, PHP_URL_SCHEME) !== 'vfs')
			{
				$path = 'vfs://default'.($path[0] == '/' ? '' : '/').$path;
			}

			// We don't allow sharing links, share target instead
			if(($target = Vfs::readlink($path)))
			{
				$path = $target;
			}

			if (($exists = ($stat = Vfs::stat($path)) && Vfs::check_access($path, Vfs::READABLE, $stat)))
			{
				// Make sure we get the correct path if sharing from a share
				if(isset($GLOBALS['egw']->sharing) && $exists)
				{
					$resolved_stat = Vfs::parse_url($stat['url']);
					$path = 'vfs://default'. $resolved_stat['path'];
				}

				$vfs_path = $path;
			}
		}
		// check if file exists and is readable
		if (!$exists)
		{
			throw new Api\Exception\NotFound("'$path' NOT found!");
		}
		// check if file has been shared before, with identical attributes
		if (($mode != self::LINK ))
		{
			return parent::create($vfs_path ? $vfs_path : $path, $mode, $name, $recipients, $extra);
		}
		else
		{
			// if not create new share
			if ($mode == 'link')
			{
				$user_tmp = '/home/'.$GLOBALS['egw_info']['user']['account_lid'].'/.tmp';
				if (!Vfs::file_exists($user_tmp) && !Vfs::mkdir($user_tmp, null, STREAM_MKDIR_RECURSIVE))
				{
					throw new Api\Exception\AssertionFailed("Could NOT create temp. directory '$user_tmp'!");
				}
				$n = 0;
				do {
					$tmp_file = Vfs::concat($user_tmp, ($n?$n.'.':'').Vfs::basename($name));
				}
				while(!(is_dir($path) && Vfs::mkdir($tmp_file, null, STREAM_MKDIR_RECURSIVE) ||
					!is_dir($path) && (!Vfs::file_exists($tmp_file) && ($fp = Vfs::fopen($tmp_file, 'x')) ||
						// do not copy identical files again to users tmp dir, just re-use them
						Vfs::file_exists($tmp_file) && Vfs::compare(Vfs::PREFIX.$tmp_file, $path))) && $n++ < 100);

				if ($n >= 100)
				{
					throw new Api\Exception\AssertionFailed("Could NOT create temp. file '$tmp_file'!");
				}
				if ($fp) fclose($fp);

				if (is_dir($path) && !Vfs::copy_files(array($path), $tmp_file) ||
					!is_dir($path) && !copy($path, Vfs::PREFIX.$tmp_file))
				{
					throw new Api\Exception\AssertionFailed("Could NOT create temp. file '$tmp_file'!");
				}
				// store temp. path in session, to be able to add more recipients
				$path2tmp[$path] = $tmp_file;

				$vfs_path = $tmp_file;
			}

			return parent::create($vfs_path, $mode, $name, $recipients, $extra);
		}
	}

	/**
	 * Api\Storage\Base instance for egw_sharing table
	 *
	 * @var Api\Storage\Base
	 */
	protected static $so;

	/**
	 * Get a so_sql instance initialised for shares
	 */
	public static function so()
	{
		if (!isset(self::$so))
		{
			self::$so = new Api\Storage\Base('phpgwapi', self::TABLE, null, '', true);
			self::$so->set_times('string');
		}
		return self::$so;
	}

	/**
	 * Delete specified shares and unlink temp. files
	 *
	 * @param int|array $keys
	 * @return int number of deleted shares
	 */
	public static function delete($keys)
	{
		self::$db = $GLOBALS['egw']->db;

		if (is_scalar($keys)) $keys = array('share_id' => $keys);

		// get all temp. files, to be able to delete them
		$tmp_paths = array();
		foreach(self::$db->select(self::TABLE, 'share_path', array(
			"share_path LIKE '/home/%/.tmp/%'")+$keys, __LINE__, __FILE__, false) as $row)
		{
			$tmp_paths[] = $row['share_path'];
		}

		// delete specified shares
		self::$db->delete(self::TABLE, $keys, __LINE__, __FILE__);
		$deleted = self::$db->affected_rows();

		// check if temp. files are used elsewhere
		if ($tmp_paths)
		{
			foreach(self::$db->select(self::TABLE, 'share_path,COUNT(*) AS cnt', array(
				'share_path' => $tmp_paths,
			), __LINE__, __FILE__, false, 'GROUP BY share_path') as $row)
			{
				if (($key = array_search($row['share_path'], $tmp_paths)))
				{
					unset($tmp_paths[$key]);
				}
			}
			// if not delete them
			foreach($tmp_paths as $path)
			{
				Vfs::remove($path);
			}
		}
		return $deleted;
	}

	/**
	 * Home long to keep temp. files: 100 day
	 */
	const TMP_KEEP = 8640000;
	/**
	 * How long to keep automatic created Wopi shares
	 */
	const WOPI_KEEP = '-3month';

	/**.
	 * Periodic (monthly) cleanup of temporary sharing files (download link)
	 *
	 * Exlicit expireds shares are delete, as ones created over 100 days ago and last accessed over 100 days ago.
	 */
	public static function tmp_cleanup()
	{
		if (!isset(self::$db)) self::$db = $GLOBALS['egw']->db;
		Vfs::$is_root = true;

		try {
			$cols = array(
				'share_path',
				'MAX(share_expires) AS share_expires',
				'MAX(share_created) AS share_created',
				'MAX(share_last_accessed) AS share_last_accessed',
			);
			if (($group_concat = self::$db->group_concat('share_id'))) $cols[] = $group_concat.' AS share_id';
			// remove expired tmp-files unconditionally
			$having = 'HAVING MAX(share_expires) < '.self::$db->quote(self::$db->to_timestamp(time())).' OR '.
				// remove without expiration date, when created over 100 days ago AND
				'MAX(share_expires) IS NULL AND MAX(share_created) < '.self::$db->quote(self::$db->to_timestamp(time()-self::TMP_KEEP)). ' AND '.
					// (last accessed over 100 days ago OR never)
					'(MAX(share_last_accessed) IS NULL OR MAX(share_last_accessed) < '.self::$db->quote(self::$db->to_timestamp(time()-self::TMP_KEEP)).')';

			foreach(self::$db->select(self::TABLE, $cols, array(
				"share_path LIKE '/home/%/.tmp/%'",
			), __LINE__, __FILE__, false, 'GROUP BY share_path '.$having) as $row)
			{
				Vfs::remove($row['share_path']);

				if ($group_concat)
				{
					$share_ids = $row['share_id'] ? explode(',', $row['share_id']) : array();
				}
				else
				{
					$share_ids = array();
					foreach(self::$db->selec(self::TABLE, 'share_id', array(
						'share_path' => $row['share_path'],
					), __LINE__, __FILE__) as $id)
					{
						$share_ids[] = $id['share_id'];
					}
				}
				if ($share_ids)
				{
					self::$db->delete(self::TABLE, array('share_id' => $share_ids), __LINE__, __FILE__);
				}
			}

			// delete automatic created and expired Collabora shares older then 3 month
			if (class_exists('EGroupware\\Collabora\\Wopi'))
			{
				self::$db->delete(self::TABLE, array(
					'share_expires < '.self::$db->quote(Api\DateTime::to(self::WOPI_KEEP, 'Y-m-d')),
					'share_writable IN ('.Wopi::WOPI_WRITABLE.','.Wopi::WOPI_READONLY.')',
				), __LINE__, __FILE__);
			}
		}
		catch (\Exception $e) {
			_egw_log_exception($e);
		}
		Vfs::$is_root = false;
	}

	/**
	 * Get actions for sharing an entry from filemanager
	 *
	 * @param string $appname
	 * @param int $group Current menu group
	 */
	public static function get_actions($appname, $group = 6)
	{
		$actions = parent::get_actions('filemanager', $group);

		// This one makes no sense for filemanager
		unset($actions['share']['children']['shareFiles']);

		// Move these around to mesh nicely if collabora is available
		$actions['share']['children']['shareReadonlyLink']['group'] = 2;
		$actions['share']['children']['shareReadonlyLink']['order'] = 22;
		$actions['share']['children']['shareWritable']['group'] = 3;

		// Add in merge to document
		if (class_exists($appname.'_merge'))
		{
			$documents = call_user_func(array($appname.'_merge', 'document_action'),
				$GLOBALS['egw_info']['user']['preferences'][$appname]['document_dir'],
				2, 'Insert in document', 'shareDocument_'
			);
			$documents['order'] = 25;

			// Mail only
			if ($documents['children']['message/rfc822'])
			{
				// Just email already filtered out
				$documents['children'] = $documents['children']['message/rfc822']['children'];
			}
			foreach($documents['children'] as $key => &$document)
			{
				if(strpos($document['target'],'compose_') === FALSE)
				{
					unset($documents['children'][$key]);
					continue;
				}

				$document['allowOnMultiple'] = true;
				$document['onExecute'] = "javaScript:app.$appname.share_merge";
			}
			$documents['enabled'] = $documents['enabled'] && (boolean)$documents['children'] && !!($GLOBALS['egw_info']['user']['apps']['stylite']);
			$actions['share']['children']['shareDocuments'] = $documents;
		}

		return $actions;
	}

	/**
	 * Generate link from share or share-token
	 *
	 * @param string|array $share share or share-token
	 * @return string full Url incl. schema and host
	 */
	public static function share2link($share)
	{
		if (is_array($share)) $share = $share['share_token'];

		return Api\Framework::getUrl(Api\Framework::link('/share.php')).'/'.$share;
	}
}

if (file_exists(__DIR__.'/../../../filemanager/inc/class.filemanager_ui.inc.php'))
{
	require_once __DIR__.'/../../../filemanager/inc/class.filemanager_ui.inc.php';

	class SharingUi extends filemanager_ui
	{
		/**
		 * Get the configured start directory for the current user
		 *
		 * @return string
		 */
		static function get_home_dir()
		{
			return $GLOBALS['egw']->sharing->get_root();
		}

		/**
		 * Context menu
		 *
		 * @return array
		 */
		public static function get_actions()
		{
			$actions = parent::get_actions();
			$group = 1;
			// do not add edit setting action when we are in sharing
			unset($actions['edit']);
			if(Vfs::is_writable($GLOBALS['egw']->sharing->get_root()))
			{
				return $actions;
			}
			$actions+= array(
				'egw_copy' => array(
					'enabled' => false,
					'group' => $group + 0.5,
					'hideOnDisabled' => true
				),
				'egw_copy_add' => array(
					'enabled' => false,
					'group' => $group + 0.5,
					'hideOnDisabled' => true
				),
				'paste' => array(
					'enabled' => false,
					'group' => $group + 0.5,
					'hideOnDisabled' => true
				),
			);
			return $actions;
		}

		protected function get_vfs_options($query)
		{
			$options = parent::get_vfs_options($query);

			// Hide symlinks
			$options['type'] = '!l';

			return $options;
		}
	}
}