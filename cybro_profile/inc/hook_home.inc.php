<?php
	/**************************************************************************\
	* eGroupWare - E-Mail                                                      *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id: hook_home.inc.php,v 1.19 2005/11/20 15:51:58 lkneschke Exp $ */

		$d1 = strtolower(substr(EGW_APP_INC,0,3));
		if($d1 == 'htt' || $d1 == 'ftp' )
		{
			echo 'Failed attempt to break in via an old Security Hole!<br>'."\n";
			$GLOBALS['egw']->common->egw_exit();
		}
		unset($d1);

		$GLOBALS['egw']->translation->add_app('cybro_profile');

		$title = lang('cybro_profile');
	
		$portalbox =& CreateObject('phpgwapi.listbox',
			Array(
				'title'				=> $title,
				'primary'			=> $GLOBALS['egw_info']['theme']['navbar_bg'],
				'secondary'			=> $GLOBALS['egw_info']['theme']['navbar_bg'],
				'tertiary'			=> $GLOBALS['egw_info']['theme']['navbar_bg'],
				'width'				=> '100%',
				'outerborderwidth'		=> '0',
				'header_background_image'	=> $GLOBALS['egw']->common->image('phpgwapi/templates/phpgw_website','bg_filler.gif')
			)
		);

		$app_id = $GLOBALS['egw']->applications->name2id('cybro_profile');
		//$GLOBALS['portal_order'][] = $app_id;
		$var = Array(
			'up'		=> Array('url'	=> '/set_box.php', 'app'	=> $app_id),
			'down'		=> Array('url'	=> '/set_box.php', 'app'	=> $app_id),
			'close'		=> Array('url'	=> '/set_box.php', 'app'	=> $app_id),
			'question'	=> Array('url'	=> '/set_box.php', 'app'	=> $app_id),
			'edit'		=> Array('url'	=> '/set_box.php', 'app'	=> $app_id)
		);

		while(list($key,$value) = each($var))
		{
			$portalbox->set_controls($key,$value);
		}

		$portalbox->data = Array();

	
		if($data)
		{
			$portalbox->data = $data;
		}
		
		$this->displayCharset	= $GLOBALS['egw']->translation->charset();
		$uicybro_profile=  CreateObject('cybro_profile.ui_cprofile');
		$extra_data = $uicybro_profile->hook_home();
		
		// output the portalbox and below it (1) the folders listbox (if applicable) and (2) Compose New mail link
		echo $portalbox->draw($extra_data);
?>
