<?php
  /**************************************************************************\
  * phpGroupWare - Admin - Global categories                                 *
  * http://www.phpgroupware.org                                              *
  * Written by Bettina Gille [ceb@phpgroupware.org]                          *
  * -----------------------------------------------                          *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/
  /* $Id$ */

    $phpgw_info["flags"] = array('currentapp' => 'admin',
		    'enable_nextmatchs_class' => True);

    include('../header.inc.php');

    $t = CreateObject('phpgwapi.Template',$phpgw->common->get_tpl_dir('admin'));

    $t->set_file(array('cat_list_t' => 'listcats.tpl',
                     'cat_list'   => 'listcats.tpl'));
    $t->set_block('cat_list_t','cat_list','list');

    $common_hidden_vars = "<input type=\"hidden\" name=\"sort\" value=\"$sort\">\n"
			. "<input type=\"hidden\" name=\"order\" value=\"$order\">\n"
			. "<input type=\"hidden\" name=\"query\" value=\"$query\">\n"
			. "<input type=\"hidden\" name=\"start\" value=\"$start\">\n"
			. "<input type=\"hidden\" name=\"filter\" value=\"$filter\">\n";

    $t->set_var('lang_action',lang('Category list'));
    $t->set_var('add_action',$phpgw->link('/admin/addcategory.php'));
    $t->set_var('lang_add',lang('Add'));
    $t->set_var('title_categories',lang('Global categories'));
    $t->set_var('lang_search',lang('Search'));
    $t->set_var('actionurl',$phpgw->link('/admin/categories.php'));
    $t->set_var('lang_done',lang('Done'));
    $t->set_var('doneurl',$phpgw->link('/admin/index.php'));

    if (! $start) { $start = 0; }

    if($phpgw_info["user"]["preferences"]["common"]["maxmatchs"] && $phpgw_info["user"]["preferences"]["common"]["maxmatchs"] > 0) {
                $limit = $phpgw_info["user"]["preferences"]["common"]["maxmatchs"];
    }
    else { $limit = 15; }

    $c = CreateObject('phpgwapi.categories');
    $c->categories($phpgw_info['user']['account_id'],'phpgw');
    $categories = $c->return_array('False','all',$start,$limit,$query,$sort,$order);

//--------------------------------- nextmatch --------------------------------------------

    $left = $phpgw->nextmatchs->left('/admin/categories.php',$start,$c->total_records);
    $right = $phpgw->nextmatchs->right('/admin/categories.php',$start,$c->total_records);
    $t->set_var('left',$left);
    $t->set_var('right',$right);                                                                                                                                                

    if ($c->total_records > $limit) {                                                                                      
    $lang_showing=lang("showing x - x of x",($start + 1),($start + $limit),$c->total_records);           
    } 
    else { $lang_showing=lang("showing x",$c->total_records); }
    $t->set_var('lang_showing',$lang_showing);

// ------------------------------ end nextmatch ------------------------------------------                                                                                                                                

//------------------- list header variable template-declarations ------------------------- 

    $t->set_var('th_bg',$phpgw_info["theme"][th_bg]);
    $t->set_var('sort_name',$phpgw->nextmatchs->show_sort_order($sort,'cat_name',$order,'/admin/categories.php',lang('Name')));
    $t->set_var('sort_description',$phpgw->nextmatchs->show_sort_order($sort,'cat_description',$order,'/admin/categories.php',lang('Description')));
    $t->set_var('lang_edit',lang('Edit'));
    $t->set_var('lang_delete',lang('Delete'));
  
// -------------------------- end header declaration --------------------------------------

    for ($i=0;$i<count($categories);$i++) {                                                                                                                    

    if ($categories[$i]['owner'] == $phpgw_info["user"]["account_id"]) {    

    $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
    $t->set_var(tr_color,$tr_color);

    $cat_id = $categories[$i]['id'];
    $owner = $categories[$i]['owner'];
    $space = "&nbsp;&nbsp;";
    if ($categories[$i]['parent'] > 0) { $name = $space . $phpgw->strip_html($categories[$i]['name']); }

    $descr = $phpgw->strip_html($categories[$i]['description']);
    if (! $descr) { $descr  = "&nbsp;"; }

    if ($categories[$i]['parent'] == 0) {
    $name = "<font color=FF0000><b>" . $phpgw->strip_html($categories[$i]['name']) . "</b></font>";
    $descr = "<font color=FF0000><b>" . $descr . "</b></font>";
    }

//-------------------------- template declaration for list records ---------------------------

    $t->set_var(array('name' => $name,
                      'descr' => $descr));


    if ($categories[$i]["owner"] == $phpgw_info["user"]["account_id"]) {
    $t->set_var('edit',$phpgw->link('/admin/editcategory.php',"cat_id=$cat_id&start=$start&query=$query&sort=$sort&order=$order&filter=$filter"));
    $t->set_var('lang_edit_entry',lang('Edit'));
       }
    else {
    $t->set_var('edit','');
    $t->set_var('lang_edit_entry','&nbsp;');
     }
    if ($categories[$i]["owner"] == $phpgw_info["user"]["account_id"]) {
    $t->set_var('delete',$phpgw->link('/admin/deletecategory.php',"cat_id=$cat_id&start=$start&query=$query&sort=$sort&order=$order&filter=$filter"));
    $t->set_var('lang_delete_entry',lang('Delete'));
     }
    else {
    $t->set_var('delete','');
    $t->set_var('lang_delete_entry','&nbsp;');
     }
    $t->parse('list','cat_list',True);
     }
    }
// ---------------------------- end record declaration -----------------------------------------

    $t->parse('out','cat_list_t',True);
    $t->p('out');

    $phpgw->common->phpgw_footer();
?>