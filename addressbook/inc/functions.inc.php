<?php
  /**************************************************************************\
  * phpGroupWare - addressbook                                               *
  * http://www.phpgroupware.org                                              *
  * Written by Joseph Engo <jengo@mail.com>                                  *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */
  
  // NOTE: This entire file needs to be rewritten.  There is a great deal of code not being used
  //       anymore. This should also be converted to templates while where at it (jengo)

  $abc = array('company'   => 'company',	// AddressBook Columns and their descriptions
               'firstname' => 'first name',
               'lastname'  => 'last name',
               'email'     => 'email',
               'wphone'    => 'work phone',
               'hphone'    => 'home phone',
               'fax'       => 'fax',
               'pager'     => 'pager',
	       'title'     => 'title',
               'mphone'    => 'mobile phone',
               'ophone'    => 'other phone',
               'street'    => 'street',
               'city'      => 'city',
               'state'     => 'state',
               'zip'       => 'zip code',
               'bday'      => 'birthday',
               'url'       => 'URL'
              );


  function form($format,$action,$title,$fields) {
    global $phpgw, $phpgw_info;
     
    $t = new Template($phpgw_info["server"]["app_tpl"]);
    $t->set_file(array( "form"	=> "form.tpl"));

    $email        = $fields->email;
    $firstname    = $fields->firstname;
    $lastname     = $fields->lastname;
    $title        = $fields->title;
    $hphone       = $fields->hphone;
    $wphone       = $fields->wphone;
    $fax          = $fields->fax;
    $pager        = $fields->pager;
    $mphone       = $fields->mphone;
    $ophone       = $fields->ophone;
    $street       = $fields->street;
    $address2     = $fields->address2;
    $city         = $fields->city;
    $state        = $fields->state;
    $zip          = $fields->zip;
    $bday         = $fields->bday;
    $notes        = $fields->notes;
    $access       = $fields->access;
    $ab_company   = $fields->company;
    $company_id   = $fields->company_id;
    $company_name = $fields->company_name;
    $url          = $fields->url;

    if ($format != "view") {
      $encurl    = ($url);
      $email 	 = "<input name=\"email\" value=\"$email\">";
      $firstname = "<input name=\"firstname\" value=\"$firstname\">";
      $lastname  = "<input name=\"lastname\" value=\"$lastname\">";
      $title     = "<input name=\"title\" value=\"$title\">";
      $hphone	 = "<input name=\"hphone\" value=\"$hphone\">";
      $wphone	 = "<input name=\"wphone\" value=\"$wphone\">";
      $fax	 = "<input name=\"fax\" value=\"$fax\">";
      $pager	 = "<input name=\"pager\" value=\"$pager\">";
      $mphone	 = "<input name=\"mphone\" value=\"$mphone\">";
      $ophone	 = "<input name=\"ophone\" value=\"$ophone\">";
      $street	 = "<input name=\"street\" value=\"$street\">";
      $address2  = "<input name=\"address2\" value=\"$address2\">";
      $city	 = "<input name=\"city\" value=\"$city\">";
      $state	 = "<input name=\"state\" value=\"$state\">";
      $zip	 = "<input name=\"zip\" value=\"$zip\">";
//      $url	 = "<input name=\"url\" value=\"$encurl\">";
              
      if($phpgw_info["apps"]["timetrack"]["enabled"]) {
        $company = '<select name="company">';
        $phpgw->db->query("select company_id,company_name from customers order by company_name");
        while ($phpgw->db->next_record()) {
          $ncust = $phpgw->db->f("company_id");
          $company = $company . '<option value="' . $ncust . '"';
          if ( $company_id == $ncust ) {
            $company = $company . " selected";
          }
            $company = $company . ">" . $phpgw->db->f("company_name") . "</option>";
          }
        $company = $company . "</select>";
      } else {
        $company = "<input name=\"company\" value=\"$ab_company\">";
      }

      if (strlen($bday) > 2) {
        list( $month, $day, $year ) = split( '/', $bday );
        $temp_month[$month] = "SELECTED";

        $bday_month = "<select name=bday_month>"
                    . "<option value=\"\" $temp_month[0]> </option>"
                    . "<option value=1 $temp_month[1]>January</option>" 
                    . "<option value=2 $temp_month[2]>February</option>"
                    . "<option value=3 $temp_month[3]>March</option>"
                    . "<option value=4 $temp_month[4]>April</option>"
                    . "<option value=5 $temp_month[5]>May</option>"
                    . "<option value=6 $temp_month[6]>June</option>" 
                    . "<option value=7 $temp_month[7]>July</option>"
                    . "<option value=8 $temp_month[8]>August</option>"
                    . "<option value=9 $temp_month[9]>September</option>"
                    . "<option value=10 $temp_month[10]>October</option>"
                    . "<option value=11 $temp_month[11]>November</option>"
                    . "<option value=12 $temp_month[12]>December</option>"
                    . "</select>";
        $bday_day   = '<input maxlength="2" name="bday_day" value="' . $day . '" size="2">';
        $bday_year  = '<input maxlength="4" name="bday_year" value="' . $year . '" size="4">';
      } else {
        $bday_month = "<select name=bday_month>"
                    . "<option value=\"\" SELECTED> </option>"
                    . "<option value=1>January</option>" 
                    . "<option value=2>February</option>"
                    . "<option value=3>March</option>"
                    . "<option value=4>April</option>"
                    . "<option value=5>May</option>"
                    . "<option value=6>June</option>" 
                    . "<option value=7>July</option>"
                    . "<option value=8>August</option>"
                    . "<option value=9>September</option>"
                    . "<option value=10>October</option>"
                    . "<option value=11>November</option>"
                    . "<option value=12>December</option>"
                    . "</select>";
        $bday_day  = '<input name="bday_day" size="2" maxlength="2">';
        $bday_year = '<input name="bday_year" size="4" maxlength="4">';
      }

    $notes	 = '<TEXTAREA cols="60" name="notes" rows="4">' . $notes . '</TEXTAREA>';
    } else {
      $notes	= "<form><TEXTAREA cols=\"60\" name=\"notes\" rows=\"4\">"
		. $notes . "</TEXTAREA></form>";
      if ($bday == "//")
        $bday = "";
      if($phpgw_info["apps"]["timetrack"]["enabled"]) {
        $company = $company_name;
      } else {
        $company = $ab_company;
      }
    }

  if ($action) {
     echo "<FORM action=\"".$phpgw->link($action)."\" method=\"post\">\n";
  }

  // test:
  //echo "Time track app status = " . $phpgw_info["apps"]["timetrack"]["enabled"];

    if (! ereg("^http://",$url)) {
      $url = "http://". $url;
    } 

    $birthday = $phpgw->common->dateformatorder($bday_year,$bday_month,$bday_day)
          . '<font face="'.$theme["font"].'" size="-2">(e.g. 1969)</font>';

    if ($format == "view") {
       if ($access != "private" && $access != "public") {
	  $access_link .= '<td><font size="-1">'.lang("Group access").':</font></td>'
	     . '<td colspan="3"><font size="-1">'
	     . $phpgw->accounts->convert_string_to_names($access);
       } else {
	  $access_link .=  '<td><font size="-1">'.lang("Access").':</font></td>'
	     . '<td colspan="3"><font size="-1">'
	     . $access;
       }
    } else {
          $access_link .= '<td><font size="-1">'.lang("Access").':</font></td>
    <td colspan="3">
      <font size="-1">
      <select name="access">
       <option value="private"';

        if ($access == "private") $access_link .= ' selected>'.lang("private").'</option>';
        else $access_link .= '>'.lang("private").'</option>';

	$access_link .= '<option value="public"
	';

        if ($access == "public")
          $access_link .= ' selected>'.lang("Global Public").'</option>';
        else $access_link .= '>'.lang("Global Public").'</option>';
        
        $access_link .= '<option value="group"
        ';

        if ($access != "public" && $access != "private" && $access != "")
          $access_link .= ' selected>'.lang("Group Public").'</option></select>';
        else
          $access_link .= '>'.lang("Group Public").'</option></select>';

        $access_link .= '</tr>
        ';
    }

      if ($format != "view") {
         $access_link .= '<tr><td><font size="-1">' . lang("Which groups")
	    . ':</font></td><td colspan="3"><select name="n_groups[]" '
	    . 'multiple size="5">';

        $user_groups = $phpgw->accounts->read_group_names($fields["ab_owner"]);
        for ($i=0;$i<count($user_groups);$i++) {
            $access_link .= '<option value="'.$user_groups[$i][0].'"';
            if (ereg(",".$user_groups[$i][0].",",$access))
               $access_link .= ' selected';

            $access_link .= '>'.$user_groups[$i][1].'</option>
	    ';
        }
        $access_link .= '</select></font></td></tr>';
	$t->set_var("lang_access",lang("access"));
      } else {
        $access_link = '';
	$t->set_var("lang_access",'');
      }

    if ($format == "view")
       $create .= '<tr><td><font size="-1">'.lang("Created by").':</font></td>'
	  . '<td colspan="3"><font size="-1">'
	  . grab_owner_name($fields["owner"]);
    else
       $create = '';
  
    $t->set_var("lang_lastname",lang("Last Name"));
    $t->set_var("lastname",$lastname);
    $t->set_var("lang_firstname",lang("First Name"));
    $t->set_var("firstname",$firstname);
    $t->set_var("lang_company",lang("Company Name"));
    $t->set_var("company",$company);
    $t->set_var("lang_title",lang("Title"));
    $t->set_var("title",$title);
    $t->set_var("lang_email",lang("Email"));
    $t->set_var("email",$email);
    $t->set_var("lang_url",lang("URL"));
    $t->set_var("url",$url);
    $t->set_var("lang_hphone",lang("Home Phone"));
    $t->set_var("hphone",$hphone);
    $t->set_var("lang_fax",lang("fax"));
    $t->set_var("fax",$fax);
    $t->set_var("lang_wphone",lang("Work Phone"));
    $t->set_var("wphone",$wphone);
    $t->set_var("lang_pager",lang("Pager"));
    $t->set_var("pager",$pager);
    $t->set_var("lang_mphone",lang("Mobile"));
    $t->set_var("mphone",$mphone);
    $t->set_var("lang_ophone",lang("Other Number"));
    $t->set_var("ophone",$ophone);
    $t->set_var("lang_street",lang("Street"));
    $t->set_var("street",$street);
    $t->set_var("lang_birthday",lang("Birthday"));
    $t->set_var("birthday",$birthday);
    $t->set_var("lang_address2",lang("Line 2"));
    $t->set_var("address2",$address2);
    $t->set_var("lang_city",lang("city"));
    $t->set_var("city",$city);
    $t->set_var("lang_state",lang("state"));
    $t->set_var("state",$state);
    $t->set_var("lang_zip",lang("Zip Code"));
    $t->set_var("zip",$zip);
    $t->set_var("access_link",$access_link);
    $t->set_var("create",$create);
    $t->set_var("lang_notes",lang("notes"));
    $t->set_var("notes",$notes);

    $t->parse("out","form");
    $t->pparse("out","form");
  } //end form function

  function NOlist_entries($start="",$sort="",$order="",$query="",$filter="") {
    global $phpgw,$phpgw_info;
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
      if ($phpgw_info["apps"]["timetrack"]["enabled"]) {
        $phpgw->db->query("SELECT count(*) "
        . "from addressbook as a, customers as c where a.ab_company_id = c.company_id "
        . "AND $filtermethod AND (a.ab_lastname like '"
        . "%$query%' OR a.ab_firstname like '%$query%' OR a.ab_email like '%$query%' OR "
        . "a.ab_street like '%$query%' OR a.ab_city like '%$query%' OR a.ab_state "
        . "like '%$query%' OR a.ab_zip like '%$query%' OR a.ab_notes like "
        . "'%$query%' OR c.company_name like '%$query%' OR a.ab_url like '%$query%')",__LINE__,__FILE__);
//      . "'%$query%' OR c.company_name like '%$query%')"
//      . " $ordermethod limit $limit");
      } else {
        $phpgw->db->query("SELECT count(*) "
        . "from addressbook "
        . "WHERE $filtermethod AND (ab_lastname like '"
        . "%$query%' OR ab_firstname like '%$query%' OR ab_email like '%$query%' OR "
        . "ab_street like '%$query%' OR ab_city like '%$query%' OR ab_state "
        . "like '%$query%' OR ab_zip like '%$query%' OR ab_notes like "
        . "'%$query%' OR ab_company like '%$query%' OR ab_url like '%$query$%')",__LINE__,__FILE__);
//      . "'%$query%' OR ab_company like '%$query%')"
//      . " $ordermethod limit $limit");
      }

      $phpgw->db->next_record();

      if ($phpgw->db->f(0) == 1) {
        $searchreturn=lang("your search returned 1 match");
      } else {
        $searchreturn=lang("your search returned x matchs",$phpgw->db->f(0));
      }
    } else {
      $searchreturn="";
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
      $lang_showing=lang("showing x - x of x",($start + 1),($start + $phpgw_info["user"]["preferences"]["common"]["maxmatchs"]),$phpgw->db->f(0));
    } else {
      $lang_showing=lang("showing x",$phpgw->db->f(0));
    }

    $search_filter=$phpgw->nextmatchs->show_tpl("index.php",$start,$phpgw->db->f(0),"&order=$order&filter=$filter&sort=$sort&query=$query", "75%", $phpgw_info["theme"]["th_bg"]);

    while ($column = each($this)) {
      if (isset($phpgw_info["user"]["preferences"]["addressbook"][$column[0]]) &&
                $phpgw_info["user"]["preferences"]["addressbook"][$column[0]]) {
        $cols .= '<td height="21">';
        $cols .= '<font size="-1" face="Arial, Helvetica, sans-serif">';
        $cols .= $phpgw->nextmatchs->show_sort_order($sort,"ab_" . $column[0],$order,"index.php",lang($column[1]));
        $cols .= '</font></td>';
        $cols .= "\n";
           
        // To be used when displaying the rows
        $columns_to_display[$column[0]] = True;
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
    }		// else $query
    $rows="";
    while ($phpgw->db->next_record()) {
      $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
      $rows .= '<tr bgcolor="#'.$tr_color . '">';
  
      $ab_id = $phpgw->db->f("ab_id");
  
      while ($column = each($columns_to_display)) {
        if ($column[0] == "company") {
          if ($phpgw_info["apps"]["timetrack"]["enabled"]) {        
            $field   = $phpgw->db->f("company_name");
          } else {
            $field = $phpgw->db->f("ab_company");
          }
        } else {
          $field = $phpgw->db->f("ab_" . $column[0]);
        }

        $field = htmlentities($field);

        // Some fields require special formating.       
        if ($column[0] == "url") {
          if (! ereg("^http://",$field)) {
            $data = "http://" . $field;
          }
          $rows .= '<td valign="top"><font face="' . $phpgw_info["theme"]["font"] . '" size="2">'
          . '<a href="' . $field . '" target="_new">' . $field. '</a>&nbsp;</font></td>';
          } else if ($column[0] == "email") {
            if ($phpgw_info["user"]["apps"]["email"]) {
              $rows .= '<td valign="top"><font face="' . $phpgw_info["theme"]["font"] . '" size="2">'
              . '<a href="' . $phpgw->link($phpgw_info["server"]["webserver_url"] . "/email/compose.php",
                   "to=" . urlencode($field)) . '" target="_new">' . $field . '</a>&nbsp;</font></td>';
            } else {
              $rows .= '<td valign="top"><font face="' . $phpgw_info["theme"]["font"] . '" size="2">'
              . '<a href="mailto:' . $field . '">' . $field. '</a>&nbsp;</font></td>';
            }
        } else {
          $rows .= '<td valign="top"><font face="' . $phpgw_info["theme"]["font"] . '" size="2">'
          . $field . '&nbsp;</font></td>';
        }
        #echo '</tr>';
      }
      reset($columns_to_display);		// If we don't reset it, our inside while won't loop
      $rows .= '<td valign="top" width="3%">
    <font face="'.$phpgw_info["theme"]["font"].'" size="2">
    <a href="'. $phpgw->link("view.php","ab_id=$ab_id&start=$start&order=$order&filter="
								 . "$filter&query=$query&sort=$sort").'
     ">'.lang("View").'</a>
     </font>
    </td>
     <td valign=top width=3%>
      <font face="'.$phpgw_info["theme"]["font"].'" size=2>
        <a href="'.$phpgw->link("vcardout.php","ab_id=$ab_id&start=$start&order=$order&filter="
                . "$filter&query=$query&sort=$sort").'
        ">'.lang("vcard").'</a>
      </font>
     </td>
    <td valign="top" width="5%">
     <font face="'.$phpgw_info["theme"]["font"].'" size="2">
      '.$phpgw->common->check_owner($phpgw->db->f("ab_owner"),"edit.php",lang("edit"),"ab_id=" . $phpgw->db->f("ab_id")."&start=".$start."&sort=".$sort."&order=".$order).'
     </font>
    </td>
   </tr>
';
    }
    return array($cols,$rows,$searchreturn,$lang_showing,$search_filter);
  } //end list function

?>
