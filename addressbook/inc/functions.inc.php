<?php
  /**************************************************************************\
  * phpGroupWare - addressbook                                               *
  * http://www.phpgroupware.org                                              *
  * Written by Joseph Engo <jengo@mail.com>                                  *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	/* I don't think this is needed anymore */
	/* Perform acl check, set $rights */
	if(!isset($owner)) { $owner = 0; } 

	$grants = $phpgw->acl->get_grants('addressbook');

	if(!isset($owner) || !$owner)
	{
		$owner = $phpgw_info['user']['account_id'];
		$rights = PHPGW_ACL_READ + PHPGW_ACL_ADD + PHPGW_ACL_EDIT + PHPGW_ACL_DELETE + 16;
	}
	else
	{
		if($grants[$owner])
		{
			$rights = $grants[$owner];
			if (!($rights & PHPGW_ACL_READ))
			{
				$owner = $phpgw_info['user']['account_id'];
				$rights = PHPGW_ACL_READ + PHPGW_ACL_ADD + PHPGW_ACL_EDIT + PHPGW_ACL_DELETE + 16;
			}
		}
	}

	function formatted_list($name,$list,$id='',$default=False,$java=False)
	{
		if ($java)
		{
			$jselect = ' onChange="this.form.submit();"';
		}

		$select  = "\n" .'<select name="' . $name . '"' . $jselect . ">\n";
		if($default)
		{
			$select .= '<option value="">' . lang('Please Select') . '</option>'."\n";
		}
		while (list($key,$val) = each($list))
		{
			$select .= '<option value="' . $key . '"';
			if ($key == $id && $id != '')
			{
				$select .= ' selected';
			}
			$select .= '>' . $val . '</option>'."\n";
		}

		$select .= '</select>'."\n";
		$select .= '<noscript><input type="submit" name="' . $name . '_select" value="True"></noscript>' . "\n";

		return $select;
	}

	/* Return a select form element with the categories option dialog in it */
	function cat_option($cat_id='',$notall=False,$java=True,$multiple=False)
	{
		global $phpgw;

		if ($java)
		{
			$jselect = ' onChange="this.form.submit();"';
		}
		/* Setup all and none first */
		$cats_link  = "\n" .'<select name="cat_id'.($multiple?'[]':'').'"' .$jselect . ($multiple ? 'multiple size="3"' : '') . ">\n";
		if (!$notall)
		{
			$cats_link .= '<option value=""';
			if ($cat_id=="all")
			{
				$cats_link .= ' selected';
			}
			$cats_link .= '>'.lang("all").'</option>'."\n";
		}

		/* Get global and app-specific category listings */
		$cats_link .= $phpgw->categories->formated_list('select','all',$cat_id,True);
		$cats_link .= '</select>'."\n";
		return $cats_link;
	}

	/* this cleans up the fieldnames for preferences */
	function display_name($column)
	{
		$abc = array(
			'fn'                  => 'full name',
			'sound'               => 'Sound',
			'org_name'            => 'company name',
			'org_unit'            => 'department',
			'title'               => 'title',
			'n_prefix'            => 'prefix',
			'n_given'             => 'first name',
			'n_middle'            => 'middle name',
			'n_family'            => 'last name',
			'n_suffix'            => 'suffix',
			'label'               => 'label',
			'adr_one_street'      => 'business street',
			'adr_one_locality'    => 'business city',
			'adr_one_region'      => 'business state',
			'adr_one_postalcode'  => 'business zip code',
			'adr_one_countryname' => 'business country',
			'adr_one_type'        => 'business address type',
			'adr_two_street'      => 'home street',
			'adr_two_locality'    => 'home city',
			'adr_two_region'      => 'home state',
			'adr_two_postalcode'  => 'home zip code',
			'adr_two_countryname' => 'home country',
			'adr_two_type'        => 'home address type',
			'tz'                  => 'time zone',
			'geo'                 => 'geo',
			'tel_work'            => 'business phone',
			'tel_home'            => 'home phone',
			'tel_voice'           => 'voice phone',
			'tel_msg'             => 'message phone',
			'tel_fax'             => 'fax',
			'tel_pager'           => 'pager',
			'tel_cell'            => 'mobile phone',
			'tel_bbs'             => 'bbs phone',
			'tel_modem'           => 'modem phone',
			'tel_isdn'            => 'isdn phone',
			'tel_car'             => 'car phone',
			'tel_video'           => 'video phone',
			'tel_prefer'          => 'preferred phone',
			'email'               => 'business email',
			'email_type'          => 'business email type',
			'email_home'          => 'home email',
			'email_home_type'     => 'home email type',
			'address2'            => 'address line 2',
			'address3'            => 'address line 3',
			'ophone'              => 'Other Phone',
			'bday'                => 'birthday',
			'url'                 => 'url',
			'pubkey'              => 'public key',
			'note'                => 'notes'
		);
	
		if ($abc[$column])
		{
			return lang($abc[$column]);
		}
		else
		{
			return;
		}
	}

?>
