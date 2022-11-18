<?php
/**
 * EGroupware - Hooks for admin, preferences and sidebox-menus
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package filemanager
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;
use EGroupware\Api\Framework;
use EGroupware\Api\Egw;
use EGroupware\Api\Json\Push;
use EGroupware\Api\Vfs;

/**
 * Class containing admin, preferences and sidebox-menus (used as hooks)
 */
class filemanager_hooks
{
	static $appname = 'filemanager';

	/**
	 * Data for Filemanagers sidebox menu
	 *
	 * @param array $args
	 */
	static function sidebox_menu($args)
	{
		// Magic etemplate2 favorites menu (from nextmatch widget)
		display_sidebox(self::$appname, lang('Favorites'), Framework\Favorites::list_favorites(self::$appname));

		$location = is_array($args) ? $args['location'] : $args;
		$rootpath = '/';
		$basepath = '/home';
		$homepath = '/home/'.$GLOBALS['egw_info']['user']['account_lid'];
		//echo "<p>admin_prefs_sidebox_hooks::all_hooks(".print_r($args,True).") appname='$appname', location='$location'</p>\n";
		$file_prefs    = &$GLOBALS['egw_info']['user']['preferences'][self::$appname];
		if ($location == 'sidebox_menu')
		{
			$title = $GLOBALS['egw_info']['apps'][self::$appname]['title'] . ' '. lang('Menu');
			$file = array();
			// add "file a file" (upload) dialog
			$file[] = array(
				'text' => 'File a file',
				'link' => "javascript:app.filemanager.fileafile()",
				'app'  => 'api',
				'icon' => 'upload',
				'disableIfNoEPL' => true
			);
			// add selection for available views, if we have more then one
			if (count(filemanager_ui::init_views()) > 1)
			{
				$index_url = Egw::link('/index.php',array('menuaction' => 'filemanager.filemanager_ui.index'),false);
				$file[] = array(
					'text' => Api\Html::select('filemanager_view',filemanager_ui::get_view(),filemanager_ui::$views,false,
						' onchange="'."egw_appWindow('filemanager').location='$index_url&view='+this.value;".
						'" style="width: 100%;"'),
					'no_lang' => True,
					'link' => False
				);
			}
			if ($file_prefs['showhome'] != 'no')
			{
				$file['Your home directory'] = Egw::link('/index.php',array('menuaction'=>self::$appname.'.filemanager_ui.index','path'=>$homepath,'ajax'=>'true'));
			}
			if ($file_prefs['showusers'] != 'no')
			{
				$file['Users and groups'] = Egw::link('/index.php',array('menuaction'=>self::$appname.'.filemanager_ui.index','path'=>$basepath,'ajax'=>'true'));
			}
			if (!empty($file_prefs['showbase']) && $file_prefs['showbase']=='yes')
			{
				$file['Basedirectory'] = Egw::link('/index.php',array('menuaction'=>self::$appname.'.filemanager_ui.index','path'=>$rootpath,'ajax'=>'true'));
			}
			if (!empty($file_prefs['startfolder']))
			{
				$file['Startfolder']= Egw::link('/index.php',array('menuaction'=>self::$appname.'.filemanager_ui.index','path'=>$file_prefs['startfolder'],'ajax'=>'true'));
			}
			$file['Shared files'] = Egw::link('/index.php','menuaction=filemanager.filemanager_shares.index&ajax=true');
			$file[] = ['text'=>'--'];
			$file['Placeholders'] = Egw::link('/index.php','menuaction=filemanager.filemanager_merge.show_replacements');
			display_sidebox(self::$appname,$title,$file);
		}
		if ($GLOBALS['egw_info']['user']['apps']['admin']) self::admin(self::$appname);
	}

	/**
	 * Entries for filemanagers's admin menu
	 *
	 * @param string|array $location ='admin' hook name or params
	 */
	static function admin($location = 'admin')
	{
		if (is_array($location)) $location = $location['location'];

		$file = array(
			//'Site Configuration' => Egw::link('/index.php','menuaction=admin.admin_config.index&appname='.self::$appname.'&ajax=true'),
			'Custom fields'             => Egw::link('/index.php', 'menuaction=admin.admin_customfields.index&appname=' . self::$appname . '&ajax=true'),
			'Check virtual filesystem'  => Egw::link('/index.php', 'menuaction=filemanager.filemanager_admin.fsck'),
			'Quota'                     => Egw::link('/index.php', 'menuaction=filemanager.filemanager_admin.quota&ajax=true'),
			'VFS mounts and versioning' => Egw::link('/index.php', 'menuaction=filemanager.filemanager_admin.index&ajax=true'),
		);
		if ($location == 'admin')
		{
        	display_section(self::$appname,$file);
		}
		else
		{
			display_sidebox(self::$appname,lang('Admin'),$file);
		}
	}

	/**
	 * Settings for preferences
	 *
	 * @return array with settings
	 */
	static function settings()
	{
		$yes_no = array(
			'no'  => lang('No'),
			'yes' => lang('Yes')
		);

        $settings = array(
			'sections.1' => array(
				'type'  => 'section',
				'title' => lang('General settings'),
				'no_lang'=> true,
				'xmlrpc' => False,
				'admin'  => False
			),
			'startfolder'	=> array(
				'type'		=> 'input',
				'name'		=> 'startfolder',
				'size'		=> 60,
				'label' 	=> 'Enter the complete VFS path to specify your desired start folder.',
				'help'		=> 'The default start folder is your personal Folder. The default is used, if you leave this empty, the path does not exist or you lack the neccessary access permissions.',
				'xmlrpc'	=> True,
				'admin'		=> False,
			),
		);

		$settings += array(
			'showbase'	=> array(
				'type'		=> 'select',
				'name'		=> 'showbase',
				'values'	=> $yes_no,
				'label' 	=> 'Show link to filemanagers basedirectory (/) in side box menu?',
				'help'		=> 'Default behavior is NO. The link will not be shown, but you are still able to navigate to this location, or configure this paricular location as startfolder or folderlink.',
				'xmlrpc'	=> True,
				'admin'		=> False,
				'default'   => 'no',
			),
			'showhome'		=> array(
				'type'		=> 'select',
				'name'		=> 'showhome',
				'values'	=> $yes_no,
				'label' 	=> lang('Show link "%1" in side box menu?',lang('Your home directory')),
				'xmlrpc'	=> True,
				'admin'		=> False,
				'forced'   => 'yes',
			),
			'showusers'		=> array(
				'type'   => 'select',
				'name'   => 'showusers',
				'values' => $yes_no,
				'label'  => lang('Show link "%1" in side box menu?', lang('Users and groups')),
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => 'yes',
			),
		);

		$merge = new filemanager_merge();
		$settings += $merge->merge_preferences();

		$editorLink = self::getEditorLink();
		$mimes = array('0' => lang('None'));

		foreach((array)$editorLink['mime'] as $mime => $value)
		{
			$mimes[$mime] = lang('%1 file', strtoupper($value['ext'])) . ' (' . $mime . ')';

			if(!empty($value['extra_extensions']))
			{
				$mimes[$mime] .= ', ' . strtoupper(implode(', ', $value['extra_extensions']));
			}
		}

		$merge_open_handler = array ('download' => lang('download'), 'collabora' => 'Collabora');
		$document_doubleclick_action = array (
			'collabora' => lang('open documents with Collabora, if permissions are given'),
			'download' => lang('download documents'),
			'collabeditor' => lang('open odt documents with CollabEditor')
		);
		if (!$GLOBALS['egw_info']['user']['apps']['collabora'])
		{
			unset($document_doubleclick_action['collabora'], $merge_open_handler['collabora']);
		}
		if (!$GLOBALS['egw_info']['user']['apps']['collabeditor']) unset($document_doubleclick_action['collabeditor']);
		asort($mimes);

		$settings += array (
			'sections.2' => array(
				'type'  => 'section',
				'title' => lang('Collabora Online'),
				'no_lang'=> true,
				'xmlrpc' => False,
				'admin'  => False
			),
			'collab_excluded_mimes' => array(
				'type'   => 'taglist',
				'label'  => lang('Excludes selected mime types'),
				'help'   => lang('Excludes selected mime types from being opened by editor'),
				'name'   => 'collab_excluded_mimes',
				'values' => $mimes,
				'default' => 'application/pdf',
				'attributes' => array(
					'autocompelete_url' => ' ',
					'autocomplete_params' => ' ',
					'select_options' =>  $mimes
				)
			),
			'merge_open_handler' => array(
				'type'   => 'select',
				'label'  => lang('Merge print open handler'),
				'help'   => lang('Defines how to open a merge print document'),
				'name'   => 'merge_open_handler',
				'values' => $merge_open_handler,
				'default' => file_exists(EGW_SERVER_ROOT.'/collabora') ? 'collabora' : 'download',
			),
			'document_doubleclick_action' => array (
				'type'   => 'select',
				'label'  => lang('Default action on double-click'),
				'help'   => lang('Defines how to handle double click action on a document file. Images are always opened in the expose-view and emails with email application. All other mime-types are handled by the browser itself.'),
				'name'   => 'document_doubleclick_action',
				'values' => $document_doubleclick_action,
				'default' => file_exists(EGW_SERVER_ROOT.'/collabora') ? 'collabora' : 'download',
			)
		);

		if($GLOBALS['egw_info']['user']['apps']['collabora'])
		{
			$settings += array(
				'ui_mode' => array(
					'type' => 'select',
					'label' => lang('UI mode'),
					'name' => 'ui_mode',
					'values' => ['classic' => lang('classic'),'notebookbar' => lang('notebookbar')],
					'default' => 'notebookbar'
				)
			);
		}
		return $settings;
	}

	/**
	 * Register filemanager as handler for directories
	 *
	 * @return array see Api\Link class
	 */
	static function search_link()
	{
		return array(
			'edit' => array(
				'menuaction' => 'filemanager.filemanager_ui.file',
			),
			'edit_id' => 'path',
			'edit_popup' => '495x425',
			'mime' => array(
				Vfs::DIR_MIME_TYPE => array(
					'menuaction' => 'filemanager.filemanager_ui.index',
					'ajax' => 'true',
					'mime_id' => 'path',
					'mime_target' => 'filemanager',
					// Prevent url from changing to webdav
					'mime_url' => ''
				),
			),
			'additional' => array(
				'filemanager-editor' => self::getEditorLink()
			),
			'merge' => true,
			'entry' => 'File',
			'entries' => 'Files',
			'view_popup' => '980x750'
		);
	}

	/**
	 * Gets registered links for VFS file editor
	 *
	 * @return array|null links
	 */
	static function getEditorLink()
	{
		foreach (Api\Hooks::process('filemanager-editor-link', 'collabora') as $app => $link)
		{
			if($link && !empty($GLOBALS['egw_info']['user']['apps'][$app]) &&
				(empty($GLOBALS['egw_info']['user']['preferences']['filemanager']['document_doubleclick_action']) ||
					$GLOBALS['egw_info']['user']['preferences']['filemanager']['document_doubleclick_action'] == $app))
			{
				break;
			}
		}
		return $link;
	}

	/**
	 * Hooks called by vfs, implemented to be able to notify subscribed users about changed files
	 *
	 * No need to care for rename or unlink/rmdir as subscriptions are store as properties of the file/directory!
	 *
	 * @param array $data
	 * @param string $data [location] 'vfs_read', 'vfs_added', 'vfs_modified', 'vfs_unlink', 'vfs_rename', 'vfs_mkdir', 'vfs_rmdir'
	 * @param string $data [path] vfs path
	 * @param string $data [url]  backend url
	 * @param string $data [mode] mode of fopen for location=vfs_file_modified
	 * @param string $data [to|from|to_url|from_url] for location=vfs_file_rename
	 * @param string $data [stat] only for vfs_(unlink|rmdir), as hook get's called after successful unlink/rmdir call
	 */
	public static function vfs_hooks(array $data)
	{
		$path = $data['path'];
		if(!$path && $data['to'])
		{
			$path = $data['to'];
		}

		// ignore / do NOT notify about temporary files or lockfiles created by office programms
		if(preg_match('/(^~\$|^\.~lock\.|\.tmp$)/', Vfs::basename($path)))
		{
			return;
		}

		$stat = isset($data['stat']) ? $data['stat'] : Vfs::stat($path);

		// we ignore notifications about zero size files, created by some WebDAV clients prior to every real update!
		if($stat['size'] || Vfs::is_dir($path))
		{
			self::push($data, $stat);
		}
	}

	/**
	 * Push change information to clients
	 *
	 * @param array $data
	 * @return void
	 * @throws Api\Json\Exception
	 */
	protected static function push(array $data, array $stat)
	{
		$path = $data['to'] ?? $data['from'] ?? $data['path'];
		if($path && $data['url'] && $path != $data['url'] &&
			($path_dir = Vfs::parse_url($path, PHP_URL_PATH)) !== ($url_dir = Vfs::parse_url($data['url'], PHP_URL_PATH)))
		{
			// Looks like some path remapping going on, probably a share
			// Try to notify the url path too
			$remap = [];
			foreach(['from', 'to', 'path'] as $map_path)
			{
				if($data[$map_path])
				{
					$remap[$map_path] = str_replace($path_dir, $url_dir, Vfs::parse_url($data[$map_path], PHP_URL_PATH));
				}
			}
			static::push($remap + $data, $stat);
		}

		// Who do we want to broadcast to
		$account_id = [];
		if(str_starts_with($data['from'], '/home/') || str_starts_with($data['to'], '/home/') || str_starts_with($path, '/home/'))
		{
			// In home, send to just owner and group members
			if($stat['uid'])
			{
				$account_id[] = $stat['uid'];
			}
			if($stat['gid'])
			{
				$account_id += $GLOBALS['egw']->accounts->members('-' . $stat['gid'], true);
			}
		}
		else
		{
			// Send to everyone
			$account_id = Push::ALL;
		}

		// Send along some ACL info - a list of account_ids that should have read access
		$acl = [];
		if($stat['uid'])
		{
			$acl[] = $stat['uid'];
		}
		if($stat['gid'])
		{
			$acl[] = -$stat['gid'];
		}
		foreach(['to', 'from', 'path'] as $field)
		{
			$eacl = Vfs::get_eacl($data[$field]);
			if($eacl)
			{
				$acl = array_merge($acl, array_column($eacl, 'owner'));
			}
		}
		$acl = array_unique($acl);
		$type = '';
		$push = new Push($account_id);
		switch($data['location'])
		{
			case 'vfs_rename':
				// Extra push to remove the old, since path = ID
				$push->apply("egw.push",
							 [[
								  'app'        => 'filemanager',
								  'id'         => $data['from'],
								  'type'       => 'delete',
								  'acl'        => $acl,
								  'account_id' => $GLOBALS['egw_info']['user']['account_id']
							  ]]
				);
			// fall through
			case 'vfs_added':
			case 'vfs_mkdir':
				$type = 'add';
				break;
			case 'vfs_modified':
				$type = 'update';
				break;
			case 'vfs_unlink':
			case 'vfs_rmdir':
				$type = 'delete';
				break;
		}

		$push->apply("egw.push",
					 [[
						  'app'        => 'filemanager',
						  'id'         => $data['to'] ?? $path,
						  'type'       => $type,
						  'acl'        => $acl,
						  'account_id' => $GLOBALS['egw_info']['user']['account_id']
					  ]]
		);
	}

}
