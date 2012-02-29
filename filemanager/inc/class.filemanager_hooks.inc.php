<?php
/**
 * eGroupWare - Hooks for admin, preferences and sidebox-menus
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package filemanager
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: class.filemanager_hooks.inc.php 25002 2008-03-03 12:16:11Z ralfbecker $
 */

/**
 * Class containing admin, preferences and sidebox-menus (used as hooks)
 */
class filemanager_hooks
{
	/**
	 * Functions callable via menuaction
	 *
	 * @var unknown_type
	 */
	var $public_functions = array(
		'fsck' => true,
	);

	static $appname = 'filemanager';
	static $foldercount = 1;

	/**
	 * Data for Filemanagers sidebox menu
	 *
	 * @param array $args
	 */
	static function sidebox_menu($args)
	{
		$location = is_array($args) ? $args['location'] : $args;
		$rootpath = '/';
		$basepath = '/home';
		$homepath = '/home/'.$GLOBALS['egw_info']['user']['account_lid'];
		//echo "<p>admin_prefs_sidebox_hooks::all_hooks(".print_r($args,True).") appname='$appname', location='$location'</p>\n";
		$config = config::read(self::$appname);
		if (!empty($config['max_folderlinks'])) self::$foldercount = (int)$config['max_folderlinks'];
		$file_prefs    = &$GLOBALS['egw_info']['user']['preferences'][self::$appname];
		if ($location == 'sidebox_menu')
		{
			$title = $GLOBALS['egw_info']['apps'][self::$appname]['title'] . ' '. lang('Menu');
			$file = array();
			// add selection for available views, if we have more then one
			if (count(filemanager_ui::init_views()) > 1)
			{
				$index_url = egw::link('/index.php',array('menuaction' => 'filemanager.filemanager_ui.index'),false);
				$file[] = array(
					'text' => html::select('filemanager_view',filemanager_ui::get_view(),filemanager_ui::$views,false,
						' onchange="'."egw_appWindow('filemanager').location='$index_url&view='+this.value;".
						'" style="width: 100%;"'),
					'no_lang' => True,
					'link' => False
				);
			}
			if ($file_prefs['showhome'] != 'no')
			{
				$file['Your home directory'] = egw::link('/index.php',array('menuaction'=>self::$appname.'.filemanager_ui.index','path'=>$homepath));
			}
			if ($file_prefs['showusers'] != 'no')
			{
				$file['Users and groups'] = egw::link('/index.php',array('menuaction'=>self::$appname.'.filemanager_ui.index','path'=>$basepath));
			}
			if (!empty($file_prefs['showbase']) && $file_prefs['showbase']=='yes')
			{
				$file['Basedirectory'] = egw::link('/index.php',array('menuaction'=>self::$appname.'.filemanager_ui.index','path'=>$rootpath));
			}
			if (!empty($file_prefs['startfolder'])) $file['Startfolder']= egw::link('/index.php',array('menuaction'=>self::$appname.'.filemanager_ui.index','path'=>$file_prefs['startfolder']));
			for ($i=1; $i<=self::$foldercount; $i++)
			{
				if (!empty($file_prefs['folderlink'.$i]))
				{
					$foldername = array_pop(explode('/',$file_prefs['folderlink'.$i]));
					$file[lang('Link %1: %2',$i,$foldername)]= egw::link('/index.php',array(
						'menuaction' => self::$appname.'.filemanager_ui.index',
						'path'       => $file_prefs['folderlink'.$i],
						'nolang'     => true,
					));
				}
			}
			display_sidebox(self::$appname,$title,$file);
		}
		if ($GLOBALS['egw_info']['user']['apps']['preferences']) self::preferences(self::$appname);
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
            'Site Configuration' => egw::link('/index.php','menuaction=admin.uiconfig.index&appname='.self::$appname),
            'Custom fields' => egw::link('/index.php','menuaction=admin.customfields.edit&appname='.self::$appname),
			'Check virtual filesystem' => egw::link('/index.php','menuaction=filemanager.filemanager_hooks.fsck'),
        );
        // add other administration links, eg. of filesystem backends like versioning
        if (($other = $GLOBALS['egw']->hooks->process('filemanager_admin',array(),true)))
        {
        	foreach($other as $app => $file_data)
        	{
        		$file += $file_data;
        	}
        }
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
	 * Entries for filemanagers's preferences menu
	 *
	 * @param string|array $location ='preferences' hook name or params
	 */
	static function preferences($location = 'preferences')
	{
		if (is_array($location)) $location = $location['location'];

		$file = array(
			'Preferences' => egw::link('/index.php','menuaction=preferences.uisettings.index&appname='.self::$appname),
		);
		if ($location == 'preferences')
		{
			display_section(self::$appname,$file);
		}
		else
		{
			display_sidebox(self::$appname,lang('Preferences'),$file);
		}
	}

	/**
	 * Settings for preferences
	 *
	 * @return array with settings
	 */
	static function settings()
	{
		$config = config::read(self::$appname);
		if (!empty($config['max_folderlinks'])) self::$foldercount = (int)$config['max_folderlinks'];

		$yes_no = array(
			'no'  => lang('No'),
			'yes' => lang('Yes')
		);

        $settings = array(
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
		for ($i=1; $i <= self::$foldercount; $i++)
		{
			$settings['folderlink'.$i]	= array(
				'type'		=> 'input',
				'name'		=> 'folderlink'.$i,
				'size'		=> 60,
				'default'	=> '',
				'label' 	=> lang('Enter the complete VFS path to specify a fast access link to a folder').' ('.$i.').',
				'run_lang'  => -1,	// -1 = no lang on label
				'xmlrpc'	=> True,
				'admin'		=> False
			);
		}

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
		// Import / Export for nextmatch
		if ($GLOBALS['egw_info']['user']['apps']['importexport'])
		{
			$definitions = new importexport_definitions_bo(array(
				'type' => 'export',
				'application' => 'filemanager'
			));
			$options = array();
			foreach ((array)$definitions->get_definitions() as $identifier) {
				try {
					$definition = new importexport_definition($identifier);
				} catch (Exception $e) {
					// permission error
					continue;
				}
				if ($title = $definition->get_title()) {
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
		return $settings;
	}

	/**
	 * Run fsck on sqlfs
	 */
	function fsck()
	{
		if (!isset($GLOBALS['egw_info']['user']['apps']['admin']))
		{
			throw new egw_exception_no_permission_admin();
		}
		$check_only = !isset($_POST['fix']);

		if (!($msgs = sqlfs_utils::fsck($check_only)))
		{
			$msgs = lang('Filesystem check reported no problems.');
		}
		$content = '<p>'.implode("</p>\n<p>", (array)$msgs)."</p>\n";

		$content .= html::form('<p>'.($check_only&&is_array($msgs)?html::submit_button('fix', lang('Fix reported problems')):'').
			html::submit_button('cancel', lang('Cancel'), "window.location.href='".egw::link('/admin/index.php')."'; return false;").'</p>',
			'','/index.php',array('menuaction'=>'filemanager.filemanager_hooks.fsck'));

		$GLOBALS['egw']->framework->render($content, lang('Admin').' - '.lang('Check virtual filesystem'), true);
	}
}
