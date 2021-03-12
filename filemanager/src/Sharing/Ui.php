<?php
/**
 * EGroupware - Filemanager - UI for sharing with regular users
 *
 * @link http://www.egroupware.org
 * @package filemanager
 * @author Nathan Gray
 * @copyright (c) 2020 Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Filemanager\Sharing;

use EGroupware\Api;
use EGroupware\Api\Vfs\Sharing;
use EGroupware\Api\Etemplate;
use EGroupware\Api\Framework;
use EGroupware\Api\Link;
use EGroupware\Api\Vfs;

/**
 * User interface for dealing with filemanager shares
 */
class Ui
{

	// These functions are allowed to be called from client
	public $public_functions = Array(
			'share_received' => true
	);

	/**
	 * The user has been given a new share.  Ask what they want to do with it.
	 *
	 * This allows changing the name / mountpoint, and if they want to only
	 * mount it temporarily.
	 *
	 * @param array $content
	 */
	public function share_received($content = array())
	{
		// Deal with response
		$this->handle_share_received($content);

		// Set up for display
		$template = new Api\Etemplate('filemanager.file_share_received');

		$token = $content['token'] ?: $_GET['token'];

		$share = Sharing::so()->read(['share_token' => $token]);

		// This should already have been done, but we want the correct share root
		Sharing::setup_share(true,$share);

		$content = $share;
		$content['share_passwd'] = !!$content['share_passwd'];
		unset($content['share_id']);
		$content['mount_location'] = Vfs::basename($content['share_root']);
		$content['permanent'] = true;

		$sel_options = array();
		$readonlys = array();
		$preserve = $content;
		$preserve['url'] = Vfs\Sharing\StreamWrapper::share2url($share);

		$template->exec('filemanager.'.__CLASS__.'.'.__FUNCTION__, $content, $sel_options, $readonlys, $preserve,2);
	}

	/**
	 * User submitted the share_received dialog, update the share appropriately
	 *
	 * @param array $content
	 */
	protected function handle_share_received($content = array())
	{
		if(!$content) return;

		// Set persistent
		$persistent_mount = $content['permanent'] ? $GLOBALS['egw_info']['user']['account_id'] : false;

		$new_mountpoint = Vfs::dirname($content['share_root']) . '/' . $content['mount_location'];
		Vfs::$is_root = true;
		Vfs::umount($content['share_root']);
		if(Vfs::mount($content['url'], $new_mountpoint, false, $persistent_mount))
		{
			$content['share_root'] = $new_mountpoint;
		}
		Vfs::$is_root = false;

		// also save for current session
		$GLOBALS['egw_info']['user']['preferences']['common']['vfs_fstab'][$new_mountpoint] =
				$_SESSION[Api\Session::EGW_INFO_CACHE]['user']['preferences']['common']['vfs_fstab'][$new_mountpoint] = $content['url'];

		$GLOBALS['egw_info']['server']['vfs_fstab'] = Vfs::mount();

		// Go to new share
		Api\Json\Response::get()->apply('window.opener.egw.open_link',[
				Api\Framework::link('/index.php',[
						'menuaction' => "filemanager.filemanager_ui.index",
						'path' => $content['share_root']
				]),	'filemanager',false,'filemanager'
		]);
		// This should only be seen if they pasted the link into a new tab since we can't close the tab in that case
		Framework::message(lang("Share mounted at %1.<br/>Please close this tab.", $content['share_root']),"info");

		Api\Framework::window_close();
	}
}