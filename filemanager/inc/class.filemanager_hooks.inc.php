<?php
/**
 * eGroupWare - Hooks for admin, preferences and sidebox-menus
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package filemanager
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$ 
 */

/**
 * Class containing admin, preferences and sidebox-menus (used as hooks)
 */
class filemanager_hooks
{
	function all_hooks($args)
	{
		$appname = 'filemanager';
		$location = is_array($args) ? $args['location'] : $args;
		//echo "<p>admin_prefs_sidebox_hooks::all_hooks(".print_r($args,True).") appname='$appname', location='$location'</p>\n";

		if ($location == 'sidebox_menu')
		{
			$title = $GLOBALS['egw_info']['apps'][$appname]['title'] . ' '. lang('Menu');
			$file = Array(
				array(
					'Search',
					'text' => 'Search',
					'link' => $GLOBALS['egw']->link('/index.php',array('menuaction'=>'filemanager.uifilemanager.index', 'action'=>'search')),
				)
			);
			display_sidebox($appname,$title,$file);
		}

		if ($GLOBALS['egw_info']['user']['apps']['preferences'] && $location != 'admin')
		{
			$file = array(
				'Filemanager Preferences' => $GLOBALS['egw']->link('/index.php','menuaction=preferences.uisettings.index&appname='.$appname),
				'Grant Access'    => $GLOBALS['egw']->link('/index.php','menuaction=preferences.uiaclprefs.index&acl_app='.$appname),
			);
			if ($location == 'preferences')
			{
				display_section($appname,$file);
			}
			else
			{
				display_sidebox($appname,lang('Preferences'),$file);
			}
		}
		if ($GLOBALS['egw_info']['user']['apps']['admin'] && $location != 'preferences')
		{
			$file = Array(
				'Grant Access'    => $GLOBALS['egw']->link('/index.php','menuaction=preferences.uiaclprefs.index&acl_app='.$appname),
			);
			if ($location == 'admin')
			{
				display_section($appname,$file);
			}
			else
			{
				display_sidebox($appname,lang('Admin'),$file);
			}
		}

	}
	
	function settings($args)
	{
		settype($GLOBALS['settings'],'array');
	
		$GLOBALS['settings']['display_attrs'] = array(
			'type'   => 'section',
			'title'  => 'Display attributes',
			'name'   => 'display_attrs',
			'xmlrpc' => True,
			'admin'  => False
		);
	
		$file_attributes = Array(
			'name' => 'File Name',
			'mime_type' => 'MIME Type',
			'size' => 'Size',
			'created' => 'Created',
			'modified' => 'Modified',
			'owner' => 'Owner',
			'createdby_id' => 'Created by',
			'modifiedby_id' => 'Created by',
			'modifiedby_id' => 'Modified by',
			'app' => 'Application',
			'comment' => 'Comment',
			'version' => 'Version'
		);
	
		foreach($file_attributes as $key => $value)
		{
			$GLOBALS['settings'][$key] = array(
				'type'  => 'check',
				'label' => "$value",
				'name'  => $key,
				'xmlrpc' => True,
				'admin'  => False
			);
		}
	
		$GLOBALS['settings']['other_settings'] = array(
			'type'   => 'section',
			'title'  => 'Other settings',
			'name'   => 'other_settings',
			'xmlrpc' => True,
			'admin'  => False
		);
	
		$other_checkboxes = array (
			"viewinnewwin" => "View documents in new window", 
			"viewonserver" => "View documents on server (if available)", 
			"viewtextplain" => "Unknown MIME-type defaults to text/plain when viewing", 
			"dotdot" => "Show ..", 
			"dotfiles" => "Show .files", 
		);
	
		foreach($other_checkboxes as $key => $value)
		{
			$GLOBALS['settings'][$key] = array(
				'type'  => 'check',
				'label' => "$value",
				'name'  => $key,
				'xmlrpc' => True,
				'admin'  => False
			);
		}
	
		$upload_boxes = array(
			'1'  => '1',
			'5'  => '5',
			'10' => '10',
			'20' => '20',
			'30' => '30'
		);
	
		$GLOBALS['settings']['show_upload_boxes'] = array(
			'label'  => 'Default number of upload fields to show',
			'name'   => 'show_upload_boxes',
			'values' => $upload_boxes,
			'xmlrpc' => True,
			'admin'  => False
		);

		return true;
	}
}
