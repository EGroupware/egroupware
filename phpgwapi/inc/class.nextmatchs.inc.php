<?php
  /**************************************************************************\
  * phpGroupWare API - next                                                  *
  * This file written by Joseph Engo <jengo@phpgroupware.org>                *
  * Handles limiting number of rows displayed                                *
  * Copyright (C) 2000, 2001 Joseph Engo                                     *
  * -------------------------------------------------------------------------*
  * This library is part of the phpGroupWare API                             *
  * http://www.phpgroupware.org/api                                          * 
  * ------------------------------------------------------------------------ *
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
class nextmatchs
{

  // I split this up so it can be used in differant layouts.
  function show($sn,$start,$total,$extra, $twidth, $bgtheme,
                    $search_obj=0,$filter_obj=1,$showsearch=1)
  {
    echo $this->tablestart($sn,$twidth, $bgtheme);
    echo $this->left($sn,$start,$total,$extra);
    if ($showsearch == 1)
    {
        echo $this->search($search_obj);
    }
    echo $this->filter($filter_obj);
    echo $this->right($sn,$start,$total,$extra);
    echo $this->tableend();
  }

  // --------------------------------------------------------------------
  // same as show, only without direct output for use within templates
  // *** the show function can be removed as soon as every program using
  //     nextmatch is converted to use template and show_tpl (loge)
  // --------------------------------------------------------------------
  function show_tpl($sn,$start,$total,$extra, $twidth, $bgtheme,
                    $search_obj=0,$filter_obj=1,$showsearch=1)
  {
    $var  = $this->tablestart($sn,$twidth, $bgtheme);
    $var .= $this->left($sn,$start,$total,$extra);
    if ($showsearch == 1)
    {
        $var .= $this->search($search_obj);
    }
    $var .= $this->filter($filter_obj);
    $var .= $this->right($sn,$start,$total,$extra);
    $var .= $this->tableend();
    return $var;
  }

    function tablestart($scriptname, $twidth="75%", $bgtheme="D3DCE3")
    {
    	global $filter, $qfield, $start, $order, $sort, $query, $phpgw;
    	
    	$str = "<form method=\"POST\" action=\"" . $phpgw->link($scriptname) . "\">
    	        <input type=\"hidden\" name=\"filter\" value=\"$filter\">
   		   <input type=\"hidden\" name=\"qfield\" value=\"$qfield\">
   		   <input type=\"hidden\" name=\"start\" value=\"$start\">
   		   <input type=\"hidden\" name=\"order\" value=\"$order\">
   		   <input type=\"hidden\" name=\"sort\" value=\"$sort\">
   		   <input type=\"hidden\" name=\"query\" value=\"$query\">";
   		
    	$str .= "<table width=\"$twidth\" height=\"50\" border=\"0\" bgcolor=\"$bgtheme\" cellspacing=\"0\" cellpadding=\"0\">\n<tr>\n";
    	return $str;
    }

    function tableend()
    {
    	$str = "</tr>\n</table>\n<br>";
    	$str .= "</form>";
    	return $str;
    }    	
    
  
  function left($scriptname,$start,$total,$extradata = "")
  {
    global $filter, $qfield, $order, $sort, $query, $phpgw_info, $phpgw;

    $str = "";
    $maxmatchs = $phpgw_info["user"]["preferences"]["common"]["maxmatchs"];

    if (( $start != 0 ) && ( $start > $maxmatchs ))
      $str .= "<td width=\"2%\" align=\"left\">&nbsp;<a href=\""
	   . $phpgw->link($scriptname,"start=0"
           . "&order=$order&filter=$filter&qfield=$qfield"
           . "&sort=$sort&query=$query".$extradata)
	   . "\"><img src=\"".$phpgw_info["server"]["images_dir"]
	   . "/first.gif\" border=0 width=\"12\" height=\"12\" alt=\""
           . lang("First Page") . "\"></a></td>\n";
    else 
      $str .= "<td width=\"2%\" align=\"left\">"
           . "&nbsp;<img src=\"".$phpgw_info["server"]["images_dir"]
	   . "/first-grey.gif\" "."width=\"12\" height=\"12\" alt=\""
	   . lang("First Page")."\"></td>\n";

    if ($start != 0) {
       // Changing the sorting order screaws up the starting number
       if ( ($start - $maxmatchs) < 0)
          $t_start = 0;
       else
          $t_start = ($start - $maxmatchs);

       $str .= "<td width=\"2%\" align=\"left\"><a href=\""
	    . $phpgw->link($scriptname,"start=$t_start"
	    . "&order=$order&filter=$filter&qfield=$qfield"
            . "&sort=$sort&query=$query".$extradata)
	    . "\"><img src=\"".$phpgw_info["server"]["images_dir"]
	    . "/left.gif\" border=0 width=\"12\" height=\"12\" alt=\""
            . lang("Previous Page") . "\"></a></td>\n";
    } else
      $str .= "<td width=\"2%\" align=\"left\">"
           . "<img src=\"" . $phpgw_info["server"]["images_dir"]
	   . "/left-grey.gif\" width=\"12\" height=\"12\" alt=\""
           . lang("Previous Page") . "\"></td>\n";

    return $str;
  } /* left() */

  function search($search_obj=0)
  {
     global $query;

     $str = "<td width=\"40%\">"
         . "<div align=\"center\">"
         . "<input type=\"text\" name=\"query\" value=\"".urldecode($query)."\">&nbsp;"
         . $this->searchby($search_obj)
         . "<input type=\"submit\" name=\"Search\" value=\"" . lang("Search") ."\">"
         . "</div>"
         . "</td>";
      
  	return $str;
  } /* search() */

  function filterobj($filtertable, $idxfieldname, $strfieldname)
  {
      global $phpgw;
      
      $filter_obj = array(array("none","show all"));
      $index = 0;
      
      $phpgw->db->query("SELECT $idxfieldname, $strfieldname from $filtertable",__LINE__,__FILE__);
      while($phpgw->db->next_record())
      {
          $index++;
          $filter_obj[$index][0] = $phpgw->db->f("$idxfieldname");
          $filter_obj[$index][1] = $phpgw->db->f("$strfieldname");
      }
      
      return $filter_obj;
  } /* filterobj() */
  
  function searchby($search_obj)
  {
      global $qfield, $phpgw, $phpgw_info;

      $str = "";
      if (is_array($search_obj))
      {
          $str .= "<select name=\"qfield\">";
          
          $indexlimit = count($search_obj);
          for ($index=0; $index<$indexlimit; $index++)
          {
              if ($qfield == "")
              {
                  $qfield = $search_obj[$index][0];
              }
              
              $str .= "<option value=\"".$search_obj[$index][0]."\"";
              if ($qfield == $search_obj[$index][0])
              {
                  $str .= " selected";
              }
              $str .= ">" . lang($search_obj[$index][1]) . "</option>";
          }
          
          $str .= "</select>\n";
      }
     
      return $str;
      
  } /* searchby() */
  
  function filter($filter_obj)
  {
      global $filter, $phpgw, $phpgw_info;

      $str = "";
      if (is_long($filter_obj))
      {
          if ($filter_obj == 1)
          {
              $user_groups =
                  $phpgw->accounts->memberships($phpgw_info["user"]["account_id"]);
              $indexlimit = count($user_groups);
              
              $filter_obj = array(array("none",lang("show all")),
                                  array("private",lang("only yours")));
              for ($index=0; $index<$indexlimit; $index++)
              {
                  $filter_obj[2+$index][0] = $user_groups[$index][0];
                  $filter_obj[2+$index][1] = "Group - " . $user_groups[$index][1];
              }
          }
      }
      
      if (is_array($filter_obj))
      {
          $str .= "<td width=\"14%\">"
              .  "<select name=\"filter\">";
          
          $indexlimit = count($filter_obj);
          for ($index=0; $index<$indexlimit; $index++)
          {
              if ($filter == "")
              {
                  $filter = $filter_obj[$index][0];
              }
              
              $str .= "<option value=\"".$filter_obj[$index][0]."\"";
              if ($filter == $filter_obj[$index][0])
              {
                  $str .= " selected";
              }
              $str .= ">" . $filter_obj[$index][1] . "</option>";
          }
          
          $str .= "</select>\n";
          $str .= "<input type=\"submit\" value=\"" . lang("filter") . "\">\n";
          $str .= "</td>\n";
      }
     
      return $str;
      
  } /* filter() */
  
  function right($scriptname,$start,$total,$extradata = "")
  {
    global $filter, $qfield, $order, $sort, $query, $phpgw_info, $phpgw;
    $maxmatchs = $phpgw_info["user"]["preferences"]["common"]["maxmatchs"];

    $str = "";
    if (($total > $maxmatchs) && ($total > $start + $maxmatchs))
      $str .= "<td width=\"2%\" align=\"right\"><a href=\""
	   . $phpgw->link($scriptname,"start=".($start+$maxmatchs)
	   . "&order=$order&filter=$filter&qfield=$qfield"
           . "&sort=$sort&query=$query".$extradata)
	   . "\"><img src=\"".$phpgw_info["server"]["images_dir"]
	   . "/right.gif\" width=\"12\" height=\"12\" border=\"0\" alt=\""
	   . lang("Next Page")."\"></a></td>\n";
    else
      $str .= "<td width=\"2%\" align=\"right\"><img src=\""
	   . $phpgw_info["server"]["images_dir"]."/right-grey.gif\" "
           . "width=\"12\" height=\"12\" alt=\"".lang("Next Page")
           . "\"></td>\n";

   if (($start != $total - $maxmatchs)
      && ( ($total - $maxmatchs) > ($start + $maxmatchs) ))
      $str .= "<td width=\"2%\" align=\"right\"><a href=\""
	   . $phpgw->link($scriptname,"start=".($total-$maxmatchs)
	   . "&order=$order&filter=$filter&qfield=$qfield"
           . "&sort=$sort&query=$query".$extradata)
	   . "\"><img src=\"".$phpgw_info["server"]["images_dir"]
	   . "/last.gif\" border=\"0\" width=\"12\" height=\"12\" alt=\""
	   . lang("Last Page")."\"></a>&nbsp;</td>\n";
   else
     $str .= "<td width=\"2%\" align=\"right\"><img src=\""
	  . $phpgw_info["server"]["images_dir"]."/last-grey.gif\" "
	  . "width=\"12\" height=\"12\" alt=\"".lang("Last Page")
	  . "\">&nbsp;</td>";

    return $str;
  } /* right() */

  function alternate_row_color($currentcolor = "")
  {
    global $phpgw_info;
    if (! $currentcolor) {
       global $tr_color;
       $currentcolor = $tr_color;
    }
    
    if ($currentcolor == $phpgw_info["theme"]["row_on"]) {
       $tr_color = $phpgw_info["theme"]["row_off"];
    } else {
       $tr_color = $phpgw_info["theme"]["row_on"];
    }
    return $tr_color;
  }

  // If you are using the common bgcolor="{tr_color}"
  // This function is a little cleanier approch
  function template_alternate_row_color(&$tpl)
  {
     $tpl->set_var("tr_color",$this->alternate_row_color());
  }

  function show_sort_order($sort,$var,$order,$program,$text,$extra="")
  {
    global $phpgw, $filter, $qfield, $start, $query;
    if (($order == $var) && ($sort == "ASC"))
       $sort = "DESC";
    else if (($order == $var) && ($sort == "DESC"))
       $sort = "ASC";
    else
       $sort = "ASC";

    return "<a href=\"".$phpgw->link($program,"order=$var&sort=$sort"
	    . "&filter=$filter&qfield=$qfield"
            . "&start=$start&query=$query".$extra)."\">$text</a>";
  }

  // Postgre and MySQL switch the vars in limit.  This will make it easier
  // if there are any other databases that pull this.

  // NOTE!!  This is is NO longer used.  Use db->limit() instead.
  // This is here for people to get there code up to date.

  function sql_limit($start)
  {
    echo "<center><b>WARNING:</b> Do not use sql_limit() anymore.  Use db->limit() from now on.</center>";
    
    global $phpgw_info;
    $max = $phpgw_info["user"]["preferences"]["common"]["maxmatchs"];

    switch ($phpgw_info["server"]["db_type"]) {
	case "pgsql":
	  if ($start == 0)
	    $l = $max;
	  else
	    $l = "$max,$start";
	  return $l;
	  break;
	case "mysql":
	  if ($start == 0)
	    $l = $max;
	  else
	    $l = "$start,$max";
	  return $l;
	  break;
	case "oracle":
	  if ($start == 0)
	    $l = "rownum < $max";
	  else
	    $l = "rownum >= $start AND rownum <= $max";
//	  if ($new_where)
//	    return "WHERE $l";
//	  else
//	    return "AND $l";
	  break;
    }
  }
}
