<?php
	/**************************************************************************\
	* phpGroupWare - Administration                                            *
	* http://www.phpgroupware.org                                              *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	class uiaclmanager
	{
		var $template;
		var $nextmatchs;
		var $public_functions = array(
			'list_apps'    => True,
			'access_form'  => True,
			'account_list' => True
		);

		function uiaclmanager()
		{
			$this->template = createobject('phpgwapi.Template',PHPGW_APP_TPL);
		}

		function common_header()
		{
			$GLOBALS['phpgw']->common->phpgw_header();
			echo parse_navbar();
		}

		function list_apps()
		{
			$this->common_header();

			$GLOBALS['phpgw']->hooks->process('acl_manager',array('preferences'));

			$this->template->set_file(array(
				'app_list'   => 'acl_applist.tpl'
			));
			$this->template->set_block('app_list','list');
			$this->template->set_block('app_list','app_row');
			$this->template->set_block('app_list','app_row_noicon');
			$this->template->set_block('app_list','link_row');
			$this->template->set_block('app_list','spacer_row');

			$this->template->set_var('lang_header',lang('ACL Manager'));

			while (is_array($GLOBALS['acl_manager']) && list($app,$locations) = each($GLOBALS['acl_manager']))
			{
				$icon = $GLOBALS['phpgw']->common->image($app,array('navbar.gif',$app.'.gif'));
				$this->template->set_var('icon_backcolor',$GLOBALS['phpgw_info']['theme']['row_off']);
				$this->template->set_var('link_backcolor',$GLOBALS['phpgw_info']['theme']['row_off']);
				$this->template->set_var('app_name',lang($GLOBALS['phpgw_info']['navbar'][$app]['title']));
				$this->template->set_var('a_name',$appname);
				$this->template->set_var('app_icon',$icon);

				if ($icon)
				{
					$this->template->fp('rows','app_row',True);
				}
				else
				{
					$this->template->fp('rows','app_row_noicon',True);
				}

				while (is_array($locations) && list($loc,$value) = each($locations))
				{
					$total_rights = 0;
					while (list($k,$v) = each($value['rights']))
					{
						$total_rights += $v;
					}
					reset($value['rights']);

					// If all of there rights are denied, then they shouldn't even see the option
					if ($total_rights != $GLOBALS['phpgw']->acl->get_rights($loc,$app))
					{
						$link_values = array(
							'menuaction' => 'admin.uiaclmanager.access_form',
							'location'   => urlencode(base64_encode($loc)),
							'acl_app'    => $app,
							'account_id' => $GLOBALS['account_id']
						);

						$this->template->set_var('link_location',$GLOBALS['phpgw']->link('/index.php',$link_values));
						$this->template->set_var('lang_location',lang($value['name']));
						$this->template->fp('rows','link_row',True);
					}
				}

				$this->template->parse('rows','spacer_row',True);
			}
			$this->template->pfp('out','list');
		}

		function access_form()
		{
			$GLOBALS['phpgw']->hooks->single('acl_manager',$GLOBALS['acl_app']);
			$location = base64_decode($GLOBALS['location']);

			$acl_manager = $GLOBALS['acl_manager'][$GLOBALS['acl_app']][$location];

			$this->common_header();
			$this->template->set_file('form','acl_manager_form.tpl');

			$acc = createobject('phpgwapi.accounts',$GLOBALS['account_id']);
			$acc->read_repository();
			$afn = $GLOBALS['phpgw']->common->display_fullname($acc->data['account_lid'],$acc->data['firstname'],$acc->data['lastname']);

			$this->template->set_var('lang_message',lang('Check items to <b>%1</b> to %2 for %3',$acl_manager['name'],$GLOBALS['acl_app'],$afn));
			$link_values = array(
				'menuaction' => 'admin.boaclmanager.submit',
				'acl_app'    => $GLOBALS['acl_app'],
				'location'   => urlencode($GLOBALS['location']),
				'account_id' => $GLOBALS['account_id']
			);

			$acl    = createobject('phpgwapi.acl',$GLOBALS['account_id']);
			$acl->read_repository();

			$this->template->set_var('form_action',$GLOBALS['phpgw']->link('/index.php',$link_values));
			$this->template->set_var('lang_title',lang('ACL Manager'));

			$total = 0;
			while (list($name,$value) = each($acl_manager['rights']))
			{
				$grants = $acl->get_rights($location,$GLOBALS['acl_app']);

				if (! $GLOBALS['phpgw']->acl->check($location,$value,$GLOBALS['acl_app']))
				{
					$s .= '<option value="' . $value . '"';
					$s .= (($grants & $value)?' selected':'');
					$s .= '>' . lang($name) . '</option>';
					$total++;
				}
			}

			$size = 7;
			if ($total < 7)
			{
				$size = $total;
			}
			$this->template->set_var('select_values','<select name="acl_rights[]" multiple size="' . $size . '">' . $s . '</select>');
			$this->template->set_var('lang_submit',lang('Submit'));
			$this->template->set_var('lang_cancel',lang('Cancel'));

			$this->template->pfp('out','form');
		}
	}
