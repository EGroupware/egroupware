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
     var $account_id;
     var $app_name;
     var $cats;
     var $db;
     
     function categories($account_id,$app_name)
     {
        global $phpgw;
        $this->account_id = $account_id;
        $this->app_name   = $app_name;
        $this->db         = $phpgw->db;

        $this->db->query("select * from phpgw_categories where cat_owner='$account_id' and cat_appname='"
                       . "$app_name'",__LINE__,__FILE__);
        while ($this->db->next_record()) {
           $this->cats[]["id"]          = $this->db->f("cat_id");
           $this->cats[]["parent"]      = $this->db->f("cat_parent");
           $this->cats[]["name"]        = $this->db->f("cat_name");
           $this->cats[]["description"] = $this->db->f("cat_description");
           $this->cats[]["data"]        = $this->db->f("cat_data");
        }
     }

     // Return into a select box, list or other formats
     function formated_list($format,$type)
     {
        global $phpgw;

        switch ($type)
        {
          case "onlymains":   $method = "and cat_parent='0'";   break;
          case "onlysubs":    $method = "and cat_parent !='0'"; break;
        }

        if ($format == "select") {
           $this->db->query("select * from phpgw_categories where cat_owner='" . $this->account_id
                           . "' $method",__LINE__,__FILE__);
           while ($this->db->next_record()) {
              $s .= '<option value="' . $this->db->f("cat_id") . '">'
                  . $phpgw->strip_html($this->db->f("cat_name")) . '</option>';
           }
           return $s;
        }

     }

     function add($cat_name,$cat_parent,$cat_description = "", $cat_data = "")
     {
        $this->db->query("insert into phpgw_categories (cat_parent,cat_owner,cat_appname,cat_name,"
                       . "cat_description,cat_data) values ('$cat_parent','" . $this->account_id . "','"
                       . $this->app_name . "','" . addslashes($cat_name) . "','" . addslashes($cat_description)
                       . "','$cat_data')",__LINE__,__FILE__);
     }

     function delete($cat_id)
     {
        $this->db->query("delete from phpgw_categories where cat_id='$cat_id' and cat_owner='"
                  . $this->account_id . "'",__LINE__,__FILE__);
     }

     function edit($cat_id,$cat_parent,$cat_name,$cat_description,$cat_data)
     {
         $this->db->query("update phpgw_categories set cat_name='" . addslashes($cat_name) . "', "
                        . "cat_description='" . addslashes($cat_description) . "', cat_data='"
                        . "$cat_data', cat_parent='$cat_parent' where cat_owner='"
                        . $this->account_id . "' and cat_id='$cat_id'",__LINE__,__FILE__);
     }

  }
?>