<?php
  /**************************************************************************\
  * phpGroupWare - administration                                            *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  $phpgw_info = array();
  $phpgw_info["flags"] = array("currentapp" => "admin", "enable_nextmatchs_class" => True);  
  include("../header.inc.php");
  include(PHPGW_APP_INC . "/accounts_".$phpgw_info["server"]["account_repository"].".inc.php");

  $phpgw->template->set_file(array("list" => "accounts.tpl",
              			         "row"  => "accounts_row.tpl"));

  $total = account_total();

  $phpgw->template->set_var("bg_color",$phpgw_info["theme"]["bg_color"]);
  $phpgw->template->set_var("th_bg",$phpgw_info["theme"]["th_bg"]);

  $phpgw->template->set_var("left_next_matchs",$phpgw->nextmatchs->left("accounts.php",$start,$total));
  $phpgw->template->set_var("lang_user_accounts",lang("user accounts"));
  $phpgw->template->set_var("right_next_matchs",$phpgw->nextmatchs->right("accounts.php",$start,$total));

  $phpgw->template->set_var("lang_loginid",$phpgw->nextmatchs->show_sort_order($sort,"account_lid",$order,"accounts.php",lang("LoginID")));
  $phpgw->template->set_var("lang_lastname",$phpgw->nextmatchs->show_sort_order($sort,"account_lastname",$order,"accounts.php",lang("last name")));
  $phpgw->template->set_var("lang_firstname",$phpgw->nextmatchs->show_sort_order($sort,"account_firstname",$order,"accounts.php",lang("first name")));

  $phpgw->template->set_var("lang_edit",lang("Edit"));
  $phpgw->template->set_var("lang_delete",lang("Delete"));
  $phpgw->template->set_var("lang_view",lang("View"));

  $account_info = account_read($method,$start,$sort,$order);

  while (list($null,$account) = each($account_info)) {
//  while (list($key) = each($account_info[0])) {
//  for ($i=0; $i<count($account_info);$i++) {
//    echo "<br>0: " . $account_info[1][$key];
//    echo "<br>1: " . $a[2];
//    echo "<br>2: " . $b[1];

    $lastname   = $account["account_lastname"];
    $firstname  = $account["account_firstname"];
    $account_id = $account["account_id"];
    $loginid    = $account["account_lid"];

    $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
    $phpgw->template->set_var("tr_color",$tr_color);

//    $lastname  = $account["account_lastname"];
//    $firstname = $account["account_firstname"];

    if (! $lastname)  $lastname  = '&nbsp;';
    if (! $firstname) $firstname = '&nbsp;';

    $phpgw->template->set_var("row_loginid",$loginid);
    $phpgw->template->set_var("row_firstname",$firstname);
    $phpgw->template->set_var("row_lastname",$lastname);
    $phpgw->template->set_var("row_edit",'<a href="'.$phpgw->link("editaccount.php","account_id="
       				     . $account_id) . '"> ' . lang("Edit") . ' </a>');

    if ($phpgw_info["user"]["userid"] != $account["account_lid"]) {
       $phpgw->template->set_var("row_delete",'<a href="' . $phpgw->link("deleteaccount.php",'account_id='
      						. $account_id) . '"> '.lang("Delete").' </a>');
    } else {
       $phpgw->template->set_var("row_delete","&nbsp;");
    }

    $phpgw->template->set_var("row_view",'<a href="' . $phpgw->link("viewaccount.php", "account_id="
   				         . $account_id) . '"> ' . lang("View") . ' </a>');

    $phpgw->template->parse("rows","row",True);
  }

  $phpgw->template->set_var("actionurl",$phpgw->link("newaccount.php"));
  $phpgw->template->set_var("lang_add",lang("add"));
  $phpgw->template->set_var("lang_search",lang("search"));

  $phpgw->template->pparse("out","list");

  account_close();
  $phpgw->common->phpgw_footer();
?>
