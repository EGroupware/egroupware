<?php
  /**************************************************************************\
  * phpGroupWare - Categories                                                *
  * (http://www.phpgroupware.org)                                            *
  * Written by Bettina Gille [ceb@phpgroupware.org]                          *    
  * -----------------------------------------------                          *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/
  /* $Id$ */

    if ($confirm) {
    $phpgw_info["flags"] = array('noheader' => True, 
                                  'nonavbar' => True);
    }

    $phpgw_info['flags']['currentapp'] = $cats_app;
    $phpgw_info['flags']['noappheader'] = True;
    $phpgw_info['flags']['noappfooter'] = True;

    include('../header.inc.php');

    $c = CreateObject('phpgwapi.categories');
    $c->app_name = $cats_app;

    if (! $cat_id) {
    Header('Location: ' . $phpgw->link('/preferences/categories.php',"cats_app=$cats_app&extra=$extra"));
    }

    if ($confirm) {
        if ($subs) { $c->delete($cat_id,'True'); }
        else { $c->delete($cat_id); }
	Header('Location: ' . $phpgw->link('/preferences/categories.php',"cats_app=$cats_app&extra=$extra"));
    }
    else {
	$hidden_vars = "<input type=\"hidden\" name=\"cat_id\" value=\"$cat_id\">\n"
    		     . "<input type=\"hidden\" name=\"cats_app\" value=\"$cats_app\">\n"
    		     . "<input type=\"hidden\" name=\"extra\" value=\"$extra\">\n";

    $t = CreateObject('phpgwapi.Template',$phpgw->common->get_tpl_dir('preferences'));
    $t->set_file(array('category_delete' => 'delete.tpl'));
    $t->set_var('deleteheader',lang('Are you sure you want to delete this category ?'));
    $t->set_var('font',$phpgw_info["theme"]["font"]);
    $t->set_var('hidden_vars',$hidden_vars);

    $exists = $c->exists('subs',$cat_name='',$cat_id);
    if ($exists==True) {
        $t->set_var('lang_subs',lang('Do you want to delete also all subcategories ?'));
        $t->set_var('subs','<input type="checkbox" name="subs" value="True">');
    }
    else {
        $t->set_var('lang_subs','');
        $t->set_var('subs', '');
    }

    $t->set_var('nolink',$phpgw->link('/preferences/categories.php',"cat_id=$cat_id&cats_app=$cats_app&extra=$extra"));
    $t->set_var('lang_no',lang('No'));

    $t->set_var('action_url',$phpgw->link('/preferences/deletecategory.php',"cat_id=$cat_id$cats_app=$cats_app&extra=$extra"));
    $t->set_var('lang_yes',lang('Yes'));

    $t->pparse('out','category_delete');
    }
    
    $phpgw->common->phpgw_footer();
?>