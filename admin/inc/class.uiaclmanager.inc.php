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
			$this->account_id = intval($_GET['account_id']);
			if (!$this->account_id || $GLOBALS['phpgw']->acl->check('account_access',64,'admin'))
			{
				$GLOBALS['phpgw']->redirect_link('/index.php');
			}
			$this->template = createobject('phpgwapi.Template',PHPGW_APP_TPL);
		}

		function common_header()
		{
			$GLOBALS['phpgw_info']['flags']['app_header'] = lang('Admin') . ' - ' . lang('ACL Manager') .
				': ' . $GLOBALS['phpgw']->common->grab_owner_name($this->account_id);
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

			while (is_array($GLOBALS['acl_manager']) && list($app,$locations) = each($GLOBALS['acl_manager']))
			{
				$icon = $GLOBALS['phpgw']->common->image($app,array('navbar.gif',$app.'.gif'));
				$this->template->set_var('icon_backcolor',$GLOBALS['phpgw_info']['theme']['row_off']);
				$this->template->set_var('link_backcolor',$GLOBALS['phpgw_info']['theme']['row_off']);
				$this->template->set_var('app_name',$GLOBALS['phpgw_info']['apps'][$app]['title']);
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
					$link_values = array(
						'menuaction' => 'admin.uiaclmanager.access_form',
						'location'   => urlencode($loc),
						'acl_app'    => $app,
						'account_id' => $this->account_id
					);

					$this->template->set_var('link_location',$GLOBALS['phpgw']->link('/index.php',$link_values));
					$this->template->set_var('lang_location',lang($value['name']));
					$this->template->fp('rows','link_row',True);
				}

				$this->template->parse('rows','spacer_row',True);
			}
			$this->template->set_var(array(
				'cancel_action' => $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiaccounts.list_users'),
				'lang_cancel'   => lang('Cancel')
			));
			$this->template->pfp('out','list');
		}

		function access_form()
		{
			$location = $_GET['location'];

			if ($_POST['submit'] || $_POST['cancel'])
			{
				if ($_POST['submit'])
				{
					$total_rights = 0;
					while (is_array($_POST['acl_rights']) && list(,$rights) = each($_POST['acl_rights']))
					{
						$total_rights += $rights;
					}
					if ($total_rights)
					{
						$GLOBALS['phpgw']->acl->add_repository($_GET['acl_app'], $location, $this->account_id, $total_rights);
					}
					else	// we dont need to save 0 rights (= no restrictions)
					{
						$GLOBALS['phpgw']->acl->delete_repository($_GET['acl_app'], $location, $this->account_id);
					}
				}
				$this->list_apps();
				return;
			}
			$GLOBALS['phpgw']->hooks->single('acl_manager',$_GET['acl_app']);
			$acl_manager = $GLOBALS['acl_manager'][$_GET['acl_app']][$location];

			$this->common_header();
			$this->template->set_file('form','acl_manager_form.tpl');

			$acc = createobject('phpgwapi.accounts',$this->account_id);
			$acc->read_repository();
			$afn = $GLOBALS['phpgw']->common->display_fullname($acc->data['account_lid'],$acc->data['firstname'],$acc->data['lastname']);

			$this->template->set_var('lang_message',lang('Check items to <b>%1</b> to %2 for %3',lang($acl_manager['name']),$GLOBALS['phpgw_info']['apps'][$_GET['acl_app']]['title'],$afn));
			$link_values = array(
				'menuaction' => 'admin.uiaclmanager.access_form',
				'acl_app'    => $_GET['acl_app'],
				'location'   => urlencode($_GET['location']),
				'account_id' => $this->account_id
			);

			$acl    = createobject('phpgwapi.acl',$this->account_id);
			$acl->read_repository();
			$grants = $acl->get_rights($location,$_GET['acl_app']);

			$this->template->set_var('form_action',$GLOBALS['phpgw']->link('/index.php',$link_values));

			$total = 0;
			while (list($name,$value) = each($acl_manager['rights']))
			{
				$cb .= '<input type="checkbox" name="acl_rights[]" value="'.$value.'"'.($grants & $value ? ' checked' : '').'>&nbsp;'.lang($name)."<br>\n";
			}
			$this->template->set_var('select_values',$cb);
			$this->template->set_var('lang_submit',lang('Save'));
			$this->template->set_var('lang_cancel',lang('Cancel'));

			$this->template->pfp('out','form');
		}
	}
