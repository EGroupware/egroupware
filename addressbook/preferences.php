<?php
/**************************************************************************\
* phpGroupWare - Address Book                                              *
* http://www.phpgroupware.org                                              *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

	$phpgw_info["flags"] = array(
		"currentapp"              => "addressbook", 
		"noheader"                => True, 
		"nonavbar"                => True, 
		'noappheader'             => True,
		'noappfooter'             => True,
		"enable_contacts_class"   => True,
		"enable_nextmatchs_class" => True
	);
                               
	include("../header.inc.php");

	$this = CreateObject("phpgwapi.contacts");

 	$extrafields = array(
		"ophone"   => "ophone",
		"address2" => "address2",
		"address3" => "address3"
	);

	$phpgw->preferences->read_repository();
	$customfields = array();
	if ($phpgw_info["user"]["preferences"]["addressbook"]) {
		while (list($col,$descr) = each($phpgw_info["user"]["preferences"]["addressbook"])) {
			if ( substr($col,0,6) == 'extra_' ) {
				$field = ereg_replace('extra_','',$col);
				$customfields[$field] = ucfirst($field);
			}
		}
	}

	$qfields = $this->stock_contact_fields + $extrafields + $customfields;

	if ($submit) {
		$totalerrors = 0;
		if (! count($ab_selected)) {
			$errors[$totalerrors++] = lang("You must select at least 1 column to display");
		}
		if (! $totalerrors) {
			$phpgw->preferences->read_repository();
			while (list($pref[0]) = each($qfields)) {
				if ($ab_selected["$pref[0]"]) {
					$phpgw->preferences->change("addressbook",$pref[0],"addressbook_" . $ab_selected["$pref[0]"]);
				} else {
					$phpgw->preferences->delete("addressbook",$pref[0],"addressbook_" . $ab_selected["$pref[0]"]);
				}
			}

 			if ($mainscreen_showbirthdays) {
				$phpgw->preferences->delete("addressbook","mainscreen_showbirthdays");
				$phpgw->preferences->add("addressbook","mainscreen_showbirthdays");
			} else {
				$phpgw->preferences->delete("addressbook","mainscreen_showbirthdays");
			}

 			if ($autosave_category) {
				$phpgw->preferences->delete("addressbook","autosave_category");
				$phpgw->preferences->add("addressbook","autosave_category",True);
			} else {
				$phpgw->preferences->delete("addressbook","autosave_category");
			}

 			if ($cat_id) {
				$phpgw->preferences->delete("addressbook","default_category");
				$phpgw->preferences->add("addressbook","default_category",$cat_id);
			} else {
				$phpgw->preferences->delete("addressbook","default_category");
			}

			$phpgw->preferences->save_repository(True);
			Header("Location: " . $phpgw->link("/preferences/index.php"));
		}
	}

	$phpgw->common->phpgw_header();
	echo parse_navbar();

	if ($totalerrors) {  
		echo "<p><center>" . $phpgw->common->error_list($errors) . "</center>";
	}

	$t = new Template($phpgw->common->get_tpl_dir("addressbook"));
	$t->set_file(array(
		"preferences"	=> "preferences.tpl",
	));

	$t->set_var(action_url,$phpgw->link('/addressbook/preferences.php'));

	$i = 0; $j = 0;
	$tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);

	while (list($col, $descr) = each($qfields))
	{
		// echo "<br>test: $col - $i $j - " . count($abc);
		$i++; $j++;
		$showcol = display_name($col);
		if (!$showcol) { $showcol = $col; }
		// yank the *'s prior to testing for a valid column description
		$coltest = ereg_replace("\*","",$showcol);
		if ($coltest)
		{
			$t->set_var($col,$showcol);
			if ($phpgw_info["user"]["preferences"]["addressbook"][$col])
			{
				$t->set_var($col.'_checked'," checked");
			}
			else
			{
				$t->set_var($col.'_checked','');
			}
		}
	}

	if ($customfields)
	{
		$custom_var = '
  <tr>
    <td><font color="#000000" face="">'.lang('Custom').' '.lang('Fields').':</font></td>
    <td></td>
    <td></td>
  </tr>
';
		while( list($cf) = each($customfields) )
		{
			$tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
			$custom_var .= "\n" . '<tr bgcolor="' . $tr_color . '">';
			$custom_var .= '    <td><input type="checkbox" name="ab_selected['
				. strtolower($cf) . ']"'
				. ($phpgw_info["user"]["preferences"]["addressbook"][$cf]?" checked":"")
				. '>' . $cf . '</option></td>' . "\n"
				. '</tr>' . "\n";
		}
		$t->set_var(custom_fields,$custom_var);
	}
	else
	{
		$t->set_var(custom_fields,'');
	}

	$tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
    $t->set_var(tr_color,$tr_color);
	$t->set_var(lang_showbirthday,lang("show birthday reminders on main screen"));

	if ($phpgw_info["user"]["preferences"]["addressbook"]["mainscreen_showbirthdays"])
	{
		$t->set_var(show_birthday," checked");
	}
	else
	{
		$t->set_var(show_birthday,'');
	}

	$t->set_var(lang_autosave,lang("Autosave default category"));
	if ($phpgw_info["user"]["preferences"]["addressbook"]["autosave_category"])
	{
		$t->set_var(autosave," checked");
	}
	else
	{
		$t->set_var(autosave,"");
	}
	$t->set_var(lang_defaultcat,lang("Default Category"));
    $t->set_var(cat_select,cat_option($phpgw_info["user"]["preferences"]["addressbook"]["default_category"]));
	$t->set_var(lang_abprefs,lang('Addressbook').' '.lang('Preferences'));
	$t->set_var(lang_fields,lang('Fields to show in address list'));
	$t->set_var(lang_personal,lang('Personal'));
	$t->set_var(lang_business,lang('Business'));
	$t->set_var(lang_home,lang('Home'));
	$t->set_var(lang_phones,lang('Extra').' '.lang('Phone Numbers'));
	$t->set_var(lang_other,lang('Other').' '.lang('Fields'));
	$t->set_var(lang_otherprefs,lang('Other').' '.lang('Preferences'));
	$t->set_var(lang_submit,lang("submit"));

	$t->pparse('out','preferences');
	$phpgw->common->phpgw_footer();
?>
