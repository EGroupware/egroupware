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

    $t = CreateObject('phpgwapi.Template',$phpgw->common->get_tpl_dir('admin'));
    $t->set_file(array('form' => 'category_form.tpl'));
    $t->set_block('form','add','addhandle');
    $t->set_block('form','edit','edithandle');

    $c = CreateObject('phpgwapi.categories');
    $c->categories($phpgw_info['user']['account_id'],'phpgw');

    if ($submit) {
	$errorcount = 0;

        if (!$cat_name) { $error[$errorcount++] = lang('Please enter a name for that category !'); }
	if (!$error) {
	    if (!$cat_parent) { $exists = $c->exists('mains',$cat_name,$cat_id=''); }
	    else { $exists = $c->exists('subs',$cat_name,$cat_id=''); }
	    if ($exists == True) { $error[$errorcount++] = lang('That category name has been used already !'); }
	}
	if (! $error) {
	    $cat_name = addslashes($cat_name);
	    $cat_description = addslashes($cat_description);
	    $cat_access = 'public';

	    $c->add($cat_name,$cat_parent,$cat_description,$cat_data,$cat_access);
	}
    }

    if ($errorcount) { $t->set_var('message',$phpgw->common->error_list($error)); }
    if (($submit) && (! $error) && (! $errorcount)) { $t->set_var('message',lang("Category x has been added !", $cat_name)); }
    if ((! $submit) && (! $error) && (! $errorcount)) { $t->set_var('message',''); }

    $t->set_var('title_categories',lang('Add global category'));
    $t->set_var('actionurl',$phpgw->link('/admin/addcategory.php'));
    $t->set_var('doneurl',$phpgw->link('/admin/categories.php'));
    $t->set_var('hidden_vars','<input type="hidden" name="cat_id" value="' . $cat_id . '">');
    $t->set_var('lang_select_parent',lang('Select parent category'));

    $t->set_var('category_list',$c->formated_list('select','all',$cat_parent,'False'));
    $t->set_var('lang_name',lang('Category name'));
    $t->set_var('lang_descr',lang('Category description'));
    $t->set_var('cat_name',$cat_name);
    $t->set_var('cat_description',$cat_description);
    $t->set_var('lang_add',lang('Add'));
    $t->set_var('lang_reset',lang('Clear Form'));
    $t->set_var('lang_done',lang('Done'));

    $t->set_var('edithandle','');
    $t->set_var('addhandle','');
    $t->pparse('out','form');
    $t->pparse('addhandle','add');

    $phpgw->common->phpgw_footer();
?>
