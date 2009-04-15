<?php
/**
 * eGroupWare - Filemanager - select file to open / save dialog
 *
 * @link http://www.egroupware.org
 * @package filemanager
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2009 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
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
	 * Callback to fetch the rows for the nextmatch widget
	 *
	 * @param array $query
	 * @param array &$rows
	 * @param array &$readonlys
	 */
	function get_rows($query,&$rows,&$readonlys)
	{

	}

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
			if (!isset($content['path']) && ($content['path'] = egw_session::appsession('select_path','filemanger')) === false ||
				!egw_vfs::is_dir($content['path']))
			{
				$content['path'] = filemanager_ui::get_home_dir();
			}
			$content['method'] = $_GET['method'];
			$content['id']     = $_GET['id'];
			$content['label'] = isset($_GET['label']) ? $_GET['label'] : lang('Open');
			$content['mime']   = $_GET['mime'];
		}
		else
		{
			//_debug_array($content); die('Stop');
			if ($content['button']) list($button) = each($content['button']);
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
		if (!($d = egw_vfs::opendir($content['path'])))
		{
			$content['msg'] = lang("Can't open directory %1!",$content['path']);
		}
		else
		{
			$n = 0;
			$content['dir'] = array('mode' => $content['mode']);
			while (($name = readdir($d)))
			{
				$path = egw_vfs::concat($content['path'],$name);
				$is_dir = egw_vfs::is_dir($path);
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
			closedir($d);
		}
		$content['name'] = '';
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
		//_debug_array($content);
		//_debug_array($readonlys);
		egw_session::appsession('select_path','filemanger',$content['path']);
		$tpl = new etemplate('filemanager.select');
		$tpl->exec('filemanager.filemanager_select.select',$content,$sel_options,$readonlys,array(
			'mode'   => $content['mode'],
			'method' => $content['method'],
			'id'     => $content['id'],
			'label'  => $content['label'],
		),2);
	}
}
