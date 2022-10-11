<?php


namespace EGroupware\Filemanager\Sharing;

use EGroupware\Api\Etemplate;
use EGroupware\Api\Json;
use EGroupware\Api\Vfs;
use EGroupware\Api\Translation;
use EGroupware\Api\Vfs\Sharing;
use EGroupware\Api\Vfs\UploadSharingUi;

class HiddenUpload extends AnonymousList
{

	/**
	 * Get active view - override so it points to this class
	 *
	 * @return string
	 */
	public static function get_view()
	{
		return array(new HiddenUpload(), 'listview');
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
		$this->etemplate = $this->etemplate ? $this->etemplate : new Etemplate(static::LIST_TEMPLATE);

		if (isset($GLOBALS['egw']->sharing) && array_key_exists(Vfs\Sharing::get_token(), $GLOBALS['egw']->sharing) &&
				$GLOBALS['egw']->sharing[Vfs\Sharing::get_token()]->has_hidden_upload())
		{
			// Tell client side that the path is actually writable
			$content['initial_path_readonly'] = false;

			// No new anything
			$this->etemplate->disableElement('nm[new]');
			$this->etemplate->setElementAttribute('nm[button][createdir]', 'readonly', true);

			// Take over upload, change target and conflict strategy
			$path = Vfs::concat(self::get_home_dir(), Sharing::HIDDEN_UPLOAD_DIR);
			$this->etemplate->setElementAttribute('nm[upload]', 'onFinishOne', "app.filemanager.hiddenUploadOnOne");
			// Not a real attribute, but we need to make sure we always upload to the correct place
			$this->etemplate->setElementAttribute('nm[upload]', 'uploadPath', $path);
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
	 * @param string[] $props ui_path=>path the sharing UI is running eg. "/egroupware/share.php/<token>"
	 * @param string[] $arr Result
	 *
	 * @throws Api\Exception\AssertionFailed
	 */
	protected static function handle_upload_action(string $action, $selected, $dir, $props, &$arr)
	{
		Translation::add_app('filemanager');
		$vfs = Vfs::mount();
		$GLOBALS['egw']->sharing[Sharing::get_token($props['ui_path'])]->redo();
		parent::handle_upload_action($action, $selected, $dir, null, $arr);
		if ($arr['files'])
		{
			$arr['msg'] .= "\n" . lang("The uploaded file is only visible to the person sharing these files with you, not to yourself or other people knowing this sharing link.");
			$arr['type'] = 'notice';
		}
		else
		{
			$arr['type'] = 'error';
		}
	}

	protected function is_hidden_upload_dir($directory)
	{
		if (!isset($GLOBALS['egw']->sharing)) return false;
		// Just hide anything that is 'Upload' mounted where we expect, not just this share, to avoid exposing when
		// more than one share is used
		$mounts = Vfs::mount();
		return Vfs::is_dir($directory) && '/'.Vfs::basename($directory) == Sharing::HIDDEN_UPLOAD_DIR && $mounts[$directory];
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
		$hidden_upload = (isset($GLOBALS['egw']->sharing) && array_key_exists(Vfs\Sharing::get_token($_SERVER['HTTP_REFERER']), $GLOBALS['egw']->sharing) &&
				$GLOBALS['egw']->sharing[Sharing::get_token($_SERVER['HTTP_REFERER'])]->has_hidden_upload());

		// Not allowed in hidden upload dir
		$check_path = Sharing::HIDDEN_UPLOAD_DIR . (substr($query['path'], -1) == '/' ? '/' : '');
		if(($length = strlen($check_path)) && (substr($query['path'], -$length) === $check_path))
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
		$response = Json\Response::get();
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