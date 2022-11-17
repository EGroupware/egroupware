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
use EGroupware\Api\Json\Response;
use EGroupware\Api\Vfs;
use EGroupware\Collabora\Wopi;
use EGroupware\Filemanager\Sharing\AnonymousList;
use EGroupware\Filemanager\Sharing\Ui;
use EGroupware\Filemanager\Sharing\Ui as ShareUi;

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

	const HIDDEN_UPLOAD = 9; // 8 is the next bitwise flag + 1 for writable
	const HIDDEN_UPLOAD_DIR = '/Upload';

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
	 * Subdirectory of user's home directory to mount shares into
	 */
	const SHARES_DIRECTORY = 'shares';

	/**
	 * Create sharing session
	 *
	 * There are two cases:
	 *
	 * 1) there is no session $keep_session === null
	 *    --> create new anon session with just filemanager rights and resolved share incl. sharee as only fstab entry
	 *
	 * 2) there is a (non-anonymous) session $keep_session === true
	 *    --> mount share with sharing stream-wrapper into users "shares" subdirectory of home directory
	 *        and ask user if he wants the share permanently mounted there
	 *
	 * Even with sharing stream-wrapper a) and b) need to be different, as sharing SW needs an intact fstab!
	 *
	 * Not yet sure if this still needs extra handling:
	 *
	 * 2a) single file or WebDAV
	 *       --> modify EGroupware enviroment for that request only, no change in session
	 *
	 * @param boolean $keep_session =null null: create a new session, true: try mounting it into existing (already verified) session
	 * @return string with sessionid, does NOT return if no session created
	 */
	public static function setup_share($keep_session, &$share)
	{
		// need to reset fs_tab, as resolve_url does NOT work with just share mounted
		if(empty($GLOBALS['egw_info']['server']['vfs_fstab']) || count($GLOBALS['egw_info']['server']['vfs_fstab']) <= 1 ||
			// anonymous always needs a reset since we clear normal fstab
			$GLOBALS['egw_info']['user']['account_lid'] == 'anonymous')
		{
			unset($GLOBALS['egw_info']['server']['vfs_fstab']);    // triggers reset of fstab in mount()
			$GLOBALS['egw_info']['server']['vfs_fstab'] = Vfs::mount();
			Vfs::clearstatcache();
		}

		// for a regular user session, mount the share into "shares" subdirectory of his home-directory
		if ($keep_session && $GLOBALS['egw_info']['user']['account_lid'] && $GLOBALS['egw_info']['user']['account_lid'] !== 'anonymous')
		{
			$shares_dir = '/home/'.Vfs::encodePathComponent($GLOBALS['egw_info']['user']['account_lid']).'/'.self::SHARES_DIRECTORY;
			if (!Vfs::file_exists($shares_dir)) Vfs::mkdir($shares_dir, 0750, true);
			$share['share_root'] = Vfs::concat($shares_dir, Vfs::basename($share['share_path']));

			// ToDo: handle there's already something there with that name (incl. maybe the same share!)

			Vfs::$is_root = true;
			if (!Vfs::mount(Vfs\Sharing\StreamWrapper::share2url($share), $share['share_root'], false, false, false))
			{
				sleep(1);
				return static::share_fail(
					'404 Not Found',
					"Requested resource '/".htmlspecialchars($share['share_token'])."' does NOT exist!\n"
				);
			}
			Vfs::$is_root = false;

			// get_ui() will ask user if he wants the share permanently mounted
			return;
		}

		/**
		 * From here on pure sharing url without regular EGroupware user (session)
		 */
		$share['resolve_url'] = Vfs::build_url([
			'user' => Api\Accounts::id2name($share['share_owner']),
		]+Vfs::parse_url(Vfs::resolve_url($share['share_path'], true, true, true, true)));	// true = fix evtl. contained url parameter
		// if share not writable append ro=1 to mount url to make it readonly
		if (!($share['share_writable'] & 1))
		{
			$share['resolve_url'] .= (strpos($share['resolve_url'], '?') ? '&' : '?').'ro=1';
		}
		$share['share_root'] = '/';
		if(!$GLOBALS['egw_info']['user']['account_lid'] || $GLOBALS['egw_info']['user']['account_lid'] == 'anonymous')
		{
			// Do not mount to root or it will be overwritten if they open another share
			$share['share_root'] = '/' . Vfs::basename($share['share_path']);
		}


		// only allow filemanager app & collabora
		// In some cases, $GLOBALS['egw_info']['apps'] is not yet set at all.  Set it to app => true, it will be used
		// in Session->read_repositories() to make sure we get access to these apps when the session loads the apps.
		$apps = $GLOBALS['egw']->acl->get_user_applications($share['share_owner']);
		$GLOBALS['egw_info']['user']['apps'] = array(
			'filemanager' => $GLOBALS['egw_info']['apps']['filemanager'] ?? true,
			'collabora'   => $GLOBALS['egw_info']['apps']['collabora'] ?? $apps['collabora']
		);

		// Need to re-init stream wrapper, as some of them look at preferences or permissions
		$class = Vfs\StreamWrapper::scheme2class(Vfs::parse_url($share['resolve_url'],PHP_URL_SCHEME));
		if($class && method_exists($class, 'init_static'))
		{
			$class::init_static();
		}

		// mounting share
		Vfs::$is_root = true;
		if(!Vfs::mount($share['resolve_url'], $share['share_root'], false, false, true))
		{
			sleep(1);
			return static::share_fail(
				'404 Not Found',
				"Requested resource '/" . htmlspecialchars($share['share_token']) . "' does NOT exist!\n"
			);
		}

		/*
		We want to hide any normal VFS folders from anonymous users, so we emptied the fstab (above)
		and now add any other shares from this session
		*/
		$session_fstab = $_SESSION[Api\Session::EGW_INFO_CACHE]['server']['vfs_fstab'];
		if(!$session_fstab)
		{
			$session_fstab = array();
		}
		foreach($session_fstab as $path => $url)
		{
			Vfs::mount($url, $path, false, false);
		}

		Vfs::$is_root = false;
		Vfs::clearstatcache();
		// clear link-cache and load link registry without permission check to access /apps
		Api\Link::init_static(true);
	}

	/**
	 * Just temporary until we get the share streamwrapper in
	 * @param $target
	 * @param $mount
	 */
	protected static function session_mount($target, $mount)
	{
		$session_fstab =& Api\Cache::getSession('api', 'fstab');
		if(!$session_fstab)
		{
			$session_fstab = array();
		}
		$session_fstab[$target] = array(
			'mount' => $mount,
			'class' => get_called_class()
		);
	}

	protected static function after_login($share)
	{
		// only allow filemanager app (gets overwritten by session::create)
		$GLOBALS['egw_info']['user']['apps'] = array(
			'filemanager' => $GLOBALS['egw_info']['apps']['filemanager']
		);
		// check if sharee has Collabora run rights --> give is to share too
		$apps = $GLOBALS['egw']->acl->get_user_applications($share['share_owner']);
		if (!empty($apps['collabora']))
		{
			$GLOBALS['egw_info']['user']['apps']['collabora'] = $GLOBALS['egw_info']['apps']['collabora'];
		}
		// session::create also overwrites link-registry
		Vfs::clearstatcache();
		// clear link-cache and load link registry without permission check to access /apps
		Api\Link::init_static(true);
	}

	/**
	 * Server a request on a share specified in REQUEST_URI
	 */
	public function get_ui()
	{
		//  * Link has already been opened in a new tab by this point *

		// User testing their own share?
		if($GLOBALS['egw_info']['user']['account_id'] == $this->share['share_owner'])
		{
			Api\Framework::message(lang(
					"This is your own share.  To test it, use an anonymous / incognito window.\n%1",
					'<a href="'.$_SERVER['REQUEST_URI'].'">' . $this->share['share_root'] . '</a>'
			),'info');
		}


		// Opened within EGroupware.  Ask about the share, if the user is not anonymous.
		if($GLOBALS['egw_info']['user']['account_lid'] !== 'anonymous' && $GLOBALS['egw_info']['user']['account_id'] != $this->share['share_owner'])
		{
			// Check to see if this is already permanent mounted share
			$mount_target = Vfs\Sharing\StreamWrapper::share2url($this->share);

			// Logged in user opening someone else's share, but they've opened the URL in its own tab
			if (!Response::isJSONResponse())
			{
				Api\Framework::message(lang(
						"Share mounted.  To test it, use an anonymous / incognito window.\n%1",
						'<a href="'.$_SERVER['REQUEST_URI'].'">' . $this->share['share_root'] . '</a>'
				),'info');
				Api\Framework::redirect(Api\Framework::link('/index.php',[
						'menuaction' => 'filemanager.EGroupware\\Filemanager\\Sharing\\Ui.share_received',
						'token' => $this->share['share_token']
				]));
			}
			if(($mounted = array_search($mount_target,$GLOBALS['egw_info']['user']['preferences']['common']['vfs_fstab'])))
			{
				Response::get()->apply('window.opener.egw.open_link',[
					Api\Framework::link('/index.php',[
						'menuaction' => 'filemanager.filemanager_ui.index',
						'path' => $mounted
						]),'filemanager',false,'filemanager'
				]);
			}
			else
			{
				// New share, ask about it
				Response::get()->apply('window.opener.egw.open_link',[
					Api\Framework::link('/index.php',[
						'menuaction' => 'filemanager.EGroupware\\Filemanager\\Sharing\\Ui.share_received',
						'token' => $this->share['share_token']
						]),'filemanager','600x150','filemanager'
				]);
			}

			Response::get()->apply('window.close');
			// Still need to load the list after though, since loading it processes what we just added
			$ui = new \filemanager_ui();
			return $ui->index();
		}

		// run full eTemplate2 UI for directories
		$_GET['path'] = $this->share['share_root'];
		$GLOBALS['egw_info']['user']['preferences']['filemanager']['nm_view'] = 'tile';
		$_GET['cd'] = 'no';
		$GLOBALS['egw_info']['flags']['js_link_registry'] = true;
		$GLOBALS['egw_info']['flags']['currentapp'] = 'filemanager';
		Api\Framework::includeCSS('filemanager', 'sharing');
		$ui = new AnonymousList();
		$ui->index();
	}

	/**
	 * Create a new share
	 *
	 * @param string $action_id Name of the action used to create the share.  Allows for customization.
	 * @param string $path either path in temp_dir or vfs with optional vfs scheme
	 * @param string $mode self::LINK: copy file in users tmp-dir or self::READABLE share given vfs file,
	 *  if no vfs behave as self::LINK
	 * @param string $name filename to use for $mode==self::LINK, default basename of $path
	 * @param string|array $recipients one or more recipient email addresses
	 * @param array $extra =array() extra data to store
	 * @return array with share data, eg. value for key 'share_token'
	 * @throw Api\Exception\NotFound if $path not found
	 * @throw Api\Exception\AssertionFailed if user temp. directory does not exist and can not be created
	 */
	public static function create(string $action_id, $path, $mode, $name, $recipients, $extra = array())
	{
		if (!isset(self::$db)) self::$db = $GLOBALS['egw']->db;

		$path2tmp =& Api\Cache::getSession(__CLASS__, 'path2tmp');
		$path = static::validate_path($path, $mode);

		if (empty($name)) $name = $path;

		// check if file has been shared before, with identical attributes
		if (($mode != self::LINK ))
		{
			return parent::create($action_id, $path, $mode, $name, $recipients, $extra);
		}
		else
		{
			// if not create new share
			if ($mode == 'link')
			{
				$user_tmp = '/home/'.Api\Accounts::id2name(Api\Vfs::$user).'/.tmp';
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
				if (isset($fp)) fclose($fp);

				if (is_dir($path) && !Vfs::copy_files(array($path), $tmp_file) ||
					!is_dir($path) && !copy($path, Vfs::PREFIX.$tmp_file))
				{
					throw new Api\Exception\AssertionFailed("Could NOT create temp. file '$tmp_file'!");
				}
				// store temp. path in session, to be able to add more recipients
				$path2tmp[$path] = $tmp_file;

				$vfs_path = $tmp_file;
			}

			return parent::create($action_id, $vfs_path, $mode, $name, $recipients, $extra);
		}
	}

	/**
	 * Clean and validate the share path
	 *
	 * @param $path Proposed share path
	 * @param $mode Share mode
	 * @return string
	 *
	 * @throws Api\Exception\AssertionFailed
	 * @throws Api\Exception\NotFound
	 * @throws Api\Exception\WrongParameter
	 */
	protected static function validate_path($path, &$mode)
	{
		// Parent puts the application as a prefix.  If we're coming from there, pull it off
		if(strpos($path, 'filemanager::') === 0)
		{
			list(,$path) = explode('::', $path);
		}

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
				$path = Vfs::PREFIX.Vfs::parse_url($path, PHP_URL_PATH);
			}

			// We don't allow sharing paths that contain links, resolve to target instead
			if(($target = Vfs::readlink($path)))
			{
				$path = $target;
			}
			$check = $path;
			do
			{
				if(($delinked = Vfs::readlink($check)))
				{
					if($delinked[strlen($delinked)-1] == '/') $check .='/';
					$path = str_replace($check, $delinked, $path);
					if(parse_url($path, PHP_URL_SCHEME) !== 'vfs')
					{
						$path = Vfs::PREFIX.Vfs::parse_url($path, PHP_URL_PATH);
					}
					$check = $path;
				}
				else
				{
					$check = Vfs::dirname($check);
				}
			} while ($check && $check != '/');

			if (($exists = ($stat = Vfs::stat($path)) && Vfs::check_access($path, Vfs::READABLE, $stat)))
			{
				// Not sure why stat puts a / on the end of files, but remove it
				if(substr($stat['url'], -1) == '/' && !Vfs::is_dir($stat['url']))
				{
					$stat['url'] = substr($stat['url'], 0, strlen($stat['url']) - 1);
				}
				// Make sure we get the correct path if sharing from a share
				if(isset($GLOBALS['egw']->sharing) && array_key_exists(static::get_token(), $GLOBALS['egw']->sharing) && $exists
					// Not for Collabora, it's working with share fstab and can't access full URL
					&& $mode !== Wopi::WOPI_WRITABLE
				)
				{
					$path = $stat['url'];
				}
			}
		}
		// check if file exists and is readable
		if (!$exists)
		{
			throw new Api\Exception\NotFound("'$path' NOT found!");
		}

		return $path;
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

		$deleted = parent::delete($keys);

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
	 * Check that a share path still exists (and is readable)
	 */
	protected static function check_path($share)
	{
		// remove VFS::PREFIX (vfs://default), as Vfs::file_exists returns false if path does NOT start with a /
		if ($share['share_path'][0] !== '/')
		{
			$share['share_path'] = Api\Vfs::parse_url($share['share_path'], PHP_URL_PATH);
		}
		Vfs::$is_root = true;
		$exists = Vfs::file_exists($share['share_path']);
		Vfs::$is_root = false;
		return $exists;
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
	 * Hook callback to watch VFS and remove any shares for files that get moved or removed
	 */
	public static function vfsUpdate($data)
	{
		$path = $data['location'] == 'vfs_rename' ? $data['from'] : $data['path'];
		if (parse_url($path, PHP_URL_SCHEME) !== 'vfs')
		{
			$path = Api\Vfs::PREFIX . ($path[0] == '/' ? '' : '/') . $path;
		}
		if ($data['location'] == 'vfs_rmdir')
		{
			// Normally removing a directory removes the files first, so any shares inside the directory would
			// be handled already, but just in case, get it all.
			$path .= '%';
		}

		$shares = array();
		foreach ($GLOBALS['egw']->db->select(self::TABLE, array(
				'share_id', 'share_path', 'share_owner'
		),
				array(
						"share_path LIKE " . $GLOBALS['egw']->db->quote($path)
				),
				__LINE__, __FILE__, false) as $share)
		{
			$shares[] = $share;
		}
		foreach ($shares as $share)
		{
			if ($data['location'] == 'vfs_rename')
			{
				if (parse_url($data['to'], PHP_URL_SCHEME) !== 'vfs')
				{
					$data['to'] = $path = Api\Vfs::PREFIX . ($data['to'][0] == '/' ? '' : '/') . $data['to'];
				}
				$GLOBALS['egw']->db->update(self::TABLE, array(
						'share_path' => $data['to']
				), $share, __LINE__, __FILE__);
			}
			else
			{
				static::delete($share['share_id']);
			}
		}
	}
}