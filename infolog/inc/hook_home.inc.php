<?php
	/**************************************************************************\
	* eGroupWare - Info Log administration                                     *
	* http://www.egroupware.org                                              *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	/* $Id$ */

	$showevents = intval($GLOBALS['egw_info']['user']['preferences']['infolog']['homeShowEvents']);
	if($showevents > 0)
	{
		$save_app = $GLOBALS['egw_info']['flags']['currentapp'];
		$GLOBALS['egw_info']['flags']['currentapp'] = 'infolog';

		$GLOBALS['egw']->translation->add_app('infolog');

		$app_id = $GLOBALS['egw']->applications->name2id('infolog');
		$GLOBALS['portal_order'][] = $app_id;

		$infolog =& CreateObject('infolog.uiinfolog');
		switch($showevents)
		{
			case 1:
				$html = $infolog->index(array('nm' => array('filter' => 'own-open-today')),'','',0,False,True);
				break;
			case 2:
				$html = ExecMethod('calendar.uicalendar.get_todos', array('', false));
				break;
		}
		$title = lang('InfoLog').' - '.lang($infolog->filters['own-open-today']);
		$xslt = $infolog->tmpl->xslt;
		unset($infolog);

		if(!$xslt)	// .14/6
		{
			$portalbox =& CreateObject('phpgwapi.listbox',array(
				'title'     => $title,
				'primary'   => $GLOBALS['egw_info']['theme']['navbar_bg'],
				'secondary' => $GLOBALS['egw_info']['theme']['navbar_bg'],
				'tertiary'  => $GLOBALS['egw_info']['theme']['navbar_bg'],
				'width'     => '100%',
				'outerborderwidth' => '0',
				'header_background_image' => $GLOBALS['egw']->common->image('phpgwapi/templates/default','bg_filler')
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
			$GLOBALS['egw']->portalbox->set_params(array(
				'app_id' => $app_id,
				'title'  => $title
			));
			$GLOBALS['egw']->portalbox->draw($html);
		}
		unset($html);
		$GLOBALS['egw_info']['flags']['currentapp'] = $save_app;
	}
	unset($showevents);
?>
