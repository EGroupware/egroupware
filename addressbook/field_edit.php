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
	include("../header.inc.php");

/*	if (!$id) {
		header("Location: " . $phpgw->link('/addressbook/fields.php'));
		$phpgw->common->phpgw_exit();
	}
*/
	$t = CreateObject('phpgwapi.Template',$phpgw->common->get_tpl_dir('addressbook'));
	$t->set_file(array('form' => 'edit_field.tpl'));
	$t->set_block('form','add','addhandle');
	$t->set_block('form','edit','edithandle');
	$t->set_block('form','delete','deletehandle');

	$font = $phpgw_info["theme"]["font"];
	$field = $phpgw->strip_html($field);
	$field = ereg_replace(' ','_',$field);
	$ofield = ereg_replace(' ','_',$ofield);

	$t->set_var('font',$font);
	$t->set_var('note',$note);
	$t->set_var('lang_add',lang('Add'));
	$t->set_var('lang_edit',lang('Edit'));
	$t->set_var('lang_delete',lang("Delete"));
	$t->set_var('deleteurl',$phpgw->link("/addressbook/field_edit.php"));
	$t->set_var('actionurl',$phpgw->link("/addressbook/field_edit.php"));
	$t->set_var('lang_list',lang('Field list'));
	$t->set_var('listurl',$phpgw->link("/addressbook/fields.php"));
	$t->set_var('lang_reset',lang('Clear Form'));
	$t->set_var('edithandle','');
	$t->set_var('deletehandle','');
	$t->set_var('addhandle','');

	switch($method) {
		case "add":
			if ($addfield) {
				$phpgw->preferences->read_repository();
				$phpgw->preferences->add("addressbook","extra_".$field);
				$phpgw->preferences->save_repository(1);
				$t->set_var('lang_action',lang(''));
				$t->set_var('message',lang("Field has been added."));
				$t->set_var('hidden_vars','');
				$t->set_var('field',$field);
				$t->set_var('name',"");
			} else {
				$t->set_var('lang_action',lang('Add a field'));
				$t->set_var('message',"");
				$t->set_var(
					'hidden_vars',
					'<input type="hidden" name="method" value="add">
					<input type="hidden" name="addfield" value="Add">'
				);
				$t->set_var('field',$field);
				$t->set_var('name',"");
			}
			$t->set_var('actionurl',$phpgw->link("/addressbook/field_edit.php"));
			$t->set_var('lang_btn',lang('Add'));
			$t->pparse('out','form');
			$t->pparse('addhandle','add');
			break;
		case "edit":
			if ($editfield && $field) {
				$phpgw->preferences->read_repository();
				$phpgw->preferences->delete("addressbook","extra_".$ofield);
				$phpgw->preferences->add("addressbook","extra_".$field);
				$phpgw->preferences->save_repository(1);
				$t->set_var('lang_action',lang(''));
				$t->set_var('message',lang("Field has been changed."));
				$t->set_var('hidden_vars','');
				$t->set_var('field',$field);
				$t->set_var('name',"");
			} else {
				$t->set_var('lang_action',lang('Edit field'));
				$t->set_var('message',"");
				$t->set_var(
					'hidden_vars',
					'<input type="hidden" name="ofield" value="'.$ofield.'">
					<input type="hidden" name="method" value="edit">
					<input type="hidden" name="editfield" value="Edit">'
				);
				$t->set_var('field',$ofield);
				$t->set_var('name','');
			}
			$t->set_var('lang_btn',lang('Edit'));
			$t->pparse('out','form');
			$t->pparse('edithandle','edit');
			break;
		case "delete":
			if ($deletefield) {
				$phpgw->preferences->read_repository();
				$phpgw->preferences->delete("addressbook","extra_".$field);
				$phpgw->preferences->save_repository(1);
				$t->set_var('lang_action',lang(''));
				$t->set_var('message',lang("Field has been deleted."));
				$t->set_var('hidden_vars','');
				$t->set_var('field',$field);
				$t->set_var('name',"");
			} else {
				$t->set_var('lang_action','');
				$t->set_var('message',"");
				$t->set_var(
					'hidden_vars',
					'<input type="hidden" name="method" value="delete">
					<input type="hidden" name="deletefield" value="Delete">'
				);
				$t->set_var('field',$field);
				$t->set_var('name','');
			}
			$t->set_var('lang_btn',lang('Delete'));
			$t->pparse('out','form');
			$t->pparse('deletehandle','delete');
			break;
		default:
			$t->set_var(
				'hidden_vars',
				'<input type="hidden" name="field" value="' . $field . '">'
			);
			$t->set_var('lang_action',lang('Add a field'));
			$t->set_var('name',"");
			$t->set_var('field',$field);
			$t->set_var('message',"");
			break;
	}

	$phpgw->common->phpgw_footer();
?>
