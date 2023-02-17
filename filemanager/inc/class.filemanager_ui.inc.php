<?php
/**
 * EGroupware - Filemanager - user interface
 *
 * @link http://www.egroupware.org
 * @package filemanager
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2008-17 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Egw;
use EGroupware\Api\Etemplate;
use EGroupware\Api\Framework;
use EGroupware\Api\Link;
use EGroupware\Api\Vfs;

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
		'editor' => true
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
	protected $etemplate;
	const LIST_TEMPLATE = 'filemanager.index';

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
		// do we have root rights
		if (Api\Cache::getSession('filemanager', 'is_root'))
		{
			Vfs::$is_root = true;
		}

		static::init_views();
		static::$merge_prop_namespace = Vfs::DEFAULT_PROP_NAMESPACE.$GLOBALS['egw_info']['flags']['currentapp'];
	}

	/**
	 * Initialise and return available views
	 *
	 * @return array with method => label pairs
	 */
	public static function init_views()
	{
		if (!static::$views_init)
		{
			// translate our labels
			foreach(static::$views as &$label)
			{
				$label = lang($label);
			}
			// search for plugins with additional filemanager views
			foreach(Api\Hooks::process('filemanager_views') as $views)
			{
				if (is_array($views)) static::$views += $views;
			}
			static::$views_init = true;
		}
		return static::$views;
	}

	/**
	 * Get active view
	 *
	 * @return string
	 */
	public static function get_view()
	{
		$view =& Api\Cache::getSession('filemanager', 'view');
		if (isset($_GET['view']))
		{
			$view = $_GET['view'];
		}
		if (!isset(static::$views[$view]))
		{
			reset(static::$views);
			$view = key(static::$views);
		}
		return $view;
	}

	/**
	 * Method to build select options out of actions
	 * @param type $actions
	 * @return type
	 */
	public static function convertActionsToselOptions ($actions)
	{
		$sel_options = array ();
		foreach ($actions as $action => $value)
		{
			$sel_options[] = array(
				'value' => $action,
				'label' => $value['caption'],
				'icon'  => $value['icon']
			);
		}
		return $sel_options;
	}

	/**
	 * Context menu
	 *
	 * @return array
	 */
	public static function get_actions()
	{
		$actions = array(
			'open'      => array(
				'caption'         => lang('Open'),
				'icon'            => '',
				'group'           => $group = 1,
				'allowOnMultiple' => false,
				'onExecute'       => 'javaScript:app.filemanager.open',
				'default'         => true
			),
			'new'       => array(
				'caption'      => 'New',
				'group'        => $group,
				'disableClass' => 'noEdit',
				'children'     => array(
					'document' => array(
						'caption'   => 'Document',
						'icon'      => 'new',
						'onExecute' => 'javaScript:app.filemanager.create_new',
					)
				)
			),
			'mkdir'     => array(
				'caption'         => lang('Create directory'),
				'icon'            => 'filemanager/button_createdir',
				'group'           => $group,
				'allowOnMultiple' => false,
				'disableClass'    => 'noEdit',
				'onExecute'       => 'javaScript:app.filemanager.createdir'
			),
			'edit'      => array(
				'caption'            => lang('Edit settings'),
				'group'              => $group,
				'allowOnMultiple'    => false,
				'onExecute'          => Api\Header\UserAgent::mobile() ? 'javaScript:app.filemanager.viewEntry' : 'javaScript:app.filemanager.editprefs',
				'mobileViewTemplate' => 'file?' . filemtime(Api\Etemplate\Widget\Template::rel2path('/filemanager/templates/mobile/file.xet'))
			),
			'unlock'    => array(
				'caption'         => lang('Unlock'),
				'icon'            => 'unlock',
				'enableClass'     => 'locked',
				'group'           => $group,
				'allowOnMultiple' => true,
				'hideOnDisabled'  => true
			),
			'saveas'    => array(
				'caption'         => lang('Save as'),
				'group'           => $group,
				'allowOnMultiple' => true,
				'icon'            => 'filesave',
				'onExecute'       => 'javaScript:app.filemanager.force_download',
				'disableClass'    => 'isDir',
				'enabled'         => 'javaScript:app.filemanager.is_multiple_allowed',
				'shortcut'        => array('ctrl'    => true, 'shift' => true, 'keyCode' => 83,
										   'caption' => 'Ctrl + Shift + S'),
			),
			'saveaszip' => array(
				'caption'         => lang('Save as ZIP'),
				'group'           => $group,
				'allowOnMultiple' => true,
				'icon'            => 'save_zip',
				'postSubmit'      => true,
				'shortcut'        => array('ctrl'    => true, 'shift' => true, 'keyCode' => 90,
										   'caption' => 'Ctrl + Shift + Z'),
			),
			'egw_paste' => array(
				'enabled' => false,
				'group' => $group + 0.5,
				'hideOnDisabled' => true
			),
			'paste' => array(
				'caption' => lang('Paste'),
				'acceptedTypes' => 'file',
				'group' => $group + 0.5,
				'order' => 10,
				'enabled' => 'javaScript:app.filemanager.paste_enabled',
				'children' => array()
			),
			'copylink' => array(
				'caption' => lang('Copy link address'),
				'group' => $group + 0.5,
				'icon' => 'copy',
				'allowOnMultiple' => false,
				'order' => 10,
				'onExecute' => 'javaScript:app.filemanager.copy_link'
			),
			'share' => EGroupware\Api\Vfs\HiddenUploadSharing::get_actions('filemanager', ++$group)['share'],
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
				'disableClass' => 'noDelete'
			),
			// DRAG and DROP events
			'file_drag' => array(
				'dragType' => array('file','link'),
				'type' => 'drag'
			),
			'file_drop_mail' => array(
				'type' => 'drop',
				'acceptedTypes' => 'mail',
				'onExecute' => 'javaScript:app.filemanager.drop',
				'hideOnDisabled' => true
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
				'icon' => 'stylite/copy',
				'acceptedTypes' => 'file',
				'caption' => lang('Copy into folder'),
				'type' => 'drop',
				'onExecute' => 'javaScript:app.filemanager.drop'
			),
			'file_drop_symlink' => array(
				'icon' => 'linkpaste',
				'acceptedTypes' => 'file',
				'caption' => lang('Link into folder'),
				'type' => 'drop',
				'onExecute' => 'javaScript:app.filemanager.drop'
			)
		);

		// This one makes no sense in filemanager
		unset($actions['share']['children']['shareFilemanager']);
		if (isset($GLOBALS['egw_info']['user']['apps']['mail'])) {
			$actions['share']['children']['share_mail'] = array(
				'caption' => lang('Mail'),
				'icon' => 'mail',
				'group' => 1,
				'order' => 0,
				'allowOnMultiple' => true,
			);
			foreach(Vfs\Sharing::$modes as $mode => $data)
			{
				$actions['share']['children']['share_mail']['children']['mail_'.$mode] = array(
					'caption' => $data['label'],
					'hint' => $data['title'],
					'icon' => $mode == Vfs\Sharing::ATTACH ?
						'mail/attach' : 'api/link',
					'group' => 2,
					'onExecute' => 'javaScript:app.filemanager.mail',
				);
				if ($mode == Vfs\Sharing::ATTACH || $mode == Vfs\Sharing::LINK)
				{
					$actions['share']['children']['share_mail']['children']['mail_'.$mode]['disableClass'] = 'isDir';
				}
			}
			foreach(Vfs\HiddenUploadSharing::$modes as $mode => $data)
			{
				$actions['share']['children']['share_mail']['children']['mail_shareUploadDir'] = array(
					'caption' => $data['label'],
					'hint' => $data['title'],
					'icon' => 'api/link',
					'group' => 3,
					'data' => ['share_writable' => $mode],
					'enabled' => 'javaScript:app.filemanager.hidden_upload_enabled',
					'onExecute' => 'javaScript:app.filemanager.mail_share_link',
				);
			}
		}

		// This would be done automatically, but we're overriding
		foreach($actions as $action_id => $action)
		{
			if($action['type'] == 'drop' && $action['caption'])
			{
				$action['type'] = 'popup';
				if($action['acceptedTypes'] == 'file')
				{
					$action['enabled'] = 'javaScript:app.filemanager.paste_enabled';
				}
				$actions['paste']['children']["{$action_id}_paste"] = $action;
			}
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
				$props = Vfs::propfind($path, static::$merge_prop_namespace);
				$app = empty($props) ? null : $props[0]['val'];
				break;
			case 'parents':
				// search for props in parent directories
				$currentpath = $path;
				while($dir = Vfs::dirname($currentpath))
				{
					$props = Vfs::propfind($dir, static::$merge_prop_namespace);
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
				'nm' => Api\Cache::getSession('filemanager', 'index'),
			);
			if (!is_array($content['nm']))
			{
				$content['nm'] = array(
					'get_rows'     => 'filemanager.filemanager_ui.get_rows',
					// I  method/callback to request the data for the rows eg. 'notes.bo.get_rows'
					'filter'       => '',    // current dir only
					'no_filter2'   => True,    // I  disable the 2. filter (params are the same as for filter)
					'no_cat'       => True,    // I  disable the cat-selectbox
					'lettersearch' => True,    // I  show a lettersearch
					'searchletter' => false,    // I0 active letter of the lettersearch or false for [all]
					'start'        => 0,        // IO position in list
					'order'        => 'mtime',    // IO name of the column to sort after (optional for the sortheaders)
					'sort'         => 'DESC',    // IO direction of the sort: 'ASC' or 'DESC'
					'default_cols' => '!comment,ctime',
					// I  columns to use if there's no user or default pref (! as first char uses all but the named columns), default all columns
					'csv_fields'   => false,
					// I  false=disable csv export, true or unset=enable it with auto-detected fieldnames,
					//or array with name=>label or name=>array('label'=>label,'type'=>type) pairs (type is a eT widget-type)
					'row_id'       => 'path',
					'row_modified' => 'mtime',
					'parent_id'    => 'dir',
					'is_parent'    => 'is_dir',
					'favorites'    => true
				);
				$content['nm']['path'] = static::get_home_dir();
			}
			$content['nm']['actions'] = static::get_actions();
			$content['nm']['home_dir'] = static::get_home_dir();
			$content['nm']['view'] = $GLOBALS['egw_info']['user']['preferences']['filemanager']['nm_view'];
			$content['nm']['placeholder_actions'] = array('mkdir','paste','share','file_drop_mail','file_drop_move','file_drop_copy','file_drop_symlink');

			if (isset($_GET['msg'])) $msg = $_GET['msg'];

			// Blank favorite set via GET needs special handling for path
			if (isset($_GET['favorite']) && $_GET['favorite'] == 'blank')
			{
				$content['nm']['path'] = static::get_home_dir();
			}
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
						$path = Vfs::dirname($content['nm']['path']);
						break;
					case '~':
						$path = static::get_home_dir();
						break;
				}
				if ($path && $path[0] == '/' && Vfs::stat($path,true) && Vfs::is_dir($path) && Vfs::check_access($path,Vfs::READABLE))
				{
					$content['nm']['path'] = $path;
				}
				else
				{
					$msg .= lang('The requested path %1 is not available.', $path ? Vfs::decodePath($path) : "false");
				}
				// reset lettersearch as it confuses users (they think the dir is empty)
				$content['nm']['searchletter'] = false;
				// switch recusive display off
				if (!$content['nm']['filter']) $content['nm']['filter'] = '';
			}
		}
		$view = static::get_view();

		call_user_func($view,$content,$msg);
	}

	/**
	 * Make the current user (vfs) root
	 *
	 * The user/pw is either the "root_" prefixed setup config user or a specially configured vfs_root user
	 *
	 * @param string $user setup config user to become root or '' to log off as root
	 * @param string $password setup config password to become root
	 * @param string &$msg on return error or success message
	 * @param bool $allow_setup =false true: allow "root_" prefixed setup-config user/pw
	 * @param boolean &$is_setup=null on return true if authenticated user is setup config user, false otherwise
	 * @return boolean true is root user given, false otherwise (including logout / empty $user)
	 */
	protected function sudo($user='', $password='', string &$msg=null, bool $allow_setup=false, &$is_setup=null)
	{
		$is_root = $is_setup = false;
		$msg = null;

		if (!$user)
		{
			$msg = lang('Root access stopped.');
		}
		// config user & password
		elseif ($allow_setup && substr($user, 0, 5) === 'root_')
		{
			if (!($msg = setup::checkip()))
			{
				$is_root = $is_setup = setup::check_auth(substr($user, 5), $password, $GLOBALS['egw_info']['server']['header_admin_user'],
					$GLOBALS['egw_info']['server']['header_admin_password']);
			}
		}
		// or vfs root user from setup >> configuration
		else
		{
			$is_root = $GLOBALS['egw_info']['server']['vfs_root_user'] &&
				in_array($user,preg_split('/, */',$GLOBALS['egw_info']['server']['vfs_root_user'])) &&
				$GLOBALS['egw']->auth->authenticate($user, $password, 'text');
		}
		if (empty($msg))
		{
			$msg = $is_root ? lang('Root access granted.') : lang('Wrong username or password!');
		}
		//error_log(__METHOD__."('$user', '$password', $is_setup, '$msg') --> returning ".array2string($is_root));
		Api\Cache::setSession('filemanager', 'is_setup',$is_setup);
		Api\Cache::setSession('filemanager', 'is_root',Vfs::$is_root = $is_root);

		return Vfs::$is_root;
	}

	/**
	 * Filemanager listview
	 *
	 * @param array $content
	 * @param string $msg
	 */
	function listview(array $content=null,$msg=null)
	{
		$tpl = $this->etemplate ? $this->etemplate : new Etemplate(static::LIST_TEMPLATE);

		if ($msg)
		{
			Framework::message($msg);
		}

		if (($content['nm']['action'] || $content['nm']['rows']) && (empty($content['button']) || !isset($content['button'])))
		{
			if ($content['nm']['action'])
			{
				$msg = static::action($content['nm']['action'], $content['nm']['selected'], $content['nm']['path']);
				if ($msg)
				{
					Framework::message($msg);
				}

				// clean up after action
				unset($content['nm']['selected']);
				// reset any occasion where action may be stored, as it may be ressurected out of the helpers by etemplate, which is quite unconvenient in case of action delete
				if (isset($content['nm']['action']))
				{
					unset($content['nm']['action']);
				}
				if (isset($content['nm']['nm_action']))
				{
					unset($content['nm']['nm_action']);
				}
				if (isset($content['nm_action']))
				{
					unset($content['nm_action']);
				}
				// we dont use ['nm']['rows']['delete'], so unset it, if it is present
				if (isset($content['nm']['rows']['delete']))
				{
					unset($content['nm']['rows']['delete']);
				}
			}
			elseif ($content['nm']['rows']['delete'])
			{
				$msg = static::action('delete', array_keys($content['nm']['rows']['delete']), $content['nm']['path']);
				if ($msg)
				{
					Framework::message($msg);
				}

				// clean up after action
				unset($content['nm']['rows']['delete']);
				// reset any occasion where action may be stored, as we use ['nm']['rows']['delete'] anyhow
				// we clean this up, as it may be ressurected out of the helpers by etemplate, which is quite unconvenient in case of action delete
				if (isset($content['nm']['action']))
				{
					unset($content['nm']['action']);
				}
				if (isset($content['nm']['nm_action']))
				{
					unset($content['nm']['nm_action']);
				}
				if (isset($content['nm_action']))
				{
					unset($content['nm_action']);
				}
				if (isset($content['nm']['selected']))
				{
					unset($content['nm']['selected']);
				}
			}
			unset($content['nm']['rows']);
			Api\Cache::setSession('filemanager', 'index', $content['nm']);
		}

		// be tolerant with (in previous versions) not correct urlencoded pathes
		if ($content['nm']['path'][0] == '/' && !Vfs::stat($content['nm']['path'], true) && Vfs::stat(urldecode($content['nm']['path'])))
		{
			$content['nm']['path'] = urldecode($content['nm']['path']);
		}
		if (!empty($content['button']))
		{
			$button = key($content['button']);
			unset($content['button']);

			switch ($button)
			{
				case 'upload':
					if (!$content['upload'])
					{
						Framework::message(lang('You need to select some files first!'), 'error');
						break;
					}
					$upload_success = $upload_failure = array();
					foreach (isset($content['upload'][0]) ? $content['upload'] : array($content['upload']) as $upload)
					{
						// encode chars which special meaning in url/vfs (some like / get removed!)
						$to = Vfs::concat($content['nm']['path'], Vfs::encodePathComponent($upload['name']));
						if ($upload &&
								(Vfs::is_writable($content['nm']['path']) || Vfs::is_writable($to)) &&
								copy($upload['tmp_name'], Vfs::PREFIX . $to))
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
						Framework::message(count($upload_success) == 1 && !$upload_failure ? lang('File successful uploaded.') :
								lang('%1 successful uploaded.', implode(', ', $upload_success)));
					}
					if ($upload_failure)
					{
						Framework::message(lang('Error uploading file!') . "\n" . etemplate::max_upload_size_message(), 'error');
					}
					break;
			}
		}
		$readonlys['button[mailpaste]'] = !isset($GLOBALS['egw_info']['user']['apps']['mail']);

		$sel_options['filter'] = array(
				'' => 'Current directory',
				'2' => 'Directories sorted in',
				'3' => 'Show hidden files',
				'4' => 'All subdirectories',
				'5' => 'Files from links',
				'0' => 'Files from subdirectories',
		);

		$sel_options['new'] = self::convertActionsToselOptions($content['nm']['actions']['new']['children']);

		// sharing has no divAppbox, we need to set popupMainDiv instead, to be able to drop files everywhere
		if (substr($_SERVER['SCRIPT_FILENAME'], -10) == '/share.php')
		{
			$tpl->setElementAttribute('nm[upload]', 'drop_target', 'popupMainDiv');
		}
		// Set view button to match current settings
		if ($content['nm']['view'] == 'tile')
		{
			$tpl->setElementAttribute('nm[button][change_view]', 'statustext', lang('List view'));
			$tpl->setElementAttribute('nm[button][change_view]', 'image', 'list_row');
		}
		// if initial load is done via GET request (idots template or share.php)
		// get_rows cant call app.filemanager.set_readonly, so we need to do that here
		if (!array_key_exists('initial_path_readonly', $content))
		{
			$content['initial_path_readonly'] = !Vfs::is_writable($content['nm']['path']);
		}

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
			$path[0] == '/' && Vfs::is_dir($path) && Vfs::check_access($path, Vfs::READABLE))
		{
			$start = $path;
		}
		elseif (!Vfs::is_dir($start) && Vfs::check_access($start, Vfs::READABLE))
		{
			$start = '/';
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
	static public function action($action,$selected,$dir=null,&$errs=null,&$files=null,&$dirs=null)
	{
		if (!count((array)$selected))
		{
			return lang('You need to select some files first!');
		}
		$errs = $dirs = $files = 0;

		switch($action)
		{

			case 'delete':
				return static::do_delete($selected,$errs,$files,$dirs);

			case 'mail':
			case 'copy':
				foreach($selected as $path)
				{
					if (strpos($path, 'mail::') === 0 && $path = substr($path, 6))
					{
						// Support for dropping mail in filemanager - Pass mail back to mail app
						if(ExecMethod2('mail.mail_ui.vfsSaveMessages', $path, $dir))
						{
							++$files;
						}
						else
						{
							++$errs;
						}
					}
					elseif (!Vfs::is_dir($path))
					{
						$to = Vfs::concat($dir,Vfs::basename($path));
						if ($path != $to && Vfs::copy($path,$to))
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
						foreach(Vfs::find($path) as $p)
						{
							$to = $dir.substr($p,$len);
							if ($to == $p)	// cant copy into itself!
							{
								++$errs;
								continue;
							}
							if (($is_dir = Vfs::is_dir($p)) && Vfs::mkdir($to,null,STREAM_MKDIR_RECURSIVE))
							{
								++$dirs;
							}
							elseif(!$is_dir && Vfs::copy($p,$to))
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
					$to = Vfs::is_dir($dir) || count($selected) > 1 ? Vfs::concat($dir,Vfs::basename($path)) : $dir;
					if ($path != $to && Vfs::rename($path,$to))
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
					$link = Vfs::concat($dir, Vfs::basename($target));
					if (!Vfs::stat($dir) || ($ok = Vfs::mkdir($dir,0,true)))
					{
						if(!$ok)
						{
							$errs++;
							continue;
						}
					}
					if ($target[0] != '/') $target = Vfs::concat($dir, $target);
					if (!Vfs::stat($target))
					{
						return lang('Link target %1 not found!', Vfs::decodePath($target));
					}
					if ($target != $link && Vfs::symlink($target, $link))
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
					return $files ? lang('Symlink to %1 created.', Vfs::decodePath($target)) :
						lang('Error creating symlink to target %1!', Vfs::decodePath($target));
				}
				$ret = lang('%1 elements linked.', $files);
				if ($errs)
				{
					$ret = lang('%1 errors linking (%2)!',$errs, $ret);
				}
				return $ret;//." Vfs::symlink('$target', '$link')";

			case 'createdir':
				$dst = Vfs::concat($dir, is_array($selected) ? $selected[0] : $selected);
				if(Vfs::mkdir($dst, null, STREAM_MKDIR_RECURSIVE))
				{
					return lang("Directory successfully created.");
				}
				return lang("Error while creating directory.");

			case 'saveaszip':
				Vfs::download_zip($selected);
				exit;
			case 'unlock':
				foreach((array)$selected as $target)
				{
					$link = Vfs::concat($dir, Vfs::basename($target));
					$lock = Vfs::checkLock($link);
					if($lock && Vfs::unlock($link, $lock['token']))
					{
						$files++;
					}
					else
					{
						$errs++;
					}
				}
				return lang('%1 files unlocked.', $files);
			default:
				list($action, $settings) = explode('_', $action, 2);
				switch($action)
				{
					case 'document':
						if(!$settings)
						{
							$settings = $GLOBALS['egw_info']['user']['preferences']['filemanager']['default_document'];
						}
						$document_merge = new filemanager_merge(Vfs::decodePath($dir));
						$msg = $document_merge->download($settings, $selected, '', $GLOBALS['egw_info']['user']['preferences']['filemanager']['document_dir']);
						if($msg)
						{
							return $msg;
						}
						$errs = count($selected);
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
		// feeding the links to dirs to Vfs::find() deletes the content of the dirs, not just the link!
		foreach($selected as $key => $path)
		{
			if (Vfs::is_dir($path) && $GLOBALS['egw_info']['user']['preferences']['common']['vfs_fstab'][$path])
			{
				// Trying to delete a share.  Unmount.
				$root = Vfs::$is_root;
				Vfs::$is_root = true;
				Vfs::umount($path);
				Vfs::$is_root = $root;
				++$dirs;
				unset($selected[$key]);
			}
			else if (!Vfs::is_dir($path) || Vfs::is_link($path))
			{
				if (Vfs::unlink($path))
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
				if (Vfs::isProtectedDir($path))
				{
					$errs++;
					return lang("Cautiously rejecting to remove folder '%1'!",Vfs::decodePath($path));
				}
			}
			// now we use find to loop through all files and dirs: (selected only contains dirs now)
			// - depth=true to get first the files and then the dir containing it
			// - hidden=true to also return hidden files (eg. Thumbs.db), as we cant delete non-empty dirs
			// - show-deleted=false to not (finally) deleted versioned files
			foreach(Vfs::find($selected,array('depth'=>true,'hidden'=>true,'show-deleted'=>false)) as $path)
			{
				if (($is_dir = Vfs::is_dir($path) && !Vfs::is_link($path)) && Vfs::rmdir($path,0))
				{
					++$dirs;
				}
				elseif (!$is_dir && Vfs::unlink($path))
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
	 */
	function get_rows(&$query, &$rows)
	{
		$old_session = Api\Cache::getSession('filemanager','index');

		// do NOT store query, if hierarchical data / children are requested
		if (!$query['csv_export'])
		{
			$store_query = array_diff_key ($query, array_flip(array('rows','actions','action_links','placeholder_actions')));
			// Don't store mime filter from expose, just in case user reloads and the UI can't remove it
			unset($store_query['col_filter']['mime']);
			Api\Cache::setSession('filemanager', 'index', $store_query);
		}
		if(!$query['path']) $query['path'] = static::get_home_dir();

		// Change template to match selected view
		if($query['view'])
		{
			$query['template'] = ($query['view'] == 'row' ? 'filemanager.index.rows' : 'filemanager.tile');

			// Store as preference but only for index, not home
			if($query['get_rows'] == 'filemanager.filemanager_ui.get_rows')
			{
				$GLOBALS['egw']->preferences->add('filemanager','nm_view',$query['view']);
				$GLOBALS['egw']->preferences->save_repository();
			}
		}
		// be tolerant with (in previous versions) not correct urlencoded pathes
		if (!Vfs::stat($query['path'],true) && Vfs::stat(urldecode($query['path'])))
		{
			$query['path'] = urldecode($query['path']);
		}
		if (!Vfs::stat($query['path'],true) || !Vfs::is_dir($query['path']) || !Vfs::check_access($query['path'],Vfs::READABLE))
		{
			// only redirect, if it would be to some other location, gives redirect-loop otherwise
			foreach([$old_session['path'], static::get_home_dir()] as $new_path)
			{
				if ($new_path && Vfs::stat($new_path) && $query['path'] != $new_path)
				{
					// we will leave here, since we are not allowed, or the location does not exist. Index must handle that, and give
					// an appropriate message
					Egw::redirect_link('/index.php', array('menuaction' => 'filemanager.filemanager_ui.index',
							'path' => $new_path,
							'msg' => lang('The requested path %1 is not available.', Vfs::decodePath($query['path'])),
							'ajax' => 'true'
					));
					break;
				}
			}
			$rows = array();
			return 0;
		}
		$GLOBALS['egw']->session->commit_session();
		$rows = $dir_is_writable = array();
		$vfs_options = $this->get_vfs_options($query);

		if($query['col_filter']['path'] && $vfs_options['name'])
		{
			// Query the requested path, in case it's nested
			foreach($query['col_filter']['path'] as $filter_path)
			{
				$query['col_filter']['dir'][] = Vfs::is_dir($filter_path) ? $filter_path : Vfs::dirname($filter_path);
			}
		}

		// query and cache locks for whole directory
		$locks = [];
		foreach(!empty($query['col_filter']['dir']) ? (array)$query['col_filter']['dir'] : (array)$query['path'] as $path)
		{
			$locks += Vfs::checkLock($path, 999);
		}
		$n = 0;
		foreach(Vfs::find(!empty($query['col_filter']['dir']) ? $query['col_filter']['dir'] : $query['path'],$vfs_options,true) as $path => $row)
		{
			//echo $path; _debug_array($row);

			$dir = dirname($path);
			if (!isset($dir_is_writable[$dir]))
			{
				$dir_is_writable[$dir] = Vfs::is_writable($dir);
			}
			if (Vfs::is_dir($path))
			{
				if (!isset($dir_is_writable[$path]))
				{
					$dir_is_writable[$path] = Vfs::is_writable($path);
				}

				$row['class'] .= 'isDir ';
				$row['is_dir'] = 1;
				if(!$dir_is_writable[$path])
				{
					$row['class'] .= 'noEdit ';
				}
				if(!Vfs::is_executable($path))
				{
					$row['class'] .= 'noExecute';
				}
			}
			elseif(!$dir_is_writable[Vfs::dirname($path)])
			{
				$row['class'] .= 'noEdit ';
			}
			if (!empty($lock = $locks[$path]))
			{
				$row['locked'] = 'lock';
				$row['locked_status'] = lang(
					"LOCK from %1, created %2",
					// Not sure why sometimes the lock is owned by a user ID, sometimes mailto:user@email
					is_numeric($lock['owner']) ? $GLOBALS['egw']->accounts->username($lock['owner']) : str_replace('mailto:', '', $lock['owner']),
					Api\DateTime::to(APi\DateTime::server2user($lock['created']), '')
				);
				if($GLOBALS['egw_info']['user']['apps']['admin'] || Vfs::$is_admin || Vfs::$is_root ||
					$lock['owner'] == $GLOBALS['egw_info']['user']['account_id'] ||
					$lock['owner'] == 'mailto:' . $GLOBALS['egw_info']['user']['account_email']
				)
				{
					$row['class'] .= ' locked ';
				}
			}

			$row['class'] .= !$dir_is_writable[$dir] ? 'noDelete' : '';
			$row['download_url'] = Vfs::download_url($path);
			$row['gid'] = -abs($row['gid']);    // gid are positive, but we use negagive account_id for groups internal

			foreach(['mtime', 'ctime'] as $date_field)
			{
				$row[$date_field] = Api\DateTime::server2user($row[$date_field]);
			}
			$rows[++$n] = $row;
			$path2n[$path] = $n;
		}
		// query comments and cf's for the displayed rows
		$cols_to_show = explode(',',$GLOBALS['egw_info']['user']['preferences']['filemanager']['nextmatch-filemanager.index.rows']);

		// Always include comment in tiles
		if($query['view'] == 'tile')
		{
			$cols_to_show[] = 'comment';
		}
		$all_cfs = in_array('customfields',$cols_to_show) && $cols_to_show[count($cols_to_show)-1][0] != '#';
		if ($path2n && (in_array('comment',$cols_to_show) || in_array('customfields',$cols_to_show)) &&
			($path2props = Vfs::propfind(array_keys($path2n))))
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
		$response = Api\Json\Response::get();
		$response->call('app.filemanager.set_readonly', $query['path'], !Vfs::is_writable($query['path']));

		//_debug_array($readonlys);
		if ($GLOBALS['egw_info']['flags']['currentapp'] == 'projectmanager')
		{
			// we need our app.css file
			if (!file_exists(EGW_SERVER_ROOT.($css_file='/filemanager/templates/'.$GLOBALS['egw_info']['server']['template_set'].'/app.css')))
			{
				$css_file = '/filemanager/templates/default/app.css';
			}
			$GLOBALS['egw_info']['flags']['css'] .= "\n\t\t</style>\n\t\t".'<link href="'.$GLOBALS['egw_info']['server']['webserver_url'].
				$css_file.'?'.filemtime(EGW_SERVER_ROOT.$css_file).'" type="text/css" rel="StyleSheet" />'."\n\t\t<style>\n\t\t\t";
		}
		return Vfs::$find_total;
	}


	/**
	 * Get the VFS options (for get rows)
	 */
	protected function get_vfs_options($query)
	{
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

		$maxdepth = $filter && $filter != 4 ? (int)(boolean)$filter : null;
		if($filter == 5) $maxdepth = 2;
		$n = 0;
		$vfs_options = array(
			'mindepth' => 1,
			'maxdepth' => $maxdepth,
			'dirsontop' => $filter <= 1,
			'type' => $filter && $filter != 5 ? ($filter == 4 ? 'd' : null) : ($filter == 5 ? 'F':'f'),
			'order' => $query['order'], 'sort' => $query['sort'],
			'limit' => (int)$query['num_rows'].','.(int)$query['start'],
			'need_mime' => true,
			'name_preg' => $namefilter,
			'hidden' => $filter == 3,
			'follow' => $filter == 5,
		);
		if($query['col_filter']['mime'])
		{
			$vfs_options['mime'] = $query['col_filter']['mime'];
		}
		if($namefilter)
		{
			$vfs_options['name'] = $query['search'];
		}
		if($query['col_filter']['path'])
		{
			$path = is_array($query['col_filter']['path']) ? $query['col_filter']['path'][0] : $query['col_filter']['path'];
			$vfs_options['name'] = Vfs::basename($path);
		}

		return $vfs_options;
	}

	/**
	 * Preferences of a file/directory
	 *
	 * @param array $content
	 * @param string $msg
	 */
	function file(array $content=null,$msg='')
	{
		$tpl = new Etemplate('filemanager.file');

		if (!is_array($content))
		{
			if (isset($_GET['msg']))
			{
				$msg .= $_GET['msg'];
			}
			if (!($path = str_replace(array('#','?'),array('%23','%3F'),$_GET['path'])) ||	// ?, # need to stay encoded!
				// actions enclose pathes containing comma with "
				($path[0] == '"' && substr($path,-1) == '"' && !($path = substr(str_replace('""','"',$path),1,-1))) ||
				!($stat = Vfs::lstat($path)))
			{
				$msg .= lang('File or directory not found!')." path='$path', stat=".array2string($stat);
			}
			else
			{
				$content = $stat;
				$content['name'] = $content['itempicker_merge']['name'] = Vfs::basename($path);
				$content['dir'] = $content['itempicker_merge']['dir'] = ($dir = Vfs::dirname($path)) ? Vfs::decodePath($dir) : '';
				$content['path'] = $path;
				$content['hsize'] = Vfs::hsize($stat['size']);
				$content['mime'] = Vfs::mime_content_type($path);
				$content['gid'] *= -1;	// our widgets use negative gid's
				if (($props = Vfs::propfind($path)))
				{
					foreach($props as $prop)
					{
						$content[$prop['name']] = $prop['val'];
					}
				}
				if (($content['is_link'] = Vfs::is_link($path)))
				{
					$content['symlink'] = Vfs::readlink($path);
				}
			}
			$content['tabs'] = $_GET['tabs'];
			if (!($content['is_dir'] = Vfs::is_dir($path) && !Vfs::is_link($path)))
			{
				$content['perms']['executable'] = (int)!!($content['mode'] & 0111);
				$mask = 6;
				if (preg_match('/^text/',$content['mime']) && $content['size'] < 100000)
				{
					$content['text_content'] = file_get_contents(Vfs::PREFIX.$path);
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
			$content['is_owner'] = Vfs::has_owner_rights($path,$content);
		}
		else
		{
			//_debug_array($content);
			$path =& $content['path'];

			$button = @key($content['button'] ?? []);
			unset($content['button']);
			if(!$button && $content['sudo'] && $content['sudouser'])
			{
				// Button to stop sudo is not in button namespace
				$button = 'sudo';
				unset($content['sudo'], $content['sudouser']);
			}
			// need to check 'setup' button (submit button in sudo popup), as some browsers (eg. chrome) also fill the hidden field
			if ($button == 'sudo' && Vfs::$is_root || $button == 'setup' && $content['sudo']['user'])
			{
				$this->sudo($button === 'setup' ? $content['sudo']['user'] : '', $content['sudo']['passwd'], $msg);
				unset($content['sudo']);
				$content['is_owner'] = Vfs::has_owner_rights($path);
			}
			if (in_array($button,array('save','apply')))
			{
				$props = array();
				$perm_changed = $perm_failed = 0;
				foreach($content['old'] as $name => $old_value)
				{
					if (isset($content[$name]) && ($old_value != $content[$name] ||
						// do not check for modification, if modify_subs is checked!
						$content['modify_subs'] && in_array($name,array('uid','gid','perms'))) &&
						($name != 'uid' || Vfs::$is_root))
					{
						if ($name == 'name')
						{
							if (!($dir = Vfs::dirname($path)))
							{
								$msg .= lang('File or directory not found!')." Vfs::dirname('$path')===false";
								if ($button == 'save') $button = 'apply';
								continue;
							}
							$to = Vfs::concat($dir, $content['name']);
							if (file_exists(Vfs::PREFIX.$to) && $content['confirm_overwrite'] !== $to)
							{
								$tpl->set_validation_error('name',lang("There's already a file with that name!").'<br />'.
									lang('To overwrite the existing file store again.',lang($button)));
								$content['confirm_overwrite'] = $to;
								if ($button == 'save') $button = 'apply';
								continue;
							}
							if (Vfs::rename($path,$to))
							{
								$msg .= lang('Renamed %1 to %2.',Vfs::decodePath(basename($path)),Vfs::decodePath(basename($to))).' ';
								$content['old']['name'] = $content[$name];
								$path = $to;
								$content['mime'] = Api\MimeMagic::filename2mime($path);	// recheck mime type
								$refresh_path = Vfs::dirname($path);	// for renames, we have to refresh the parent
							}
							else
							{
								$msg .= lang('Rename of %1 to %2 failed!',Vfs::decodePath(basename($path)),Vfs::decodePath(basename($to))).' ';
								if (Vfs::deny_script($to))
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
									'ns'	=> static::$merge_prop_namespace,
									'name'	=> 'mergeapp',
									'val'	=> (!empty($content[$name]) ? $content[$name] : null),
								),
							);
							if (Vfs::proppatch($path,$mergeprop))
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
							$cmd = array('EGroupware\\Api\\Vfs',$name2cmd[$name]);
							$value = $name == 'perms' ? static::perms2mode($content['perms']) : $content[$name];
							if(!$value) continue;
							if ($content['modify_subs'])
							{
								if ($name == 'perms')
								{
									$changed = Vfs::find($path,array('type'=>'d'),$cmd,array($value));
									$changed += Vfs::find($path,array('type'=>'f'),$cmd,array($value & 0666));	// no execute for files
								}
								else
								{
									$changed = Vfs::find($path,null,$cmd,array($value));
								}
								$ok = $failed = 0;
								foreach($changed as $sub_path => &$r)
								{
									if ($r)
									{
										++$ok;
										// Changing owner does not change mtime.  Clear subs on UI so they get reloaded
										if($sub_path == $path) continue;
										Api\Json\Response::get()->apply('egw.dataStoreUID',['filemanager::'.$sub_path,null]);
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
					if (Vfs::proppatch($path,$props))
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
			elseif (!empty($content['eacl']) && !empty($content['is_owner']))
			{
				if (!empty($content['eacl']['delete']))
				{
					$ino_owner = key($content['eacl']['delete']);
					list(, $owner) = explode('-',$ino_owner,2);	// $owner is a group and starts with a minus!
					$msg .= Vfs::eacl($path,null,$owner) ? lang('ACL deleted.') : lang('Error deleting the ACL entry!');
				}
				elseif ($button == 'eacl')
				{
					if (!$content['eacl_owner'])
					{
						$msg .= lang('You need to select an owner!');
					}
					else
					{
						$msg .= Vfs::eacl($path,$content['eacl']['rights'],$content['eacl_owner']) ?
							lang('ACL added.') : lang('Error adding the ACL!');
					}
				}
			}
			Framework::refresh_opener($msg, 'filemanager', $refresh_path ? $refresh_path : $path, 'edit', null, '&path=[^&]*');
			if ($button == 'save') Framework::window_close();
		}
		if ($content['is_link'] && !Vfs::stat($path))
		{
			$msg .= ($msg ? "\n" : '').lang('Link target %1 not found!',$content['symlink']);
		}
		$content['link'] = Egw::link(Vfs::download_url($path));
		$content['icon'] = Vfs::mime_icon($content['mime']);
		$content['msg'] = $msg;

		if (($readonlys['uid'] = !Vfs::$is_root) && !$content['uid']) $content['ro_uid_root'] = 'root';
		// only owner can change group & perms
		if (($readonlys['gid'] = !$content['is_owner'] ||
			Vfs::parse_url(Vfs::resolve_url($content['path']),PHP_URL_SCHEME) == 'oldvfs') ||// no uid, gid or perms in oldvfs
				 !Vfs::is_writable($path))
		{
			if (!$content['gid']) $content['ro_gid_root'] = 'root';
			foreach($content['perms'] as $name => $value)
			{
				$readonlys['perms['.$name.']'] = true;
			}
		}
		$readonlys['gid'] = $readonlys['gid'] || !Vfs::is_writable($path);
		$readonlys['name'] = $path == '/' || !($dir = Vfs::dirname($path)) || !Vfs::is_writable($dir);
		$readonlys['comment'] = !Vfs::is_writable($path);
		$readonlys['tabs']['filemanager.file.preview'] = $readonlys['tabs']['filemanager.file.perms'] = $content['is_link'];

		// Don't allow permission changes for these, even for root - it causes too many problems.
		// Use the CLI if you really need to make changes
		if(in_array($content['path'], ['/','/home','/apps']))
		{
			foreach($content['perms'] as $name => $value)
			{
				$readonlys['perms['.$name.']'] = true;
			}
			$readonlys['gid'] = true;
			$readonlys['uid'] = true;
			$readonlys['modify_subs'] = true;
		}

		// if neither owner nor is writable --> disable save&apply
		$readonlys['button[save]'] = $readonlys['button[apply]'] = !$content['is_owner'] && !Vfs::is_writable($path);

		if (!($cfs = Api\Storage\Customfields::get('filemanager')))
		{
			$readonlys['tabs']['custom'] = true;
		}
		elseif (!Vfs::is_writable($path))
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
			if(($content['eacl'] = Vfs::get_eacl($content['path'])) !== false &&	// backend supports eacl
				$GLOBALS['egw_info']['user']['account_id'] == Vfs::$user)	// leave eACL tab disabled for sharing
			{
				unset($readonlys['tabs']['filemanager.file.eacl']);	// --> switch the tab on again
				foreach($content['eacl'] as &$eacl)
				{
					$eacl['path'] = rtrim(Vfs::parse_url($eacl['path'],PHP_URL_PATH),'/');
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
			$sel_options['owner'] = $sel_options['group'] = $sel_options['other'] = array(
				6 => lang('Read & write access'),
				4 => lang('Read access only'),
				0 => lang('No access'),
			);
		}
		// Use 'root' for ID 0
		$sel_options['gid'] = $sel_options['uid'] = array(
			['value' => '0', 'label' => lang("root")]
		);

		// Times are in server time, convert to user timezone
		foreach(['mtime', 'ctime'] as $date_field)
		{
			$time = new Api\DateTime($content[$date_field], Api\DateTime::$server_timezone);
			$time->setUser();
			$content[$date_field] = $time->format('ts');
		}

		// mergeapp
		$content['mergeapp'] = static::get_mergeapp($path, 'self');
		$content['mergeapp_parent'] = static::get_mergeapp($path, 'parents');
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
		$mergeapp_list = Link::app_list('merge');
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
		if (Vfs::$is_root)
		{
			$tpl->setElementAttribute('sudouser', 'label', 'Logout');
			$tpl->setElementAttribute('sudouser', 'help','Log out as superuser');
			// Need a more complex submit because button type is buttononly, which doesn't submit
			$tpl->setElementAttribute('sudouser', 'onclick','app.filemanager.set_sudoButton(widget,"login")');

		}
		elseif ($button == 'sudo')
		{
			$tpl->setElementAttribute('sudouser', 'label', 'Superuser');
			$tpl->setElementAttribute('sudouser', 'help','Enter setup user and password to get root rights');
			$tpl->setElementAttribute('sudouser', 'onclick','app.filemanager.set_sudoButton(widget,"logout")');
		}
		else if (self::is_anonymous($GLOBALS['egw_info']['user']['account_id']))
		{
			// Just hide sudo for anonymous users
			$readonlys['sudouser'] = true;
		}
		if (($extra_tabs = Vfs::getExtraInfo($path,$content)))
		{
			// add to existing tabs in template
			$tpl->setElementAttribute('tabs', 'add_tabs', true);

			$tabs =& $tpl->getElementAttribute('tabs', 'extraTabs');
			if (true) $tabs = array();

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
		Framework::window_focus();
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Preferences').' '.Vfs::decodePath($path);

		$tpl->exec('filemanager.filemanager_ui.file',$content,$sel_options,$readonlys,$preserve,2);
	}

	/**
	 * Check if the user is anonymous user
	 * @param integer $account_id
	 */
	protected static function is_anonymous($account_id)
	{
		$acl = new Api\Acl($account_id);
		$acl->read_repository();
		return $acl->check('anonymous', 1, 'phpgwapi');
	}

	/**
	 * Run given action on given path(es) and return array/object with values for keys 'msg', 'errs', 'dirs', 'files'
	 *
	 * @param string $action eg. 'delete', ...
	 * @param array $selected selected path(s)
	 * @param string $dir=null current directory
	 * @param string[] $props Properties for the file, eg: [comment => 'Latest from Ralf']
	 * @see static::action()
	 */
	public static function ajax_action($action, $selected, $dir=null, $props=null)
	{
		// do we have root rights, need to run here too, as method is static and therefore does NOT run __construct
		if (Api\Cache::getSession('filemanager', 'is_root'))
		{
			Vfs::$is_root = true;
		}
		$response = Api\Json\Response::get();

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
				static::handle_upload_action($action, $selected, $dir, $props, $arr);
				break;
			case 'shareWritableLink':
			case 'shareReadonlyLink':
				if ($action === 'shareWritableLink')
				{
					$share = Vfs\Sharing::create(
							'', $selected, Vfs\Sharing::WRITABLE, basename($selected), array(), array('share_writable' => true)
					);
				}
				else
				{
					$share = Vfs\Sharing::create(
							'', $selected, Vfs\Sharing::READONLY, basename($selected), array()
					);
				}
				$arr["share_link"] = $link = Vfs\Sharing::share2link($share);
				$arr["template"] = Api\Etemplate\Widget\Template::rel2url('/filemanager/templates/default/share_dialog.xet');
				break;

			// Upload, then link
			case 'link':
				// First upload
				$arr = static::ajax_action('upload', $selected, $dir, $props);
				$app_dir = Link::vfs_path($props['entry']['app'],$props['entry']['id'],'',true);

				foreach($arr['uploaded'] as $file)
				{
					$target=Vfs::concat($dir,Vfs::encodePathComponent($file['name']));
					if (Vfs::file_exists($target) && $app_dir)
					{
						if (!Vfs::file_exists($app_dir)) Vfs::mkdir($app_dir);
						error_log("Symlinking $target to $app_dir");
						Vfs::symlink($target, Vfs::concat($app_dir,Vfs::encodePathComponent($file['name'])));
					}
				}
				// Must return to avoid adding to $response again
				return;

			default:
				$arr['msg'] = static::action($action, $selected, $dir, $arr['errs'], $arr['dirs'], $arr['files']);
		}
		$response->data($arr);
		//error_log(__METHOD__."('$action',".array2string($selected).') returning '.array2string($arr));
		return $arr;
	}

	/**
	 * Deal with an uploaded file
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
		$script_error = 0;
		$conflict = $selected['conflict'];
		unset($selected['conflict']);

		foreach($selected as $tmp_name => &$data)
		{
			$path = Vfs::concat($dir, Vfs::encodePathComponent($data['name']));

			if(Vfs::deny_script($path))
			{
				if (!isset($script_error))
				{
					$arr['msg'] .= ($arr['msg'] ? "\n" : '').lang('You are NOT allowed to upload a script!');
				}
				++$script_error;
				++$arr['errs'];
				unset($selected[$tmp_name]);
				continue;
			}
			elseif (Vfs::is_dir($path))
			{
				$data['confirm'] = 'is_dir';
				continue;
			}
			elseif (!$data['confirmed'] && Vfs::stat($path))
			{
				// File exists, what to do?
				switch($conflict)
				{
					case 'overwrite':
						unset($data['confirm']);
						$data['confirmed'] = true;
						break;
					case 'rename':
						// Find a unique name
						$i = 1;
						$info = pathinfo($path);
						while(Vfs::file_exists($path))
						{
							$path = $info['dirname'] . '/'. $info['filename'] . " ($i)." . $info['extension'];
							$i++;
						}
						break;
					case 'ask':
					default:
						$data['confirm'] = true;
				}
			}
			if(!$data['confirm'])
			{
				if (is_dir($GLOBALS['egw_info']['server']['temp_dir']) && is_writable($GLOBALS['egw_info']['server']['temp_dir']))
				{
					$tmp_path = $GLOBALS['egw_info']['server']['temp_dir'] . '/' . basename($tmp_name);
				}
				else
				{
					$tmp_path = ini_get('upload_tmp_dir') . '/' . basename($tmp_name);
				}

				try
				{
					if(Vfs::copy_uploaded($tmp_path, $path, $props, false))
					{
						++$arr['files'];
						$uploaded[] = $data['name'];
					}
					else
					{
						++$arr['errs'];
					}
				}
				catch (\EGroupware\Stylite\Vfs\QuotaExceededException $e)
				{
					// File was touched but not written.  Might need to delete it
					$stat = Vfs::stat($path);
					if($stat && $stat['size'] == 0)
					{
						Vfs::unlink($path);
					}

					// Send error
					++$arr['errs'];
					++$script_error;
					$arr['msg'] = $e->getMessage();
					$arr['msg'] .= "\n" . lang("You can either delete some files or get in touch with your administrator to get more space");
					// No point continuing
					break;
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


	/**
	 * User just got a new share.  Ask the questions, let them customize
	 *
	 * @param array $content
	 */
	public function share_received($content = array())
	{
		// Deal with returning data & changes

		// Read info for display
		$content['mount_location'] = $content['share_path'];
		$sel_options = array();
		$readonlys = array();
		$preserve = $content;
		$template = new Api\Etemplate("api.file_share_received");

		$template->exec(__CLASS__ . '::' . __METHOD__, $content, $sel_options, $readonlys, $preserve, 2);
	}
}
