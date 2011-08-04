<?php
/**
 * eGroupWare - Filemanager - select file to open or save dialog
 *
 * @link http://www.egroupware.org
 * @package filemanager
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2009 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
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
			$content['mode'] = $_GET['mode'];
			if (!in_array($content['mode'],array('open','open-multiple','saveas','select-dir')))
			{
				throw new egw_exception_wrong_parameter("Wrong or unset required mode parameter!");
			}
			$content['path'] = $_GET['path'];
			if (!isset($content['path']))
			{
				$content['path'] = egw_session::appsession('select_path','filemanger');
			}
			$content['name'] = (string)$_GET['name'];
			$content['method'] = $_GET['method'];
			$content['id']     = $_GET['id'];
			$content['label'] = isset($_GET['label']) ? $_GET['label'] : lang('Open');
			if (($content['options-mime'] = isset($_GET['mime'])))
			{
				$content['options-mime'] = array();
				foreach((array)$_GET['mime'] as $key => $value)
				{
					if (is_numeric($key))
					{
						$content['options-mime'][$value] = lang('%1 files',strtoupper(mime_magic::mime2ext($value))).' ('.$value.')';
					}
					else
					{
						$content['options-mime'][$key] = lang('%1 files',strtoupper($value)).' ('.$key.')';
					}
				}
				list($content['mime']) = each($content['options-mime']);
			}
		}
		elseif(isset($content['button']))
		{
			list($button) = each($content['button']);
			unset($content['button']);
			switch($button)
			{
				case 'up':
					if ($content['path'] != '/') $content['path'] = egw_vfs::dirname($content['path']);
					break;
				case 'home':
					$content['path'] = filemanager_ui::get_home_dir();
					break;
				case 'ok':
					switch($content['mode'])
					{
						case 'open-multiple':
							foreach((array)$content['dir']['selected'] as $name)
							{
								$files[] = egw_vfs::concat($content['path'],$name);
							}
							break;
						case 'select-dir':
							$files = $content['path'];
							break;
						default:
							$files = egw_vfs::concat($content['path'],$content['name']);
							break;
					}
					$js = ExecMethod2($content['method'],$content['id'],$files);
					echo "<html>\n<head>\n<script type='text/javascript'>\n$js\n</script>\n</head>\n</html>\n";
					$GLOBALS['egw']->common->egw_exit();
			}
		}
		elseif(isset($content['apps']))
		{
			list($app) = each($content['apps']);
			if ($app == 'home') $content['path'] = filemanager_ui::get_home_dir();
		}
		$content['apps'] = array_keys(self::get_apps());

		if (isset($app))
		{
			$content['path'] = '/apps/'.(isset($content['apps'][$app]) ? $content['apps'][$app] : $app);
		}
		if ((substr($content['path'],0,strlen('/apps/favorites/')) == '/apps/favorites/' /*||	// favorites the imediatly resolved
			egw_vfs::is_link($content['path'])*/) &&	// we could replace all symlinks with the link, to save space in the URL
			$link = egw_vfs::readlink($content['path']))
		{
			$content['path'] = $link[0] == '/' ? $link : egw_vfs::concat($content['path'],'../'.$link);
		}
		if (!$content['path'] || !egw_vfs::is_dir($content['path']))
		{
			$content['path'] = filemanager_ui::get_home_dir();
		}
		if (!($files = egw_vfs::find($content['path'],array(
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
				if ($content['mime'] && !$is_dir && egw_vfs::mime_content_type($path) != $content['mime'])
				{
					continue;	// does not match mime-filter --> ignore
				}
				$content['dir'][$n] = array(
					'name' => $name,
					'path' => $path,
					'onclick' => $is_dir ? "return select_goto('".addslashes($path)."');" :
						($content['mode'] != 'open-multiple' ? "return select_show('".addslashes($name)."');" :
						"return select_toggle('".addslashes($name)."');"),
				);
				if ($is_dir && $content['mode'] == 'open-multiple')
				{
					$readonlys['selected['.$name.']'] = true;
				}
				++$n;
			}
			if (!$n) $readonlys['selected[]'] = true;	// remove checkbox from empty line
		}
		$content['js'] = '<script type="text/javascript">
function select_goto(to)
{
	path = document.getElementById("exec[path]");
	path.value = to;
	path.form.submit();
	return false;
}
function select_show(file)
{
	name = document.getElementById("exec[name]");
	name.value = file;
	return false;
}
function select_toggle(file)
{
	checkbox = document.getElementById("exec[dir][selected]["+file+"]");
	if (checkbox) checkbox.checked = !checkbox.checked;
	return false;
}
</script>
';
		// scroll to end of path
		$GLOBALS['egw']->js->set_onload("document.getElementById('exec[path][c". (count(explode('/',$content['path']))-1) ."]').scrollIntoView();");

		//_debug_array($readonlys);
		egw_session::appsession('select_path','filemanger',$content['path']);
		$tpl = new etemplate('filemanager.select');
		$tpl->exec('filemanager.filemanager_select.select',$content,$sel_options,$readonlys,array(
			'mode'   => $content['mode'],
			'method' => $content['method'],
			'id'     => $content['id'],
			'label'  => $content['label'],
			'mime'   => $content['mime'],
			'options-mime' => $content['options-mime'],
		),2);
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

		return $apps;
	}
}
