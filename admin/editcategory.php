<?php
  /**************************************************************************\
  * phpGroupWare - Admin                                                     *
  * http://www.phpgroupware.org                                              *
  * Written by Bettina Gille [ceb@phpgroupware.org]                          *
  * -----------------------------------------------                          *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/
  /* $Id$ */

  $phpgw_info["flags"]["currentapp"] = 'admin';
  include('../header.inc.php');
  
  if (! $cat_id) {
     Header('Location: ' . $phpgw->link('/admin/categories.php',"sort=$sort&order=$order&query=$query&start=$start"
					. "&filter=$filter"));
          }

    $t = CreateObject('phpgwapi.Template',$phpgw->common->get_tpl_dir('admin'));
    $t->set_file(array('form' => 'category_form.tpl'));
    $t->set_block('form','add','addhandle');
    $t->set_block('form','edit','edithandle');

    $c = CreateObject('phpgwapi.categories');
    $c->categories($phpgw_info['user']['account_id'],'phpgw');

    $hidden_vars = "<input type=\"hidden\" name=\"sort\" value=\"$sort\">\n"
                        . "<input type=\"hidden\" name=\"order\" value=\"$order\">\n"
                        . "<input type=\"hidden\" name=\"query\" value=\"$query\">\n"
                        . "<input type=\"hidden\" name=\"start\" value=\"$start\">\n"
                        . "<input type=\"hidden\" name=\"filter\" value=\"$filter\">\n"
                        . "<input type=\"hidden\" name=\"cat_id\" value=\"$cat_id\">\n";

    if ($submit) {
    $errorcount = 0;
    if (!$cat_name) { $error[$errorcount++] = lang('Please enter a name for that category !'); }
    $phpgw->db->query("SELECT count(*) from phpgw_categories WHERE cat_name='$cat_name' AND cat_id !='$cat_id' AND cat_appname='phpgw'");
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

    $t->set_var('title_categories',lang('Edit global category'));
    $t->set_var('lang_select_parent',lang('Select parent category'));
    $t->set_var('actionurl',$phpgw->link('/admin/editcategory.php'));
    $t->set_var('deleteurl',$phpgw->link('/admin/deletecategory.php',"cat_id=$cat_id&start=$start&query=$query&sort=$sort&order=$order&filter=$filter"));
    $t->set_var('doneurl',$phpgw->link('/admin/categories.php',"start=$start&query=$query&sort=$sort&order=$order&filter=$filter"));

    $t->set_var('hidden_vars',$hidden_vars);
    $t->set_var('lang_name',lang('Category name'));
    $t->set_var('lang_descr',lang('Category description'));
    $t->set_var('lang_done',lang('Done'));
    $t->set_var('lang_edit',lang('Edit'));
    $t->set_var('lang_delete',lang('Delete'));

    $cat_id = $cats[0]['id'];
    $cat_parent = $cats[0]['parent'];

    $t->set_var('cat_name',$phpgw->strip_html($cats[0]['name']));
    $t->set_var('cat_description',$phpgw->strip_html($cats[0]['description']));
    $t->set_var('category_list',$c->formated_list('select','all',$cat_parent,'False'));

    $t->set_var('edithandle','');
    $t->set_var('addhandle','');

    $t->pparse('out','form');
    $t->pparse('edithandle','edit');

    $phpgw->common->phpgw_footer();
?>