<?php
	/**************************************************************************\
	* phpGroupWare                                                             *
	* http://www.phpgroupware.org                                              *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	function parse_navbar($force = False)
	{
		$GLOBALS['idots_tpl'] = createobject('phpgwapi.Template',PHPGW_TEMPLATE_DIR);

		$GLOBALS['idots_tpl']->set_file(
			array(
				'navbar' => 'navbar.tpl'
			)
		);
		
		$GLOBALS['idots_tpl']->set_block('navbar','navbar_header','navbar_header');
		$GLOBALS['idots_tpl']->set_block('navbar','extra_blocks_header','extra_block_header');
		$GLOBALS['idots_tpl']->set_block('navbar','extra_block_row','extra_block_row');
		$GLOBALS['idots_tpl']->set_block('navbar','extra_block_spacer','extra_block_spacer');
		$GLOBALS['idots_tpl']->set_block('navbar','extra_blocks_footer','extra_blocks_footer');
		$GLOBALS['idots_tpl']->set_block('navbar','navbar_footer','navbar_footer');

		$var['img_root'] = $GLOBALS['phpgw_info']['server']['webserver_url'] . '/phpgwapi/templates/idots/images';
		$var['table_bg_color'] = $GLOBALS['phpgw_info']['theme']['navbar_bg'];

		$applications = '';
	//	== 'icons_and_text')
		foreach($GLOBALS['phpgw_info']['navbar'] as $app => $app_data)
		{
			if ($app != 'home' && $app != 'preferences' && $app != 'about' && $app != 'logout')
			{
				$title = $GLOBALS['phpgw_info']['apps'][$app]['title'];
				
				$icon = '<img src="' . $app_data['icon'] . '" alt="' . $title . 
					'" title="'. 	$title . '" border="0" />';

				$app_icons .= '<td height="66" valign="bottom" align="center"><a href="' . $app_data['url'] . '"';
				if (isset($GLOBALS['phpgw_info']['flags']['navbar_target']) &&
				$GLOBALS['phpgw_info']['flags']['navbar_target'])
				{
					$app_icons .= ' target="' . $GLOBALS['phpgw_info']['flags']['navbar_target'] . '"';
				}
				$app_icons .= '>' . $icon . '</a></td>';

				$app_titles .= '<td align=center valign="top" class="appTitles"><a href="'.$app_data['url'] . '"';
				if (isset($GLOBALS['phpgw_info']['flags']['navbar_target']) &&
				$GLOBALS['phpgw_info']['flags']['navbar_target'])
				{
					$app_titles .= ' target="' . $GLOBALS['phpgw_info']['flags']['navbar_target'] . '"';
				}
				$app_titles .= '>' . $title . '</a></td>';

				unset($icon);
				unset($title);
			}
		}

		$var['app_icons']  = $app_icons;
		if($GLOBALS['phpgw_info']['user']['preferences']['common']['navbar_format']!='icons')
		{
			$var['app_titles'] = $app_titles;
		}
		if (isset($GLOBALS['phpgw_info']['flags']['app_header']))
		{
			$var['current_app_title'] = $GLOBALS['phpgw_info']['flags']['app_header'];
		}
		else
		{
			$var['current_app_title']=$GLOBALS['phpgw_info']['navbar'][$GLOBALS['phpgw_info']['flags']['currentapp']]['title'];
		}

		if (isset($GLOBALS['phpgw_info']['navbar']['admin']) && $GLOBALS['phpgw_info']['user']['preferences']['common']['show_currentusers'])
		{
			$var['current_users'] = '<a href="'
			. $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uicurrentsessions.list_sessions') . '">'
			. lang('Current users') . ': ' . $GLOBALS['phpgw']->session->total() . '</a>';
		}
		$now = time();
		$var['user_info'] = '<b>'.$GLOBALS['phpgw']->common->display_fullname() .'</b>'. ' - '
		. lang($GLOBALS['phpgw']->common->show_date($now,'l')) . ' '
		. $GLOBALS['phpgw']->common->show_date($now,$GLOBALS['phpgw_info']['user']['preferences']['common']['dateformat']);

		if ($GLOBALS['phpgw_info']['user']['lastpasswd_change'] == 0)
		{
			$api_messages = lang('You are required to change your password during your first login')
			. '<br> Click this image on the navbar: <img src="'
			. $GLOBALS['phpgw']->common->image('preferences','navbar.gif').'">';
		}
		else if ($GLOBALS['phpgw_info']['user']['lastpasswd_change'] < time() - (86400*30))
		{
			$api_messages = lang('it has been more then %1 days since you changed your password',30);
		}

		// This is gonna change
		if (isset($cd))
		{
			$var['messages'] = $api_messages . '<br>' . checkcode($cd);
		}

		$var['logo_file'] = $GLOBALS['phpgw']->common->image('phpgwapi',$GLOBALS['phpgw_info']['server']['login_logo_file']?$GLOBALS['phpgw_info']['server']['login_logo_file']:'logo');
		$var['logo_url'] = $GLOBALS['phpgw_info']['server']['login_logo_url']?$GLOBALS['phpgw_info']['server']['login_logo_url']:'http://www.eGroupWare.org';
		$var['logo_title'] = $GLOBALS['phpgw_info']['server']['login_logo_title']?$GLOBALS['phpgw_info']['server']['login_logo_title']:'www.eGroupWare.org';

		$GLOBALS['idots_tpl']->set_var($var);
		$GLOBALS['idots_tpl']->pfp('out','navbar_header');

		$menu_title = lang('General Menu');

		$file['Home'] = $GLOBALS['phpgw_info']['navbar']['home']['url'];
		if ($GLOBALS['phpgw_info']['user']['apps']['preferences'])
		{
			$file['Preferences'] = $GLOBALS['phpgw_info']['navbar']['preferences']['url'];
		}
		$file += array(
			'About %1'=>$GLOBALS['phpgw_info']['navbar']['about']['url'],
			'Logout'=>$GLOBALS['phpgw_info']['navbar']['logout']['url']
		);

		display_sidebox('',$menu_title,$file);
		
		$GLOBALS['phpgw']->hooks->single('sidebox_menu',$GLOBALS['phpgw_info']['flags']['currentapp']);

		$GLOBALS['idots_tpl']->pparse('out','navbar_footer');

		// If the application has a header include, we now include it
		if (!@$GLOBALS['phpgw_info']['flags']['noappheader'] && @isset($GLOBALS['HTTP_GET_VARS']['menuaction']))
		{
			list($app,$class,$method) = explode('.',$GLOBALS['HTTP_GET_VARS']['menuaction']);
			if (is_array($GLOBALS[$class]->public_functions) && $GLOBALS[$class]->public_functions['header'])
			{
				$GLOBALS[$class]->header();
			}
		}
		$GLOBALS['phpgw']->hooks->process('after_navbar');
		return;
	}

	function display_sidebox($appname,$menu_title,$file)
	{
		
		if(!$appname || ($appname==$GLOBALS['phpgw_info']['flags']['currentapp'] && $file))
		{
			$var['lang_title']=$menu_title;//$appname.' '.lang('Menu');
			$GLOBALS['idots_tpl']->set_var($var);
			$GLOBALS['idots_tpl']->pfp('out','extra_blocks_header');
			
			foreach($file as $text => $url)
			{
				sidebox_menu_item($url,$text);
			}

			$GLOBALS['idots_tpl']->pparse('out','extra_blocks_footer');
		}
	}

	function sidebox_menu_item($item_link='',$item_text='')
	{
		if($item_text === '_NewLine_' || $item_link === '_NewLine_')
		{
			$GLOBALS['idots_tpl']->pparse('out','extra_block_spacer');
		}
		else
		{
			$var['icon_or_star']='<img src="'.$GLOBALS['phpgw_info']['server']['webserver_url'] . '/phpgwapi/templates/idots/images'.'/orange-ball.png" width="9" height="9" alt="ball"/>';
			if (is_array($item_link))
			{
				if (isset($item_link['icon']))
				{
					$app = isset($item_link['app']) ? $item_link['app'] : $GLOBALS['phpgw_info']['flags']['currentapp'];
					$var['icon_or_star'] = '<img src="'.$GLOBALS['phpgw']->common->image($app,$item_link['icon']).'">';
				}
				$var['lang_item'] = isset($item_link['no_lang']) && $item_link['no_lang'] ? $item_link['text'] : lang($item_link['text']);
				$var['item_link'] = $item_link['link'];
			}
			else
			{
				$var['lang_item']=lang($item_text);
				$var['item_link']=$item_link;
			}
			$GLOBALS['idots_tpl']->set_var($var);		
			$GLOBALS['idots_tpl']->pparse('out','extra_block_row');
		}
	}
	
	function parse_navbar_end()
	{
		$GLOBALS['idots_tpl'] = createobject('phpgwapi.Template',PHPGW_TEMPLATE_DIR);

		$GLOBALS['idots_tpl']->set_file(
			array(
				'footer' => 'footer.tpl'
			)
		);
		$var = Array(
			'img_root'			=> $GLOBALS['phpgw_info']['server']['webserver_url'] . '/phpgwapi/templates/idots/images',
			'table_bg_color'	=> $GLOBALS['phpgw_info']['theme']['navbar_bg'],
			'version'			=> $GLOBALS['phpgw_info']['server']['versions']['phpgwapi']
		);
		$GLOBALS['phpgw']->hooks->process('navbar_end');

		$var['powered_by'] = lang('Powered by phpGroupWare version %1',$GLOBALS['phpgw_info']['server']['versions']['phpgwapi']);
		$GLOBALS['idots_tpl']->set_var($var);
		$GLOBALS['idots_tpl']->pfp('out','footer');
	}
