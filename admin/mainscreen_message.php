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

  $phpgw->template->set_file(array("form"   => "mainscreen_message.tpl",
                                   "row"    => "mainscreen_message_row.tpl",
                                   "row_2"  => "mainscreen_message_row_2.tpl"
                                  ));

  if ($submit) {
     $phpgw->db->query("delete from lang where message_id='$section" . "_message' and app_name='"
                     . "$section' and lang='$select_lang'",__LINE__,__FILE__);
     $phpgw->db->query("insert into lang values ('$section" . "_message','$section','$select_lang','"
                     . addslashes($message) . "')",__LINE__,__FILE__);
     $message = "<center>Message has been updated</center>";
  }
  
  if (! isset($select_lang)) {
     $phpgw->template->set_var("header_lang",lang("Main screen message"));
     $phpgw->template->set_var("form_action",$phpgw->link("mainscreen_message.php"));
     $phpgw->template->set_var("tr_color",$phpgw_info["theme"]["th_bg"]);
     $phpgw->template->set_var("value","&nbsp;");
     $phpgw->template->parse("rows","row_2",True);     

     $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
     $phpgw->template->set_var("tr_color",$tr_color);

     $select_lang = '<select name="select_lang">';
     $phpgw->db->query("select lang,languages.lang_name,languages.lang_id from lang,languages where "
                     . "lang.lang=languages.lang_id group by lang,languages.lang_name,"
                     . "languages.lang_id order by lang");
     while ($phpgw->db->next_record()) {
        $select_lang .= '<option value="' . $phpgw->db->f("lang") . '">' . $phpgw->db->f("lang_id")
                      . ' - ' . $phpgw->db->f("lang_name") . '</option>';
     }      
     $select_lang .= '</select>';
     $phpgw->template->set_var("label",lang("Language"));
     $phpgw->template->set_var("value",$select_lang);
     $phpgw->template->parse("rows","row",True);

     $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
     $phpgw->template->set_var("tr_color",$tr_color);
     $select_section = '<select name="section"><option value="mainscreen">' . lang("Main screen")
                     . '</option><option value="loginscreen">' . lang("Login screen") . '</option>'
                     . '</select>';
     $phpgw->template->set_var("label",lang("Section"));
     $phpgw->template->set_var("value",$select_section);
     $phpgw->template->parse("rows","row",True);

     $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
     $phpgw->template->set_var("tr_color",$tr_color);
     $phpgw->template->set_var("value",'<input type="submit" value="' . lang("Submit") . '">');
     $phpgw->template->parse("rows","row_2",True);

  } else {
     $phpgw->db->query("select content from lang where lang='$select_lang' and message_id='$section"
                     . "_message'");
     $phpgw->db->next_record();
     $current_message = $phpgw->db->f("content");

     if ($section == "mainscreen") {
        $phpgw->template->set_var("header_lang",lang("Edit main screen message"));
     } else {
        $phpgw->template->set_var("header_lang",lang("Edit login screen message"));
     }
     $phpgw->template->set_var("form_action",$phpgw->link("mainscreen_message.php","select_lang=$select_lang&section=$section"));
     $phpgw->template->set_var("tr_color",$phpgw_info["theme"]["th_bg"]);
     $phpgw->template->set_var("value","&nbsp;");
     $phpgw->template->parse("rows","row_2",True);
    
     $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
     $phpgw->template->set_var("tr_color",$tr_color);  
     $phpgw->template->set_var("value",'<textarea name="message" cols="50" rows="10" wrap="hard">' . stripslashes($current_message) . '</textarea>');
     $phpgw->template->parse("rows","row_2",True);
    
     $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
     $phpgw->template->set_var("tr_color",$tr_color);  
     $phpgw->template->set_var("value",'<input type="submit" name="submit" value="' . lang("Update") . '">');  
     $phpgw->template->parse("rows","row_2",True);
  }
  $phpgw->template->set_var("error_message",$message);      
  $phpgw->template->pparse("out","form");

?>