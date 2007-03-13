<?php
/**************************************************************************\
* eGroupWare - Calendar on Homepage                                        *
* http://www.egroupware.org                                                *
* Written and (c) 2004 by Ralf Becker <RalfBecker@outdoor-training.de>     *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

if($GLOBALS['egw_info']['user']['preferences']['calendar']['mainscreen_showevents'])
{
	$GLOBALS['egw']->translation->add_app('calendar');

	$save_app_header = $GLOBALS['egw_info']['flags']['app_header'];
	
	if ($GLOBALS['egw_info']['user']['preferences']['calendar']['defaultcalendar'] == 'listview')
	{
		if (!file_exists(EGW_SERVER_ROOT.($et_css_file ='/etemplate/templates/'.$GLOBALS['egw_info']['user']['preferences']['common']['template_set'].'/app.css')))
		{
			$et_css_file = '/etemplate/templates/default/app.css';
		}
		$content =& ExecMethod('calendar.uilist.home');
	}
	else
	{
		unset($et_css_file);
		$content =& ExecMethod('calendar.uiviews.home');
	}
	$portalbox =& CreateObject('phpgwapi.listbox',array(
		'title'	=> $GLOBALS['egw_info']['flags']['app_header'],
		'primary'	=> $GLOBALS['egw_info']['theme']['navbar_bg'],
		'secondary'	=> $GLOBALS['egw_info']['theme']['navbar_bg'],
		'tertiary'	=> $GLOBALS['egw_info']['theme']['navbar_bg'],
		'width'	=> '100%',
		'outerborderwidth'	=> '0',
		'header_background_image'	=> $GLOBALS['egw']->common->image('phpgwapi/templates/default','bg_filler')
	));
	$GLOBALS['egw_info']['flags']['app_header'] = $save_app_header; 
	unset($save_app_header);

	$GLOBALS['portal_order'][] = $app_id = $GLOBALS['egw']->applications->name2id('calendar');
	foreach(array('up','down','close','question','edit') as $key)
	{
		$portalbox->set_controls($key,Array('url' => '/set_box.php', 'app' => $app_id));
	}
	$portalbox->data = Array();

	if (!file_exists(EGW_SERVER_ROOT.($css_file ='/calendar/templates/'.$GLOBALS['egw_info']['user']['preferences']['common']['template_set'].'/app.css')))
	{
		$css_file = '/calendar/templates/default/app.css';
	}
	echo '
<!-- BEGIN Calendar info -->
<style type="text/css">
<!--';
	if ($et_css_file)	// listview
	{
		echo '
@import url('.$GLOBALS['egw_info']['server']['webserver_url'].$et_css_file.');';
	}
	echo '
@import url('.$GLOBALS['egw_info']['server']['webserver_url'].$css_file.');
-->
</style>
'.$portalbox->draw($content)."\n".'<!-- END Calendar info -->'."\n";
	
	unset($css_file); unset($et_css_file);
	unset($key);
	unset($app_id);
	unset($content); 
	unset($portalbox);
}
