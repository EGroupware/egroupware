<?php
/**
 * EGroupware - Filemanager - select file to open or save dialog
 *
 * @link http://www.egroupware.org
 * @package filemanager
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2009-2016 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Link;
use EGroupware\Api\Framework;
use EGroupware\Api\Egw;
use EGroupware\Api\Vfs;
use EGroupware\Api\Etemplate;

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
 *
 * @deprecated This class is deprecated in favor of et2_vfsSelect widget. Please
 * use et2_vfsSelect widget if you are not running old etemplate.
 *
 * @todo this class should be removed once we have all applications ported in et2
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
		if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc() && $_GET)
		{
			$_GET = array_stripslashes($_GET);
		}
	}

	/**
	 * File selector
	 *
	 * @param array $content
	 *
	 * @deprecated Please use et2_vfsSelect widget in client side instead
	 */
	function select(array $content=null)
	{
		if (!is_array($content))
		{
			$content = array();
			$content['mode'] = $_GET['mode'];
			if (!in_array($content['mode'],array('open','open-multiple','saveas','select-dir')))
			{
				throw new Api\Exception\WrongParameter("Wrong or unset required mode parameter!");
			}
			$content['path'] = $_GET['path'];
			if (empty($content['path']))
			{
				$content['path'] = Api\Cache::getSession('filemanger', 'select_path');
			}
			$content['name'] = (string)$_GET['name'];
			$content['method'] = $_GET['method'];
			$content['id']     = $_GET['id'];
			$content['label'] = isset($_GET['label']) ? $_GET['label'] : lang('Open');
			if (($content['options-mime'] = isset($_GET['mime'])))
			{
				$sel_options['mime'] = array();
				foreach((array)$_GET['mime'] as $key => $value)
				{
					if (is_numeric($key))
					{
						$sel_options['mime'][$value] = lang('%1 files',strtoupper(Api\MimeMagic::mime2ext($value))).' ('.$value.')';
					}
					else
					{
						$sel_options['mime'][$key] = lang('%1 files',strtoupper($value)).' ('.$key.')';
					}
				}

				$content['mime'] = key($sel_options['mime'] ?? []);
				error_log(array2string($content['options-mime']));
			}
		}
		elseif(!empty($content['button']))
		{
			$button = key($content['button']);
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
						$content['name'] = Vfs::encodePathComponent($content['file_upload']['name']);
						$to_path = Vfs::concat($content['path'],$content['name']);

						$copy_result = (Vfs::is_writable($content['path']) || Vfs::is_writable($to_path)) &&
							copy($content['file_upload']['tmp_name'],Vfs::PREFIX.$to_path);
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
								$files[] = Vfs::concat($content['path'],$name);
							}
							//Add an uploaded file to the files result array2string
							if ($copy_result === true) $files[] = $to_path;
							break;

						case 'select-dir':
							$files = $content['path'];
							break;

						case 'saveas':
							// Don't trust the name the user gives, encode it
							$content['name'] = Vfs::encodePathComponent($content['name']);
							// Fall through

						default:
							$files = Vfs::concat($content['path'],$content['name']);
							break;
					}

					if ($content['method'] == 'download_url' && !is_array($files))
					{
							$files =  Vfs::download_url($files);
							if ($files[0] == '/') $files = Egw::link($files);
					}
					else
					{
						$js = ExecMethod2($content['method'],$content['id'],$files);
					}

					if(Api\Json\Response::isJSONResponse())
					{
						$response = Api\Json\Response::get();
						if($js)
						{
							$response->script($js);
						}
						// Ahh!
						// The vfs-select widget looks for this
						$response->script('this.selected_files = '.json_encode($files) . ';');
						Framework::window_close();
					}
					else
					{
						header('Content-type: text/html; charset='.Api\Translation::charset());
						echo "<html>\n<head>\n<script type='text/javascript'>\n$js\n</script>\n</head>\n</html>\n";
					}
					exit();
			}

			$sel_options['mime'] = $content['options-mime'];
		}
		elseif(!empty($content['apps']))
		{
			$app = key($content['apps']);
			if ($app == 'home') $content['path'] = filemanager_ui::get_home_dir();
		}

		//Deactivate the opload field if the current directory is not writeable or
		//we're currently not in the single file open mode.
		$content['no_upload'] = !Vfs::is_writable($content['path']) ||
			!in_array($content['mode'],array('open'));

		$content['apps'] = array_keys(self::get_apps());

		if (isset($app))
		{
			$content['path'] = '/apps/'.(isset($content['apps'][$app]) ? $content['apps'][$app] : $app);
		}

		// Set a flag for easy detection as we go
		$favorites_flag = substr($content['path'],0,strlen('/apps/favorites')) == '/apps/favorites';

		if (!$favorites_flag && (!$content['path'] || !Vfs::is_dir($content['path'])))
		{
			$content['path'] = filemanager_ui::get_home_dir();
		}
		$tpl = new Etemplate('filemanager.select');

		if ($favorites_flag)
		{
			// Display favorites as if they were folders
			$files = array();
			$favorites = Framework\Favorites::get_favorites('filemanager');
			$n = 0;
			foreach($favorites as $favorite)
			{
				$path = $favorite['state']['path'];
				// Just directories
				if(!$path) continue;
				if ($path == $content['path']) continue;	// remove directory itself

				$mime = Vfs::mime_content_type($path);
				$content['dir'][$n] = array(
					'name' => $favorite['name'],
					'path' => $path,
					'mime' => $mime,
					'is_dir' => true
				);
				if ($content['mode'] == 'open-multiple')
				{
					$readonlys['selected['.$favorite['name'].']'] = true;
				}
				++$n;
			}
		}
		else if (!($files = Vfs::find($content['path'],array(
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

				$name = Vfs::basename($path);
				$is_dir = Vfs::is_dir($path);
				$mime = Vfs::mime_content_type($path);
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
		$readonlys['button[createdir]'] = !Vfs::is_writable($content['path']);

		//_debug_array($readonlys);
		Api\Cache::setSession('filemanger', 'select_path', $content['path']);
		$preserve = array(
			'mode'   => $content['mode'],
			'method' => $content['method'],
			'id'     => $content['id'],
			'label'  => $content['label'],
			'mime'   => $content['mime'],
			'options-mime' => $sel_options['mime'],
			'old_path' => $content['path'],
		);
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
		$apps += Link::app_list('query');

		unset($apps['mydms']);	// they do NOT support adding files to VFS
		unset($apps['wiki']);
		unset($apps['api-accounts']);
		unset($apps['addressbook-email']);

		return $apps;
	}
}
