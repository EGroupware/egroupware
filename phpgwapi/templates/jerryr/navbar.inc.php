<?php
	/**************************************************************************\
	* eGroupWare                                                               *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	function parse_navbar($force = False)
	{
		$GLOBALS['jerryr_tpl'] = createobject('phpgwapi.Template',PHPGW_TEMPLATE_DIR);

		$GLOBALS['jerryr_tpl']->set_file(
			array(
				'navbar' => 'navbar.tpl'
			)
		);

		$GLOBALS['jerryr_tpl']->set_block('navbar','navbar_header','navbar_header');
		$GLOBALS['jerryr_tpl']->set_block('navbar','extra_blocks_header','extra_block_header');
		$GLOBALS['jerryr_tpl']->set_block('navbar','extra_block_row','extra_block_row');
		$GLOBALS['jerryr_tpl']->set_block('navbar','extra_block_spacer','extra_block_spacer');
		$GLOBALS['jerryr_tpl']->set_block('navbar','extra_blocks_footer','extra_blocks_footer');
		$GLOBALS['jerryr_tpl']->set_block('navbar','sidebox_hide_header','sidebox_hide_header');
		$GLOBALS['jerryr_tpl']->set_block('navbar','sidebox_hide_footer','sidebox_hide_footer');
		$GLOBALS['jerryr_tpl']->set_block('navbar','appbox','appbox');
		$GLOBALS['jerryr_tpl']->set_block('navbar','navbar_footer','navbar_footer');

		$var['img_root'] = $GLOBALS['egw_info']['server']['webserver_url'] . '/phpgwapi/templates/jerryr/images';
		$var['table_bg_color'] = $GLOBALS['egw_info']['theme']['navbar_bg'];

		if($GLOBALS['egw_info']['user']['preferences']['common']['click_or_onmouseover']=='onmouseover')
		{
			$show_menu_event = 'onMouseOver';
		}
		else
		{
			$show_menu_event = 'onClick';
		}


		$applications = '';

		//	== 'icons_and_text')

		$max_icons=$GLOBALS['egw_info']['user']['preferences']['common']['max_icons']; 
		if(!$max_icons)
		{
			$max_icons=200;
		}

		foreach($GLOBALS['egw_info']['navbar'] as $app => $app_data)
		{
			if($app != 'preferences' && $app != 'about' && $app != 'logout')
			{
				$title = $GLOBALS['egw_info']['apps'][$app]['title'];
				$icon = '<img src="' . $app_data['icon'] . '" alt="' . $title . '" title="'. $title . '" border="0" />';

				if($app=='home')
				{
					$title = lang('home');
					$icon = '<img src="' . $app_data['icon'] . '" alt="' . lang('home') . '" title="' . lang('home') . '" border="0" />';
				}

				if($i<$max_icons)
				{
					if($GLOBALS['egw_info']['user']['preferences']['common']['start_and_logout_icons']=='no')
					{
						$tdwidth = 100/($max_icons);
					}
					else
					{
						$tdwidth = 100/($max_icons+2);
					}

					$app_icons .= '<td height="40" valign="bottom" align="center"><a href="' . $app_data['url'] . '"';

					if(isset($GLOBALS['egw_info']['flags']['navbar_target']) && $GLOBALS['egw_info']['flags']['navbar_target'])
					{
						$app_icons .= ' target="' . $GLOBALS['egw_info']['flags']['navbar_target'] . '"';
					}

					$app_icons .= $app_data['target'] . '>' . $icon . '</a></td>';

					$app_titles .= '<td align=center valign="top" class="appTitles"><a href="'.$app_data['url'] . '"';

					if(isset($GLOBALS['egw_info']['flags']['navbar_target']) && $GLOBALS['egw_info']['flags']['navbar_target'])
					{
						$app_titles .= ' target="' . $GLOBALS['egw_info']['flags']['navbar_target'] . '"';
					}

					$app_titles .= $app_data['target'] . '>' . $title . '</a></td>';
				}
				//				else // generate extra icon layer | always shows icons and text
				else // generate extra icon layer shows icons and/or text
				{
					// check for small icon version else use default and let the browser resize
					$icon = '<img src="' . $app_data['icon'] . '" alt="' . $title . '" width="16" title="'. $title . '" border="0" />';
					
					$app_extra_icons .= '<tr>';

					if($GLOBALS['egw_info']['user']['preferences']['common']['navbar_format']!='text')
					{
						$app_extra_icons .= '<td class="extraIconsRow"><a href="' . $app_data['url'] . '"';

						if(isset($GLOBALS['egw_info']['flags']['navbar_target']) && $GLOBALS['egw_info']['flags']['navbar_target'])
						{
							$app_extra_icons .= ' target="' . $GLOBALS['egw_info']['flags']['navbar_target'] . '"';
						}

						$app_extra_icons .= ' >' . $icon . '</a></td>';
					}



					//					$app_extra_icons .= '<tr><td><a href="' . $app_data['url'] . '"';

					//					if(isset($GLOBALS['egw_info']['flags']['navbar_target']) && $GLOBALS['egw_info']['flags']['navbar_target'])
					//					{
						//						$app_extra_icons .= ' target="' . $GLOBALS['egw_info']['flags']['navbar_target'] . '"';
						//					}

						//					$app_extra_icons .= '>' . $icon . '</a></td>';

						$app_extra_icons .= '<td align="left" class="extraIconsRow" style=""><a href="'.$app_data['url'] . '"';

						if(isset($GLOBALS['egw_info']['flags']['navbar_target']) && $GLOBALS['egw_info']['flags']['navbar_target'])
						{
							$app_extra_icons .= ' target="' . $GLOBALS['egw_info']['flags']['navbar_target'] . '"';
						}

						$app_extra_icons .= '>' . $title . '</a></td></tr>';
					}

					unset($icon);
					unset($title);
					$i++;
				}
			}

			if($GLOBALS['egw_info']['user']['preferences']['common']['start_and_logout_icons']!='no')
			{
				$app_icons .= '<td width="'.$tdwidth.'%" height="32" valign="bottom" align="center" style="text-align:center"><a 
href="'.$GLOBALS['egw_info']['navbar']['logout']['url'].'"><img src="'.$GLOBALS['egw_info']['navbar']['logout']['icon'].'" title="'.$GLOBALS['egw_info']['navbar']['logout']['title'].'" alt="'.$GLOBALS['egw_info']['navbar']['logout']['title'].'"></a></td>';
				$app_titles .= '<td align="center" valign="top" class="appTitles" style="text-align:center"><a href="'.$GLOBALS['egw_info']['navbar']['logout']['url'].'">'.$GLOBALS['egw_info']['navbar']['logout']['title'].'</a></td>';

			}
//			$var['app_icons'] = $app_icons;
		if($GLOBALS['egw_info']['user']['preferences']['common']['navbar_format']!='text')
		{
			$var['app_icons'] = $app_icons;
		}

			if($i > $max_icons)
			{
				$app_extra_icons_div = '
				<script language="javascript">
				new ypSlideOutMenu("menu1", "down", 10, 90, 160, 200,\'right\')
				</script>
				<div id="menu1Container">
				<div id="menu1Content" style="position: relative; left: 0; text-align: left;">

				<div id="extraIcons">
				<table cellspacing="0" cellpadding="0" border="0" width="100%">
				<tr><td colspan="2" nowrap="nowrap" align="right" style="background-color:#dddddd;padding:1px;">
				<a href="#" '.$show_menu_event.'="ypSlideOutMenu.hide(\'menu1\')" title="'.lang('close').'">
				<img style="" border="0" src="'.$var['img_root'].'/close.png"/></a></td></tr>
				'.$app_extra_icons.'					</table>
				</div>

				</div>
				</div>
				';

				$var['app_extra_icons_div']= $app_extra_icons_div;
				//			$var['app_extra_icons_icon']= '<td width="26" valign="top" align="right" style="padding-right:3px;padding-top:30px;"><a title="'.lang('show_more_apps').'" href="javascript:void(0);" onClick="HideShow(\'extraIcons\');"><img src="'.$var['img_root'].'/extra_icons.png" border="0" /></a></td>';
				$var['app_extra_icons_icon']= '<td width="26" valign="top" align="right" style="padding-right:3px;padding-top:30px;"><a title="'.lang('show_more_apps').'" href="#"  '.$show_menu_event.'="ypSlideOutMenu.showMenu(\'menu1\')"><img src="'.$var['img_root'].'/extra_icons.png" border="0" /></a></td>';
			}

			if($GLOBALS['egw_info']['user']['preferences']['common']['navbar_format']!='icons')
			{
				$var['app_titles'] = $app_titles;
			}
			else
			{
				$var['app_titles'] = '<td colspan="'.$max_icons.'">&nbsp;</td>'; 
			}
			if(isset($GLOBALS['egw_info']['flags']['app_header']))
			{
				$var['current_app_title'] = $GLOBALS['egw_info']['flags']['app_header'];
			}
			else
			{
				$var['current_app_title']=$GLOBALS['egw_info']['navbar'][$GLOBALS['egw_info']['flags']['currentapp']]['title'];
			}

			if(isset($GLOBALS['egw_info']['navbar']['admin']) && $GLOBALS['egw_info']['user']['preferences']['common']['show_currentusers'])
			{
				$var['current_users'] = '<a href="'
				. $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uicurrentsessions.list_sessions') . '">'
				. lang('Current users') . ': ' . $GLOBALS['phpgw']->session->total() . '</a>';
			}
			$now = time();
			$var['user_info'] = '<b>'.$GLOBALS['phpgw']->common->display_fullname() .'</b>'. ' - '
			. lang($GLOBALS['phpgw']->common->show_date($now,'l')) . ' '
			. $GLOBALS['phpgw']->common->show_date($now,$GLOBALS['egw_info']['user']['preferences']['common']['dateformat']);

			if($GLOBALS['egw_info']['user']['lastpasswd_change'] == 0)
			{
				$api_messages = lang('You are required to change your password during your first login')
				. '<br> Click this image on the navbar: <img src="'
				. $GLOBALS['phpgw']->common->image('preferences','navbar.gif').'">';
			}
			elseif($GLOBALS['egw_info']['user']['lastpasswd_change'] < time() - (86400*30))
			{
				$api_messages = lang('it has been more then %1 days since you changed your password',30);
			}

			// This is gonna change
			if(isset($cd))
			{
				$var['messages'] = $api_messages . '<br>' . checkcode($cd);
			}

			if (substr($GLOBALS['egw_info']['server']['login_logo_file'],0,4) == 'http')
			{
				$var['logo_file'] = $GLOBALS['egw_info']['server']['login_logo_file'];
			}
			else
			{
				$var['logo_file'] = $GLOBALS['phpgw']->common->image('phpgwapi',$GLOBALS['egw_info']['server']['login_logo_file']?$GLOBALS['egw_info']['server']['login_logo_file']:'logo');
			}
			$var['logo_url'] = $GLOBALS['egw_info']['server']['login_logo_url']?$GLOBALS['egw_info']['server']['login_logo_url']:'http://www.eGroupWare.org';
			if (substr($var['logo_url'],0,4) != 'http')
			{
				$var['logo_url'] = 'http://'.$var['logo_url'];
			}
			$var['logo_title'] = $GLOBALS['egw_info']['server']['login_logo_title']?$GLOBALS['egw_info']['server']['login_logo_title']:'www.eGroupWare.org';

			$GLOBALS['jerryr_tpl']->set_var($var);
			$GLOBALS['jerryr_tpl']->pfp('out','navbar_header');

			/******************************************************\
			* The sidebox menu's                                   *
			\******************************************************/

			$menu_title = lang('General Menu');

			$file['Home'] = $GLOBALS['egw_info']['navbar']['home']['url'];
			if($GLOBALS['egw_info']['user']['apps']['preferences'])
			{
				$file['Preferences'] = $GLOBALS['egw_info']['navbar']['preferences']['url'];
			}
			$file += array(
				array(
					'text'    => lang('About %1',$GLOBALS['egw_info']['apps'][$GLOBALS['egw_info']['flags']['currentapp']]['title']),
					'no_lang' => True,
					'link'    => $GLOBALS['egw_info']['navbar']['about']['url']
				),
				'Logout'=>$GLOBALS['egw_info']['navbar']['logout']['url']
			);

			if($GLOBALS['egw_info']['user']['preferences']['common']['auto_hide_sidebox']==1)
			{
				$GLOBALS['jerryr_tpl']->set_var('show_menu_event',$show_menu_event);
				$GLOBALS['jerryr_tpl']->pparse('out','sidebox_hide_header');

				display_sidebox('',$menu_title,$file);
				$GLOBALS['phpgw']->hooks->single('sidebox_menu',$GLOBALS['egw_info']['flags']['currentapp']);

				$GLOBALS['jerryr_tpl']->pparse('out','sidebox_hide_footer');

				$var['sideboxcolstart']='';

				$GLOBALS['jerryr_tpl']->set_var($var);
				$GLOBALS['jerryr_tpl']->pparse('out','appbox');
				$var['remove_padding'] = 'style="padding-left:0px;"';
				$var['sideboxcolend'] = '';
			}
			else
			{
				$var['menu_link'] = '';
				$var['sideboxcolstart'] = '<td id="tdSidebox" valign="top">';
				$var['remove_padding'] = '';
				$GLOBALS['jerryr_tpl']->set_var($var);
				$GLOBALS['jerryr_tpl']->pparse('out','appbox');

				display_sidebox('',$menu_title,$file);
				$GLOBALS['phpgw']->hooks->single('sidebox_menu',$GLOBALS['egw_info']['flags']['currentapp']);

				$var['sideboxcolend'] = '</td>';
			}

			$GLOBALS['jerryr_tpl']->set_var($var);
			$GLOBALS['jerryr_tpl']->pparse('out','navbar_footer');

			// If the application has a header include, we now include it
			if(!@$GLOBALS['egw_info']['flags']['noappheader'] && @isset($_GET['menuaction']))
			{
				list($app,$class,$method) = explode('.',$_GET['menuaction']);
				if(is_array($GLOBALS[$class]->public_functions) && $GLOBALS[$class]->public_functions['header'])
				{
					$GLOBALS[$class]->header();
				}
			}
			$GLOBALS['phpgw']->hooks->process('after_navbar');
			return;
		}

		function display_sidebox($appname,$menu_title,$file)
		{
			if(!$appname || ($appname==$GLOBALS['egw_info']['flags']['currentapp'] && $file))
			{
				$var['lang_title']=$menu_title;//$appname.' '.lang('Menu');
				$GLOBALS['jerryr_tpl']->set_var($var);
				$GLOBALS['jerryr_tpl']->pfp('out','extra_blocks_header');

				foreach($file as $text => $url)
				{
					sidebox_menu_item($url,$text);
				}

				$GLOBALS['jerryr_tpl']->pparse('out','extra_blocks_footer');
			}
		}

		function sidebox_menu_item($item_link='',$item_text='')
		{
			if($item_text === '_NewLine_' || $item_link === '_NewLine_')
			{
				$GLOBALS['jerryr_tpl']->pparse('out','extra_block_spacer');
			}
			else
			{
				$var['icon_or_star']='<img src="'.$GLOBALS['egw_info']['server']['webserver_url'] . '/phpgwapi/templates/jerryr/images'.'/orange-ball.png" width="9" height="9" alt="ball"/>';
				$var['target'] = '';
				if(is_array($item_link))
				{
					if(isset($item_link['icon']))
					{
						$app = isset($item_link['app']) ? $item_link['app'] : $GLOBALS['egw_info']['flags']['currentapp'];
						$var['icon_or_star'] = '<img src="'.$GLOBALS['phpgw']->common->image($app,$item_link['icon']).'"/>';
					}
					$var['lang_item'] = isset($item_link['no_lang']) && $item_link['no_lang'] ? $item_link['text'] : lang($item_link['text']);
					$var['item_link'] = $item_link['link'];
					if ($item_link['target'])
					{
						$var['target'] = ' target="' . $item_link['target'] . '"';
					}
				}
				else
				{
					$var['lang_item'] = lang($item_text);
					$var['item_link'] = $item_link;
				}
				$GLOBALS['jerryr_tpl']->set_var($var);
				$GLOBALS['jerryr_tpl']->pparse('out','extra_block_row');
			}
		}

		function parse_navbar_end()
		{
			$GLOBALS['jerryr_tpl'] = createobject('phpgwapi.Template',PHPGW_TEMPLATE_DIR);

			$GLOBALS['jerryr_tpl']->set_file(
				array(
					'footer' => 'footer.tpl'
				)
			);
			$var = Array(
				'img_root'       => $GLOBALS['egw_info']['server']['webserver_url'] . '/phpgwapi/templates/jerryr/images',
				'table_bg_color' => $GLOBALS['egw_info']['theme']['navbar_bg'],
				'version'        => $GLOBALS['egw_info']['server']['versions']['phpgwapi']
			);
			$GLOBALS['phpgw']->hooks->process('navbar_end');

			if($GLOBALS['egw_info']['user']['preferences']['common']['show_generation_time'])
			{
				$totaltime = sprintf('%4.2lf',perfgetmicrotime() - $GLOBALS['egw_info']['flags']['page_start_time']); 

				$var['page_generation_time'] = '<div id="divGenTime"><br/><span>'.lang('Page was generated in %1 seconds',$totaltime).'</span></div>';
			}

			$var['powered_by'] = lang('Powered by eGroupWare version %1',$GLOBALS['egw_info']['server']['versions']['phpgwapi']);
			$var['activate_tooltips'] = '<script src="'.$GLOBALS['egw_info']['server']['webserver_url'].'/phpgwapi/js/wz_tooltip/wz_tooltip.js" type="text/javascript"></script>';



			$GLOBALS['jerryr_tpl']->set_var($var);
			$GLOBALS['jerryr_tpl']->pfp('out','footer');
		}
