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

		$app_id = $GLOBALS['phpgw']->applications->name2id('infolog');
		$GLOBALS['portal_order'][] = $app_id;

		$GLOBALS['phpgw']->portalbox->set_params(array('app_id'	=> $app_id,
														'title'	=> lang('infolog')));

		$GLOBALS['HTTP_POST_VARS']['filter'] = $GLOBALS['filter'] = 'own-open-today';
		$infolog = CreateObject('infolog.uiinfolog');

		$GLOBALS['phpgw']->portalbox->draw($infolog->get_list(2));

		unset($infolog);
		$GLOBALS['phpgw_info']['flags']['currentapp'] = $save_app;
	}
?>
