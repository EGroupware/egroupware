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
    $c->delete($cat_id);
    Header('Location: ' . $phpgw->link('/preferences/categories.php',"cats_app=$cats_app&extra=$extra"));
    }
    else {
	$hidden_vars = "<input type=\"hidden\" name=\"cat_id\" value=\"$cat_id\">\n"
      .	$hidden_vars = "<input type=\"hidden\" name=\"cats_app\" value=\"$cats_app\">\n"
      .	$hidden_vars = "<input type=\"hidden\" name=\"extra\" value=\"$extra\">\n";

    $t = CreateObject('phpgwapi.Template',$phpgw->common->get_tpl_dir('preferences'));
    $t->set_file(array('category_delete' => 'delete.tpl'));
    $t->set_var('deleteheader',lang('Are you sure you want to delete this category ?'));
    $t->set_var('font',$phpgw_info["theme"]["font"]);
    $nolinkf = $phpgw->link('/preferences/categories.php',"cat_id=$cat_id&cats_app=$cats_app&extra=$extra");
    $nolink = "<a href=\"$nolinkf\">" . lang('No') ."</a>";
    $t->set_var("nolink",$nolink);

    $yeslinkf = $phpgw->link('/preferences/deletecategory.php',"cat_id=$cat_id&confirm=True");
    $yeslinkf = "<FORM method=\"POST\" name=yesbutton action=\"".$phpgw->link('/preferences/deletecategory.php') . "\">"
                 . $hidden_vars
                 . "<input type=hidden name=cat_id value=$cat_id>"
		 . "<input type=hidden name=confirm value=True>"
                 . "<input type=submit name=yesbutton value=Yes>"
                 . "</FORM><SCRIPT>document.yesbutton.yesbutton.focus()</SCRIPT>";

    $yeslink = "<a href=\"$yeslinkf\">" . lang('Yes') ."</a>";
    $yeslink = $yeslinkf;

    $t->set_var('yeslink',$yeslink);

    $t->pparse('out','category_delete');
    }
    
    $phpgw->common->phpgw_footer();
?>