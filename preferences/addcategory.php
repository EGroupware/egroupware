<?php
  /**************************************************************************\
  * phpGroupWare - Categories                                                *
  * http://www.phpgroupware.org                                              *
  * Written by Bettina Gille [ceb@phpgroupware.org]                          *
  * -----------------------------------------------                          *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/
/* $Id$ */

    $phpgw_flags = array('currentapp' => $cats_app,
                        'noappheader' => True,
                        'noappfooter' => True);

    $phpgw_info['flags'] = $phpgw_flags;
    include('../header.inc.php');

    $hidden_vars = "<input type=\"hidden\" name=\"sort\" value=\"$sort\">\n"
                . "<input type=\"hidden\" name=\"order\" value=\"$order\">\n"
                . "<input type=\"hidden\" name=\"query\" value=\"$query\">\n"
                . "<input type=\"hidden\" name=\"start\" value=\"$start\">\n"
                . "<input type=\"hidden\" name=\"cats_app\" value=\"$cats_app\">\n"
                . "<input type=\"hidden\" name=\"extra\" value=\"$extra\">\n"
                . "<input type=\"hidden\" name=\"filter\" value=\"$filter\">\n";


    $t = CreateObject('phpgwapi.Template',$phpgw->common->get_tpl_dir('preferences'));
    $t->set_file(array('form' => 'category_form.tpl'));
    $t->set_block('form','add','addhandle');
    $t->set_block('form','edit','edithandle');

    $c = CreateObject('phpgwapi.categories');
    $c->app_name = $cats_app;

    if ($submit) {
    $errorcount = 0;

    if (!$cat_name) { $error[$errorcount++] = lang('Please enter a name for that category !'); }
    if (!$cat_parent) { $exists = $c->exists('mains',$cat_name,$cat_id=''); }
    else { $exists = $c->exists('subs',$cat_name,$cat_id=''); }
    if ($exists == True) { $error[$errorcount++] = lang('That category name has been used already !'); }

    if (! $error) {
	$cat_name = addslashes($cat_name);
	$cat_description = addslashes($cat_description);
        if ($access) { $cat_access = 'private'; }
        else { $cat_access = 'public'; }

	$c->add($cat_name,$cat_parent,$cat_description,$cat_data,$cat_access);
	}
    }

    if ($errorcount) { $t->set_var('message',$phpgw->common->error_list($error)); }
    if (($submit) && (! $error) && (! $errorcount)) { $t->set_var('message',lang("Category x has been added !",$cat_name)); }
    if ((! $submit) && (! $error) && (! $errorcount)) { $t->set_var('message',''); }


    $t->set_var('font',$phpgw_info["theme"]["font"]);
    $t->set_var('category_list',$c->formated_list('select','all',$cat_parent,'False'));
    $t->set_var('hidden_vars',$hidden_vars);
    $t->set_var('user_name',$phpgw_info["user"]["fullname"]);
    $t->set_var('doneurl',$phpgw->link('/preferences/categories.php'));
    $t->set_var('title_categories',lang("Add $cats_app category for"));
    $t->set_var('actionurl',$phpgw->link('/preferences/addcategory.php'));
    $t->set_var('lang_parent',lang('Parent category'));
    $t->set_var('lang_select_parent',lang('Select parent category'));
    $t->set_var('lang_access',lang('Private'));

    if ($access) { $t->set_var('access', '<input type="checkbox" name="access" value="True" checked>'); }
    else { $t->set_var('access', '<input type="checkbox" name="access" value="True">'); }
    $t->set_var('lang_name',lang('Name'));
    $t->set_var('lang_descr',lang('Description'));
    $t->set_var('cat_name',$cat_name);
    $t->set_var('cat_description',$cat_description);

    if ($extra) {
        $t->set_var('td_data','<input name="cat_data" size="50" value="' . $cat_data . '">');
	$t->set_var('lang_data',lang($extra));
    }
    else {
        $t->set_var('td_data','');
        $t->set_var('lang_data','');
    }

    $t->set_var('lang_add',lang('Add'));
    $t->set_var('lang_reset',lang('Clear Form'));
    $t->set_var('lang_done',lang('Done'));
    $t->set_var('edithandle','');
    $t->set_var('addhandle','');
    $t->pparse('out','form');
    $t->pparse('addhandle','add');

    $phpgw->common->phpgw_footer();
?>
