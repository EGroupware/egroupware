<?php
  /**************************************************************************\
  * phpGroupWare - Addressbook                                               *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  $phpgw_info["flags"] = array("currentapp" => "addressbook", "enable_nextmatchs_class" => True, "noappheader" => True, "noappfooter" => True);

  if(isset($submit) && $submit) {
    $phpgw_info["flags"]["noheader"] = True;
    $phpgw_info["flags"]["nonavbar"] = True;
  }
  
  include("../header.inc.php");

  function display_row($bg_color,$label,$id,$name) {
    global $p;
    global $phpgw;
    global $phpgw_info;
    
    $p->set_var('row_color',$bg_color);
    $p->set_var('user',$name);
    $rights = $phpgw->acl->get_rights($label.$id,$phpgw_info["flags"]["currentapp"]);
    $p->set_var('read',$label.$phpgw_info["flags"]["currentapp"].'['.$id.']['.PHPGW_ACL_READ.']');
    if ($rights & PHPGW_ACL_READ) {
      $p->set_var('read_selected',' checked');
    } else {
      $p->set_var('read_selected','');
    }
    $p->set_var('add',$label.$phpgw_info["flags"]["currentapp"].'['.$id.']['.PHPGW_ACL_ADD.']');
    if ($rights & PHPGW_ACL_ADD) {
      $p->set_var('add_selected',' checked');
    } else {
      $p->set_var('add_selected','');
    }
    $p->set_var('edit',$label.$phpgw_info["flags"]["currentapp"].'['.$id.']['.PHPGW_ACL_EDIT.']');
    if ($rights & PHPGW_ACL_EDIT) {
      $p->set_var('edit_selected',' checked');
    } else {
      $p->set_var('edit_selected','');
    }
    $p->set_var('delete',$label.$phpgw_info["flags"]["currentapp"].'['.$id.']['.PHPGW_ACL_DELETE.']');
    if ($rights & PHPGW_ACL_DELETE) {
      $p->set_var('delete_selected',' checked');
    } else {
      $p->set_var('delete_selected','');
    }
    $p->parse('row','acl_row',True);
  }

  if ($submit) {

    $phpgw->acl->remove_granted_rights($phpgw_info["flags"]["currentapp"],"u");
    $phpgw->acl->remove_granted_rights($phpgw_info["flags"]["currentapp"],"g");
  
// Group records
    $group_variable = 'g_'.$phpgw_info["flags"]["currentapp"];
    
    while(list($group_id,$acllist) = each($$group_variable)) {
      $totalacl = 0;
      while(list($acl,$permission) = each($acllist)) {
        $totalacl += $acl;
      }
      $phpgw->acl->add($phpgw_info["flags"]["currentapp"],'g_'.$group_id,$phpgw_info["user"]["account_id"],'u',$totalacl);
    }

// User records
    $user_variable = 'u_'.$phpgw_info["flags"]["currentapp"];
    
    while(list($user_id,$acllist) = each($$user_variable)) {
      $totalacl = 0;
      while(list($acl,$permission) = each($acllist)) {
        $totalacl += $acl;
      }
      $phpgw->acl->add($phpgw_info["flags"]["currentapp"],'u_'.$user_id,$phpgw_info["user"]["account_id"],'u',$totalacl);
    }
     
     header("Location: ".$phpgw->link($phpgw_info["server"]["webserver_url"]."/preferences/index.php"));
     $phpgw->common->phpgw_exit();
  }

  $p = CreateObject('phpgwapi.Template',$phpgw_info["server"]["app_tpl"]);
  $p->set_file(array('preferences' => 'preference_acl.tpl',
                     'row_colspan' => 'preference_colspan.tpl',
                     'acl_row' => 'preference_acl_row.tpl'));

  $p->set_var('errors','<p><center><b>This does nothing at this time!<br>Strictly as a template for use!</b></center>');
  $p->set_var('title','<p><b>'.lang($phpgw_info["flags"]["currentapp"]." preferences").' - '.lang("acl").':</b><hr><p>');

  $p->set_var('action_url',$phpgw->link(''));
  $p->set_var('bg_color',$phpgw_info["theme"]["th_bg"]);
  $p->set_var('submit_lang',lang('submit'));
  $p->set_var('string',lang('Groups'));
  $p->set_var('read_lang',lang('Read'));
  $p->set_var('add_lang',lang('Add'));
  $p->set_var('edit_lang',lang('Edit'));
  $p->set_var('delete_lang',lang('Delete'));
  $p->parse('row','row_colspan',True);

  $groups = $phpgw->accounts->read_group_names($phpgw->info["user"]["account_id"]);
  while(list(,$group) = each($groups)) {
    $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
    display_row($tr_color,'g_',$group[0],$group[1]);
  }

  $db = $phpgw->db;

  $db->query("select account_id from accounts ORDER BY account_lastname, account_firstname, account_lid",__LINE__,__FILE__);
  if($db->num_rows()) {
    $p->set_var('string',ucfirst(lang('Users')));
    $p->parse('row','row_colspan',True);
    $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
    while($db->next_record()) {
      $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
      $id = $db->f("account_id");
      display_row($tr_color,'u_',$id,$phpgw->common->grab_owner_name($id));
    }
  }
  $p->pparse('out','preferences');
  $phpgw->common->phpgw_footer();
?>
