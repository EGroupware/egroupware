<?php
  /**************************************************************************\
  * phpGroupWare - API (categories)                                          *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  class categories
  {
     function read($format = "", $app_name = "", $owner = "", $cat_id = "")
     {
        global $phpgw_info, $phpgw;
        $db2 = $phpgw->db;

        if (! isset($owner)) {
           $owner = $phpgw_info["user"]["account_id"];
        }
        
        if (! isset($format)) {
           $format   = "array";
        }
        
        if ($format == "single") {
           $db2->query("select * from categories where cat_id='$cat_id' and account_id='$owner' "
                     . "and app_name='" . addslashes($app_name) . "'");
           $db2->next_record();

           $cat_info[]["id"]         = $db2->f("cat_id");           
           $cat_info[]["name"]       = $db2->f("cat_name");
           $cat_info[]["descrption"] = $db2->f("cat_descrption");
           return $cat_info;
        }

        if (! $app_name) {
           $app_name = $phpgw_info["flags"]["currentapp"];
        }
        if (! $account_id) {
           $owner    = $phpgw_info["user"]["account_id"];
        }

        $db2->query("select cat_id,cat_name,cat_description from categories where app_name='$app_name' "
                  . "and account_id='$owner'");
        $i = 0;
        while ($db2->next_record()) {
           if ($format == "array") {
              $cat_list[$i]["cat_id"]          = $db2->f("cat_id");
              $cat_list[$i]["cat_name"]        = $db2->f("cat_name");
              $cat_list[$i]["cat_description"] = $db2->f("cat_description");
              $i++;
           }
           if ($format == "select") {
              $cat_list .= '<option value="' . $db2->f("cat_id") . '">' . $db2->f("cat_name")
                         . '</option>';
           }
        }
        return $cat_list;   
     }

     function add($owner,$app_name,$cat_name,$cat_description = "")
     {
        global $phpgw;
        $db2 = $phpgw->db;

        $db2->query("insert into categories (account_id,app_name,cat_name,cat_description) values ('"
                  . "$owner','" . addslashes($app_name) . "','" . addslashes($cat_name) . "','"
                  . addslashes($cat_description) . "')");
     }

     function delete($owner,$app_name,$cat_name)
     {
        global $phpgw;
        $db2 = $phpgw->db;

        $db2->query("delete from categories where account_id='$account_id' and app_name='"
                  . addslashes($app_name) . "' and cat_name='" . addslashes($cat_name) . "'");
     }

     function edit($owner,$app_name,$cat_name,$cat_description)
     {
        global $phpgw;
        $db2 = $phpgw->db;

        $db2->query("update categories set cat_name='" . addslashes($cat_name) . "', cat_description='"
                  . addslashes($cat_description) . "' where account_id='$owner' and app_name='"
                  . addslashes($app_name) . "'");
     }

  }
?>