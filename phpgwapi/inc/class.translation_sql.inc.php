<?php
  /**************************************************************************\
  * phpGroupWare API - Translation class for SQL                             *
  * http://www.phpgroupware.org/api                                          *
  * This file written by Joseph Engo <jengo@phpgroupware.org>                *
  * and Dan Kuykendall <seek3r@phpgroupware.org>                             *
  * Handles multi-language support use SQL tables                            *
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

  class translation
  {

    function translate($key, $vars=false ) 
    {
      if ( ! $vars ) $vars = array();
      global $phpgw, $phpgw_info, $lang;
      $ret = $key;
      if (!isset($lang) || !$lang) {
        if (isset($phpgw_info["user"]["preferences"]["common"]["lang"]) &&
	    $phpgw_info["user"]["preferences"]["common"]["lang"]) {
          $userlang = $phpgw_info["user"]["preferences"]["common"]["lang"];
        }else{
          $userlang = "en";
        }
        $sql = "select message_id,content from lang where lang like '".$userlang."' ".
               "and (app_name like '".$phpgw_info["flags"]["currentapp"]."' or app_name like 'common')";
               
        if (strcasecmp ($phpgw_info["flags"]["currentapp"], "common")>0){
          $sql .= " order by app_name asc";
        }else{
          $sql .= " order by app_name desc";
        }

        $phpgw->db->query($sql,__LINE__,__FILE__);
        $phpgw->db->next_record();
        $count = $phpgw->db->num_rows();
        for ($idx = 0; $idx < $count; ++$idx) {
           $lang[strtolower ($phpgw->db->f("message_id"))] = $phpgw->db->f("content");
           $phpgw->db->next_record();
        }
      }
      if (isset($lang[strtolower ($key)]) && $lang[strtolower ($key)]){
         $ret = $lang[strtolower ($key)];
      }else{
         $ret = $key."*";
      }
      $ndx = 1;
      while( list($key,$val) = each( $vars ) ) {
     	$ret = preg_replace( "/%$ndx/", $val, $ret );
     	++$ndx;
      }
      return $ret;
    }
  
    function add_app($app) 
    {
      global $phpgw, $phpgw_info, $lang;
      if ($phpgw_info["user"]["preferences"]["common"]["lang"]){
        $userlang = $phpgw_info["user"]["preferences"]["common"]["lang"];
      }else{
        $userlang = "en";
      }
      $sql = "select message_id,content from lang where lang like '".$userlang."' and app_name like '".$app."'";
      $phpgw->db->query($sql,__LINE__,__FILE__);
      $phpgw->db->next_record();
      $count = $phpgw->db->num_rows();
      for ($idx = 0; $idx < $count; ++$idx) {
        $lang[strtolower ($phpgw->db->f("message_id"))] = $phpgw->db->f("content");
        $phpgw->db->next_record();
      }
    }
  }
