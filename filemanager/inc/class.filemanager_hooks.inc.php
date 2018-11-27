<?php
/**
 * EGroupware - Hooks for admin, preferences and sidebox-menus
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package filemanager
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Framework;
use EGroupware\Api\Egw;
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
			$file['Placeholders'] = Egw::link('/index.php','menuaction=filemanager.filemanager_merge.show_replacements');
			$file['Shared files'] = Egw::link('/index.php','menuaction=filemanager.filemanager_shares.index&ajax=true');
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

		$file = Array(
			//'Site Configuration' => Egw::link('/index.php','menuaction=admin.admin_config.index&appname='.self::$appname.'&ajax=true'),
			'Custom fields' => Egw::link('/index.php','menuaction=admin.admin_customfields.index&appname='.self::$appname.'&ajax=true'),
			'Check virtual filesystem' => Egw::link('/index.php','menuaction=filemanager.filemanager_admin.fsck'),
			'VFS mounts and versioning' => Egw::link('/index.php', 'menuaction=filemanager.filemanager_admin.index'),
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
				'type'		=> 'select',
				'name'		=> 'showusers',
				'values'	=> $yes_no,
				'label' 	=> lang('Show link "%1" in side box menu?',lang('Users and groups')),
				'xmlrpc'	=> True,
				'admin'		=> False,
				'forced'   => 'yes',
			),
		);

		$settings['default_document'] = array(
			'type'   => 'vfs_file',
			'size'   => 60,
			'label'  => 'Default document to insert entries',
			'name'   => 'default_document',
			'help'   => lang('If you specify a document (full vfs path) here, %1 displays an extra document icon for each entry. That icon allows to download the specified document with the data inserted.',lang('filemanager')).' '.
				lang('The document can contain placeholder like {{%1}}, to be replaced with the data.', 'name').' '.
				lang('The following document-types are supported:'). implode(',',Api\Storage\Merge::get_file_extensions()),
			'run_lang' => false,
			'xmlrpc' => True,
			'admin'  => False,
		);
		$settings['document_dir'] = array(
			'type'   => 'vfs_dirs',
			'size'   => 60,
			'label'  => 'Directory with documents to insert entries',
			'name'   => 'document_dir',
			'help'   => lang('If you specify a directory (full vfs path) here, %1 displays an action for each document. That action allows to download the specified document with the %1 data inserted.', lang('filemanager')).' '.
				lang('The document can contain placeholder like {{%1}}, to be replaced with the data.','name').' '.
				lang('The following document-types are supported:'). implode(',',Api\Storage\Merge::get_file_extensions()),
			'run_lang' => false,
			'xmlrpc' => True,
			'admin'  => False,
			'default' => '/templates/filemanager',
		);

		// Import / Export for nextmatch
		if ($GLOBALS['egw_info']['user']['apps']['importexport'])
		{
			$definitions = new importexport_definitions_bo(array(
				'type' => 'export',
				'application' => 'filemanager'
			));
			$options = array();
			foreach ((array)$definitions->get_definitions() as $identifier)
			{
				try {
					$definition = new importexport_definition($identifier);
				}
				catch (Exception $e) {
					unset($e);
					// permission error
					continue;
				}
				if (($title = $definition->get_title()))
				{
					$options[$title] = $title;
				}
				unset($definition);
			}
			$settings['nextmatch-export-definition'] = array(
				'type'   => 'select',
				'values' => $options,
				'label'  => 'Export definition to use for nextmatch export',
				'name'   => 'nextmatch-export-definition',
				'help'   => lang('If you specify an export definition, it will be used when you export'),
				'run_lang' => false,
				'xmlrpc' => True,
				'admin'  => False,
			);
		}
		$editorLink = self::getEditorLink();
		$mimes = array();
		foreach ($editorLink['mime'] as $mime => $value)
		{
			$mimes[$mime] = lang('%1 file', strtoupper($value['ext'])).' ('.$mime.')';

			if (!empty($value['extra_extensions']))
			{
				$mimes[$mime] .= ', '.strtoupper(implode(', ', $value['extra_extensions']));
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
				'title' => lang('Collab Editor settings'),
				'no_lang'=> true,
				'xmlrpc' => False,
				'admin'  => False
			),
			'collab_user_color' => array(
				'type' => 'color',
				'label' => lang('User color indicator'),
				'name' => 'collab_user_color',
				'help' => lang('Use eg. %1 or %2','#FF0000','orange'),
				'no_lang'=> true,
				'xmlrpc' => True,
				'admin'  => False,
			),
			'collab_excluded_mimes' => array(
				'type'   => 'taglist',
				'label'  => lang('Excludes selected mime types'),
				'help'   => lang('Excludes selected mime types from being opened by editor'),
				'name'   => 'collab_excluded_mimes',
				'values' => $mimes,
				'default' => '',
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
				'default' => $GLOBALS['egw_info']['user']['apps']['collabora'] ? 'collabora' : 'download',
			),
			'document_doubleclick_action' => array (
				'type'   => 'select',
				'label'  => lang('Default action on double-click'),
				'help'   => lang('Defines how to handle double click action on a document file. Images are always opened in the expose-view and emails with email application. All other mime-types are handled by the browser itself.'),
				'name'   => 'document_doubleclick_action',
				'values' => $document_doubleclick_action,
				'default' => $GLOBALS['egw_info']['user']['apps']['collabora'] ? 'collabora' : 'download',
			)
		);

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
	 * @return array links
	 */
	static function getEditorLink()
	{
		$implemented = Api\Hooks::implemented('filemanager-editor-link');
		foreach ($implemented as $app)
		{
			if (($access = \EGroupware\Api\Vfs\Links\StreamWrapper::check_app_rights($app)) &&
					($l = Api\Hooks::process('filemanager-editor-link',$app, true)) && $l[$app]
					&& $GLOBALS['egw_info']['user']['preferences']['filemanager']['document_doubleclick_action'] == $app)
			{
				$link = $l[$app];
			}
		}
		return $link;
	}
}
