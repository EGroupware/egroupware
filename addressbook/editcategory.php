<?php
  /**************************************************************************\
  * phpGroupWare - Addressbook                                               *
  * http://www.phpgroupware.org                                              *
  * Written by Bettina Gille [ceb@phpgroupware.org]                          *
  * -----------------------------------------------                          *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/
  /* $Id$ */

  $phpgw_info["flags"]["currentapp"] = 'addressbook';
  include("../header.inc.php");
  
  if (! $cat_id) {                                                                                                                                                              
     Header("Location: " . $phpgw->link('/addressbook/categories.php',"sort=$sort&order=$order&query=$query&start=$start"                                                                                                             
					. "&filter=$filter"));
          }
  
    $t = CreateObject('phpgwapi.Template',$phpgw->common->get_tpl_dir('addressbook'));
    $t->set_file(array('form' => 'category_form.tpl'));
    $t->set_block('form','add','addhandle');                                                                                                                          
    $t->set_block('form','edit','edithandle');  

    $c = CreateObject('phpgwapi.categories');

    if ($submit) {
    $errorcount = 0;
    if (!$cat_name) { $error[$errorcount++] = lang('Please enter a name for that category !'); }
    $phpgw->db->query("SELECT count(*) from phpgw_categories WHERE cat_name='$cat_name' AND cat_id !='$cat_id' AND cat_appname='"
		     . $phpgw_info["flags"]["currentapp"] ."'");
    $phpgw->db->next_record();
    if ($phpgw->db->f(0) != 0) { $error[$errorcount++] = lang('That category name has been used already !'); }

    $cat_name = addslashes($cat_name);
    $cat_description = addslashes($cat_description);

    if (! $error) { $c->edit($cat_id,$cat_parent,$cat_name,$cat_description,$cat_data);	}
    }

    if ($errorcount) { $t->set_var('message',$phpgw->common->error_list($error)); }
    if (($submit) && (! $error) && (! $errorcount)) { $t->set_var('message',lang("Category $cat_name has been updated !")); }
    if ((! $submit) && (! $error) && (! $errorcount)) { $t->set_var('message',''); }

    $cats = $c->return_single($cat_id);

	$t->set_var('category_list',$c->formated_list('select','all',$cat_parent,'False'));
    $t->set_var('font',$font);
    $t->set_var('user_name',$phpgw_info["user"]["fullname"]);
    $t->set_var('title_categories',lang('Edit category for'));
    $t->set_var('lang_action',lang('Edit category'));
    $t->set_var('doneurl',$phpgw->link('/addressbook/categories.php'));
    $t->set_var('actionurl',$phpgw->link('/addressbook/editcategory.php'));
    $t->set_var('deleteurl',$phpgw->link('/addressbook/deletecategory.php'));
    $hidden_vars = "<input type=\"hidden\" name=\"cat_id\" value=\"$cat_id\">\n";
    $t->set_var('hidden_vars',$hidden_vars);
    $t->set_var('lang_name',lang('Category name'));
    $t->set_var('lang_descr',lang('Category description'));
    $t->set_var('lang_select_parent',lang('Select parent category'));

    $cat_id = $cats[0]['id'];

    $t->set_var('cat_name',$phpgw->strip_html($cats[0]['name']));
    $t->set_var('cat_description',$phpgw->strip_html($cats[0]['description']));

    $t->set_var('lang_edit',lang('Edit'));
    $t->set_var('lang_delete',lang('Delete'));
	$t->set_var('lang_done',lang('Done'));

    $t->set_var('edithandle','');
    $t->set_var('addhandle','');

    $t->pparse('out','form');
    $t->pparse('edithandle','edit');

    $phpgw->common->phpgw_footer();
?>
