<?php
  /**************************************************************************\
  * phpGroupWare - addressbook sql                                           *
  * http://www.phpgroupware.org                                              *
  * Written by Joseph Engo <jengo@phpgroupware.org>                          *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  class addressbook_
  {
    var $id;
    var $company;
    var $firstname;
    var $lastname;
    var $email;
    var $wphone;
    var $hphone;
    var $company;
    var $fax;
    var $pager;
    var $mphone;
    var $ophone;
    var $street;
    var $city;
    var $state;
    var $zip;
    var $bday;
    var $url;
    var $notes;
    var $access;
    var $searchreturn;
    var $search_filter;
    var $lang_showing;
    var $columns_to_display;
    var $cols;

    function get_entry($id) {
      global $phpgw,$phpgw_info;
      $phpgw->db->query("SELECT * FROM addressbook WHERE ab_owner='"
        . $phpgw_info["user"]["account_id"] . "' AND ab_id='".$id."'");
      $phpgw->db->next_record();

      $this->ab_id       = stripslashes($phpgw->db->f("ab_id"));
      $this->owner       = stripslashes($phpgw->db->f("ab_owner"));
      $this->access      = stripslashes($phpgw->db->f("ab_access"));
      $this->firstname   = stripslashes($phpgw->db->f("ab_firstname"));
      $this->lastname    = stripslashes($phpgw->db->f("ab_lastname"));
      $this->title       = stripslashes($phpgw->db->f("ab_title"));
      $this->email       = stripslashes($phpgw->db->f("ab_email"));
      $this->hphone      = stripslashes($phpgw->db->f("ab_hphone"));
      $this->wphone      = stripslashes($phpgw->db->f("ab_wphone"));
      $this->fax         = stripslashes($phpgw->db->f("ab_fax"));
      $this->pager       = stripslashes($phpgw->db->f("ab_pager"));
      $this->mphone      = stripslashes($phpgw->db->f("ab_mphone"));
      $this->ophone      = stripslashes($phpgw->db->f("ab_ophone"));
      $this->street      = stripslashes($phpgw->db->f("ab_street"));
      $this->address2    = stripslashes($phpgw->db->f("ab_address2"));
      $this->city        = stripslashes($phpgw->db->f("ab_city"));
      $this->state       = stripslashes($phpgw->db->f("ab_state"));
      $this->zip         = stripslashes($phpgw->db->f("ab_zip"));
      $this->bday        = stripslashes($phpgw->db->f("ab_bday"));
      $this->company     = stripslashes($phpgw->db->f("ab_company"));
      $this->company_id  = stripslashes($phpgw->db->f("ab_company_id"));
      $this->notes       = stripslashes($phpgw->db->f("ab_notes"));
      $this->url         = stripslashes($phpgw->db->f("ab_url"));
      $this->access      = stripslashes($phpgw->db->f("ab_access"));

      return $this;
    }

    function add_entry() {
      global $phpgw,$phpgw_info;

      if($phpgw_info["apps"]["timetrack"]["enabled"]) {
        $sql = "INSERT INTO addressbook ("
            . "ab_email,ab_firstname,ab_lastname,ab_title,ab_hphone,ab_wphone,"
	    . "ab_fax,ab_pager,ab_mphone,ab_ophone,ab_street,ab_address2,"
	    . "ab_city,ab_state,ab_zip,ab_bday,ab_notes,ab_company_id,ab_access,ab_url,"
	    . "ab_owner) VALUES ("
	    . "  '" . addslashes($this->email)
            . "','" . addslashes($this->firstname)
	    . "','" . addslashes($this->lastname)
            . "','" . addslashes($this->title)
   	    . "','" . addslashes($this->hphone)
	    . "','" . addslashes($this->wphone)
   	    . "','" . addslashes($this->fax)
	    . "','" . addslashes($this->pager)
   	    . "','" . addslashes($this->mphone)
	    . "','" . addslashes($this->ophone)
   	    . "','" . addslashes($this->street)
            . "','" . addslashes($this->address2)
	    . "','" . addslashes($this->city)
   	    . "','" . addslashes($this->state)
	    . "','" . addslashes($this->zip)
   	    . "','" . addslashes($this->bday)
	    . "','" . addslashes($this->notes)
   	    . "','" . addslashes($this->company)
	    . "','" . addslashes($this->access)
	    . "','" . addslashes($this->url)
   	    . "','" . $phpgw_info["user"]["account_id"]
            . "')";
      } else {
        $sql = "INSERT INTO addressbook ("
            . "ab_email,ab_firstname,ab_lastname,ab_title,ab_hphone,ab_wphone,"
	    . "ab_fax,ab_pager,ab_mphone,ab_ophone,ab_street,ab_address2,"
	    . "ab_city,ab_state,ab_zip,ab_bday,ab_notes,ab_company,ab_access,ab_url,"
	    . "ab_owner) VALUES ("
	    . "  '" . addslashes($this->email)
            . "','" . addslashes($this->firstname)
	    . "','" . addslashes($this->lastname)
            . "','" . addslashes($this->title)
   	    . "','" . addslashes($this->hphone)
	    . "','" . addslashes($this->wphone)
   	    . "','" . addslashes($this->fax)
	    . "','" . addslashes($this->pager)
   	    . "','" . addslashes($this->mphone)
	    . "','" . addslashes($this->ophone)
   	    . "','" . addslashes($this->street)
            . "','" . addslashes($this->address2)
	    . "','" . addslashes($this->city)
   	    . "','" . addslashes($this->state)
	    . "','" . addslashes($this->zip)
   	    . "','" . addslashes($this->bday)
	    . "','" . addslashes($this->notes)
   	    . "','" . addslashes($this->company)
	    . "','" . addslashes($this->access)
	    . "','" . addslashes($this->url)
   	    . "','" . $phpgw_info["user"]["account_id"]
            . "')";
      }
      $phpgw->db->query($sql);
      return;
    }

    function update_entry() {
      global $phpgw,$phpgw_info;

      if($phpgw_info["apps"]["timetrack"]["enabled"]) {
        $sql = "UPDATE addressbook set "
            . "   ab_email='"       . addslashes($this->email)
            . "', ab_firstname='"   . addslashes($this->firstname)
	    . "', ab_lastname='"    . addslashes($this->lastname)
            . "', ab_title='"       . addslashes($this->title)
   	    . "', ab_hphone='" 	    . addslashes($this->hphone)
	    . "', ab_wphone='" 	    . addslashes($this->wphone)
   	    . "', ab_fax='"         . addslashes($this->fax)
	    . "', ab_pager='"       . addslashes($this->pager)
   	    . "', ab_mphone='" 	    . addslashes($this->mphone)
	    . "', ab_ophone='" 	    . addslashes($this->ophone)
   	    . "', ab_street='" 	    . addslashes($this->street)
            . "', ab_address2='"    . addslashes($this->address2)
	    . "', ab_city='" 	    . addslashes($this->city)
   	    . "', ab_state='" 	    . addslashes($this->state)
	    . "', ab_zip='" 	    . addslashes($this->zip)
   	    . "', ab_bday='"        . addslashes($this->bday)
	    . "', ab_notes='"       . addslashes($this->notes)
   	    . "', ab_company_id='"  . addslashes($this->company)
	    . "', ab_access='" 	    . addslashes($this->access)
	    . "', ab_url='"    	    . addslashes($this->url)
   	    . "'  WHERE ab_owner='" . $phpgw_info["user"]["account_id"]
            . "'  AND ab_id='"      . $this->id."'";
      } else {
        $sql = "UPDATE addressbook set "
            . "   ab_email='"       . addslashes($this->email)
            . "', ab_firstname='"   . addslashes($this->firstname)
            . "', ab_lastname='"    . addslashes($this->lastname)
            . "', ab_title='"       . addslashes($this->title)
            . "', ab_hphone='"      . addslashes($this->hphone)
            . "', ab_wphone='"      . addslashes($this->wphone)
            . "', ab_fax='"         . addslashes($this->fax)
            . "', ab_pager='"       . addslashes($this->pager)
            . "', ab_mphone='"      . addslashes($this->mphone)
            . "', ab_ophone='"      . addslashes($this->ophone)
            . "', ab_street='"      . addslashes($this->street)
            . "', ab_address2='"    . addslashes($this->address2)
            . "', ab_city='"        . addslashes($this->city)
            . "', ab_state='"       . addslashes($this->state)
            . "', ab_zip='"         . addslashes($this->zip)
            . "', ab_bday='"        . addslashes($this->bday)
            . "', ab_notes='"       . addslashes($this->notes)
            . "', ab_company='"     . addslashes($this->company)
            . "', ab_access='"      . addslashes($this->access)
            . "', ab_url='"         . addslashes($this->url)
            . "'  WHERE ab_owner='" . $phpgw_info["user"]["account_id"]
            . "'  AND ab_id='"      . $this->id."'";
      }
      $phpgw->db->query($sql);
      return;
    }

    function delete_entry() {
      global $phpgw,$phpgw_info;

      $phpgw->db->query("delete from addressbook where ab_owner='"
        . $phpgw_info["user"]["account_id"]
        . "' and ab_id='".$this->id."'");

      return;
    }
    
    function count_entries($query,$filter,$filtermethod) {
      global $phpgw,$phpgw_info;
      if ($phpgw_info["apps"]["timetrack"]["enabled"]) {
        $phpgw->db->query("SELECT count(*) "
        . "from addressbook as a, customers as c where a.ab_company_id = c.company_id "
        . "AND $filtermethod AND (a.ab_lastname like '"
        . "%$query%' OR a.ab_firstname like '%$query%' OR a.ab_email like '%$query%' OR "
        . "a.ab_street like '%$query%' OR a.ab_city like '%$query%' OR a.ab_state "
        . "like '%$query%' OR a.ab_zip like '%$query%' OR a.ab_notes like "
        . "'%$query%' OR c.company_name like '%$query%' OR a.ab_url like '%$query%')",__LINE__,__FILE__);
//      . "'%$query%' OR c.company_name like '%$query%')"
      } else {
        $phpgw->db->query("SELECT count(*) "
        . "from addressbook "
        . "WHERE $filtermethod AND (ab_lastname like '"
        . "%$query%' OR ab_firstname like '%$query%' OR ab_email like '%$query%' OR "
        . "ab_street like '%$query%' OR ab_city like '%$query%' OR ab_state "
        . "like '%$query%' OR ab_zip like '%$query%' OR ab_notes like "
        . "'%$query%' OR ab_company like '%$query%' OR ab_url like '%$query$%')",__LINE__,__FILE__);
//      . "'%$query%' OR ab_company like '%$query%')"
      }
      $phpgw->db->next_record();

      if ($phpgw->db->f(0) == 1) {
        return lang("your search returned 1 match");
      } else {
        $this->limit = $phpgw->db->f(0);
        return lang("your search returned x matchs",$phpgw->db->f(0));
      }
    }

    function get_entries($query="",$filter="",$sort="",$order="",$start=0) {
      global $phpgw,$phpgw_info,$abc;

      $limit = $phpgw->nextmatchs->sql_limit($start);

      if ($order) {
        $ordermethod = "order by $order $sort";
      } else {
        $ordermethod = "order by ab_lastname,ab_firstname,ab_email asc";
      }
      
      if (! $filter) {
        $filter = "none";
      }

      if ($filter != "private") {
        if ($filter != "none") {
          $filtermethod = " ab_access like '%,$filter,%' ";
        } else {
          $filtermethod = " (ab_owner='" . $phpgw_info["user"]["account_id"] ."' OR ab_access='public' "
                      . $phpgw->accounts->sql_search("ab_access") . " ) ";
        }
      } else {
        $filtermethod = " ab_owner='" . $phpgw_info["user"]["account_id"] . "' ";
      }

      if ($query) {
        $this->searchreturn=$this->count_entries($query,$filter,$filtermethod);
      } else {
        $this->searchreturn="";
        $phpgw->db->query("select count(*) from addressbook where $filtermethod",__LINE__,__FILE__);
        $phpgw->db->next_record();
      }

      if ($phpgw_info["apps"]["timetrack"]["enabled"]) {
        $company_sortorder = "c.company_name";
      } else {
        $company_sortorder = "ab_company";
      }

      //$phpgw->db->next_record();

      if ($phpgw->db->f(0) > $phpgw_info["user"]["preferences"]["common"]["maxmatchs"]) {
        $this->lang_showing=lang("showing x - x of x",($start + 1),($start + $phpgw_info["user"]["preferences"]["common"]["maxmatchs"]),$phpgw->db->f(0));
      } else {
        $this->lang_showing=lang("showing x",$phpgw->db->f(0));
      }

      $this->search_filter = $phpgw->nextmatchs->show_tpl("index.php",$start,$phpgw->db->f(0),"&order=$order&filter=$filter&sort=$sort&query=$query", "75%", $phpgw_info["theme"]["th_bg"]);

      while ($column = each($abc)) {
        if (isset($phpgw_info["user"]["preferences"]["addressbook"][$column[0]]) &&
          $phpgw_info["user"]["preferences"]["addressbook"][$column[0]]) {
          $this->cols .= '<td height="21">';
          $this->cols .= '<font size="-1" face="Arial, Helvetica, sans-serif">';
          $this->cols .= $phpgw->nextmatchs->show_sort_order($sort,"ab_" . $column[0],$order,"index.php",lang($column[1]));
          $this->cols .= '</font></td>';
          $this->cols .= "\n";
             
      // To be used when displaying the rows
          $this->columns_to_display[$column[0]] = True;
        }
      }

      if (isset($query) && $query) {
        if (isset($phpgw_info["apps"]["timetrack"]["enabled"]) &&
                $phpgw_info["apps"]["timetrack"]["enabled"]) {
          $phpgw->db->query("SELECT a.ab_id,a.ab_owner,a.ab_firstname,a.ab_lastname,a.ab_company_id,"
                      . "a.ab_email,a.ab_wphone,c.company_name,a.ab_hphone,a.ab_fax,a.ab_mphone "
                      . "from addressbook as a, customers as c where a.ab_company_id = c.company_id "
                      . "AND $filtermethod AND (a.ab_lastname like '"
                      . "%$query%' OR a.ab_firstname like '%$query%' OR a.ab_email like '%$query%' OR "
                      . "a.ab_street like '%$query%' OR a.ab_city like '%$query%' OR a.ab_state "
                      . "like '%$query%' OR a.ab_zip like '%$query%' OR a.ab_notes like "
                      . "'%$query%' OR c.company_name like '%$query%') $ordermethod limit $limit",__LINE__,__FILE__);
        } else {
          $phpgw->db->query("SELECT * from addressbook WHERE $filtermethod AND (ab_lastname like '"
                     . "%$query%' OR ab_firstname like '%$query%' OR ab_email like '%$query%' OR "
                     . "ab_street like '%$query%' OR ab_city like '%$query%' OR ab_state "
                     . "like '%$query%' OR ab_zip like '%$query%' OR ab_notes like "
                     . "'%$query%' OR ab_company like '%$query%') $ordermethod limit $limit",__LINE__,__FILE__);
        }
      } else {
        if ($phpgw_info["apps"]["timetrack"]["enabled"]) {
          $phpgw->db->query("SELECT a.ab_id,a.ab_owner,a.ab_firstname,a.ab_lastname,"
                     . "a.ab_email,a.ab_wphone,c.company_name "
                     . "from addressbook as a, customers as c where a.ab_company_id = c.company_id "
                     . "AND $filtermethod $ordermethod limit $limit",__LINE__,__FILE__);
        } else {
          $phpgw->db->query("SELECT * from addressbook WHERE $filtermethod $ordermethod limit $limit",__LINE__,__FILE__);
        }
      }
      
      $i=0;
      while ($phpgw->db->next_record()) {
        $this->ab_id[$i]       = htmlentities(stripslashes($phpgw->db->f("ab_id")));
        $this->owner[$i]       = htmlentities(stripslashes($phpgw->db->f("ab_owner")));
        $this->access[$i]      = htmlentities(stripslashes($phpgw->db->f("ab_access")));
        $this->firstname[$i]   = htmlentities(stripslashes($phpgw->db->f("ab_firstname")));
        $this->lastname[$i]    = htmlentities(stripslashes($phpgw->db->f("ab_lastname")));
        $this->title[$i]       = htmlentities(stripslashes($phpgw->db->f("ab_title")));
        $this->email[$i]       = htmlentities(stripslashes($phpgw->db->f("ab_email")));
        $this->hphone[$i]      = htmlentities(stripslashes($phpgw->db->f("ab_hphone")));
        $this->wphone[$i]      = htmlentities(stripslashes($phpgw->db->f("ab_wphone")));
        $this->fax[$i]         = htmlentities(stripslashes($phpgw->db->f("ab_fax")));
        $this->pager[$i]       = htmlentities(stripslashes($phpgw->db->f("ab_pager")));
        $this->mphone[$i]      = htmlentities(stripslashes($phpgw->db->f("ab_mphone")));
        $this->ophone[$i]      = htmlentities(stripslashes($phpgw->db->f("ab_ophone")));
        $this->street[$i]      = htmlentities(stripslashes($phpgw->db->f("ab_street")));
        $this->address2[$i]    = htmlentities(stripslashes($phpgw->db->f("ab_address2")));
        $this->city[$i]        = htmlentities(stripslashes($phpgw->db->f("ab_city")));
        $this->state[$i]       = htmlentities(stripslashes($phpgw->db->f("ab_state")));
        $this->zip[$i]         = htmlentities(stripslashes($phpgw->db->f("ab_zip")));
        $this->bday[$i]        = htmlentities(stripslashes($phpgw->db->f("ab_bday")));
        if ($phpgw_info["apps"]["timetrack"]["enabled"]) {
          $this->company[$i]   = htmlentities(stripslashes($phpgw->db->f("ab_company")));
        } else {
          $this->company[$i]   = htmlentities(stripslashes($phpgw->db->f("company_name")));
        }
        $this->company_id[$i]  = htmlentities(stripslashes($phpgw->db->f("ab_company_id")));
        $this->notes[$i]       = htmlentities(stripslashes($phpgw->db->f("ab_notes")));
	if ($phpgw->db->f("ab_url")) {
          if (! ereg("^http://",$phpgw->db->f("ab_url")) ) {
            $this->url[$i]       = htmlentities("http://".stripslashes($phpgw->db->f("ab_url")));
          } else {
            $this->url[$i]       = htmlentities(stripslashes($phpgw->db->f("ab_url")));
          }
        } else {
          $this->url[$i]         = htmlentities("");
        }
        $i++;
      }
      return $this;
    }
  }
?>
