<?php
  /**************************************************************************\
  * phpGroupWare - Admin                                                     *
  * (http://www.phpgroupware.org)                                            *
  * Written by Bettina Gille [ceb@phpgroupware.org]                          *
  * -----------------------------------------------                          *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/
  /* $Id$ */

    if (! $cat_id)
    {
        Header('Location: ' . $phpgw->link('/admin/categories.php'));
    }

    if ($confirm)
    {
	$phpgw_info['flags'] = array('noheader' => True, 
				    'nonavbar' => True);
    }

    $phpgw_info['flags']['currentapp'] = 'admin';
    include('../header.inc.php');

    $c = CreateObject('phpgwapi.categories');
    $c->app_name = 'phpgw';

    if ($confirm)
    {
	if ($subs)
	{
	    $c->delete($cat_id,'True');
	}
	else
	{
	    $c->delete($cat_id);
	}
	Header('Location: ' . $phpgw->link('/admin/categories.php',"start=$start&query=$query&sort=$sort&order=$order&filter=$filter"));
    }
    else
    {
	$hidden_vars = "<input type=\"hidden\" name=\"sort\" value=\"$sort\">\n"
			. "<input type=\"hidden\" name=\"order\" value=\"$order\">\n"
			. "<input type=\"hidden\" name=\"query\" value=\"$query\">\n"
			. "<input type=\"hidden\" name=\"start\" value=\"$start\">\n"
			. "<input type=\"hidden\" name=\"filter\" value=\"$filter\">\n"
			. "<input type=\"hidden\" name=\"cat_id\" value=\"$cat_id\">\n";

	$t = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
	$t->set_file(array('category_delete' => 'delete_cat.tpl'));
	$t->set_block('category_delete','delete','deletehandle');
	$t->set_block('category_delete','done','donehandle');

	$nolink = $phpgw->link('/admin/categories.php',"cat_id=$cat_id&start=$start&query=$query&sort=$sort&order=$order&filter=$filter");

	$apps_cats = $c->exists('subs',$cat_name='',$cat_id);

	if ($apps_cats==True)
	{
	    $t->set_var('messages',lang('This category is used from applications as parent category !'));
	    $t->set_var('hidden_vars',$hidden_vars);
	    $t->set_var('lang_subs','');
	    $t->set_var('subs','');
	    $t->set_var('nolink',$nolink);
	    $t->set_var('lang_done',lang('Done'));
	    $t->set_var('deletehandle','');
	    $t->set_var('donehandle','');
	    $t->pparse('out','category_delete');
	    $t->pparse('donehandle','done');
	    $phpgw->common->phpgw_footer();
	}
	else
	{
	    $t->set_var('messages',lang('Are you sure you want to delete this category ?'));
	    $t->set_var('hidden_vars',$hidden_vars);

	    $exists = $c->exists('subs',$cat_name='',$cat_id);

	    if ($exists==True)
	    {
		$t->set_var('lang_subs',lang('Do you also want to delete all global subcategories ?'));
		$t->set_var('subs','<input type="checkbox" name="subs" value="True">');
	    }
	    else
	    {
		$t->set_var('lang_subs','');
		$t->set_var('subs', '');
	    }

	    $t->set_var('nolink',$nolink);
	    $t->set_var('lang_no',lang('No'));
	    $t->set_var('action_url',$phpgw->link('/admin/deletecategory.php',"cat_id=$cat_id"));
	    $t->set_var('lang_yes',lang('Yes'));
	    $t->set_var('deletehandle','');
	    $t->set_var('donehandle','');
	    $t->pparse('out','category_delete');
	    $t->pparse('deletehandle','delete');
	    $phpgw->common->phpgw_footer();
	}
    }
?>
