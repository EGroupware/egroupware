<?php
  /**************************************************************************\
  * phpGroupWare - Addressbook                                               *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	$phpgw_info["flags"]["currentapp"] = "addressbook";
	$phpgw_info["flags"]["enable_nextmatchs_class"] = True;
	$phpgw_info["flags"]["enable_contacts_class"] = True;
	include("../header.inc.php");

	$this = CreateObject("phpgwapi.contacts");
 	$extrafields = array(
		"pager"    => "pager",
		"mphone"   => "mphone",
		"ophone"   => "ophone",
		"address2" => "address2",
	);
	$qfields = $this->stock_contact_fields + $extrafields;

	$phpgw->template->set_file(array(
		"body"	=> "custom_field_list.tpl",
		"row"	=> "custom_field_list_row.tpl"
	));

	$phpgw->template->set_var("title",lang('addressbook').' '.lang('custom fields'));
	$phpgw->template->set_var("message",'');
	$phpgw->template->set_var("sort_name",lang("Name"));
	$phpgw->template->set_var("lang_edit",lang("Edit"));
	$phpgw->template->set_var("lang_delete",lang("Delete"));
	$phpgw->template->set_var("th_bg",$phpgw_info["theme"]["th_bg"]);

	$phpgw->preferences->read_repository();

	while (list($col,$descr) = each($phpgw_info["user"]["preferences"]["addressbook"]))
	{
		if ( substr($col,0,6) == 'extra_' )
		{
			$fields[$i] = ereg_replace('extra_','',$col);
			$fields[$i] = ereg_replace(' ','_',$fields[$i]);
			//echo "<br>".$i.": '".$fields[$i]."'";
		}
		else
		{
			$fields[$i] = '';
		}
		$i++;
	}

	reset($fields);
	for($i=0;$i<count($fields);$i++)
	{
		if ($fields[$i])
		{
			$found = True;
			$phpgw->nextmatchs->template_alternate_row_color(&$phpgw->template);

			$phpgw->template->set_var("field_name",$fields[$i]);

			$phpgw->template->set_var("field_edit",'<a href="'
				. $phpgw->link("/addressbook/field_edit.php","ofield="
				. $fields[$i] . "&method=edit")
				. '">' . lang("Edit") . '</a>');

			$phpgw->template->set_var("field_delete",'<a href="'
				. $phpgw->link("/addressbook/field_edit.php","field="
				. $fields[$i] . "&method=delete&deletefield=delete")
				. '">' . lang("Delete") . '</a>');

			$phpgw->template->parse("rows","row",True);
		}
	}
	if (!$found)
	{
		$phpgw->nextmatchs->template_alternate_row_color(&$phpgw->template);
		$phpgw->template->set_var("field_name",'&nbsp;');
		$phpgw->template->set_var("field_edit",'&nbsp;');
		$phpgw->template->set_var("field_delete",'&nbsp;');
		$phpgw->template->parse("rows","row",False);
	}

	$phpgw->template->set_var("add_field",'<a href="'
		. $phpgw->link("/addressbook/field_edit.php","method=add")
		. '">' . lang("Add") . '</a>');

	$phpgw->template->pparse("out","body");
	$phpgw->common->phpgw_footer();
?>
