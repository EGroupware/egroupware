<?php
	/**************************************************************************\
	* eGroupWare - resources                                                   *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	* --------------------------------------------                             *
	\**************************************************************************/

	/* $Id$ */
	
	class ui_acl
	{
		var $start = 0;
		var $query = '';
		var $sort  = '';
		var $order = '';
		var $bo;
		var $accounts;
		var $nextmatchs = '';
		var $rights;
		var $public_functions = array(
			'acllist' 	=> True,
			);

		function ui_acl()
		{
			$this->bo = createobject('resources.bo_acl',True);
			$this->accounts = $GLOBALS['phpgw']->accounts->get_list();
			$this->nextmatchs = createobject('phpgwapi.nextmatchs');
			$this->start = $this->bo->start;
			$this->query = $this->bo->query;
			$this->order = $this->bo->order;
			$this->sort = $this->bo->sort;
			$this->cat_id = $this->bo->cat_id;
		}
		
		function acllist()
		{
			if (!$GLOBALS['phpgw']->acl->check('run',1,'admin'))
			{
				$this->deny();
			}

			if ($_POST['btnDone'])
			{
				$GLOBALS['phpgw']->redirect_link('/admin/index.php');
			}

			$GLOBALS['phpgw']->common->phpgw_header();
			echo parse_navbar();

			if ($_POST['btnSave'])
			{
				foreach($_POST['catids'] as $cat_id)
				{
					$this->bo->set_rights($cat_id,$_POST['inputread'][$cat_id],$_POST['inputwrite'][$cat_id],$_POST['inputbook'][$cat_id],$_POST['inputadmin'][$cat_id]);
				}
			}

			$GLOBALS['phpgw']->template->set_file('acl', 'acl.tpl');
			$GLOBALS['phpgw']->template->set_block('acl','cat_list','Cblock');
			$GLOBALS['phpgw']->template->set_var(array(
				'title' => $GLOBALS['phpgw_info']['apps']['resources']['title'] . ' - ' . lang('Configure Access Permissions'),
				'lang_search' => lang('Search'),
				'lang_save' => lang('Save'),
				'lang_done' => lang('Done'),
				'lang_read' => lang('Read permissions'),
				'lang_write' => lang('Write permissions'),
				'lang_implies' => lang('implies read permission'),
				'lang_book' => lang('Direct booking permissions'),
				'lang_cat_admin' => lang('Categories admin')
			));

			$left  = $this->nextmatchs->left('/index.php',$this->start,$this->bo->catbo->total_records,'menuaction=resources.uiacl.acllist');
			$right = $this->nextmatchs->right('/index.php',$this->start,$this->bo->catbo->total_records,'menuaction=resources.uiacl.acllist');
			
			$GLOBALS['phpgw']->template->set_var(array(
				'left' => $left,
				'right' => $right,
				'lang_showing' => $this->nextmatchs->show_hits($this->bo->catbo->total_records,$this->start),
				'th_bg' => $GLOBALS['phpgw_info']['theme']['th_bg'],
				'sort_cat' => $this->nextmatchs->show_sort_order(
					$this->sort,'cat_name','cat_name','/index.php',lang('Category'),'&menuaction=resources.uiacl.acllist'
				),
				'query' => $this->query,
			));

			@reset($this->bo->cats);
			while (list(,$cat) = @each($this->bo->cats))
			{
				$this->rights = $this->bo->get_rights($cat['id']);

				$tr_color = $this->nextmatchs->alternate_row_color($tr_color);
				$GLOBALS['phpgw']->template->set_var(array(
					'tr_color' => $tr_color,
					'catname' => $cat['name'],
					'catid' => $cat['id'],
					'read' => $this->selectlist(PHPGW_ACL_READ),
					'write' => $this->selectlist(PHPGW_ACL_ADD),
					'book' =>$this->selectlist(PHPGW_ACL_DIRECT_BOOKING),
					'admin' => '<option value="" selected="1">'.lang('choose categories admin').'</option>'.$this->selectlist(PHPGW_ACL_CAT_ADMIN,true)
				));
				$GLOBALS['phpgw']->template->parse('Cblock','cat_list',True);
			}
			$GLOBALS['phpgw']->template->pfp('out','acl',True);
		}

		function selectlist($right,$users_only=false)
		{
			reset($this->bo->accounts);
			while (list($null,$account) = each($this->bo->accounts))
			{
				if(!($users_only && $account['account_lastname'] == 'Group'))
				{
 					$selectlist .= '<option value="' . $account['account_id'] . '"';
  					if($this->rights[$account['account_id']] & $right)
 					{
 						$selectlist .= ' selected="selected"';
 					}
					$selectlist .= '>' . $account['account_firstname'] . ' ' . $account['account_lastname']
										. ' [ ' . $account['account_lid'] . ' ]' . '</option>' . "\n";
				}
			}
			return $selectlist;
		}

		function deny()
		{
			echo '<p><center><b>'.lang('Access not permitted').'</b></center>';
			$GLOBALS['phpgw']->common->phpgw_exit(True);
		}
	}
?>
