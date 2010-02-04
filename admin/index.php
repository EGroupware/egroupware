<?php
/**
 * EGgroupware administration
 *
 * @link http://www.egroupware.org
 * @author Joseph Engo <jengo@phpgroupware.org>
 * @author Stephen Brown <steve@dataclarity.net> distribute admin across the application directories
 * @package admin
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp' => 'admin',
		'noheader' => true,
	),
);
include('../header.inc.php');

admin_statistics::check();
common::egw_header();

$GLOBALS['admin_tpl'] =& CreateObject('phpgwapi.Template',EGW_APP_TPL);
$GLOBALS['admin_tpl']->set_file(
	Array(
		'admin' => 'index.tpl'
	)
);

$GLOBALS['admin_tpl']->set_block('admin','list');
$GLOBALS['admin_tpl']->set_block('admin','app_row');
$GLOBALS['admin_tpl']->set_block('admin','app_row_noicon');
$GLOBALS['admin_tpl']->set_block('admin','link_row');
$GLOBALS['admin_tpl']->set_block('admin','spacer_row');

$GLOBALS['admin_tpl']->set_var('title',lang('Administration'));

// This func called by the includes to dump a row header
function section_start($appname='',$icon='')
{
	$GLOBALS['admin_tpl']->set_var('link_backcolor',$GLOBALS['egw_info']['theme']['row_off']);
	$GLOBALS['admin_tpl']->set_var('app_name',$GLOBALS['egw_info']['apps'][$appname]['title']);
	$GLOBALS['admin_tpl']->set_var('a_name',$appname);
	$GLOBALS['admin_tpl']->set_var('app_icon',$icon);
	if ($icon)
	{
		$GLOBALS['admin_tpl']->parse('rows','app_row',True);
	}
	else
	{
		$GLOBALS['admin_tpl']->parse('rows','app_row_noicon',True);
	}
}

function section_item($pref_link='',$pref_text='')
{
	$GLOBALS['admin_tpl']->set_var('pref_link',$pref_link);
	$GLOBALS['admin_tpl']->set_var('pref_text',$pref_text);
	$GLOBALS['admin_tpl']->parse('rows','link_row',True);
}

function section_end()
{
	$GLOBALS['admin_tpl']->parse('rows','spacer_row',True);
}

function display_section($appname,$file,$file2=False)
{
	if ($file2)
	{
		$file = $file2;
	}
	if(is_array($file))
	{
		section_start($appname,
			common::image(
				$appname,
				Array(
					'navbar',
					$appname,
					'nonav'
				)
			)
		);

		while(list($text,$url) = each($file))
		{
			// If user doesn't have application configuration access, then don't show the configuration links
			if (strpos($url, 'admin.uiconfig') === False || !$GLOBALS['egw']->acl->check('site_config_access',1,'admin'))
			{
				section_item($url,lang($text));
			}
		}
		section_end();
	}
}

$GLOBALS['egw']->hooks->process('admin',array('admin'));
$GLOBALS['admin_tpl']->pparse('out','list');

common::egw_footer();
