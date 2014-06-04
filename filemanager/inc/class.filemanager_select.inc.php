<?php
/**
 * eGroupWare - Filemanager - select file to open or save dialog
 *
 * @link http://www.egroupware.org
 * @package filemanager
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2009-2012 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Select file to open or save dialog
 *
 * This dialog can be called from applications to open or store files from the VFS.
 *
 * There are the following ($_GET) parameters:
 * - menuaction=filemanager.filemanager_select.select   (required)
 * - mode=(open|open-multiple|saveas|select-dir)        (required)
 * - method=app.class.method                            (required callback, gets called with id and selected file(s))
 * - id=...                                             (optional parameter passed to callback)
 * - path=...                                           (optional start path in VFS)
 * - mime=...                                           (optional mime-type to limit display to given type)
 * - label=...                                          (optional label for submit button, default "Open")
 *
 * The application calls this method in a popup with size: 640x580 px
 * After the user selected one or more files (depending on the mode parameter), the "method" callback gets
 * called on server (!) side. Parameters are the id plus the selected files as 1. and 2. parameter.
 * The callback returns javascript to eg. update it's UI AND (!) to close the current popup ("window.close();").
 */
class filemanager_select
{
	/**
	 * Methods callable via menuaction
	 *
	 * @var array
	 */
	var $public_functions = array(
		'select' => true,
	);

	/**
	 * Constructor
	 *
	 */
	function __construct()
	{
		// strip slashes from _GET parameters, if someone still has magic_quotes_gpc on
		if (get_magic_quotes_gpc() && $_GET)
		{
			$_GET = etemplate::array_stripslashes($_GET);
		}
	}

	/**
	 * File selector
	 *
	 * @param array $content
	 */
	function select(array $content=null)
	{
		if (!is_array($content))
		{
			$content = array();
			// recover from a failed upload in CkEditor, eg. > max_uploadsize
			if ($_GET['failed_upload'] && $_GET['msg'])
			{
				$content['msg'] = $_GET['msg'];
				$_GET['mode'] = 'open';
				$_GET['method'] = 'ckeditor_return';
				$_GET['CKEditorFuncNum'] = egw_cache::getSession('filemanager','ckeditorfuncnum');
			}
			$content['mode'] = $_GET['mode'];
			if (!in_array($content['mode'],array('open','open-multiple','saveas','select-dir')))
			{
				throw new egw_exception_wrong_parameter("Wrong or unset required mode parameter!");
			}
			$content['path'] = $_GET['path'];
			if (empty($content['path']))
			{
				$content['path'] = egw_session::appsession('select_path','filemanger');
			}
			$content['name'] = (string)$_GET['name'];
			$content['method'] = $_GET['method'];
			if ($content['method'] == 'ckeditor_return')
			{
				if (isset($_GET['CKEditorFuncNum']) && is_numeric($_GET['CKEditorFuncNum']))
				{
					egw_cache::setSession('filemanager','ckeditorfuncnum',
						$content['ckeditorfuncnum'] = $_GET['CKEditorFuncNum']);
				}
				else
				{
					throw new egw_exception_wrong_parameter("chkeditor_return has been specified as a method but some parameters are missing or invalid.");
				}
			}
			$content['id']     = $_GET['id'];
			$content['label'] = isset($_GET['label']) ? $_GET['label'] : lang('Open');
			if (($content['options-mime'] = isset($_GET['mime'])))
			{
				$sel_options['mime'] = array();
				foreach((array)$_GET['mime'] as $key => $value)
				{
					if (is_numeric($key))
					{
						$sel_options['mime'][$value] = lang('%1 files',strtoupper(mime_magic::mime2ext($value))).' ('.$value.')';
					}
					else
					{
						$sel_options['mime'][$key] = lang('%1 files',strtoupper($value)).' ('.$key.')';
					}
				}

				list($content['mime']) = each($sel_options['mime']);
				error_log(array2string($content['options-mime']));
			}
		}
		elseif(isset($content['button']))
		{
			list($button) = each($content['button']);
			unset($content['button']);
			switch($button)
			{
				case 'home':
					$content['path'] = filemanager_ui::get_home_dir();
					break;
				case 'ok':
					$copy_result = null;
					if (isset($content['file_upload']['name']) && file_exists($content['file_upload']['tmp_name']))
					{
						//Set the "content" name filed accordingly to the uploaded file
						// encode chars which special meaning in url/vfs (some like / get removed!)
						$content['name'] = egw_vfs::encodePathComponent($content['file_upload']['name']);
						$to_path = egw_vfs::concat($content['path'],$content['name']);

						$copy_result = (egw_vfs::is_writable($content['path']) || egw_vfs::is_writable($to_path)) &&
							copy($content['file_upload']['tmp_name'],egw_vfs::PREFIX.$to_path);
					}

					//Break on an error condition
					if ((($content['mode'] == 'open' || $content['mode'] == 'saveas') && ($content['name'] == '')) || ($copy_result === false))
					{
						if ($copy_result === false)
						{
							$content['msg'] = lang('Error uploading file!');
						}
						else
						{
							$content['msg'] = lang('Filename must not be empty!');
						}
						$content['name'] = '';

						break;
					}

					switch($content['mode'])
					{
						case 'open-multiple':
							foreach((array)$content['dir']['selected'] as $name)
							{
								$files[] = egw_vfs::concat($content['path'],$name);
							}
							//Add an uploaded file to the files result array2string
							if ($copy_result === true) $files[] = $to_path;
							break;

						case 'select-dir':
							$files = $content['path'];
							break;

						default:
							$files = egw_vfs::concat($content['path'],$content['name']);
							break;
					}

					if ($content['method'] && $content['method'] != 'ckeditor_return')
					{
						$js = ExecMethod2($content['method'],$content['id'],$files);
					}
					else if ($content['method'] == 'ckeditor_return')
					{
						$download_url = egw_vfs::download_url(egw_vfs::concat($content['path'],$content['name']));
						if ($download_url[0] == '/') $download_url = egw::link($download_url);
						$js = "window.opener.CKEDITOR.tools.callFunction(".
							$content['ckeditorfuncnum'].",'".
							htmlspecialchars($download_url)."',".
							"'');\nwindow.close();";
					}
					if(egw_json_response::isJSONResponse() && !($content['method'] == 'ckeditor_return'))
					{
						$response = egw_json_response::get();
						if($js)
						{
							$response->script($js);
						}
						// Ahh!
						// The vfs-select widget looks for this
						$response->script('this.selected_files = '.json_encode($files) . '; this.close();');
					}
					else
					{
						header('Content-type: text/html; charset='.translation::charset());
						echo "<html>\n<head>\n<script type='text/javascript'>\n$js\n</script>\n</head>\n</html>\n";
					}
					common::egw_exit();
			}

			$sel_options['mime'] = $content['options-mime'];
		}
		elseif(isset($content['apps']))
		{
			list($app) = each($content['apps']);
			if ($app == 'home') $content['path'] = filemanager_ui::get_home_dir();
		}

		//Deactivate the opload field if the current directory is not writeable or
		//we're currently not in the single file open mode.
		$content['no_upload'] = !egw_vfs::is_writable($content['path']) ||
			!in_array($content['mode'],array('open'));

		$content['apps'] = array_keys(self::get_apps());

		if (isset($app))
		{
			$content['path'] = '/apps/'.(isset($content['apps'][$app]) ? $content['apps'][$app] : $app);
		}

		// Set a flag for easy detection as we go
		$favorites_flag = substr($content['path'],0,strlen('/apps/favorites')) == '/apps/favorites';

		if (!$favorites_flag && (!$content['path'] || !egw_vfs::is_dir($content['path'])))
		{
			$content['path'] = filemanager_ui::get_home_dir();
		}
		$tpl = new etemplate_new('filemanager.select');

		if ($favorites_flag)
		{
			// Display favorites as if they were folders
			$files = array();
			$favorites = egw_favorites::get_favorites('filemanager');
			$n = 0;
			foreach($favorites as $favorite)
			{
				$path = $favorite['state']['path'];
				// Just directories
				if(!$path) continue;
				if ($path == $content['path']) continue;	// remove directory itself

				$mime = egw_vfs::mime_content_type($path);
				$content['dir'][$n] = array(
					'name' => $favorite['name'],
					'path' => $path,
					'mime' => $mime,
				);
				if ($content['mode'] == 'open-multiple')
				{
					$readonlys['selected['.$favorite['name'].']'] = true;
				}
				++$n;
			}
		}
		else if (!($files = egw_vfs::find($content['path'],array(
			'dirsontop' => true,
			'order' => 'name',
			'sort' => 'ASC',
			'maxdepth' => 1,
		))))
		{
			$content['msg'] = lang("Can't open directory %1!",$content['path']);
		}
		else
		{
			$n = 0;
			$content['dir'] = array('mode' => $content['mode']);
			foreach($files as $path)
			{
				if ($path == $content['path']) continue;	// remove directory itself

				$name = egw_vfs::basename($path);
				$is_dir = egw_vfs::is_dir($path);
				$mime = egw_vfs::mime_content_type($path);
				if ($content['mime'] && !$is_dir && $mime != $content['mime'])
				{
					continue;	// does not match mime-filter --> ignore
				}
				$content['dir'][$n] = array(
					'name' => $name,
					'path' => $path,
					'mime' => $mime,
					'is_dir' => $is_dir
				);
				if ($is_dir && $content['mode'] == 'open-multiple')
				{
					$readonlys['selected['.$name.']'] = true;
				}
				++$n;
			}
			if (!$n) $readonlys['selected[]'] = true;	// remove checkbox from empty line
		}
		$readonlys['button[createdir]'] = !egw_vfs::is_writable($content['path']);

		//_debug_array($readonlys);
		egw_session::appsession('select_path','filemanger',$content['path']);
		$preserve = array(
			'mode'   => $content['mode'],
			'method' => $content['method'],
			'id'     => $content['id'],
			'label'  => $content['label'],
			'mime'   => $content['mime'],
			'options-mime' => $sel_options['mime'],
			'old_path' => $content['path'],
		);

		if (isset($content['ckeditorfuncnum']))
		{
			$preserve['ckeditorfuncnum'] = $content['ckeditorfuncnum'];
			$preserve['ckeditor'] = $content['ckeditor'];
		}

		// tell framework we need inline javascript for ckeditor_return
		if ($content['method'] == 'ckeditor_return')
		{
			egw_framework::csp_script_src_attrs('unsafe-inline');
		}
		$tpl->exec('filemanager.filemanager_select.select',$content,$sel_options,$readonlys,$preserve,2);
	}

	/**
	 * Get a list off all apps having an application directory in VFS
	 *
	 * @return array
	 */
	static function get_apps()
	{
		$apps = array(false);	// index starting from 1
		if (isset($GLOBALS['egw_info']['apps']['stylite'])) $apps = array('favorites' => lang('Favorites'));
		$apps += egw_link::app_list('query');

		unset($apps['mydms']);	// they do NOT support adding files to VFS
		unset($apps['wiki']);
		unset($apps['home-accounts']);
		unset($apps['addressbook-email']);

		return $apps;
	}
}
