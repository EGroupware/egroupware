<?php
  /**************************************************************************\
  * phpGroupWare - Addressbook                                               *
  * http://www.phpgroupware.org                                              *
  * Written by Joseph Engo <jengo@phpgroupware.org> and                      *
  * Miles Lott <miloschphpgroupware.org>                                     *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	class uivcard
	{
		var $template;
		var $contacts;
		var $browser;
		var $vcard;
		var $bo;

		var $public_functions = array(
			'in'  => True,
			'out' => True
		);

		var $extrafields = array(
			'ophone'   => 'ophone',
			'address2' => 'address2',
			'address3' => 'address3'
		);

		function uivcard()
		{
			$this->template = $GLOBALS['phpgw']->template;
			$this->contacts = CreateObject('phpgwapi.contacts');
			$this->browser  = CreateObject('phpgwapi.browser');
			$this->vcard    = CreateObject('phpgwapi.vcard');
			$this->bo = CreateObject('addressbook.boaddressbook',True);
		}

		function in()
		{
			$action = $GLOBALS['HTTP_POST_VARS']['action'];

			$GLOBALS['phpgw']->common->phpgw_header();

			echo '<body bgcolor="' . $GLOBALS['phpgw_info']['theme']['bg_color'] . '">';
  
			if($action == 'GetFile')
			{
				echo '<b><center>' . lang('You must select a vcard. (*.vcf)') . '</b></center><br><br>';
			}

			$this->template->set_file(array('vcardin' => 'vcardin.tpl'));

			$this->template->set_var('vcard_header','<p>&nbsp;<b>' . lang('Address book - VCard in') . '</b><hr><p>');
			$this->template->set_var('action_url',$GLOBALS['phpgw']->link('/index.php','menuaction=addressbook.boaddressbook.add_vcard'));
			$this->template->set_var('lang_access',lang('Access'));
			$this->template->set_var('lang_groups',lang('Which groups'));
			$this->template->set_var('access_option',$access_option);
			$this->template->set_var('group_option',$group_option);

			$this->template->pparse('out','vcardin');
		}

		function out()
		{
			$ab_id   = get_var('ab_id',array('GET','POST'));
			$nolname = get_var('nolname',array('GET','POST'));
			$nofname = get_var('nofname',array('GET','POST'));

			if($nolname || $nofname)
			{
				$GLOBALS['phpgw']->common->phpgw_header();
			}

			if(!$ab_id)
			{
				Header('Location: ' . $GLOBALS['phpgw']->link('/addressbook/index.php'));
				$GLOBALS['phpgw_info']['flags']['nodisplay'] = True;
				exit;
			}

			// First, make sure they have permission to this entry
			$check = $this->bo->read_entry(array(
				'id' => $ab_id,
				'fields' => array(
					'owner' => 'owner'
				)
			));
			$perms = $this->contacts->check_perms($this->contacts->grants[$check[0]['owner']],PHPGW_ACL_READ);

			if((!$perms) && ($check[0]['owner'] != $GLOBALS['phpgw_info']['user']['account_id']))
			{
				Header('Location: ' . $GLOBALS['phpgw']->link('/index.php','menuaction=addressbook.uiaddressbook.get_list'));
				$GLOBALS['phpgw_info']['flags']['nodisplay'] = True;
				exit;
			}

			$extrafields = array('address2' => 'address2');
			$qfields = $this->contacts->stock_contact_fields + $extrafields;

			$fieldlist = $this->bo->read_entry(array('id' => $ab_id, 'fields' => $qfields));
			$fields = $fieldlist[0];

			$email        = $fields['email'];
			$emailtype    = $fields['email_type'];
			if (!$emailtype)
			{
				$fields['email_type'] = 'INTERNET';
			}
			$hemail       = $fields['email_home'];
			$hemailtype   = $fields['email_home_type'];
			if (!$hemailtype)
			{
				$fields['email_home_type'] = 'INTERNET';
			}
			$firstname    = $fields['n_given'];
			$lastname     = $fields['n_family'];

			if(!$nolname && !$nofname)
			{
				/* First name and last must be in the vcard. */
				if($lastname == '')
				{
					/* Run away here. */
					Header('Location: ' . $GLOBALS['phpgw']->link('/index.php','menuaction=addressbook.uivcard.out&nolname=1&ab_id=' . $ab_id));
				}
				if($firstname == '')
				{
					Header('Location: ' . $GLOBALS['phpgw']->link('/index.php','menuaction=addressbook.uivcard.out&nofname=1&ab_id=' . $ab_id));
				}

				if ($email)
				{
					$fn =  explode('@',$email);
					$filename = sprintf('%s.vcf', $fn[0]);
				}
				elseif ($hemail)
				{
					$fn =  explode('@',$hemail);
					$filename = sprintf('%s.vcf', $fn[0]);
				}
				else
				{
					$fn = strtolower($firstname);
					$filename = sprintf('%s.vcf', $fn);
				}

				// set translation variable
				$myexport = $this->vcard->export;
				// check that each $fields exists in the export array and
				// set a new array to equal the translation and original value
				while(list($name,$value) = each($fields))
				{
					if($myexport[$name] && ($value != ''))
					{
						//echo '<br>'.$name.'='.$fields[$name]."\n";
						$buffer[$myexport[$name]] = $value;
					}
				}

				// create a vcard from this translated array
				$entry = $this->vcard->out($buffer);
				// print it using browser class for headers
				// filename, mimetype, no length, default nocache True
				$this->browser->content_header($filename,'text/x-vcard');
				echo $entry;
				$GLOBALS['phpgw']->common->exit;
			} /* !nolname && !nofname */

			if($nofname)
			{
				echo '<br><br><center>';
				echo lang("This person's first name was not in the address book.") .'<br>';
				echo lang('Vcards require a first name entry.') . '<br><br>';
				echo '<a href="' . $GLOBALS['phpgw']->link('/addressbook/index.php') . '">' . lang('OK') . '</a>';
				echo '</center>';
			}

			if($nolname)
			{
				echo '<br><br><center>';
				echo lang("This person's last name was not in the address book.") . '<br>';
				echo lang('Vcards require a last name entry.') . '<br><br>';
				echo '<a href="' . $GLOBALS['phpgw']->link('/addressbook/index.php') . '">' . lang('OK') . '</a>';
				echo '</center>';
			}
		}
	}
?>
