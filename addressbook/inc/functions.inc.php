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

	// I don't think this is needed anymore

	// Perform acl check, set $rights

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

	function read_custom_fields($start='',$limit='',$query='',$sort='ASC')
	{
		global $phpgw,$phpgw_info;
		$phpgw->preferences->read_repository($phpgw_info['user']['account_id']);

		$i=0;$j=0;
		while (list($col,$descr) = @each($phpgw_info["user"]["preferences"]["addressbook"]))
		{
			if ( substr($col,0,6) == 'extra_' )
			{
				$fields[$j]['name'] = ereg_replace('extra_','',$col);
				$fields[$j]['name'] = ereg_replace(' ','_',$fields[$j]['name']);
				$fields[$j]['id'] = $i;

				if ($query && ($fields[$j]['name'] != $query))
				{
					unset($fields[$j]['name']);
					unset($fields[$j]['id']);
				}
				else
				{
					//echo "<br>".$j.": '".$fields[$j]['name']."'";
					$j++;
				}
			}
			$i++;
		}
		return $fields;
	}

	function save_custom_field($old='',$new='')
	{
		global $phpgw,$phpgw_info;
		
		$phpgw->preferences->read_repository($phpgw_info['user']['account_id']);
		if ($old)
		{
			$phpgw->preferences->delete("addressbook","extra_".$old);
		}
		if($new)
		{
			$phpgw->preferences->add("addressbook","extra_".$new);
		}
		$phpgw->preferences->save_repository(1);
	}

	// Return a select form element with the categories option dialog in it
	function cat_option($cat_id='',$notall=False,$java=True,$multiple=False) {
		global $phpgw_info;
		if ($java)
		{
			$jselect = ' onChange="this.form.submit();"';
		}
		// Setup all and none first
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

		// Get global and app-specific category listings
		$cats       = CreateObject('phpgwapi.categories');
		$cats_link .= $cats->formated_list('select','all',$cat_id,True);
		$cats_link .= '</select>'."\n";
		return $cats_link;
	}

	### SET THE FONT TO DEFAULT IF IT DOESNT EXISTS ###
	function set_font()
	{
		if($phpgw_info["user"]["preferences"]["notes"]["notes_font"] == "")
		{
			$font = "Arial";
			return $font;
		}
		else
		{
			$font = $phpgw_info["user"]["preferences"]["notes"]["notes_font"];
			return $font;
		}
	}

	### SET FONT SIZE ####
	function set_font_size()
	{
		if($phpgw_info["user"]["preferences"]["notes"]["notes_font_size"] == "") {
			$font_size = "3";
			return $font_size;
		}
		else
		{
			$font_size = $phpgw_info["user"]["preferences"]["notes"]["notes_font_size"];
			return $font_size;
		}
	}

	// this cleans up the fieldnames for display
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

	function addressbook_strip_html($dirty = "")
	{
		global $phpgw;
		if ($dirty == ""){$dirty = array();}
		for($i=0;$i<count($dirty);$i++)
		{
			while (list($name,$value) = each($dirty[$i])) {
				$cleaned[$i][$name] = $phpgw->strip_html($dirty[$i][$name]);
			}
		}
		return $cleaned;
	}

	function addressbook_read_entries($start,$offset,$qcols,$query,$qfilter,$sort,$order,$userid="")
	{
		global $this,$rights;
		$readrights = $rights & PHPGW_ACL_READ;
		$entries = $this->read($start,$offset,$qcols,$query,$qfilter,$sort,$order,$readrights);
		$cleaned = addressbook_strip_html($entries);
		return $cleaned;
	}

	function addressbook_read_entry($id,$fields,$userid="")
	{
		global $this,$rights;
		if ($rights & PHPGW_ACL_READ)
		{
			$entry = $this->read_single_entry($id,$fields);
			$cleaned = addressbook_strip_html($entry);
			return $cleaned;
		}
		else
		{
			$rtrn = array("No access" => "No access");
			return $rtrn;
		}
	}

	function addressbook_read_last_entry($fields)
	{
		global $this,$rights;
		if ($rights & PHPGW_ACL_READ)
		{
			$entry = $this->read_last_entry($fields);
			$cleaned = addressbook_strip_html($entry);
			return $cleaned;
		}
		else
		{
			$rtrn = array("No access" => "No access");
			return $rtrn;
		}
	}

	function addressbook_add_entry($userid,$fields,$access='',$cat_id='',$tid='n')
	{
		global $this,$rights;
		if ($rights & PHPGW_ACL_ADD)
		{
			$this->add($userid,$fields,$access,$cat_id,$tid);
		}
		return;
	}

	function addressbook_get_lastid()
	{
		global $this;
	 	$entry = $this->read_last_entry();
		$ab_id = $entry[0]["id"];
		return $ab_id;
	}

	function addressbook_update_entry($id,$userid,$fields,$access,$cat_id)
	{
		global $this,$rights;
		if ($rights & PHPGW_ACL_EDIT)
		{
			$this->update($id,$userid,$fields,$access,$cat_id);
		}
		return;
	}

	// Folowing used for add/edit
	function addressbook_form($format,$action,$title="",$fields="",$customfields="",$cat_id="")
	{
		global $phpgw,$phpgw_info,$referer;

		$t = new Template(PHPGW_APP_TPL);
		$t->set_file(array('form' => 'form.tpl'));
		//$t->set_block('form','add','add');
		//$t->set_block('form','edit','edit');

		$phpgw->config->read_repository();
		$countrylist = $phpgw->config->config_data['countrylist'];

		$email        = $fields['email'];
		$emailtype    = $fields['email_type'];
		$hemail       = $fields['email_home'];
		$hemailtype   = $fields['email_home_type'];
		$firstname    = $fields['n_given'];
		$middle       = $fields['n_middle'];
		$prefix       = $fields['n_prefix'];
		$suffix       = $fields['n_suffix'];
		$lastname     = $fields['n_family'];
		$title        = $fields['title'];
		$wphone       = $fields['tel_work'];
		$hphone       = $fields['tel_home'];
		$fax          = $fields['tel_fax'];
		$pager        = $fields['tel_pager'];
		$mphone       = $fields['tel_cell'];
		$ophone       = $fields['ophone'];
		$msgphone     = $fields['tel_msg'];
		$isdnphone    = $fields['tel_isdn'];
		$carphone     = $fields['tel_car'];
		$vidphone     = $fields['tel_video'];
		$preferred    = $fields['tel_prefer'];

		$bstreet      = $fields['adr_one_street'];
		$address2     = $fields['address2'];
		$address3     = $fields['address3'];
		$bcity        = $fields['adr_one_locality'];
		$bstate       = $fields['adr_one_region'];
		$bzip         = $fields['adr_one_postalcode'];
		$bcountry     = $fields['adr_one_countryname'];
		$one_dom      = $fields['one_dom'];
		$one_intl     = $fields['one_intl'];
		$one_parcel   = $fields['one_parcel'];
		$one_postal   = $fields['one_postal'];

		$hstreet      = $fields['adr_two_street'];
		$hcity        = $fields['adr_two_locality'];
		$hstate       = $fields['adr_two_region'];
		$hzip         = $fields['adr_two_postalcode'];
		$hcountry     = $fields['adr_two_countryname'];
		$btype        = $fields['adr_two_type'];
		$two_dom      = $fields['two_dom'];
		$two_intl     = $fields['two_intl'];
		$two_parcel   = $fields['two_parcel'];
		$two_postal   = $fields['two_postal'];

		$timezone     = $fields['tz'];
		$bday         = $fields['bday'];
		$notes        = stripslashes($fields['note']);
		$label        = stripslashes($fields['label']);
		$company      = $fields['org_name'];
		$department   = $fields['org_unit'];
		$url          = $fields['url'];
		$pubkey       = $fields['pubkey'];
		$access       = $fields['access'];
		if(!$cat_id) {
			$cat_id   = $fields['cat_id'];
		}
																		// allow multiple categories on 'sql'
		$cats_link    = cat_option($cat_id,True,False,!$phpgw_info["server"]["contact_repository"] || 
																	  $phpgw_info["server"]["contact_repository"] == 'sql');

		if ($access == 'private')
		{
			$access_check = ' checked';
		}
		else
		{
			$access_check = '';
		}

		if ($customfields)
		{
			while(list($name,$value) = each($customfields))
			{
				$custom .= '
  <tr>
    <td></td>
	<td><font color="#000000" face="" size="-1">'.$value.':</font></td>
    <td>
	  <font size="-1">
	  <INPUT size="30" name="' . $name . '" value="' . $fields[$name] . '">
      </font></td>
	</td>
  </tr>
';
			}
		}

		$this = CreateObject("phpgwapi.contacts");

		if ($format != "view")
		{
			// Preferred phone number radio buttons
			$pref[0] = "<font size=\"-2\">";
			$pref[1] = "(".lang('pref').")</font>";
			while (list($name,$val) = each($this->tel_types))
			{
				$str[$name] = "\n".'      <input type="radio" name="tel_prefer" value="'.$name.'"';
				if ($name == $preferred)
				{
					$str[$name] .= ' checked';
				}
				$str[$name] .= '>';
				$str[$name] = $pref[0].$str[$name].$pref[1];
				$t->set_var("pref_".$name,$str[$name]);
			}

			if (strlen($bday) > 2)
			{
				list( $month, $day, $year ) = split( '/', $bday );
				$temp_month[$month] = "SELECTED";
				$bday_month = "<select name=bday_month>"
					. "<option value=\"\" $temp_month[0]> </option>"
					. "<option value=1  $temp_month[1]>"  . lang('january')   . "</option>" 
					. "<option value=2  $temp_month[2]>"  . lang('february')  . "</option>"
					. "<option value=3  $temp_month[3]>"  . lang('march')     . "</option>"
					. "<option value=4  $temp_month[4]>"  . lang('april')     . "</option>"
					. "<option value=5  $temp_month[5]>"  . lang('may')       . "</option>"
					. "<option value=6  $temp_month[6]>"  . lang('june')      . "</option>" 
					. "<option value=7  $temp_month[7]>"  . lang('july')      . "</option>"
					. "<option value=8  $temp_month[8]>"  . lang('august')    . "</option>"
					. "<option value=9  $temp_month[9]>"  . lang('september') . "</option>"
					. "<option value=10 $temp_month[10]>" . lang('october')   . "</option>"
					. "<option value=11 $temp_month[11]>" . lang('november')  . "</option>"
					. "<option value=12 $temp_month[12]>" . lang('december')  . "</option>"
					. "</select>";
				$bday_day   = '<input maxlength="2" name="bday_day" value="' . $day . '" size="2">';
				$bday_year  = '<input maxlength="4" name="bday_year" value="' . $year . '" size="4">';
			}
			else
			{
				$bday_month = "<select name=bday_month>"
					. "<option value=\"\" SELECTED> </option>"
					. "<option value=1>"  . lang('january')   . "</option>"
					. "<option value=2>"  . lang('february')  . "</option>"
					. "<option value=3>"  . lang('march')     . "</option>"
					. "<option value=4>"  . lang('april')     . "</option>"
					. "<option value=5>"  . lang('may')       . "</option>"
					. "<option value=6>"  . lang('june')      . "</option>"
					. "<option value=7>"  . lang('july')      . "</option>"
					. "<option value=8>"  . lang('august')    . "</option>"
					. "<option value=9>"  . lang('september') . "</option>"
					. "<option value=10>" . lang('october')   . "</option>"
					. "<option value=11>" . lang('november')  . "</option>"
					. "<option value=12>" . lang('december')  . "</option>"
					. "</select>";
				$bday_day  = '<input name="bday_day" size="2" maxlength="2">';
				$bday_year = '<input name="bday_year" size="4" maxlength="4">';
			}

			$time_zone = "<select name=\"timezone\">\n";
			for ($i = -23; $i<24; $i++)
			{
				$time_zone .= "<option value=\"$i\"";
				if ($i == $timezone)
				{
					$time_zone .= " selected";
				}
				if ($i < 1)
				{
					$time_zone .= ">$i</option>\n";
				}
				else
				{
					$time_zone .= ">+$i</option>\n";
				}
			}
			$time_zone .= "</select>\n";

			$email_type = '<select name=email_type>';
			while ($type = each($this->email_types))
			{
				$email_type .= '<option value="'.$type[0].'"';
				if ($type[0] == $emailtype) { $email_type .= ' selected'; }
				$email_type .= '>'.$type[1].'</option>';
			}
			$email_type .= "</select>";

			reset($this->email_types);
			$hemail_type = '<select name=hemail_type>';
			while ($type = each($this->email_types))
			{
				$hemail_type .= '<option value="'.$type[0].'"';
				if ($type[0] == $hemailtype) { $hemail_type .= ' selected'; }
				$hemail_type .= '>'.$type[1].'</option>';
			}
			$hemail_type .= "</select>";

			reset($this->adr_types);
			while (list($type,$val) = each($this->adr_types))
			{
				$badrtype .= "\n".'<INPUT type="checkbox" name="one_'.$type.'"';
				$ot = 'one_'.$type;
				eval("
					if (\$$ot=='on') {
						\$badrtype \.= ' value=\"on\" checked';
					}
				");
				$badrtype .= '>'.$val;
			}

			reset($this->adr_types);
			while (list($type,$val) = each($this->adr_types))
			{
				$hadrtype .= "\n".'<INPUT type="checkbox" name="two_'.$type.'"';
				$tt = 'two_'.$type;
				eval("
					if (\$$tt=='on') {
						\$hadrtype \.= ' value=\"on\" checked';
					}
				");
				$hadrtype .= '>'.$val;
			}

			$notes  = '<TEXTAREA cols="60" name="notes" rows="4">' . $notes . '</TEXTAREA>';
			$label  = '<TEXTAREA cols="60" name="label" rows="6">' . $label . '</TEXTAREA>';
			$pubkey = '<TEXTAREA cols="60" name="pubkey" rows="6">' . $pubkey . '</TEXTAREA>';
		}
		else
		{
			$notes = "<form><TEXTAREA cols=\"60\" name=\"notes\" rows=\"4\">"
					. $notes . "</TEXTAREA></form>";
			if ($bday == "//")
			{
				$bday = "";
			}
		}

		if ($action)
		{
			echo '<FORM action="' . $phpgw->link('/addressbook/' . $action, 'referer='.urlencode($referer)).'" method="post">';
		}

		if (!ereg("^http://",$url))
		{
			$url = "http://". $url;
		} 

		$birthday = $phpgw->common->dateformatorder($bday_year,$bday_month,$bday_day)
			. '<font face="'.$theme["font"].'" size="-2">(e.g. 1969)</font>';
		if ($format == 'edit')
		{
			$create .= '<tr><td><font size="-1">' . lang("Created by") . ':</font></td>'
				. '<td colspan="3"><font size="-1">'
				. $phpgw->common->grab_owner_name($fields["owner"]);
		}
		else
		{
			$create .= '';
		}
 
		$t->set_var('lang_home',lang('Home'));
		$t->set_var('lang_business',lang('Business'));
		$t->set_var('lang_personal',lang('Personal'));

		$t->set_var('lang_lastname',lang('Last Name'));
		$t->set_var('lastname',$lastname);
		$t->set_var('lang_firstname',lang('First Name'));
		$t->set_var('firstname',$firstname);
		$t->set_var('lang_middle',lang('Middle Name'));
		$t->set_var('middle',$middle);
		$t->set_var('lang_prefix',lang('Prefix'));
		$t->set_var('prefix',$prefix);
		$t->set_var('lang_suffix',lang('Suffix'));
		$t->set_var('suffix',$suffix);
		$t->set_var('lang_birthday',lang('Birthday'));
		$t->set_var('birthday',$birthday);

		$t->set_var('lang_company',lang('Company Name'));
		$t->set_var('company',$company);
		$t->set_var('lang_department',lang('Department'));
		$t->set_var('department',$department);
		$t->set_var('lang_title',lang('Title'));
		$t->set_var('title',$title);
		$t->set_var('lang_email',lang('Business Email'));
		$t->set_var('email',$email);
		$t->set_var('lang_email_type',lang('Business EMail Type'));
		$t->set_var('email_type',$email_type);
		$t->set_var('lang_url',lang('URL'));
		$t->set_var('url',$url);
		$t->set_var('lang_timezone',lang('time zone offset'));
		$t->set_var('timezone',$time_zone);
		$t->set_var('lang_fax',lang('Business Fax'));
		$t->set_var('fax',$fax);
		$t->set_var('lang_wphone',lang('Business Phone'));
		$t->set_var('wphone',$wphone);
		$t->set_var('lang_pager',lang('Pager'));
		$t->set_var('pager',$pager);
		$t->set_var('lang_mphone',lang('Cell Phone'));
		$t->set_var('mphone',$mphone);
		$t->set_var('lang_msgphone',lang('Message Phone'));
		$t->set_var('msgphone',$msgphone);
		$t->set_var('lang_isdnphone',lang('ISDN Phone'));
		$t->set_var('isdnphone',$isdnphone);
		$t->set_var('lang_carphone',lang('Car Phone'));
		$t->set_var('carphone',$carphone);
		$t->set_var('lang_vidphone',lang('Video Phone'));
		$t->set_var('vidphone',$vidphone);

		$t->set_var('lang_ophone',lang('Other Number'));
		$t->set_var('ophone',$ophone);
		$t->set_var('lang_bstreet',lang('Business Street'));
		$t->set_var('bstreet',$bstreet);
		$t->set_var('lang_address2',lang('Address Line 2'));
		$t->set_var('address2',$address2);
		$t->set_var('lang_address3',lang('Address Line 3'));
		$t->set_var('address3',$address3);
		$t->set_var('lang_bcity',lang('Business City'));
		$t->set_var('bcity',$bcity);
		$t->set_var('lang_bstate',lang('Business State'));
		$t->set_var('bstate',$bstate);
		$t->set_var('lang_bzip',lang('Business Zip Code'));
		$t->set_var('bzip',$bzip);
		$t->set_var('lang_bcountry',lang('Business Country'));
		$t->set_var('bcountry',$bcountry);
		if ($countrylist)
		{
			$t->set_var('bcountry',$phpgw->country->form_select($bcountry,'bcountry'));
		}
		else
		{
			 $t->set_var('bcountry','<input name="bcountry" value="' . $bcountry . '">');
		}
		$t->set_var('lang_badrtype',lang('Address Type'));
		$t->set_var('badrtype',$badrtype);

		$t->set_var('lang_hphone',lang('Home Phone'));
		$t->set_var('hphone',$hphone);
		$t->set_var('lang_hemail',lang('Home Email'));
		$t->set_var('hemail',$hemail);
		$t->set_var('lang_hemail_type',lang('Home EMail Type'));
		$t->set_var('hemail_type',$hemail_type);
		$t->set_var('lang_hstreet',lang('Home Street'));
		$t->set_var('hstreet',$hstreet);
		$t->set_var('lang_hcity',lang('Home City'));
		$t->set_var('hcity',$hcity);
		$t->set_var('lang_hstate',lang('Home State'));
		$t->set_var('hstate',$hstate);
		$t->set_var('lang_hzip',lang('Home Zip Code'));
		$t->set_var('hzip',$hzip);
		$t->set_var('lang_hcountry',lang('Home Country'));
		if ($countrylist)
		{
			$t->set_var('hcountry',$phpgw->country->form_select($hcountry,'hcountry'));
		}
		else
		{
			$t->set_var('hcountry','<input name="hcountry" value="' . $hcountry . '">');
		}
		$t->set_var('lang_hadrtype',lang('Address Type'));
		$t->set_var('hadrtype',$hadrtype);

		$t->set_var('create',$create);
		$t->set_var('lang_notes',lang('notes'));
		$t->set_var('notes',$notes);
		$t->set_var('lang_label',lang('label'));
		$t->set_var('label',$label);
		$t->set_var('lang_pubkey',lang('Public Key'));
		$t->set_var('pubkey',$pubkey);
		$t->set_var('access_check',$access_check);

		$t->set_var('lang_private',lang('Private'));

		$t->set_var('lang_cats',lang('Category'));
		$t->set_var('cats_link',$cats_link);
		if ($customfields)
		{
			$t->set_var('lang_custom',lang('Custom Fields').':');
			$t->set_var('custom',$custom);
		}
		else
		{
			$t->set_var('lang_custom','');
			$t->set_var('custom','');
		}

		$t->pfp('out','form');
	} //end form function

	function parsevcard($filename,$access='')
	{
		global $phpgw;
		global $phpgw_info;

		$vcard = fopen($filename, "r");
		// Make sure we have a file to read.
		if (!$vcard) {
			fclose($vcard);
			return FALSE;
		}

		// Keep running through this to support vcards
		// with multiple entries.
		while (!feof($vcard)) {
			if(!empty($varray))
				unset($varray);

			// Make sure our file is a vcard.
			// I should deal with empty line at the
			// begining of the file. Those will fail here.
			$vline = fgets($vcard,20);
			$vline = strtolower($vline);
			if(strcmp("begin:vcard", substr($vline, 0, strlen("begin:vcard")) ) != 0) {	
				fclose($vcard);
				return FALSE;
			}
			
			// Write the vcard into an array.
			// You can have multiple vcards in one file.
			// I only deal with halve of that. :)
			// It will only return values from the 1st vcard.
			$varray[0] = "begin";
			$varray[1] = "vcard";
		$i=2;
			while(!feof($vcard) && strcmp("end:vcard", strtolower(substr($vline, 0, strlen("end:vcard"))) ) !=0 ) {
					$vline = fgets($vcard,4096);
				// Check for folded lines and escaped colons '\:'
				$la = explode(":", $vline);

				if (count($la) > 1) {
					$varray[$i] = strtolower($la[0]);
					$i++;

					for($j=1;$j<=count($la);$j++) {
						$varray[$i] .= $la[$j];
					}
					$i++;
				} else { // This is the continuation of a folded line.
					$varray[$i-1] .= $la[0];
				}
			}

			// Add this entry to the addressbook before moving on to the next one.
			fillab($varray);
		} // while(!feof($vcard))

		fclose($vcard);
		return TRUE;
	}


	function fillab($varray,$access='') {
		global $phpgw;
		global $phpgw_info;

		$i=0;

//		while($i < count($varray)) {
//			echo '<br>'.$varray[$i].' %% '.$varray[$i+1];
//			$i++;$i++;
//		}
//		exit;
		// incremented by 2
		while($i < count($varray)) {
			$k = explode(";",$varray[$i]); // Key
			$v = explode(";",$varray[$i+1]); // Values
			for($h=0;$h<count($k);$h++) {
				// Cleanup groupings
				$k[$h] = ereg_replace("a\.",'',$k[$h]);
				$k[$h] = ereg_replace("b\.",'',$k[$h]);
				$k[$h] = ereg_replace("c\.",'',$k[$h]);
				$k[$h] = ereg_replace("d\.",'',$k[$h]);
				//echo '<br>kh="'.$k[$h].'",v0="'.$v[0].'",v1="'.$v[1].'",v2="'.$v[2].'",v3="'.$v[3].'",v4="'.$v[4].'",v5="'.$v[5].'",v6="'.$v[6].'",v7="'.$v[7].'"';
				switch($k[$h]) {
					case "fn":
						$fn = $v[0];
						break;
					case "n":
						$lastname  = $v[0];
						$firstname = $v[1];
						break;
					case "bday":
						$bday = $v[0];
						break;
					case "url":
						$url = $v[0];
						// Fix the result of exploding on ':' above
						if (substr($url,0,5) == 'http/') {
							$url = ereg_replace('http//','http://',$url);
						} elseif (substr($url,0,6) == 'https/') {
							$url = ereg_replace('https//','https://',$url);
						} elseif (substr($url,0,7) != 'http://') {
							$url = 'http://' . $url;
						}
						break;
					case "label":
						$label = $v[0];
						break;
					case "adr": // This one is real ugly. Still! :(
						if(!$street) {
							$street   = $v[2];
							$address2 = $v[1] . " " . $v[0];
							$city     = $v[3];
							$state    = $v[4];
							$zip      = $v[5];
							$country  = $v[6];
							if (strstr($k[$h+1],"intl")) { $adronetype .= "INTL;"; }
							if (strstr($k[$h+1],"dom"))  { $adronetype .= "DOM;"; }
						} else {
							$hstreet   = $v[2];
							$hcity     = $v[3];
							$hstate    = $v[4];
							$hzip      = $v[5];
							$hcountry  = $v[6];
							if (strstr($k[$h+1],"intl")) { $adrtwotype .= "INTL;"; }
							if (strstr($k[$h+1],"dom"))  { $adrtwotype .= "DOM;"; }
						}
						break;
					case "tel":
						switch ($k[$h+1]) {
							case "work":
								$wphone = $v[0];
								break;
							case "home":
								$hphone = $v[0];
								break;
							case "cell":
								$mphone = $v[0];
								break;
							case "pager":
								$pager = $v[0];
								break;
							case "fax":
								$fax = $v[0];
								break;
							case "msg":
								$msgphone = $v[0];
								break;
							case "bbs":
								$bbsphone = $v[0];
								break;
							case "modem":
								$modem = $v[0];
								break;
							case "car":
								$carphone = $v[0];
								break;
							case "isdn":
								$isdn = $v[0];
								break;
							case "video":
								$vidphone = $v[0];
								break;
							case "pref":
								switch ($k[$h+2]) {
									case "work":
										$tel_prefer .= "work;";
										$wphone = $v[0];
										break;
									case "home":
										$tel_prefer .= "home;";
										$hphone = $v[0];
										break;
									case "cell":
										$tel_prefer .= "cell;";
										$mphone = $v[0];
										break;
									case "pager":
										$tel_prefer .= "pager;";
										$pager = $v[0];
										break;
									case "fax":
										$tel_prefer .= "fax;";
										$fax = $v[0];
										break;
									case "msg":
										$tel_prefer .= "msg;";
										$msgphone = $v[0];
										break;
									case "bbs":
										$tel_prefer .= "bbs;";
										$bbsphone = $v[0];
										break;
									case "modem":
										$tel_prefer .= "modem;";
										$modem = $v[0];
										break;
									case "car":
										$tel_prefer .= "car;";
										$carphone = $v[0];
										break;
									case "isdn":
										$tel_prefer .= "isdn;";
										$isdn = $v[0];
										break;
									case "video":
										$tel_prefer .= "video;";
										$vidphone = $v[0];
										break;
								}
							default:
								$whphone = $v[0];
								break;
						}
						break;
					case "email":
						if (empty($email)) { $email = $v[0]; }
						else              { $hemail = $v[0]; }
						switch ($k[$h+1]) {
							case "compuserve":
								if (!$adronetype) { $emailtype="CompuServe"; }
								else              { $hemailtype="CompuServe"; }
								break;
							case "aol":
								if (!$adronetype) { $emailtype="AOL"; }
								else              { $hemailtype="AOL"; }
								break;
							case "prodigy":
								if (!$adronetype) { $emailtype="Prodigy"; }
								else              { $hemailtype="Prodigy"; }
								break;
							case "eworld":
								if (!$adronetype) { $emailtype="eWorld"; }
								else              { $hemailtype="eWorld"; }
								break;
							case "applelink":
								if (!$adronetype) { $emailtype="AppleLink"; }
								else              { $hemailtype="AppleLink"; }
								break;
							case "appletalk":
								if (!$adronetype) { $emailtype="AppleTalk"; }
								else              { $hemailtype="AppleTalk"; }
								break;
							case "powershare":
								if (!$adronetype) { $emailtype="PowerShare"; }
								else              { $hemailtype="PowerShare"; }
								break;
							case "ibmmail":
								if (!$adronetype) { $emailtype="IBMMail"; }
								else              { $hemailtype="IBMMail"; }
								break;
							case "attmail":
								if (!$adronetype) { $emailtype="ATTMail"; }
								else              { $hemailtype="ATTMail"; }
								break;
							case "mcimail":
								if (!$adronetype) { $emailtype="MCIMail"; }
								else              { $hemailtype="MCIMail"; }
								break;
							case "x.400":
								if (!$adronetype) { $emailtype="X.400"; }
								else              { $hemailtype="X.400"; }
								break;
							case "tlx":
								if (!$adronetype) { $emailtype="TLX"; }
								else              { $hemailtype="TLX"; }
								break;
							default:
								if (!$adronetype) { $emailtype="INTERNET"; }
								else              { $hemailtype="INTERNET"; }
								break;
						}
						break;
					case "title":
						$title = $v[0];
						break;
					case "org":
						$company = $v[0];
						if(count($v) > 1) {
							$notes .= $v[0] . "\n";
							for($j=1;$j<count($v);$j++) {
								$notes .= $v[$j] . "\n";
							}
						}
						break;
					default:
						break;
				} // switch
			} // for
			$i++;
		} // All of the values that are getting filled are.

		$fields["tel_prefer"]          = substr($tel_prefer,0,-1);
		$fields["owner"]               = $phpgw_info["user"]["account_id"];
		$fields["n_given"]             = addslashes($firstname);
		$fields["n_family"]            = addslashes($lastname);
		$fields["fn"]                  = addslashes($firstname . " " . $lastname);
		$fields["title"]               = addslashes($title);
		$fields["email"]               = addslashes($email);
		$fields["email_type"]          = $emailtype;
		$fields["hemail"]              = addslashes($hemail);
		$fields["hemail_type"]         = $hemailtype;
		$fields["tel_work"]            = addslashes($wphone);
		$fields["tel_home"]            = addslashes($hphone);
		$fields["tel_fax"]             = addslashes($fax);
		$fields["tel_pager"]           = addslashes($pager);
		$fields["tel_cell"]            = addslashes($mphone);
		$fields["tel_msg"]             = addslashes($ophone);
		$fields["tel_car"]             = addslashes($carphone);
		$fields["tel_modem"]           = addslashes($modem);
		$fields["tel_bbs"]             = addslashes($bbsphone);
		$fields["tel_isdn"]            = addslashes($isdn);
		$fields["tel_video"]           = addslashes($vidphone);
		$fields["adr_one_street"]      = addslashes($street);
		$fields["address2"]            = addslashes($address2);
		$fields["adr_one_locality"]    = addslashes($city);
		$fields["adr_one_region"]      = addslashes($state);
		$fields["adr_one_postalcode"]  = addslashes($zip);
		$fields["adr_one_countryname"] = addslashes($country);
		$fields["adr_one_type"]        = substr($adronetype,0,-1);
		$fields["adr_two_street"]      = addslashes($hstreet);
		$fields["adr_two_locality"]    = addslashes($hcity);
		$fields["adr_two_region"]      = addslashes($hstate);
		$fields["adr_two_postalcode"]  = addslashes($hzip);
		$fields["adr_two_countryname"] = addslashes($hcountry);
		$fields["adr_two_type"]        = substr($adrtwotype,0,-1);
		$fields["bday"]                = addslashes($bday);
		$fields["url"]                 = $url;
		$fields["note"]                = addslashes($notes);
		$fields["org_name"]            = addslashes($company);

		/*
		echo '<br>tel_prefer: '.$fields["tel_prefer"];
		echo '<br>owner: '.$fields["owner"];
		echo '<br>firstname: '.$fields["n_given"];
		echo '<br>lastname: '.$fields["n_family"];
		echo '<br>full name: '.$fields["fn"];
		echo '<br>title: '.$fields["title"];
		echo '<br>email: '.$fields["email"];
		echo '<br>work#: '.$fields["tel_work"];
		echo '<br>home#: '.$fields["tel_home"];
		echo '<br>fax#: '.$fields["tel_fax"];
		echo '<br>pager#: '.$fields["tel_pager"];
		echo '<br>cell#: '.$fields["tel_cell"];
		echo '<br>msg#: '.$fields["tel_msg"];
		echo '<br>car#: '.$fields["tel_car"];
		echo '<br>modem# '.$fields["tel_modem"];
		echo '<br>bbs#: '.$fields["tel_bbs"];
		echo '<br>isdn#: '.$fields["tel_isdn"];
		echo '<br>video#: '.$fields["tel_video"];
		echo '<br>street: '.$fields["adr_one_street"];
		echo '<br>addr2: '.$fields["address2"];
		echo '<br>city: '.$fields["adr_one_locality"];
		echo '<br>state: '.$fields["adr_one_region"];
		echo '<br>zip: '.$fields["adr_one_postalcode"];
		echo '<br>adronetype: '.$fields["adr_one_type"];
		echo '<br>bday: '.$fields["bday"];
		echo '<br>url: '.$fields["url"];
		echo '<br>note: '.$fields["note"];
		echo '<br>company: '.$fields["org_name"];
		exit;
		*/
		$this = CreateObject("phpgwapi.contacts");
		$this->add($phpgw_info["user"]["account_id"],$fields);
	}

?>
