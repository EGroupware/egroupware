<?php
	/**************************************************************************\
	* eGroupWare - Administration                                              *
	* http://www.egroupware.org                                                *
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
			$this->account_id = (int)$_GET['account_id'];
			if (!$this->account_id || $GLOBALS['egw']->acl->check('account_access',64,'admin'))
			{
				$GLOBALS['egw']->redirect_link('/index.php');
			}
			$this->template =& CreateObject('phpgwapi.Template',EGW_APP_TPL);
		}

		function common_header()
		{
			$GLOBALS['egw_info']['flags']['app_header'] = lang('Admin') . ' - ' . lang('ACL Manager') .
				': ' . $GLOBALS['egw']->common->grab_owner_name($this->account_id);
			$GLOBALS['egw']->common->egw_header();
			echo parse_navbar();
		}

		function list_apps()
		{
			$this->common_header();

			$GLOBALS['egw']->hooks->process('acl_manager',array('preferences'));

			$this->template->set_file(array(
				'app_list'   => 'acl_applist.tpl'
			));
			$this->template->set_block('app_list','list');
			$this->template->set_block('app_list','app_row');
			$this->template->set_block('app_list','app_row_noicon');
			$this->template->set_block('app_list','link_row');
			$this->template->set_block('app_list','spacer_row');

			if (is_array($GLOBALS['acl_manager']))
			{
				foreach($GLOBALS['acl_manager'] as $app => $locations)
				{
					$icon = $GLOBALS['egw']->common->image($app,array('navbar.png',$app.'png','navbar.gif',$app.'.gif'));
					$this->template->set_var('icon_backcolor',$GLOBALS['egw_info']['theme']['row_off']);
					$this->template->set_var('link_backcolor',$GLOBALS['egw_info']['theme']['row_off']);
					$this->template->set_var('app_name',$GLOBALS['egw_info']['apps'][$app]['title']);
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

					if (is_array($locations))
					{
						foreach($locations as $loc => $value)
						{
							$link_values = array(
								'menuaction' => 'admin.uiaclmanager.access_form',
								'location'   => $loc,
								'acl_app'    => $app,
								'account_id' => $this->account_id
							);

							$this->template->set_var('link_location',$GLOBALS['egw']->link('/index.php',$link_values));
							$this->template->set_var('lang_location',lang($value['name']));
							$this->template->fp('rows','link_row',True);
						}
					}

					$this->template->parse('rows','spacer_row',True);
				}
			}
			$this->template->set_var(array(
				'cancel_action' => $GLOBALS['egw']->link('/admin/index.php'),
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
					if (is_array($_POST['acl_rights']))
					{
						foreach($_POST['acl_rights'] as $rights)
						{
							$total_rights += $rights;
						}
					}
					if ($total_rights)
					{
						$GLOBALS['egw']->acl->add_repository($_GET['acl_app'], $location, $this->account_id, $total_rights);
					}
					else	// we dont need to save 0 rights (= no restrictions)
					{
						$GLOBALS['egw']->acl->delete_repository($_GET['acl_app'], $location, $this->account_id);
					}
				}
				$this->list_apps();
				return;
			}
			$GLOBALS['egw']->hooks->single('acl_manager',$_GET['acl_app']);
			$acl_manager = $GLOBALS['acl_manager'][$_GET['acl_app']][$location];

			$this->common_header();
			$this->template->set_file('form','acl_manager_form.tpl');

			$acc =& CreateObject('phpgwapi.accounts',$this->account_id);
			$acc->read_repository();
			$afn = $GLOBALS['egw']->common->display_fullname($acc->data['account_lid'],$acc->data['firstname'],$acc->data['lastname']);

			$this->template->set_var('lang_message',lang('Check items to <b>%1</b> to %2 for %3',lang($acl_manager['name']),$GLOBALS['egw_info']['apps'][$_GET['acl_app']]['title'],$afn));
			$link_values = array(
				'menuaction' => 'admin.uiaclmanager.access_form',
				'acl_app'    => $_GET['acl_app'],
				'location'   => urlencode($_GET['location']),
				'account_id' => $this->account_id
			);

			$acl    =& CreateObject('phpgwapi.acl',$this->account_id);
			$acl->read_repository();
			$grants = $acl->get_rights($location,$_GET['acl_app']);

			$this->template->set_var('form_action',$GLOBALS['egw']->link('/index.php',$link_values));

			$total = 0;
			foreach($acl_manager['rights'] as $name => $value)
			{
				$cb .= '<input type="checkbox" name="acl_rights[]" value="'.$value.'"'.($grants & $value ? ' checked' : '').'>&nbsp;'.lang($name)."<br>\n";
			}
			$this->template->set_var('select_values',$cb);
			$this->template->set_var('lang_submit',lang('Save'));
			$this->template->set_var('lang_cancel',lang('Cancel'));

			$this->template->pfp('out','form');
		}
	}
