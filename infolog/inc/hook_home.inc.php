<?php
	/**************************************************************************\
	* phpGroupWare - Info Log administration                                   *
	* http://www.phpgroupware.org                                              *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	if ($GLOBALS['phpgw_info']['user']['preferences']['infolog']['homeShowEvents'])
	{
		$save_app = $GLOBALS['phpgw_info']['flags']['currentapp'];
		$GLOBALS['phpgw_info']['flags']['currentapp'] = 'infolog';

		$GLOBALS['phpgw']->translation->add_app('infolog');

		$portalbox = CreateObject('phpgwapi.listbox',
			Array(
				'title'	=> lang('Infolog'),
				'width'	=> '100%',
				'outerborderwidth'	=> '0',
				'header_background_image'	=> $GLOBALS['phpgw']->common->image('phpgwapi/templates/default','bg_filler')
			)
		);

		$app_id = $GLOBALS['phpgw']->applications->name2id('infolog');
		$GLOBALS['portal_order'][] = $app_id;
		$var = Array(
			'up'	=> Array('url'	=> '/set_box.php', 'app'	=> $app_id),
			'down'	=> Array('url'	=> '/set_box.php', 'app'	=> $app_id),
			'close'	=> Array('url'	=> '/set_box.php', 'app'	=> $app_id),
			'question'	=> Array('url'	=> '/set_box.php', 'app'	=> $app_id),
			'edit'	=> Array('url'	=> '/set_box.php', 'app'	=> $app_id)
		);

		while(list($key,$value) = each($var))
		{
			$portalbox->set_controls($key,$value);
		}

		$portalbox->data = Array();

		$GLOBALS['HTTP_POST_VARS']['filter'] = $GLOBALS['filter'] = 'own-open-today';
		$infolog = CreateObject('infolog.uiinfolog');

		/*echo "\n".'<!-- BEGIN infolog info -->'."\n".
		     $portalbox->draw($infolog->get_list(2))."\n".
			  '<!-- END infolog info -->'."\n";
*/

		$GLOBALS['phpgw']->template->set_var('phpgw_body',$portalbox->draw(),True);

		unset($infolog);
		$GLOBALS['phpgw_info']['flags']['currentapp'] = $save_app;
	}
