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

	$showevents = intval($GLOBALS['phpgw_info']['user']['preferences']['infolog']['homeShowEvents']);
	if($showevents > 0)
	{
		$save_app = $GLOBALS['phpgw_info']['flags']['currentapp'];
		$GLOBALS['phpgw_info']['flags']['currentapp'] = 'infolog';

		$GLOBALS['phpgw']->translation->add_app('infolog');

		$app_id = $GLOBALS['phpgw']->applications->name2id('infolog');
		$GLOBALS['portal_order'][] = $app_id;

		$infolog = CreateObject('infolog.uiinfolog');
		if($showevents==1)
		{
			$html = $infolog->index(array('nm' => array('filter' => 'own-open-today')),'','',0,False,True);
		}
		elseif($showevents==2)
		{
			#$html = $infolog->index(array('nm' => array('filter' => 'own-upcoming')),'','',0,False,True);
			$html = ExecMethod('calendar.uicalendar.get_todos', array('', false));
		}
		$title = lang('InfoLog').' - '.lang($infolog->filters['own-open-today']);
		$stable = $infolog->tmpl->stable;
		unset($infolog);

		if($stable)	// .14/6
		{
			$portalbox = CreateObject('phpgwapi.listbox',array(
				'title'     => $title,
				'primary'   => $GLOBALS['phpgw_info']['theme']['navbar_bg'],
				'secondary' => $GLOBALS['phpgw_info']['theme']['navbar_bg'],
				'tertiary'  => $GLOBALS['phpgw_info']['theme']['navbar_bg'],
				'width'     => '100%',
				'outerborderwidth' => '0',
				'header_background_image' => $GLOBALS['phpgw']->common->image('phpgwapi/templates/default','bg_filler')
			));
			foreach(array(
				'up'       => Array('url' => '/set_box.php', 'app' => $app_id),
				'down'     => Array('url' => '/set_box.php', 'app' => $app_id),
				'close'    => Array('url' => '/set_box.php', 'app' => $app_id),
				'question' => Array('url' => '/set_box.php', 'app' => $app_id),
				'edit'     => Array('url' => '/set_box.php', 'app' => $app_id)
			) as $key => $value)
			{
				$portalbox->set_controls($key,$value);
			}
			$portalbox->data = $data;

			echo "\n<!-- BEGIN InfoLog info -->\n".$portalbox->draw($html)."\n<!-- END InfoLog info -->\n";
			unset($portalbox);
		}
		else	// HEAD / XSLT
		{
			$GLOBALS['phpgw']->portalbox->set_params(array(
				'app_id' => $app_id,
				'title'  => $title
			));
			$GLOBALS['phpgw']->portalbox->draw($html);
		}
		unset($html);
		$GLOBALS['phpgw_info']['flags']['currentapp'] = $save_app;
	}
	unset($showevents);
?>
