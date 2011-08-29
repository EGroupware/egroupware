<?php
/**
 * eGroupWare - Filemanager - user interface
 *
 * @link http://www.egroupware.org
 * @package filemanager
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2008-11 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Filemanage user interface class
 */
class filemanager_ui
{
	/**
	 * Methods callable via menuaction
	 *
	 * @var array
	 */
	var $public_functions = array(
		'index' => true,
		'file' => true,
	);

	/**
	 * Views available from plugins
	 *
	 * @var array
	 */
	public static $views = array(
		'filemanager_ui::listview' => 'Listview',
	);
	public static $views_init = false;

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
		// do we have root rights
		if (egw_session::appsession('is_root','filemanager'))
		{
			egw_vfs::$is_root = true;
		}

		self::init_views();
	}

	/**
	 * Initialise and return available views
	 *
	 * @return array with method => label pairs
	 */
	public static function init_views()
	{
		if (!self::$views_init)
		{
			// translate our labels
			foreach(self::$views as $method => &$label)
			{
				$label = lang($label);
			}
			// search for plugins with additional filemanager views
			foreach($GLOBALS['egw']->hooks->process('filemanager_views') as $app => $views)
			{
				if ($views) self::$views += $views;
			}
			self::$views_init = true;
		}
		return self::$views;
	}

	/**
	 * Get active view
	 *
	 * @return string
	 */
	public function get_view()
	{
		$view =& egw_cache::getSession('filemanager', 'view');
		if (isset($_GET['view']))
		{
			$view = $_GET['view'];
		}
		if (!isset(self::$views[$view]))
		{
			reset(self::$views);
			$view = key(self::$views);
		}
		return $view;
	}

	/**
	 * Context menu
	 *
	 * @return array
	 */
	private static function get_actions()
	{
		$actions = array(
			'open' => array(
				'caption' => lang('Open'),
				'icon' => '',
				'group' => $group=1,
				'allowOnMultiple' => false,
				'onExecute' => 'javaScript:nm_activate_link',
				'default' => true
			),
			'saveas' => array(
				'caption' => lang('Save as'),
				'group' => $group,
				'allowOnMultiple' => false,
				'icon' => 'filesave',
				'onExecute' => 'javaScript:force_download',
				'disableClass' => 'isDir',
			),
			'edit' => array(
				'caption' => lang('Edit settings'),
				'group' => $group,
				'allowOnMultiple' => false,
				'url' => 'menuaction=filemanager.filemanager_ui.file&path=$id',
				'popup' => '495x425',
			),
			'mail' => array(
				'caption' => lang('Mail files'),
				'icon' => 'filemanager/mail_post_to',
				'group' => $group,
				'onExecute' => 'javaScript:egw_fileman_mail',
			),
			'copy' => array(
				'caption' => lang('Copy'),
				'group' => ++$group,
				'onExecute' => 'javaScript:do_clipboard',
			),
			'add' => array(
				'caption' => lang('Add to clipboard'),
				'group' => $group,
				'icon' => 'copy',
				'onExecute' => 'javaScript:do_clipboard',
			),
			'cut' => array(
				'caption' => lang('Cut'),
				'group' => $group,
				'onExecute' => 'javaScript:do_clipboard',
			),
			'delete' => array(
				'caption' => lang('Delete'),
				'group' => ++$group,
				'confirm' => 'Delete these files or directories?',
			),
		);
		if (!isset($GLOBALS['egw_info']['user']['apps']['felamimail']))
		{
			unset($actions['mail']);
		}
		return $actions;
	}

	/**
	 * Main filemanager page
	 *
	 * @param array $content=null
	 * @param string $msg=null
	 */
	function index(array $content=null,$msg=null)
	{
		if (!is_array($content))
		{
			$content = array(
				'nm' => egw_session::appsession('index','filemanager'),
			);
			if (!is_array($content['nm']))
			{
				$content['nm'] = array(
					'get_rows'       =>	'filemanager.filemanager_ui.get_rows',	// I  method/callback to request the data for the rows eg. 'notes.bo.get_rows'
					'filter'         => '1',	// current dir only
					'no_filter2'     => True,	// I  disable the 2. filter (params are the same as for filter)
					'no_cat'         => True,	// I  disable the cat-selectbox
					'lettersearch'   => True,	// I  show a lettersearch
					'searchletter'   =>	false,	// I0 active letter of the lettersearch or false for [all]
					'start'          =>	0,		// IO position in list
					'order'          =>	'name',	// IO name of the column to sort after (optional for the sortheaders)
					'sort'           =>	'ASC',	// IO direction of the sort: 'ASC' or 'DESC'
					'default_cols'   => '!comment,ctime',	// I  columns to use if there's no user or default pref (! as first char uses all but the named columns), default all columns
					'csv_fields'     =>	false, // I  false=disable csv export, true or unset=enable it with auto-detected fieldnames,
									//or array with name=>label or name=>array('label'=>label,'type'=>type) pairs (type is a eT widget-type)
					'actions'        => self::get_actions(),
					'row_id'         => 'path',
				);
				$content['nm']['path'] = self::get_home_dir();
			}
			if (isset($_GET['msg'])) $msg = $_GET['msg'];

			// switch to projectmanager folders
			if (isset($_GET['pm_id']))
			{
				$_GET['path'] = '/apps/projectmanager'.((int)$_GET['pm_id'] ? '/'.(int)$_GET['pm_id'] : '');
			}
			if (isset($_GET['path']) && ($path = $_GET['path']))
			{
				switch($path)
				{
					case '..':
						$path = egw_vfs::dirname($content['nm']['path']);
						break;
					case '~':
						$path = self::get_home_dir();
						break;
				}
				if ($path[0] == '/' && egw_vfs::stat($path,true) && egw_vfs::is_dir($path) && egw_vfs::check_access($path,egw_vfs::READABLE))
				{
					$content['nm']['path'] = $path;
				}
				else
				{
					$msg .= lang('The requested path %1 is not available.',egw_vfs::decodePath($path));
				}
				// reset lettersearch as it confuses users (they think the dir is empty)
				$content['nm']['searchletter'] = false;
				// switch recusive display off
				if (!$content['nm']['filter']) $content['nm']['filter'] = '1';
			}
		}
		$view = self::get_view();

		if (strpos($view,'::') !== false && version_compare(PHP_VERSION,'5.3.0','<'))
		{
			$view = explode('::',$view);
		}
		call_user_func($view,$content,$msg);
	}

	/**
	 * Make the current user (vfs) root
	 *
	 * The user/pw is either the setup config user or a specially configured vfs_root user
	 *
	 * @param string $user='' setup config user to become root or '' to log off as root
	 * @param string $password=null setup config password to become root
	 * @param boolean &$is_setup=null on return true if authenticated user is setup config user, false otherwise
	 * @return boolean true is root user given, false otherwise (including logout / empty $user)
	 */
	protected function sudo($user='',$password=null,&$is_setup=null)
	{
		if (!$user)
		{
			$is_root = $is_setup = false;
		}
		else
		{
			// config user & password
			$is_setup = egw_session::user_pw_hash($user,$password) === $GLOBALS['egw_info']['server']['config_hash'];
			// or vfs root user from setup >> configuration
			$is_root = $is_setup ||	$GLOBALS['egw_info']['server']['vfs_root_user'] &&
				in_array($user,preg_split('/, */',$GLOBALS['egw_info']['server']['vfs_root_user'])) &&
				$GLOBALS['egw']->auth->authenticate($user, $password, 'text');
		}
		//echo "<p>".__METHOD__."('$user','$password',$is_setup) user_pw_hash(...)='".egw_session::user_pw_hash($user,$password)."', config_hash='{$GLOBALS['egw_info']['server']['config_hash']}' --> returning ".array2string($is_root)."</p>\n";
		egw_session::appsession('is_setup','filemanager',$is_setup);
		return egw_session::appsession('is_root','filemanager',egw_vfs::$is_root = $is_root);
	}

	/**
	 * Filemanager listview
	 *
	 * @param array $content=null
	 * @param string $msg=null
	 */
	function listview(array $content=null,$msg=null)
	{
		$tpl = new etemplate('filemanager.index');

		$content['nm']['msg'] = $msg;

		if ($content['nm']['action'] || $content['nm']['rows'])
		{
			if ($content['nm']['action'])
			{
				$content['nm']['msg'] = self::action($content['nm']['action'],$content['nm']['selected'],$content['nm']['path']);
				unset($content['nm']['action']);
			}
			elseif($content['nm']['rows']['delete'])
			{
				$content['nm']['msg'] = self::action('delete',array_keys($content['nm']['rows']['delete']),$content['nm']['path']);
			}
			unset($content['nm']['rows']);
		}
		$clipboard_files = egw_session::appsession('clipboard_files','filemanager');
		$clipboard_type = egw_session::appsession('clipboard_type','filemanager');

		// be tolerant with (in previous versions) not correct urlencoded pathes
		if ($content['nm']['path'][0] == '/' && !egw_vfs::stat($content['nm']['path'],true) && egw_vfs::stat(urldecode($content['nm']['path'])))
		{
			$content['nm']['path'] = urldecode($content['nm']['path']);
		}
		if ($content['button'])
		{
			if ($content['button'])
			{
				list($button) = each($content['button']);
				unset($content['button']);
			}
			switch($button)
			{
				case 'up':
					if ($content['nm']['path'] != '/')
					{
						$content['nm']['path'] = dirname($content['nm']['path']);
						// switch recusive display off
						if (!$content['nm']['filter']) $content['nm']['filter'] = '1';
					}
					break;
				case 'home':
					$content['nm']['path'] = self::get_home_dir();
					break;
				case 'createdir':
					if ($content['nm']['path'][0] != '/')
					{
						$ses = egw_session::appsession('index','filemanager');
						$old_path = $ses['path'];
						$content['nm']['path'] = egw_vfs::concat($old_path,$content['nm']['path']);
					}
					if (!@egw_vfs::mkdir($content['nm']['path'],null,STREAM_MKDIR_RECURSIVE))
					{
						$content['nm']['msg'] = !egw_vfs::is_writable(dirname($content['nm']['path'])) ?
							lang('Permission denied!') : lang('Failed to create directory!');
						if (!$old_path)
						{
							$ses = egw_session::appsession('index','filemanager');
							$old_path = $ses['path'];
						}
						$content['nm']['path'] = $old_path;
					}
					break;
				case 'symlink':
					$target = $content['nm']['path'];
					$ses = egw_session::appsession('index','filemanager');
					$content['nm']['path'] = $ses['path'];
					$link = egw_vfs::concat($content['nm']['path'],egw_vfs::basename($target));
					$abs_target = $target[0] == '/' ? $target : egw_vfs::concat($content['nm']['path'],$target);
					if (!egw_vfs::stat($abs_target))
					{
						$content['nm']['msg'] = lang('Link target %1 not found!',egw_vfs::decodePath($abs_target));
						break;
					}
					$content['nm']['msg'] = egw_vfs::symlink($target,$link) ?
						lang('Symlink to %1 created.',$target) : lang('Error creating symlink to target %1!',egw_vfs::decodePath($target));
					break;
				case 'paste':
					$content['nm']['msg'] = self::action($clipboard_type.'_paste',$clipboard_files,$content['nm']['path']);
					break;
				case 'linkpaste':
					$content['nm']['msg'] = self::action('link_paste',$clipboard_files,$content['nm']['path']);
					break;
				case 'upload':
					if (!$content['upload'])
					{
						$content['nm']['msg'] = lang('You need to select some files first!');
						break;
					}
					$upload_success = $upload_failure = array();
					foreach(isset($content['upload'][0]) ? $content['upload'] : array($content['upload']) as $upload)
					{
						// encode chars which special meaning in url/vfs (some like / get removed!)
						$to = egw_vfs::concat($content['nm']['path'],egw_vfs::encodePathComponent($upload['name']));

						if ($upload && is_uploaded_file($upload['tmp_name']) &&
							(egw_vfs::is_writable($content['nm']['path']) || egw_vfs::is_writable($to)) &&
							copy($upload['tmp_name'],egw_vfs::PREFIX.$to))
						{
							$upload_success[] = $upload['name'];
						}
						else
						{
							$upload_failure[] = $upload['name'];
						}
					}
					$content['nm']['msg'] = '';
					if ($upload_success)
					{
						$content['nm']['msg'] = count($upload_success) == 1 && !$upload_failure ? lang('File successful uploaded.') :
							lang('%1 successful uploaded.',implode(', ',$upload_success));
					}
					if ($upload_failure)
					{
						$content['nm']['msg'] .= ($upload_success ? "\n" : '').lang('Error uploading file!')."\n".etemplate::max_upload_size_message();
					}
					break;
			}
		}
		if (!egw_vfs::stat($content['nm']['path'],true) || !egw_vfs::is_dir($content['nm']['path']))
		{
			$content['nm']['msg'] .= ' '.lang('Directory not found or no permission to access it!');
		}
		else
		{
			$dir_is_writable = egw_vfs::is_writable($content['nm']['path']);
		}
		$content['mailpaste_files'] = $clipboard_files;
		$content['upload_size'] = etemplate::max_upload_size_message();
		//_debug_array($content);

		$readonlys['button[linkpaste]'] = $readonlys['button[paste]'] = !$dir_is_writable;
		$readonlys['button[mailpaste]'] = !isset($GLOBALS['egw_info']['user']['apps']['felamimail']);
		$readonlys['button[createdir]'] = !$dir_is_writable;
		$readonlys['button[symlink]'] = !$dir_is_writable;
		$readonlys['button[upload]'] = $readonlys['upload'] = !$dir_is_writable;

		$sel_options['filter'] = array(
			'1' => 'Current directory',
			'2' => 'Directories sorted in',
			'3' => 'Show hidden files',
			''  => 'Files from subdirectories',
		);
		if ($GLOBALS['egw_info']['user']['apps']['felamimail'])
		{
			list($width,$height) = explode('x',egw_link::get_registry('felamimail','add_popup'));
			$GLOBALS['egw_info']['flags']['java_script'] .= "<script type=\"text/javascript\">
	function open_mail(attachments)
	{
		if (typeof attachments == 'undefined') attachments = clipboard_files;

		var link = '".egw::link('/index.php',array('menuaction' => 'felamimail.uicompose.compose'))."';
		if (!(attachments instanceof Array)) attachments = [ attachments ];

		for(var i=0; i < attachments.length; i++)
		{
		   link += '&preset[file][]='+encodeURIComponent('vfs://default'+attachments[i]);
		}
		egw_openWindowCentered2(link, '_blank', $width, $height, 'yes');
	}
	function egw_fileman_mail(_action, _elems)
	{
		var ids = [];
		for (var i = 0; i < _elems.length; i++)
		{
			ids.push(_elems[i].id);
		}
		open_mail(ids);
	}
</script>\n";
		}
		$GLOBALS['egw_info']['flags']['java_script'] .= '<script type="text/javascript">
var clipboard_files = [];

function check_files(upload)
{
	var files = [];
	if (upload.files)
	{
		for(var i = 0; i < upload.files.length; ++i)
		{
			files.push(upload.files[i].fileName);
		}
	}
	else if (upload.value)
	{
		files = upload.value;
	}
	var path = document.getElementById(upload.id.replace(/upload\]\[/,"nm][path"));

	xajax_doXMLHTTP("filemanager_ui::ajax_check_upload_target",upload.id, files, path.value);
}

function clipboard_tooltip(link)
{
	xajax_doXMLHTTP("filemanager_ui::ajax_clipboard_tooltip", link.id);

	window.setTimeout(UnTip, 3000);
}

function do_clipboard(_action, _elems)
{
	if (_action.id != "cut") clipboard_files = [];

	var ids = [];
	for (var i = 0; i < _elems.length; i++)
	{
		clipboard_files.push(_elems[i].id);
		ids.push(_elems[i].id);
	}
	xajax_doXMLHTTP("filemanager_ui::ajax_clipboard", _action.id, ids);
}

function force_download(_action, _senders)
{
	var a_href = $j(_senders[0].iface.getDOMNode()).find("a:first").attr("href");

	window.location = a_href+"?download";
}
</script>'."\n";
		$tpl->exec('filemanager.filemanager_ui.index',$content,$sel_options,$readonlys,array('nm' => $content['nm']));
	}

	/**
	 * Check if a file upload would overwrite an existing file and get a user confirmation in that case
	 *
	 * @param string $id id of the input
	 * @param string|array $names name(s) (incl. client-path) of the file(s) to upload
	 * @param string $dir current vfs directory
	 * @return string xajax output
	 */
	static function ajax_check_upload_target($id,$names,$dir)
	{
		$response = new xajaxResponse();

		//$response->addAlert(__METHOD__."('$id',".array2string($name).",'$dir')");

		$ask_overwrite = array();
		foreach((array)$names as $name)
		{
			$name = explode('/',str_replace('\\','/',$name));	// in case of win clients
			$name = array_pop($name);

			// encode chars which special meaning in url/vfs (some like / get removed!)
			$path = egw_vfs::concat($dir,egw_vfs::encodePathComponent($name));

			if(egw_vfs::deny_script($path))
			{
				$response->addAlert(lang('You are NOT allowed to upload a script!'));
				$response->addScript("document.getElementById('$id').value='';");
				$ask_overwrite = array();
				break;
			}
			elseif (egw_vfs::stat($path))
			{
				if (egw_vfs::is_dir($path))
				{
					$response->addAlert(lang("There's already a directory with that name!"));
					$response->addScript("document.getElementById('$id').value='';");
					$ask_overwrite = array();
					break;
				}
				else
				{
					$ask_overwrite[] = egw_vfs::decodePath($path);
				}
			}
			else
			{
				// do nothing new file
			}
		}
		if ($ask_overwrite)
		{
			$response->addScript("if (!confirm('".addslashes(lang('Do you want to overwrite the existing file %1?',implode(', ',$ask_overwrite)))."')) document.getElementById('$id').value='';");
		}
		return $response->getXML();
	}

	/**
	 * Get the configured start directory for the current user
	 *
	 * @return string
	 */
	static function get_home_dir()
	{
		$start = '/home/'.$GLOBALS['egw_info']['user']['account_lid'];

		// check if user specified a valid startpath in his prefs --> use it
		if (($path = $GLOBALS['egw_info']['user']['preferences']['filemanager']['startfolder']) &&
			$path[0] == '/' && egw_vfs::is_dir($path) && egw_vfs::check_access($path, egw_vfs::READABLE))
		{
			$start = $path;
		}
		return $start;
	}

	/**
	 * Run a certain action with the selected file
	 *
	 * @param string $action
	 * @param array $selected selected pathes
	 * @param mixed $dir=null current directory
	 * @param int &$errs=null on return number of errors
	 * @param int &$dirs=null on return number of dirs deleted
	 * @param int &$files=null on return number of files deleted
	 * @return string success or failure message displayed to the user
	 */
	static private function action($action,$selected,$dir=null,&$errs=null,&$files=null,&$dirs=null)
	{
		if (!count($selected))
		{
			return lang('You need to select some files first!');
		}
		$errs = $dirs = $files = 0;
		switch($action)
		{
			case 'mail':
				throw new egw_exception_assertion_failed('Implemented on clientside!');

			case 'delete':
				return self::do_delete($selected,$errs,$files,$dirs);

			case 'add':
				$files = egw_session::appsession('clipboard_files','filemanager');
				egw_session::appsession('clipboard_files','filemanager',$files ? array_unique(array_merge($files,$selected)) : $selected);
				egw_session::appsession('clipboard_type','filemanager','copy');	// always switch to copy, to be on the save side
				return lang('%1 URLs %2 to clipboard.',count($selected),lang('copied'));

			case 'copy':
			case 'cut':
				egw_session::appsession('clipboard_files','filemanager',$selected);
				egw_session::appsession('clipboard_type','filemanager',$action);
				return lang('%1 URLs %2 to clipboard.',count($selected),$action=='copy'?lang('copied'):lang('cut'));

			case 'copy_paste':
				foreach($selected as $path)
				{
					if (!egw_vfs::is_dir($path))
					{
						$to = egw_vfs::concat($dir,egw_vfs::basename($path));
						if ($path != $to && egw_vfs::copy($path,$to))
						{
							++$files;
						}
						else
						{
							++$errs;
						}
					}
					else
					{
						$len = strlen(dirname($path));
						foreach(egw_vfs::find($path) as $p)
						{
							$to = $dir.substr($p,$len);
							if ($to == $p)	// cant copy into itself!
							{
								++$errs;
								continue;
							}
							if (($is_dir = egw_vfs::is_dir($p)) && egw_vfs::mkdir($to,null,STREAM_MKDIR_RECURSIVE))
							{
								++$dirs;
							}
							elseif(!$is_dir && egw_vfs::copy($p,$to))
							{
								++$files;
							}
							else
							{
								++$errs;
							}
						}
					}
				}
				if ($errs)
				{
					return lang('%1 errors copying (%2 diretories and %3 files copied)!',$errs,$dirs,$files);
				}
				return $dirs ? lang('%1 directories and %2 files copied.',$dirs,$files) : lang('%1 files copied.',$files);

			case 'move':
				if (!isset($dir)) $dir = array_pop($selected);
				// fall throught
			case 'cut_paste':
				foreach($selected as $path)
				{
					$to = egw_vfs::is_dir($dir) || count($selected) > 1 ? egw_vfs::concat($dir,egw_vfs::basename($path)) : $dir;
					if ($path != $to && egw_vfs::rename($path,$to))
					{
						++$files;
					}
					else
					{
						++$errs;
					}
				}
				if ($action == 'cut_paste') egw_session::appsession('clipboard_files','filemanager',false);	// cant move again
				if ($errs)
				{
					return lang('%1 errors moving (%2 files moved)!',$errs,$files);
				}
				return lang('%1 files moved.',$files);

			case 'link_paste':
				foreach($selected as $path)
				{
					$to = egw_vfs::concat($dir,egw_vfs::basename($path));
					if ($path != $to && egw_vfs::symlink($path,$to))
					{
						++$files;
					}
					else
					{
						++$errs;
					}
				}
				$ret = lang('%1 elements linked.',$files);
				if ($errs)
				{
					$ret = lang('%1 errors linking (%2)!',$errs,$ret);
				}
				return $ret." egw_vfs::symlink('$to','$path')";
		}
		return "Unknown action '$action'!";
	}

	/**
	 * Delete selected files and return success or error message
	 *
	 * @param array $selected
	 * @param int &$errs=null on return number of errors
	 * @param int &$dirs=null on return number of dirs deleted
	 * @param int &$files=null on return number of files deleted
	 * @return string
	 */
	public static function do_delete(array $selected, &$errs=null, &$dirs=null, &$files=null)
	{
		$dirs = $files = $errs = 0;
		// we first delete all selected links (and files)
		// feeding the links to dirs to egw_vfs::find() deletes the content of the dirs, not just the link!
		foreach($selected as $key => $path)
		{
			if (!egw_vfs::is_dir($path) || egw_vfs::is_link($path))
			{
				if (egw_vfs::unlink($path))
				{
					++$files;
				}
				else
				{
					++$errs;
				}
				unset($selected[$key]);
			}
		}
		if ($selected)	// somethings left to delete
		{
			// some precaution to never allow to (recursivly) remove /, /apps or /home
			foreach((array)$selected as $path)
			{
				if (preg_match('/^\/?(home|apps|)\/*$/',$path))
				{
					$errs++;
					return lang("Cautiously rejecting to remove folder '%1'!",egw_vfs::decodePath($path));
				}
			}
			// now we use find to loop through all files and dirs: (selected only contains dirs now)
			// - depth=true to get first the files and then the dir containing it
			// - hidden=true to also return hidden files (eg. Thumbs.db), as we cant delete non-empty dirs
			foreach(egw_vfs::find($selected,array('depth'=>true,'hidden'=>true)) as $path)
			{
				if (($is_dir = egw_vfs::is_dir($path) && !egw_vfs::is_link($path)) && egw_vfs::rmdir($path,0))
				{
					++$dirs;
				}
				elseif (!$is_dir && egw_vfs::unlink($path))
				{
					++$files;
				}
				else
				{
					++$errs;
				}
			}
		}
		if ($errs)
		{
			return lang('%1 errors deleteting (%2 directories and %3 files deleted)!',$errs,$dirs,$files);
		}
		if ($dirs)
		{
			return lang('%1 directories and %2 files deleted.',$dirs,$files);
		}
		return $files == 1 ? lang('File deleted.') : lang('%1 files deleted.',$files);
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
		// show projectmanager sidebox for projectmanager path
		if (substr($query['path'],0,20) == '/apps/projectmanager' && isset($GLOBALS['egw_info']['user']['apps']['projectmanager']))
		{
			$GLOBALS['egw_info']['flags']['currentapp'] = 'projectmanager';
		}
		egw_session::appsession('index','filemanager',$query);

		// be tolerant with (in previous versions) not correct urlencoded pathes
		if (!egw_vfs::stat($query['path'],true) && egw_vfs::stat(urldecode($query['path'])))
		{
			$query['path'] = urldecode($query['path']);
		}
		if (!egw_vfs::stat($query['path'],true) || !egw_vfs::is_dir($query['path']) || !egw_vfs::check_access($query['path'],egw_vfs::READABLE))
		{
			// we will leave here, since we are not allowed, or the location does not exist. Index must handle that, and give
			// an appropriate message
			egw::redirect_link('/index.php',array('menuaction'=>'filemanager.filemanager_ui.index',
				'path' => self::get_home_dir(),
				'msg' => lang('The requested path %1 is not available.',egw_vfs::decodePath($query['path'])),
			));
		}
		$rows = $dir_is_writable = array();
		if($query['searchletter'] && !empty($query['search']))
		{
			$namefilter = '/^'.$query['searchletter'].'.*'.str_replace(array('\\?','\\*'),array('.{1}','.*'),preg_quote($query['search'])).'/i';
			if ($query['searchletter'] == strtolower($query['search'][0]))
			{
				$namefilter = '/^('.$query['searchletter'].'.*'.str_replace(array('\\?','\\*'),array('.{1}','.*'),preg_quote($query['search'])).'|'.
					str_replace(array('\\?','\\*'),array('.{1}','.*'),preg_quote($query['search'])).')/i';
			}
		}
		elseif ($query['searchletter'])
		{
			$namefilter = '/^'.$query['searchletter'].'/i';
		}
		elseif(!empty($query['search']))
		{
			$namefilter = '/'.str_replace(array('\\?','\\*'),array('.{1}','.*'),preg_quote($query['search'])).'/i';
		}
		foreach(egw_vfs::find($query['path'],array(
			'mindepth' => 1,
			'maxdepth' => $query['filter'] ? (int)(boolean)$query['filter'] : null,
			'dirsontop' => $query['filter'] <= 1,
			'type' => $query['filter'] ? null : 'f',
			'order' => $query['order'], 'sort' => $query['sort'],
			'limit' => (int)$query['num_rows'].','.(int)$query['start'],
			'need_mime' => true,
			'name_preg' => $namefilter,
			'hidden' => $query['filter'] == 3,
		),true) as $path => $row)
		{
			//echo $path; _debug_array($row);

			$dir = dirname($path);
			if (!isset($dir_is_writable[$dir]))
			{
				$dir_is_writable[$dir] = egw_vfs::is_writable($dir);
			}
			$path_quoted = str_replace(array('"',"'"),array('&quot;',"\\'"),$path);
			if (!$dir_is_writable[$dir])
			{
				$readonlys["delete[$path_quoted]"] = true;	// no rights to delete the file
			}
			if (egw_vfs::is_dir($path))
			{
				$readonlys["mail[$path_quoted]"] = true;
				$row['class'] = 'isDir';
			}
			elseif (!egw_vfs::is_readable($path) ||
				!$GLOBALS['egw_info']['user']['apps']['felamimail'])
			{
				$readonlys["mail[$path_quoted]"] = true;
			}
			$rows[++$n] = $row;
			$path2n[$path] = $n;
		}
		// query comments and cf's for the displayed rows
		$cols_to_show = explode(',',$GLOBALS['egw_info']['user']['preferences']['filemanager']['nextmatch-filemanager.index.rows']);
		$cfs = config::get_customfields('filemanager');
		$all_cfs = in_array('customfields',$cols_to_show) && $cols_to_show[count($cols_to_show)-1][0] != '#';
		if ($path2n && (in_array('comment',$cols_to_show) || in_array('customfields',$cols_to_show)) &&
			($path2props = egw_vfs::propfind(array_keys($path2n))))
		{
			foreach($path2props as $path => $props)
			{
				unset($row);	// fixes a weird problem with php5.1, does NOT happen with php5.2
				$row =& $rows[$path2n[$path]];
				if ( !is_array($props) ) continue;
				foreach($props as $prop)
				{
					if (!$all_cfs && $prop['name'][0] == '#' && !in_array($prop['name'],$cols_to_show)) continue;
					$row[$prop['name']] = strlen($prop['val']) < 64 ? $prop['val'] : substr($prop['val'],0,64).' ...';
				}
			}
		}
		//_debug_array($readonlys);
		if ($GLOBALS['egw_info']['flags']['currentapp'] == 'projectmanager')
		{
			$GLOBALS['egw_info']['flags']['app_header'] = lang('Projectmanager').' - '.lang('Filemanager');
			// we need our app.css file
			if (!file_exists(EGW_SERVER_ROOT.($css_file='/filemanager/templates/'.$GLOBALS['egw_info']['server']['template_set'].'/app.css')))
			{
				$css_file = '/filemanager/templates/default/app.css';
			}
			$GLOBALS['egw_info']['flags']['css'] .= "\n\t\t</style>\n\t\t".'<link href="'.$GLOBALS['egw_info']['server']['webserver_url'].
				$css_file.'?'.filemtime(EGW_SERVER_ROOT.$css_file).'" type="text/css" rel="StyleSheet" />'."\n\t\t<style>\n\t\t\t";
		}
		else
		{
			$GLOBALS['egw_info']['flags']['app_header'] = lang('Filemanager').': '.egw_vfs::decodePath($query['path']);
		}
		return egw_vfs::$find_total;
	}

	/**
	 * Preferences of a file/directory
	 *
	 * @param array $content=null
	 * @param string $msg=''
	 */
	function file(array $content=null,$msg='')
	{
		$tpl = new etemplate('filemanager.file');

		if (!is_array($content))
		{
			if (isset($_GET['msg']))
			{
				$msg .= $_GET['msg'];
			}
			if (!($path = $_GET['path']) || !($stat = egw_vfs::lstat($path)))
			{
				$msg .= lang('File or directory not found!');
			}
			else
			{
				$content = $stat;
				$content['name'] = egw_vfs::basename($path);
				$content['dir'] = dirname($path);
				$content['path'] = $path;
				$content['hsize'] = egw_vfs::hsize($stat['size']);
				$content['mime'] = egw_vfs::mime_content_type($path);
				$content['gid'] *= -1;	// our widgets use negative gid's
				if (($props = egw_vfs::propfind($path)))
				{
					foreach($props as $prop) $content[$prop['name']] = $prop['val'];
				}
				if (($content['is_link'] = egw_vfs::is_link($path)))
				{
					$content['symlink'] = egw_vfs::readlink($path);
				}
			}
			$content['tabs'] = $_GET['tabs'];
			if (!($content['is_dir'] = egw_vfs::is_dir($path) && !egw_vfs::is_link($path)))
			{
				$content['perms']['executable'] = (int)!!($content['mode'] & 0111);
				$mask = 6;
				if (preg_match('/^text/',$content['mime']) && $content['size'] < 100000)
				{
					$content['text_content'] = file_get_contents(egw_vfs::PREFIX.$path);
				}
			}
			else
			{
				//currently not implemented in backend $content['perms']['sticky'] = (int)!!($content['mode'] & 0x201);
				$mask = 7;
			}
			foreach(array('owner' => 6,'group' => 3,'other' => 0) as $name => $shift)
			{
				$content['perms'][$name] = ($content['mode'] >> $shift) & $mask;
			}
			$content['is_owner'] = egw_vfs::has_owner_rights($path,$content);
		}
		else
		{
			//_debug_array($content);
			$path =& $content['path'];

			list($button) = @each($content['button']); unset($content['button']);
			// need to check 'setup' button (submit button in sudo popup), as some browsers (eg. chrome) also fill the hidden field
			if ($button == 'sudo' && egw_vfs::$is_root || $button == 'setup' && $content['sudo']['user'])
			{
				$msg = $this->sudo($button == 'setup' ? $content['sudo']['user'] : '',$content['sudo']['passwd']) ?
					lang('Root access granted.') : ($button == 'setup' && $content['sudo']['user'] ?
					lang('Wrong username or password!') : lang('Root access stopped.'));
				unset($content['sudo']);
				$content['is_owner'] = egw_vfs::has_owner_rights($path);
			}
			if (in_array($button,array('save','apply')))
			{
				$props = array();
				foreach($content['old'] as $name => $old_value)
				{
					if (isset($content[$name]) && ($old_value != $content[$name] ||
						// do not check for modification, if modify_subs is checked!
						$content['modify_subs'] && in_array($name,array('uid','gid','perms'))) &&
						($name != 'uid' || egw_vfs::$is_root))
					{
						if ($name == 'name')
						{
							$to = egw_vfs::concat($content['dir'],$content['name']);
							if (file_exists(egw_vfs::PREFIX.$to) && $content['confirm_overwrite'] !== $to)
							{
								$tpl->set_validation_error('name',lang("There's already a file with that name!").'<br />'.
									lang('To overwrite the existing file store again.',lang($button)));
								$content['confirm_overwrite'] = $to;
								if ($button == 'save') $button = 'apply';
								continue;
							}
							if (egw_vfs::rename($path,$to))
							{
								$msg .= lang('Renamed %1 to %2.',egw_vfs::decodePath(basename($path)),egw_vfs::decodePath(basename($to))).' ';
								$content['old']['name'] = $content[$name];
								$path = $to;
								$content['mime'] = mime_magic::filename2mime($path);	// recheck mime type
								$refresh_path = egw_vfs::dirname($path);	// for renames, we have to refresh the parent
							}
							else
							{
								$msg .= lang('Rename of %1 to %2 failed!',egw_vfs::decodePath(basename($path)),egw_vfs::decodePath(basename($to))).' ';
								if (egw_vfs::deny_script($to))
								{
									$msg .= lang('You are NOT allowed to upload a script!').' ';
								}
							}
						}
						elseif ($name[0] == '#' || $name == 'comment')
						{
							$props[] = array('name' => $name, 'val' => $content[$name] ? $content[$name] : null);
						}
						else
						{
							static $name2cmd = array('uid' => 'chown','gid' => 'chgrp','perms' => 'chmod');
							$cmd = array('egw_vfs',$name2cmd[$name]);
							$value = $name == 'perms' ? self::perms2mode($content['perms']) : $content[$name];
							if ($content['modify_subs'])
							{
								if ($name == 'perms')
								{
									$changed = egw_vfs::find($path,array('type'=>'d'),$cmd,array($value));
									$changed += egw_vfs::find($path,array('type'=>'f'),$cmd,array($value & 0666));	// no execute for files
								}
								else
								{
									$changed = egw_vfs::find($path,null,$cmd,array($value));
								}
								$ok = $failed = 0;
								foreach($changed as $p => $r)
								{
									if ($r)
									{
										++$ok;
									}
									else
									{
										++$failed;
									}
								}
								if ($ok && !$failed)
								{
									if(!$perm_changed++) $msg .= lang('Permissions of %1 changed.',$path.' '.lang('and all it\'s childeren'));
									$content['old'][$name] = $content[$name];
								}
								elseif($failed)
								{
									if(!$perm_failed++) $msg .= lang('Failed to change permissions of %1!',$path.lang('and all it\'s childeren').
										($ok ? ' ('.lang('%1 failed, %2 succeded',$failed,$ok).')' : ''));
								}
							}
							elseif (call_user_func_array($cmd,array($path,$value)))
							{
								$msg .= lang('Permissions of %1 changed.',$path);
								$content['old'][$name] = $content[$name];
							}
							else
							{
								$msg .= lang('Failed to change permissions of %1!',$path);
							}
						}
					}
				}
				if ($props)
				{
					if (egw_vfs::proppatch($path,$props))
					{
						foreach($props as $prop)
						{
							$content['old'][$prop['name']] = $prop['val'];
						}
						$msg .= lang('Properties saved.');
					}
					else
					{
						$msg .= lang('Saving properties failed!');
					}
				}
			}
			elseif ($content['eacl'] && $content['is_owner'])
			{
				if ($content['eacl']['delete'])
				{
					list($ino_owner) = each($content['eacl']['delete']);
					list($ino,$owner) = explode('-',$ino_owner,2);	// $owner is a group and starts with a minus!
					$msg .= egw_vfs::eacl($path,null,$owner) ? lang('ACL deleted.') : lang('Error deleting the ACL entry!');
				}
				elseif ($button == 'eacl')
				{
					if (!$content['eacl']['owner'])
					{
						$msg .= lang('You need to select an owner!');
					}
					else
					{
						$msg .= egw_vfs::eacl($path,$content['eacl']['rights'],$content['eacl']['owner']) ?
							lang('ACL added.') : lang('Error adding the ACL!');
					}
				}
			}
			$js = "opener.egw_refresh('".str_replace("'","\\'",$msg)."','filemanager','".
				str_replace("'","\\'",$refresh_path ? $refresh_path : $path)."','edit',null,/&path=[^&]*/);";

			if ($button == 'save') $js .= "window.close();";
			echo "<html>\n<body>\n<script>\n$js\n</script>\n</body>\n</html>\n";
			if ($button == 'save') common::egw_exit();
		}
		if ($content['is_link'] && !egw_vfs::stat($path))
		{
			$msg .= ($msg ? "\n" : '').lang('Link target %1 not found!',$content['symlink']);
		}
		$content['link'] = egw::link(egw_vfs::download_url($path));
		$content['icon'] = egw_vfs::mime_icon($content['mime']);
		$content['msg'] = $msg;

		if (($readonlys['uid'] = !egw_vfs::$is_root) && !$content['uid']) $content['ro_uid_root'] = 'root';
		// only owner can change group & perms
		if (($readonlys['gid'] = !$content['is_owner'] ||
			parse_url(egw_vfs::resolve_url($content['path']),PHP_URL_SCHEME) == 'oldvfs'))	// no uid, gid or perms in oldvfs
		{
			if (!$content['gid']) $content['ro_gid_root'] = 'root';
			foreach($content['perms'] as $name => $value)
			{
				$readonlys['perms['.$name.']'] = true;
			}
		}
		$readonlys['name'] = $path == '/' || !egw_vfs::is_writable($content['dir']);
		$readonlys['comment'] = !egw_vfs::is_writable($path);
		$readonlys['tabs']['preview'] = $readonlys['tabs']['perms'] = $content['is_link'];

		// if neither owner nor is writable --> disable save&apply
		$readonlys['button[save]'] = $readonlys['button[apply]'] = !$content['is_owner'] && !egw_vfs::is_writable($path);

		if (!($cfs = config::get_customfields('filemanager')))
		{
			$readonlys['tabs']['custom'] = true;
		}
		elseif (!egw_vfs::is_writable($path))
		{
			foreach($cfs as $name => $data)
			{
				$readonlys['#'.$name] = true;
			}
		}
		$readonlys['tabs']['eacl'] = true;	// eacl off by default
		if ($content['is_dir'])
		{
			$readonlys['tabs']['preview'] = true;	// no preview tab for dirs
			$sel_options['rights']=$sel_options['owner']=$sel_options['group']=$sel_options['other'] = array(
				7 => lang('Display and modification of content'),
				5 => lang('Display of content'),
				0 => lang('No access'),
			);
			if(($content['eacl'] = egw_vfs::get_eacl($content['path'])) !== false)	// backend supports eacl
			{
				unset($readonlys['tabs']['eacl']);	// --> switch the tab on again
				foreach($content['eacl'] as &$eacl)
				{
					$eacl['path'] = parse_url($eacl['path'],PHP_URL_PATH);
					$readonlys['delete['.$eacl['ino'].'-'.$eacl['owner'].']'] = $eacl['ino'] != $content['ino'] ||
						$eacl['path'] != $content['path'] || !$content['is_owner'];
				}
				array_unshift($content['eacl'],false);	// make the keys start with 1, not 0
				$content['eacl']['owner'] = 0;
				$content['eacl']['rights'] = 5;
			}
		}
		else
		{
			$sel_options['owner']=$sel_options['group']=$sel_options['other'] = array(
				6 => lang('Read & write access'),
				4 => lang('Read access only'),
				0 => lang('No access'),
			);
		}
		$preserve = $content;
		if (!isset($preserve['old']))
		{
			$preserve['old'] = array(
				'perms' => $content['perms'],
				'name'  => $content['name'],
				'uid'   => $content['uid'],
				'gid'   => $content['gid'],
				'comment' => (string)$content['comment'],
			);
			if ($cfs) foreach($cfs as $name => $data)
			{
				$preserve['old']['#'.$name] = (string)$content['#'.$name];
			}
		}
		if (egw_vfs::$is_root)
		{
			$sudo_button =& $tpl->get_widget_by_name('sudo');
			$sudo_button = etemplate::empty_cell('button','button[sudo]',array(
				'label' => 'Logout',
				'help'  => 'Log out as superuser',
				'align' => 'right',
			));
		}
		if (($extra_tabs = egw_vfs::getExtraInfo($path,$content)))
		{
			$tabs =& $tpl->get_widget_by_name('tabs=general|perms|eacl|preview|custom');
			foreach(isset($extra_tabs[0]) ? $extra_tabs : array($extra_tabs) as $extra_tab)
			{
				$tabs['name'] .= '|'.$extra_tab['name'];
				$tabs['label'] .= '|'.$extra_tab['label'];
				$tabs['help'] .= '|'.$extra_tab['help'];
				if ($extra_tab['data'] && is_array($extra_tab['data']))
				{
					$content = array_merge($content, $extra_tab['data']);
				}
				if ($extra_tab['preserve'] && is_array($extra_tab['preserve']))
				{
					$preserve = array_merge($preserve, $extra_tab['preserve']);
				}
				if ($extra_tab['readonlys'] && is_array($extra_tab['readonlys']))
				{
					$readonlys = array_merge($readonlys, $extra_tab['readonlys']);
				}
			}
		}
		$GLOBALS['egw_info']['flags']['java_script'] = "<script>window.focus();</script>\n";
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Preferences').' '.egw_vfs::decodePath($path);

		$tpl->exec('filemanager.filemanager_ui.file',$content,$sel_options,$readonlys,$preserve,2);
	}

	/**
	 * Get tooltip for paste icons containing files currently in clipboard
	 *
	 * @param string $id id of button/icon
	 */
	public static function ajax_clipboard_tooltip($id)
	{
		$response = egw_json_response::get();

		$clipboard_files = egw_session::appsession('clipboard_files','filemanager');
		$clipboard_type = egw_session::appsession('clipboard_type','filemanager');

		if (!$clipboard_files)
		{
			$tooltip = '<p><b>'.lang('Clipboard is empty!').'</b></p>';
			$clipboard_files = array();
		}
		elseif (preg_match('/\[([a-z]+)\]$/',$id,$matches))
		{
			switch($matches[1])
			{
				case 'paste':
					$tooltip = lang('%1 the following files into current directory',$clipboard_type=='copy'?lang('Copy'):lang('Move'));
					break;
				case 'linkpaste':
					$tooltip = lang('%1 the following files into current directory',lang('Link'));
					break;
				case 'mailpaste':
					$tooltip = lang('Mail files');
					break;
			}
			$tooltip = '<p><b>'.$tooltip.':</b><br />'.egw_vfs::decodePath(implode('<br />',$clipboard_files)).'</p>';
		}
		$response->script('clipboard_files = '.json_encode($clipboard_files).';');
		$response->script('Tip.call(document.getElementById("'.$id.'"),"'.$tooltip.'");');
	}

	/**
	 * Copy, add or cut files to clipboard
	 *
	 * @param string $action 'copy', 'cut' or 'add'
	 * @param array $selected files
	 */
	public static function ajax_clipboard($action, $selected)
	{
		$response = egw_json_response::get();

		$msg = self::action($action, $selected);

		$response->script('$j(document.getElementById("nm[msg]")).text("'.$msg.'");');	// ->jquery('#nm[msg]','text' does not work!
		$response->script('clipboard_files = '.json_encode($clipboard_files).';');
	}

	/**
	 * Run given action on given path(es) and return array/object with values for keys 'msg', 'errs', 'dirs', 'files'
	 *
	 * @param string $action eg. 'delete', ...
	 * @param string $path
	 * @see self::action()
	 */
	public static function ajax_action($action,$path)
	{
		$response = egw_json_response::get();

		$arr = array(
			'msg' => '',
			'errs' => 0,
			'dirs' => 0,
			'files' => 0,
		);

		$selected = func_get_args();
		$action = array_shift($selected);
		switch ($action)
		{
			case "copy_files":
				$dst = array_pop($selected);
				$src = $selected;
				$copied = array();

				if (egw_vfs::copy_files($src, $dst, $arr['errs'], $copied))
				{
					$arr['msg'] = sprintf(lang("%d files successfully copied."), count($copied));
				}
				else
				{
					$arr['msg'] = sprintf(lang("%d erros while copying, %d files successfully copied."),
						$arr['errs'], count($copied));
				}

				break;

			case "move_files":
				$dst = array_pop($selected);
				$src = $selected;
				$moved = array();

				if (egw_vfs::move_files($src, $dst, $arr['errs'], $moved))
				{
					$arr['msg'] = sprintf(lang("%d files successfully moved."), count($moved));
				}
				else
				{
					$arr['msg'] = sprintf(lang("%d erros while moving, %d files successfully moved."),
						$arr['errs'], count($moved));
				}

				break;

			case "createdir":
				$curdir = $selected[0];
				$newdir = $selected[1];

				$dst = egw_vfs::concat($curdir, $newdir);
				if (egw_vfs::mkdir($dst, null, STREAM_MKDIR_RECURSIVE))
				{
					$arr['msg'] = lang("Directory successfully created.");
				}
				else
				{
					$arr['msg'] = lang("Error while creating directory.");
				}
				break;

			case "symlink":
				$curdir = $selected[0];
				$target = $selected[1];

				$link = egw_vfs::concat($curdir, egw_vfs::basename($target));
				$abs_target = $target[0] == '/' ? $target : egw_vfs::concat($target, $target);
				if (!egw_vfs::stat($abs_target))
				{
					$arr['msg'] = lang('Link target %1 not found!', egw_vfs::decodePath($abs_target));
					break;
				}
				$arr['msg'] = egw_vfs::symlink($target,$link) ?
					lang('Symlink to %1 created.',$target) : lang('Error creating symlink to target %1!',egw_vfs::decodePath($target));
				break;

			default:
				$arr['msg'] = self::action($action,$selected,null,$arr['errs'],$arr['dirs'],$arr['files']);
				break;
		}

		$response->data($arr);
		//error_log(__METHOD__."('$action',".array2string($selected).') returning '.array2string($arr));
	}

	/**
	 * Convert perms array back to integer mode
	 *
	 * @param array $perms with keys owner, group, other, executable, sticky
	 * @return int
	 */
	private function perms2mode(array $perms)
	{
		$mode = $perms['owner'] << 6 | $perms['group'] << 3 | $perms['other'];
		if ($mode['executable'])
		{
			$mode |= 0111;
		}
		if ($mode['sticky'])
		{
			$mode |= 0x201;
		}
		return $mode;
	}
}
