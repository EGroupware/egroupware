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
	$phpgw_info['flags'] = array('currentapp' => 'admin', 'enable_nextmatchs_class' => True);
	include('../header.inc.php');

	$p = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
	$p->set_file(array('message' => 'mainscreen_message.tpl'));
	$p->set_block('message','form','form');
	$p->set_block('message','row','row');
	$p->set_block('message','row_2','row_2');

	if ($submit)
	{
		$phpgw->db->query("delete from lang where message_id='$section" . "_message' and app_name='"
			. "$section' and lang='$select_lang'",__LINE__,__FILE__);
		$phpgw->db->query("insert into lang values ('$section" . "_message','$section','$select_lang','"
			. addslashes($message) . "')",__LINE__,__FILE__);
		$message = "<center>".lang("message has been updated")."</center>";
	}

	if (! isset($select_lang))
	{
		$p->set_var("header_lang",lang("Main screen message"));
		$p->set_var("form_action",$phpgw->link("/admin/mainscreen_message.php"));
		$p->set_var("tr_color",$phpgw_info["theme"]["th_bg"]);
		$p->set_var("value","&nbsp;");
		$p->parse("rows","row_2",True);

		$tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
		$p->set_var("tr_color",$tr_color);

		$select_lang = '<select name="select_lang">';
		$phpgw->db->query("select lang,languages.lang_name,languages.lang_id from lang,languages where "
			. "lang.lang=languages.lang_id group by lang,languages.lang_name,"
			. "languages.lang_id order by lang");
		while ($phpgw->db->next_record())
		{
			$select_lang .= '<option value="' . $phpgw->db->f("lang") . '">' . $phpgw->db->f("lang_id")
				. ' - ' . $phpgw->db->f("lang_name") . '</option>';
		}
		$select_lang .= '</select>';
		$p->set_var("label",lang("Language"));
		$p->set_var("value",$select_lang);
		$p->parse("rows","row",True);

		$tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
		$p->set_var("tr_color",$tr_color);
		$select_section = '<select name="section"><option value="mainscreen">' . lang("Main screen")
			. '</option><option value="loginscreen">' . lang("Login screen") . '</option>'
			. '</select>';
		$p->set_var("label",lang("Section"));
		$p->set_var("value",$select_section);
		$p->parse("rows","row",True);

		$tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
		$p->set_var("tr_color",$tr_color);
		$p->set_var("value",'<input type="submit" value="' . lang("Submit") . '">');
		$p->parse("rows","row_2",True);

	}
	else
	{
		$phpgw->db->query("select content from lang where lang='$select_lang' and message_id='$section"
			. "_message'");
		$phpgw->db->next_record();
		$current_message = $phpgw->db->f("content");

		if ($section == "mainscreen")
		{
			$p->set_var("header_lang",lang("Edit main screen message"));
		}
		else
		{
			$p->set_var("header_lang",lang("Edit login screen message"));
		}

		$p->set_var("form_action",$phpgw->link("/admin/mainscreen_message.php","select_lang=$select_lang&section=$section"));
		$p->set_var("tr_color",$phpgw_info["theme"]["th_bg"]);
		$p->set_var("value","&nbsp;");
		$p->parse("rows","row_2",True);

		$tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
		$p->set_var("tr_color",$tr_color);
		$p->set_var("value",'<textarea name="message" cols="50" rows="10" wrap="hard">' . stripslashes($current_message) . '</textarea>');
		$p->parse("rows","row_2",True);

		$tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
		$p->set_var("tr_color",$tr_color);
		$p->set_var("value",'<input type="submit" name="submit" value="' . lang("Update") . '">');
		$p->parse("rows","row_2",True);
	}

	$p->set_var("error_message",$message);
	$p->pparse("out","form");
	$phpgw->common->phpgw_footer();
?>
