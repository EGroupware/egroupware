<?php
  /**************************************************************************\
  * phpGroupWare API - Categories                                            *
  * http://www.phpgroupware.org/api                                          *
  * This file written by Joseph Engo <jengo@phpgroupware.org>                *
  * Category manager                                                         *
  * Copyright (C) 2000, 2001 Joseph Engo                                     *
  * -------------------------------------------------------------------------*
  * This library is part of phpGroupWare (http://www.phpgroupware.org)       * 
  * This library is free software; you can redistribute it and/or modify it  *
  * under the terms of the GNU Lesser General Public License as published by *
  * the Free Software Foundation; either version 2.1 of the License,         *
  * or any later version.                                                    *
  * This library is distributed in the hope that it will be useful, but      *
  * WITHOUT ANY WARRANTY; without even the implied warranty of               *
  * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.                     *
  * See the GNU Lesser General Public License for more details.              *
  * You should have received a copy of the GNU Lesser General Public License *
  * along with this library; if not, write to the Free Software Foundation,  *
  * Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA            *
  \**************************************************************************/

  /* $Id$ */

  class categories
  {
     var $account_id;
     var $app_name;
     var $cats;
     var $db;
     
     function categories($account_id = "",$app_name = "")
     {
        global $phpgw, $phpgw_info;

        if (! $account_id) {
           $account_id = $phpgw_info["user"]["account_id"];
        }
        if (! $app_name) {
           $app_name   = $phpgw_info["flags"]["currentapp"];
        }

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