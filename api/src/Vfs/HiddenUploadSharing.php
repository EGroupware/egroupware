<?php
/**
 * EGroupware API: VFS sharing with a hidden upload folder
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage Vfs
 * @author Nathan Gray <ng@egroupware.org
 * @copyright (c) 2020 Nathan Gray
 */

namespace EGroupware\Api\Vfs;

use EGroupware\Api;
use EGroupware\Api\Vfs;

/**
 * VFS sharing for a folder, but always read-only.  A /Upload directory is used to receive uploads without allowing any
 * other changes.  The /Upload directory is not visible to the anonymous users, only those logged in with Egw accounts
 * and appropriate access.
 */
class HiddenUploadSharing extends Sharing
{
	const HIDDEN_UPLOAD = 8; // Just picking these kind of in sequence as we go...
	const HIDDEN_UPLOAD_DIR = '/Upload';

	/**
	 * Modes for sharing files
	 *
	 * @var array
	 */
	static $modes = array(
			self::HIDDEN_UPLOAD => array(
					'label' => 'Hidden upload',
					'title' => 'Share as readonly, but allow uploads.  Uploads are hidden, and only accessable by those with an account',
			)
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
		// Get these before root is mounted readonly
		$resolve_url = Vfs::resolve_url($share['share_path'], true, true, true, true);
		$upload_dir = Vfs::concat($resolve_url, self::HIDDEN_UPLOAD_DIR);

		// Parent mounts the root read-only
		parent::setup_share($keep_session, $share);

		// Mounting upload dir, has original share owner access (write)
		Vfs::$is_root = true;
		if (!Vfs::mount($upload_dir, Vfs::concat($share['share_root'], self::HIDDEN_UPLOAD_DIR), false, false, false))
		{
			sleep(1);
			return static::share_fail(
					'404 Not Found',
					"Requested resource '/" . htmlspecialchars($share['share_token']) . "' does NOT exist!\n"
			);
		}

		Vfs::$is_root = false;
		Vfs::clearstatcache();
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
		if (!isset(self::$db))
		{
			self::$db = $GLOBALS['egw']->db;
		}

		$path = parent::validate_path($path, $mode);

		// Set up anonymous upload directory
		static::create_hidden_upload($path, $extra);

		return parent::create($action_id, $path, $mode, $name, $recipients, $extra);
	}

	/**
	 * Check the given path for an anonymous upload directory, and create it if it does not
	 * exist yet.  Anon upload directory is not visible over the share, and any files uploaded
	 * to the share are placed inside it instead.
	 *
	 * @param string $path Target path in the VFS
	 * @param string[] $extra Extra settings
	 *
	 * @throws Api\Exception\AssertionFailed
	 * @throws Api\Exception\NoPermission
	 * @throws Api\Exception\WrongParameter
	 */
	protected static function create_hidden_upload(string $path, &$extra)
	{
		$upload_dir = Vfs::concat($path, self::HIDDEN_UPLOAD_DIR);

		if (($stat = Vfs::stat($upload_dir)) && !Vfs::check_access($upload_dir, Vfs::WRITABLE, $stat))
		{
			throw new Api\Exception\NoPermission("Upload directory exists, but you have no write permission");
		}
		if (!($stat = Vfs::stat($upload_dir)))
		{
			// Directory is not there, create it
			if (!mkdir($upload_dir))
			{
				throw new Api\Exception\NoPermission("Could not make upload directory");
			}
		}

		// Set flags so things work
		$extra['share_writable'] = self::HIDDEN_UPLOAD;
	}

	/**
	 * Get actions for sharing an entry from filemanager
	 *
	 * @param string $appname
	 * @param int $group Current menu group
	 *
	 * @return array Actions
	 */
	public static function get_actions($appname, $group = 6)
	{
		$actions = parent::get_actions('filemanager', $group);

		// Add in a hidden upload directory
		$actions['share']['children']['shareUploadDir'] = array(
				'caption' => 'Hidden uploads',
				'group' => 2,
				'order' => 30,
				'enabled' => 'javaScript:app.filemanager.hidden_upload_enabled',
				'onExecute' => 'javaScript:app.filemanager.share_link',
				'data' => ['share_writable' => self::HIDDEN_UPLOAD],
				'icon' => 'upload',
				'hideOnDisabled' => true
		);

		return $actions;
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

		$ui = new UploadSharingUi();
		$ui->index();
	}

	/**
	 * Does this share have a hidden upload directory
	 */
	public function has_hidden_upload()
	{
		return (int)$this->share['share_writable'] == self::HIDDEN_UPLOAD;
	}
}

if (file_exists(__DIR__.'/../../../filemanager/inc/class.filemanager_ui.inc.php'))
{
	require_once __DIR__.'/../../../filemanager/inc/class.filemanager_ui.inc.php';

	class UploadSharingUi extends SharingUi
	{

		/**
		 * Get active view - override so it points to this class
		 *
		 * @return string
		 */
		public static function get_view()
		{
			return array(new UploadSharingUi(), 'listview');
		}

		/**
		 * Filemanager listview
		 *
		 * Override to customize for sharing with a hidden upload directory.
		 * Everything not in the upload directory is readonly, but we make it look like you can upload.
		 * The upload directory is not shown.
		 *
		 * @param array $content
		 * @param string $msg
		 */
		function listview(array $content=null,$msg=null)
		{
			$this->etemplate = $this->etemplate ? $this->etemplate : new Api\Etemplate(static::LIST_TEMPLATE);

			if (isset($GLOBALS['egw']->sharing) && $GLOBALS['egw']->sharing->has_hidden_upload())
			{
				// Tell client side that the path is actually writable
				$content['initial_path_readonly'] = false;

				// No new anything
				$this->etemplate->disableElement('nm[new]');
				$this->etemplate->setElementAttribute('nm[button][createdir]', 'readonly', true);

				// Take over upload, change target and conflict strategy
				$path = Vfs::concat(self::get_home_dir(), Sharing::HIDDEN_UPLOAD_DIR);
				$target = str_replace('\\', '\\\\', __CLASS__);
				$this->etemplate->setElementAttribute('nm[upload]', 'onFinishOne', "app.filemanager.upload(ev, 1, '$path', 'rename', '{$target}::ajax_action')");
			}

			return parent::listview($content, $msg);
		}

		/**
		 * Deal with an uploaded file.
		 * Overridden from the parent to change the message and message type
		 *
		 * @param string $action Should be 'upload'
		 * @param $selected Array of file information
		 * @param string $dir Target directory
		 * @param $props
		 * @param string[] $arr Result
		 *
		 * @throws Api\Exception\AssertionFailed
		 */
		protected static function handle_upload_action(string $action, $selected, $dir, $props, &$arr)
		{
			parent::handle_upload_action($action, $selected, $dir, $props, $arr);
			$arr['msg'] .= "\n" . lang("The uploaded file is only visible to the person sharing these files with you, not to yourself or other people knowing this sharing link.");
			$arr['type'] = 'notice';
		}

		protected function is_hidden_upload_dir($directory)
		{
			if (!isset($GLOBALS['egw']->sharing)) return false;
			return Vfs::is_dir($directory) && $directory == Vfs::concat( $GLOBALS['egw']->sharing->get_root(), Sharing::HIDDEN_UPLOAD_DIR );
		}

		/**
		 * Callback to fetch the rows for the nextmatch widget
		 *
		 * @param array $query
		 * @param array &$rows
		 * @return int
		 *
		 * @throws Api\Json\Exception
		 */
		function get_rows(&$query, &$rows)
		{
			$hidden_upload = (isset($GLOBALS['egw']->sharing) && $GLOBALS['egw']->sharing->has_hidden_upload());

			// Not allowed in hidden upload dir
			if($hidden_upload && strpos($query['path'], Sharing::HIDDEN_UPLOAD_DIR) === 0)
			{
				// only redirect, if it would be to some other location, gives redirect-loop otherwise
				if ($query['path'] != ($path = static::get_home_dir()))
				{
					// we will leave here, since we are not allowed, go back to root
					// TODO: Give message about it, redirect to home dir
				}
				$rows = array();
				return 0;
			}

			// Get file list from parent
			$total = parent::get_rows($query, $rows);

			if(! $hidden_upload )
			{
				return $total;
			}

			// tell client-side that this directory is writeable - allows upload + button
			$response = Api\Json\Response::get();
			$response->call('app.filemanager.set_readonly', $query['path'], false);

			// Hide the hidden upload directory, mark everything else as readonly
			foreach($rows as $key => &$row)
			{
				if($this->is_hidden_upload_dir($row['path']))
				{
					unset($rows[$key]);
					$total--;
					continue;
				}
				$row['class'] .= 'noEdit noDelete ';
			}
			return $total;
		}
	}
}