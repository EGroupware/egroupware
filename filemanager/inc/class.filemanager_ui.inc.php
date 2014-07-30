<?php
/**
 * eGroupWare - Filemanager - user interface
 *
 * @link http://www.egroupware.org
 * @package filemanager
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2008-13 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
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
	 * vfs namespace for document merge properties
	 *
	 */
	public static $merge_prop_namespace = '';

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
		self::$merge_prop_namespace = egw_vfs::DEFAULT_PROP_NAMESPACE.$GLOBALS['egw_info']['flags']['currentapp'];
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
				if (is_array($views)) self::$views += $views;
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
	public static function get_view()
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
				'onExecute' => 'javaScript:app.filemanager.open',
				'default' => true
			),
			'saveas' => array(
				'caption' => lang('Save as'),
				'group' => $group,
				'allowOnMultiple' => true,
				'icon' => 'filesave',
				'onExecute' => 'javaScript:app.filemanager.force_download',
				'disableClass' => 'isDir',
				'enabled' => 'javaScript:app.filemanager.is_multiple_allowed'
			),
			'saveaszip' => array(
				'caption' => lang('Save as ZIP'),
				'group' => $group,
				'allowOnMultiple' => true,
				'icon' => 'save_zip',
				'postSubmit' => true
			),
			'edit' => array(
				'caption' => lang('Edit settings'),
				'group' => $group,
				'allowOnMultiple' => false,
				'onExecute' => 'javaScript:app.filemanager.editprefs',
			),
			'mail' => array(
				'caption' => lang('Mail files'),
				'icon' => 'filemanager/mail_post_to',
				'group' => $group,
				'onExecute' => 'javaScript:app.filemanager.mail',
			),
			'copy' => array(
				'caption' => lang('Copy'),
				'group' => ++$group,
				'onExecute' => 'javaScript:app.filemanager.clipboard',
			),
			'add' => array(
				'caption' => lang('Add to clipboard'),
				'group' => $group,
				'icon' => 'copy',
				'onExecute' => 'javaScript:app.filemanager.clipboard',
			),
			'cut' => array(
				'caption' => lang('Cut'),
				'group' => $group,
				'onExecute' => 'javaScript:app.filemanager.clipboard',
			),
			'documents' => filemanager_merge::document_action(
				$GLOBALS['egw_info']['user']['preferences']['filemanager']['document_dir'],
				++$group, 'Insert in document', 'document_',
				$GLOBALS['egw_info']['user']['preferences']['filemanager']['default_document']
			),
			'delete' => array(
				'caption' => lang('Delete'),
				'group' => ++$group,
				'confirm' => 'Delete these files or directories?',
				'onExecute' => 'javaScript:app.filemanager.action',
			),
			// DRAG and DROP events
			'file_drag' => array(
				'dragType' => 'file',
				'type' => 'drag',
				'onExecute' => 'javaScript:app.filemanager.drag'
			),
			'file_drop_move' => array(
				'icon' => 'stylite/move',
				'acceptedTypes' => 'file',
				'caption' => lang('Move into folder'),
				'type' => 'drop',
				'onExecute' => 'javaScript:app.filemanager.drop',
				'default' => true
			),
			'file_drop_copy' => array(
				'icon' => 'stylite/edit_copy',
				'acceptedTypes' => 'file',
				'caption' => lang('Copy into folder'),
				'type' => 'drop',
				'onExecute' => 'javaScript:app.filemanager.drop'
			)
		);
		if (!isset($GLOBALS['egw_info']['user']['apps']['mail']))
		{
			unset($actions['mail']);
		}
		return $actions;
	}

	/**
	 * Get mergeapp property for given path
	 *
	 * @param string $path
	 * @param string $scope (default) or 'parents'
	 *    $scope == 'self' query only the given path
	 *    $scope == 'parents' query only path parents for property (first parent in hierarchy upwards wins)
	 *
	 * @return string merge application or NULL if no property found
	 */
	private static function get_mergeapp($path, $scope='self')
	{
		$app = null;
		switch($scope)
		{
			case 'self':
				$props = egw_vfs::propfind($path, self::$merge_prop_namespace);
				$app = empty($props) ? null : $props[0]['val'];
				break;
			case 'parents':
				// search for props in parent directories
				$currentpath = $path;
				while($dir = egw_vfs::dirname($currentpath))
				{
					$props = egw_vfs::propfind($dir, self::$merge_prop_namespace);
					if(!empty($props))
					{
						// found prop in parent directory
						return $app = $props[0]['val'];
					}
					$currentpath = $dir;
				}
				break;
		}

		return $app;
	}

	/**
	 * Main filemanager page
	 *
	 * @param array $content
	 * @param string $msg
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
					'filter'         => '',	// current dir only
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
					'row_modified'   => 'mtime',
					'parent_id'      => 'dir',
					'is_parent'      => 'mime',
					'is_parent_value'=> egw_vfs::DIR_MIME_TYPE,
					'header_left'    => 'filemanager.index.header_left',
					'favorites'      => true
				);
				$content['nm']['path'] = self::get_home_dir();
			}
			$content['nm']['home_dir'] = self::get_home_dir();

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
				if (!$content['nm']['filter']) $content['nm']['filter'] = '';
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
	 * @param string $user setup config user to become root or '' to log off as root
	 * @param string $password setup config password to become root
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
	 * @param array $content
	 * @param string $msg
	 */
	function listview(array $content=null,$msg=null)
	{
		$tpl = new etemplate_new('filemanager.index');

		if($msg) egw_framework::message($msg);

		if (($content['nm']['action'] || $content['nm']['rows']) && (empty($content['button']) || !isset($content['button'])))
		{
			if ($content['nm']['action'])
			{
				$msg = self::action($content['nm']['action'],$content['nm']['selected'],$content['nm']['path']);
				if($msg) egw_framework::message($msg);

				// clean up after action
				unset($content['nm']['selected']);
				// reset any occasion where action may be stored, as it may be ressurected out of the helpers by etemplate, which is quite unconvenient in case of action delete
				if (isset($content['nm']['action'])) unset($content['nm']['action']);
				if (isset($content['nm']['nm_action'])) unset($content['nm']['nm_action']);
				if (isset($content['nm_action'])) unset($content['nm_action']);
				// we dont use ['nm']['rows']['delete'], so unset it, if it is present
				if (isset($content['nm']['rows']['delete'])) unset($content['nm']['rows']['delete']);
			}
			elseif($content['nm']['rows']['delete'])
			{
				$msg = self::action('delete',array_keys($content['nm']['rows']['delete']),$content['nm']['path']);
				if($msg) egw_framework::message($msg);

				// clean up after action
				unset($content['nm']['rows']['delete']);
				// reset any occasion where action may be stored, as we use ['nm']['rows']['delete'] anyhow
				// we clean this up, as it may be ressurected out of the helpers by etemplate, which is quite unconvenient in case of action delete
				if (isset($content['nm']['action'])) unset($content['nm']['action']);
				if (isset($content['nm']['nm_action'])) unset($content['nm']['nm_action']);
				if (isset($content['nm_action'])) unset($content['nm_action']);
				if (isset($content['nm']['selected'])) unset($content['nm']['selected']);
			}
			unset($content['nm']['rows']);
			egw_session::appsession('index','filemanager',$content['nm']);
		}

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
				case 'upload':
					if (!$content['upload'])
					{
						egw_framework::message(lang('You need to select some files first!'),'error');
						break;
					}
					$upload_success = $upload_failure = array();
					foreach(isset($content['upload'][0]) ? $content['upload'] : array($content['upload']) as $upload)
					{
						// encode chars which special meaning in url/vfs (some like / get removed!)
						$to = egw_vfs::concat($content['nm']['path'],egw_vfs::encodePathComponent($upload['name']));
						if ($upload &&
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
						egw_framework::message( count($upload_success) == 1 && !$upload_failure ? lang('File successful uploaded.') :
							lang('%1 successful uploaded.',implode(', ',$upload_success)));
					}
					if ($upload_failure)
					{
						egw_framework::message(lang('Error uploading file!')."\n".etemplate::max_upload_size_message(),'error');
					}
					break;
			}
		}
		$readonlys['button[mailpaste]'] = !isset($GLOBALS['egw_info']['user']['apps']['mail']);

		$sel_options['filter'] = array(
			'' => 'Current directory',
			'2' => 'Directories sorted in',
			'3' => 'Show hidden files',
			'0'  => 'Files from subdirectories',
		);
		$tpl->setElementAttribute('nm', 'onfiledrop', 'app.filemanager.filedrop');

		$tpl->exec('filemanager.filemanager_ui.index',$content,$sel_options,$readonlys,array('nm' => $content['nm']));
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
	 * @param mixed $dir current directory
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

			case 'copy':
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
				if ($errs)
				{
					return lang('%1 errors moving (%2 files moved)!',$errs,$files);
				}
				return lang('%1 files moved.',$files);

			case 'symlink':	// symlink given files to $dir
				foreach((array)$selected as $target)
				{
					$link = egw_vfs::concat($dir, egw_vfs::basename($target));
					if ($target[0] != '/') $target = egw_vfs::concat($dir, $target);
					if (!egw_vfs::stat($target))
					{
						return lang('Link target %1 not found!', egw_vfs::decodePath($target));
					}
					if ($target != $link && egw_vfs::symlink($target, $link))
					{
						++$files;
					}
					else
					{
						++$errs;
					}
				}
				if (count((array)$selected) == 1)
				{
					return $files ? lang('Symlink to %1 created.', egw_vfs::decodePath($target)) :
						lang('Error creating symlink to target %1!', egw_vfs::decodePath($target));
				}
				$ret = lang('%1 elements linked.', $files);
				if ($errs)
				{
					$ret = lang('%1 errors linking (%2)!',$errs, $ret);
				}
				return $ret;//." egw_vfs::symlink('$target', '$link')";

			case 'createdir':
				$dst = egw_vfs::concat($dir, is_array($selected) ? $selected[0] : $selected);
				if (egw_vfs::mkdir($dst, null, STREAM_MKDIR_RECURSIVE))
				{
					return lang("Directory successfully created.");
				}
				return lang("Error while creating directory.");

			case 'saveaszip':
				egw_vfs::download_zip($selected);
				common::egw_exit();
			
			default:
				list($action, $settings) = explode('_', $action, 2);
				switch($action)
				{
					case 'document':
						if (!$settings) $settings = $GLOBALS['egw_info']['user']['preferences']['filemanager']['default_document'];
						$document_merge = new filemanager_merge(egw_vfs::decodePath($dir));
						$msg = $document_merge->download($settings, $selected, '', $GLOBALS['egw_info']['user']['preferences']['filemanager']['document_dir']);
						if($msg) return $msg;
						$failed = count($selected);
						return false;
				}
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
		// do NOT store query, if hierarchical data / children are requested
		if (!$query['csv_export'])
		{
			egw_session::appsession('index','filemanager',$query);
		}
		if(!$query['path']) $query['path'] = self::get_home_dir();

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
				'ajax' => 'true'
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

		// Re-map so 'No filters' favorite ('') is depth 1
		$filter = $query['filter'] === '' ? 1 : $query['filter'];
		foreach(egw_vfs::find(!empty($query['col_filter']['dir']) ? $query['col_filter']['dir'] : $query['path'],array(
			'mindepth' => 1,
			'maxdepth' => $filter ? (int)(boolean)$filter : null,
			'dirsontop' => $filter <= 1,
			'type' => $filter ? null : 'f',
			'order' => $query['order'], 'sort' => $query['sort'],
			'limit' => (int)$query['num_rows'].','.(int)$query['start'],
			'need_mime' => true,
			'name_preg' => $namefilter,
			'hidden' => $filter == 3,
		),true) as $path => $row)
		{
			//echo $path; _debug_array($row);

			$dir = dirname($path);
			if (!isset($dir_is_writable[$dir]))
			{
				$dir_is_writable[$dir] = egw_vfs::is_writable($dir);
			}
			if (egw_vfs::is_dir($path))
			{
				$row['class'] = 'isDir';
			}
			$row['download_url'] = egw_vfs::download_url($path);

			$rows[++$n] = $row;
			$path2n[$path] = $n;
		}
		// query comments and cf's for the displayed rows
		$cols_to_show = explode(',',$GLOBALS['egw_info']['user']['preferences']['filemanager']['nextmatch-filemanager.index.rows']);
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
		// tell client-side if directory is writeable or not
		$response = egw_json_response::get();
		$response->call('app.filemanager.set_readonly', $query['path'], !egw_vfs::is_writable($query['path']));

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
	 * @param array $content
	 * @param string $msg
	 */
	function file(array $content=null,$msg='')
	{
		$tpl = new etemplate_new('filemanager.file');

		if (!is_array($content))
		{
			if (isset($_GET['msg']))
			{
				$msg .= $_GET['msg'];
			}
			if (!($path = str_replace(array('#','?'),array('%23','%3F'),$_GET['path'])) ||	// ?, # need to stay encoded!
				// actions enclose pathes containing comma with "
				($path[0] == '"' && substr($path,-1) == '"' && !($path = substr(str_replace('""','"',$path),1,-1))) ||
				!($stat = egw_vfs::lstat($path)))
			{
				$msg .= lang('File or directory not found!')." path='$path', stat=".array2string($stat);
			}
			else
			{
				$content = $stat;
				$content['name'] = $content['itempicker_merge']['name'] = egw_vfs::basename($path);
				$content['dir'] = $content['itempicker_merge']['dir'] = egw_vfs::decodePath(egw_vfs::dirname($path));
				$content['path'] = $path;
				$content['hsize'] = egw_vfs::hsize($stat['size']);
				$content['mime'] = egw_vfs::mime_content_type($path);
				$content['gid'] *= -1;	// our widgets use negative gid's
				if (($props = egw_vfs::propfind($path)))
				{
					foreach($props as $prop)
					{
						$content[$prop['name']] = $prop['val'];
					}
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
			if(!$button && $content['sudo'])
			{
				// Button to stop sudo is not in button namespace
				$button = 'sudo';
				unset($content['sudo']);
			}
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
							$to = egw_vfs::concat(egw_vfs::dirname($path),$content['name']);
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
						elseif ($name == 'mergeapp')
						{
							$mergeprop = array(
								array(
									'ns'	=> self::$merge_prop_namespace,
									'name'	=> 'mergeapp',
									'val'	=> (!empty($content[$name]) ? $content[$name] : null),
								),
							);
							if (egw_vfs::proppatch($path,$mergeprop))
							{
								$content['old'][$name] = $content[$name];
								$msg .= lang('Setting for document merge saved.');
							}
							else
							{
								$msg .= lang('Saving setting for document merge failed!');
							}
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
								foreach($changed as &$r)
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
					if (!$content['eacl_owner'])
					{
						$msg .= lang('You need to select an owner!');
					}
					else
					{
						$msg .= egw_vfs::eacl($path,$content['eacl']['rights'],$content['eacl_owner']) ?
							lang('ACL added.') : lang('Error adding the ACL!');
					}
				}
			}
			egw_framework::refresh_opener($msg, 'filemanager', $refresh_path ? $refresh_path : $path, 'edit', null, '&path=[^&]*');
			if ($button == 'save') egw_framework::window_close();
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
		$readonlys['name'] = $path == '/' || !egw_vfs::is_writable(egw_vfs::dirname($path));
		$readonlys['comment'] = !egw_vfs::is_writable($path);
		$readonlys['tabs']['filemanager.file.preview'] = $readonlys['tabs']['filemanager.file.perms'] = $content['is_link'];

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
		$readonlys['tabs']['filemanager.file.eacl'] = true;	// eacl off by default
		if ($content['is_dir'])
		{
			$readonlys['tabs']['filemanager.file.preview'] = true;	// no preview tab for dirs
			$sel_options['rights']=$sel_options['owner']=$sel_options['group']=$sel_options['other'] = array(
				7 => lang('Display and modification of content'),
				5 => lang('Display of content'),
				0 => lang('No access'),
			);
			if(($content['eacl'] = egw_vfs::get_eacl($content['path'])) !== false)	// backend supports eacl
			{
				unset($readonlys['tabs']['filemanager.file.eacl']);	// --> switch the tab on again
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

		// mergeapp
		$content['mergeapp'] = self::get_mergeapp($path, 'self');
		$content['mergeapp_parent'] = self::get_mergeapp($path, 'parents');
		if(!empty($content['mergeapp']))
		{
			$content['mergeapp_effective'] = $content['mergeapp'];
		}
		elseif(!empty($content['mergeapp_parent']))
		{
			$content['mergeapp_effective'] = $content['mergeapp_parent'];
		}
		else
		{
			$content['mergeapp_effective'] = null;
		}
		// mergeapp select options
		$mergeapp_list = egw_link::app_list('merge');
		unset($mergeapp_list[$GLOBALS['egw_info']['flags']['currentapp']]); // exclude filemanager from list
		$mergeapp_empty = !empty($content['mergeapp_parent'])
			? $mergeapp_list[$content['mergeapp_parent']] . ' (parent setting)' : '';
		$sel_options['mergeapp'] = array(''	=> $mergeapp_empty);
		$sel_options['mergeapp'] = $sel_options['mergeapp'] + $mergeapp_list;
		// mergeapp other gui options
		$content['mergeapp_itempicker_disabled'] = $content['is_dir'] || empty($content['mergeapp_effective']);

		$preserve = $content;
		if (!isset($preserve['old']))
		{
			$preserve['old'] = array(
				'perms' => $content['perms'],
				'name'  => $content['name'],
				'uid'   => $content['uid'],
				'gid'   => $content['gid'],
				'comment' => (string)$content['comment'],
				'mergeapp' => $content['mergeapp']
			);
			if ($cfs) foreach($cfs as $name => $data)
			{
				$preserve['old']['#'.$name] = (string)$content['#'.$name];
			}
		}
		if (egw_vfs::$is_root)
		{
			$tpl->setElementAttribute('sudo', 'label', 'Logout');
			$tpl->setElementAttribute('sudo', 'help','Log out as superuser');
			// Need a more complex submit because button type is buttononly, which doesn't submit
			$tpl->setElementAttribute('sudo', 'onclick','widget.getInstanceManager().submit(widget)');

		}
		if (($extra_tabs = egw_vfs::getExtraInfo($path,$content)))
		{
			// add to existing tabs in template
			$tpl->setElementAttribute('tabs', 'add_tabs', true);

			$tabs =& $tpl->getElementAttribute('tabs','tabs');
			$tabs = array();

			foreach(isset($extra_tabs[0]) ? $extra_tabs : array($extra_tabs) as $extra_tab)
			{
				$tabs[] = array(
					'label' =>	$extra_tab['label'],
					'template' =>	$extra_tab['name']
				);
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
		egw_framework::window_focus();
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Preferences').' '.egw_vfs::decodePath($path);

		$tpl->exec('filemanager.filemanager_ui.file',$content,$sel_options,$readonlys,$preserve,2);
	}

	/**
	 * Run given action on given path(es) and return array/object with values for keys 'msg', 'errs', 'dirs', 'files'
	 *
	 * @param string $action eg. 'delete', ...
	 * @param array $selected selected path(s)
	 * @param string $dir=null current directory
	 * @see self::action()
	 */
	public static function ajax_action($action, $selected, $dir=null, $props=null)
	{
		$response = egw_json_response::get();

		$arr = array(
			'msg' => '',
			'action' => $action,
			'errs' => 0,
			'dirs' => 0,
			'files' => 0,
		);

		if (!isset($dir)) $dir = array_pop($selected);

		switch($action)
		{
			case 'upload':
				$script_error = 0;
				foreach($selected as $tmp_name => &$data)
				{
					$path = egw_vfs::concat($dir, egw_vfs::encodePathComponent($data['name']));

					if(egw_vfs::deny_script($path))
					{
						if (!isset($script_error))
						{
							$arr['msg'] .= ($arr['msg'] ? "\n" : '').lang('You are NOT allowed to upload a script!');
						}
						++$script_error;
						++$arr['errs'];
						unset($selected[$tmp_name]);
					}
					elseif (egw_vfs::is_dir($path))
					{
						$data['confirm'] = 'is_dir';
					}
					elseif (!$data['confirmed'] && egw_vfs::stat($path))
					{
						$data['confirm'] = true;
					}
					else
					{
						if (is_dir($GLOBALS['egw_info']['server']['temp_dir']) && is_writable($GLOBALS['egw_info']['server']['temp_dir']))
						{
							$tmp_path = $GLOBALS['egw_info']['server']['temp_dir'] . '/' . basename($tmp_name);
						}
						else
						{
							$tmp_path = ini_get('upload_tmp_dir').'/'.basename($tmp_name);
						}

						if (egw_vfs::copy_uploaded($tmp_path, $path, $props, false))
						{
							++$arr['files'];
							$uploaded[] = $data['name'];
						}
						else
						{
							++$arr['errs'];
						}
					}
				}
				if ($arr['errs'] > $script_error)
				{
					$arr['msg'] .= ($arr['msg'] ? "\n" : '').lang('Error uploading file!');
				}
				if ($arr['files'])
				{
					$arr['msg'] .= ($arr['msg'] ? "\n" : '').lang('%1 successful uploaded.', implode(', ', $uploaded));
				}
				$arr['uploaded'] = $selected;
				$arr['path'] = $dir;
				$arr['props'] = $props;
				break;

			// Upload, then link
			case 'link':
				// First upload
				$arr = self::ajax_action('upload', $selected, $dir, $props);
				$app_dir = egw_link::vfs_path($props['entry']['app'],$props['entry']['id'],'',true);

				foreach($arr['uploaded'] as $file)
				{
					$target=egw_vfs::concat($dir,egw_vfs::encodePathComponent($file['name']));
					if (egw_vfs::file_exists($target) && $app_dir)
					{
						if (!egw_vfs::file_exists($app_dir)) egw_vfs::mkdir($app_dir);
						error_log("Symlinking $target to $app_dir");
						egw_vfs::symlink($target, egw_vfs::concat($app_dir,egw_vfs::encodePathComponent($file['name'])));
					}
				}
				// Must return to avoid adding to $response again
				return;

			default:
				$arr['msg'] = self::action($action, $selected, $dir, $arr['errs'], $arr['dirs'], $arr['files']);
		}
		$response->data($arr);
		//error_log(__METHOD__."('$action',".array2string($selected).') returning '.array2string($arr));
		return $arr;
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
