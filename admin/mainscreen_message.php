<?php
  /**************************************************************************\
  * phpGroupWare - administration                                            *
  * http://www.phpgroupware.org                                              *
  * Written by Joseph Engo <jengo@phpgroupware.org>                          *
  * Modified by Stephen Brown <steve@dataclarity.net>                        *
  *  to distribute admin across the application directories                  *
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

  $phpgw->template->set_file(array("form" => "mainscreen_message.tpl",
                                   "row"  => "mainscreen_message_row.tpl"));

  if ($submit) {
     $phpgw->db->query("delete from config where config_name='mainscreen_message'",__LINE__,__FILE__);
     $phpgw->db->query("insert into config values ('mainscreen_message','" . addslashes($message)
                    . "')",__LINE__,__FILE__);
     $message = "<center>Message has been updated</center>";
  }
  
  $phpgw->template->set_var("header_lang",lang("Edit main screen message"));
  $phpgw->template->set_var("form_action",$phpgw->link("mainscreen_message.php"));
  $phpgw->template->set_var("th_bg",$phpgw_info["theme"]["th_bg"]);

  $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
  $phpgw->template->set_var("tr_color",$tr_color);  
  $phpgw->template->set_var("value",'<textarea name="message" cols="50" rows="10" wrap="hard">' . $phpgw_info["server"]["mainscreen_message"] . '</textarea>');
  $phpgw->template->parse("rows","row",True);

  $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
  $phpgw->template->set_var("tr_color",$tr_color);  
  $phpgw->template->set_var("value",'<input type="submit" name="submit" value="' . lang("Update") . '">');  
  $phpgw->template->parse("rows","row",True);
  
  $phpgw->template->pparse("out","form");
?>