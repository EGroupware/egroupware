<?php
  /**************************************************************************\
  * phpGroupWare API - Access Control List                                   *
  * This file written by Dan Kuykendall <seek3r@phpgroupware.org>            *
  * Security scheme based on ACL design                                      *
  * Copyright (C) 2000, 2001 Dan Kuykendall                                  *
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
  
	/*!!
	* @Type: class
	* @Name: acl
	* @Author: Seek3r
	* @Title: Access Control List security system
	* @Description: This class provides an ACL security scheme. 
	* This can manage rights to 'run' applications, and limit certain features within an application.
	* It is also used for granting a user "membership" to a group, or making a user have the security equivilance of another user.
	* It is also used for granting a user or group rights to various records, such as todo or calendar items of another user.
	* @Syntax: CreateObject('phpgwapi.acl',int account_id);
	* @Example1: $acl = CreateObject('phpgwapi.acl',5);  // 5 is the user id
	*/
  class acl
  {
    var $account_id;
    var $account_type;
    var $data = Array();
    var $db;

		/*!!
		* @Type: function
		* @Name: acl
		* @Author: Seek3r
		* @Title: ACL constructor for setting account id. 
		* @Description: Sets the ID for $acl->account_id. Can be used to change a current instances id as well.
		* Some functions are specific to this account, and others are generic.
		* @Syntax: int acl(int account_id)
		* @Example1: acl->acl(5); // 5 is the user id
		*/
    function acl($account_id = False)
    {
      global $phpgw, $phpgw_info;
      $this->db = $phpgw->db;
      if ($account_id != False){ $this->account_id = $account_id; }
    }

    /**************************************************************************\
    * These are the standard $this->account_id specific functions              *
    \**************************************************************************/

		/*!!
		* @Type: function
		* @Name: read_repository
		* @Author: Seek3r
		* @Title: Read acl records from repository. 
		* @Description: Reads ACL records for $acl->account_id and returns array along with storing it in $acl->data.
		* @Syntax: array read_repository()
		* @Example1: acl->read_repository();
		*/
    function read_repository()
    {
      global $phpgw, $phpgw_info;
      $sql = 'select * from phpgw_acl where (acl_account in ('.$this->account_id.', 0'; 
//      $equalto = $phpgw->accounts->security_equals($this->account_id);
//      if (is_array($equalto) && count($equalto) > 0){
//        for ($idx = 0; $idx < count($equalto); ++$idx){
//          $sql .= ",".$equalto[$idx][0];
//        }
//      }
      $sql .= '))';
      $this->db->query($sql ,__LINE__,__FILE__);
      $count = $this->db->num_rows();
      $this->data = Array();
      for ($idx = 0; $idx < $count; ++$idx){
      //reset ($this->data);
      //while(list($idx,$value) = each($this->data)){
        $this->db->next_record();
        $this->data[] = array('appname' => $this->db->f('acl_appname'),
                              'location' => $this->db->f('acl_location'), 
                              'account' => $this->db->f('acl_account'), 
                              'rights' => $this->db->f('acl_rights')
                             );
      }
      reset ($this->data);
      return $this->data;
    }

		/*!!
		* @Type: function
		* @Name: read
		* @Author: Seek3r
		* @Title: Read acl records from $acl->data. 
		* @Description: Returns ACL records from $acl->data.
		* @Syntax: array read()
		* @Example1: acl->read();
		*/
    function read()
    {
      if (count($this->data) == 0){ $this->read_repository(); }
      reset ($this->data);
      return $this->data;
    }

		/*!!
		* @Type: function
		* @Name: add
		* @Author: Seek3r
		* @Title: Adds ACL record to $acl->data. 
		* @Description: Adds ACL record to $acl->data.
		* @Syntax: array read()
		* @Example1: acl->read();
		*/
    function add($appname = False, $location, $rights)
    {
      if ($appname == False){
        $appname = $phpgw_info['flags']['currentapp'];
      }
      $this->data[] = array('appname' => $appname, 'location' => $location, 'account' => $this->account_id, 'rights' => $rights);
      reset($this->data);
      return $this->data;
    }
    
    function delete($appname = False, $location)
    {
      if ($appname == False){
        $appname = $phpgw_info['flags']['currentapp'];
      }
      $count = count($this->data);
      reset ($this->data);
      while(list($idx,$value) = each($this->data)){
        if ($this->data[$idx]['appname'] == $appname && $this->data[$idx]['location'] == $location && $this->data[$idx]['account'] == $this->account_id){
          $this->data[$idx] = Array();
        }
      }
      reset($this->data);
      return $this->data;
    }

    function save_repository(){
      global $phpgw, $phpgw_info;
      reset($this->data);

      $sql = 'delete from phpgw_acl where acl_account = '.$this->account_id;
      $this->db->query($sql ,__LINE__,__FILE__);

      $count = count($this->data);
      reset ($this->data);
      while(list($idx,$value) = each($this->data)){
        if ($this->data[$idx]['account'] == $this->account_id){
          $sql = 'insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights)';
          $sql .= " values('".$this->data[$idx]['appname']."', '".$this->data[$idx]['location']."', ".$this->account_id.', '.$this->data[$idx]['rights'].')';
          $this->db->query($sql ,__LINE__,__FILE__);
        }
      }
      reset($this->data);
      return $this->data;
    }

    /**************************************************************************\
    * These are the non-standard $this->account_id specific functions          *
    \**************************************************************************/

    function get_rights($location,$appname = False){
      global $phpgw, $phpgw_info;
      if (count($this->data) == 0){ $this->read_repository(); }
      reset ($this->data);
      if ($appname == False){
        $appname = $phpgw_info['flags']['currentapp'];
      }
      $count = count($this->data);
      if ($count == 0 && $phpgw_info['server']['acl_default'] != 'deny'){ return True; }
      $rights = 0;
//      for ($idx = 0; $idx < $count; ++$idx){
      reset ($this->data);
      while(list($idx,$value) = each($this->data)){
        if ($this->data[$idx]['appname'] == $appname) {
          if ($this->data[$idx]['location'] == $location || $this->data[$idx]['location'] == 'everywhere'){
            if ($this->data[$idx]['rights'] == 0){ return False; }
            $rights |= $this->data[$idx]['rights'];
          }
        }
      }
      return $rights;
    }

    function check($location, $required, $appname = False){
      global $phpgw, $phpgw_info;
      $rights = $this->get_rights($location,$appname);
      return !!($rights & $required);
    }

    function get_specific_rights($location, $appname = False){
      global $phpgw, $phpgw_info;

      if ($appname == False){
        $appname = $phpgw_info['flags']['currentapp'];
      }

      $count = count($this->data);
      if ($count == 0 && $phpgw_info['server']['acl_default'] != 'deny'){ return True; }
      $rights = 0;

      reset ($this->data);
      while(list($idx,$value) = each($this->data)){
        if ($this->data[$idx]['appname'] == $appname && 
         ($this->data[$idx]['location'] == $location || $this->data[$idx]['location'] == 'everywhere') &&
         $this->data[$idx]['account'] == $this->account_id) {
          if ($this->data[$idx]['rights'] == 0){ return False; }
          $rights |= $this->data[$idx]['rights'];
        }
      }
      return $rights;
    }

    function check_specific($location, $required, $appname = False){
      $rights = $this->get_specific_rights($location,$appname);
      return !!($rights & $required);
    }

    function get_location_list($app, $required){
      global $phpgw, $phpgw_info;
      // User piece
      $sql = "select acl_location, acl_rights from phpgw_acl where acl_appname = '$app' ";
      $sql .= " and (acl_account in ('".$this->account_id."', 0"; // group 0 covers all users
      $equalto = $phpgw->accounts->security_equals($this->account_id);           
      if (is_array($equalto) && count($equalto) > 0){
        for ($idx = 0; $idx < count($equalto); ++$idx){
          $sql .= ','.$equalto[$idx][0];
        }
      }
      $sql .= ')))';

      $this->db->query($sql ,__LINE__,__FILE__);
      $rights = 0;
      if ($this->db->num_rows() == 0 ){ return False; }
      while ($this->db->next_record()) {
        if ($this->db->f('acl_rights') == 0){ return False; }
        $rights |= $this->db->f('acl_rights');
        if (!!($rights & $required) == True){
          $locations[] = $this->db->f('acl_location');
        }else{
          return False;
        }
      }
      return $locations;
    }

/*
This is kinda how the function SHOULD work, so that it doesnt need to do its own sql query. 
It should use the values in the $this->data

    function get_location_list($app, $required){
      global $phpgw, $phpgw_info;
       if ($appname == False){
        $appname = $phpgw_info['flags']['currentapp'];
      }

      $count = count($this->data);
      if ($count == 0 && $phpgw_info['server']['acl_default'] != 'deny'){ return True; }
      $rights = 0;

      reset ($this->data);
      while(list($idx,$value) = each($this->data)){
        if ($this->data[$idx]['appname'] == $appname && $this->data[$idx]['rights'] != 0){
          $location_rights[$this->data[$idx]['location']] |= $this->data[$idx]['rights'];
        }
      }
      reset($location_rights);
      for ($idx = 0; $idx < count($location_rights); ++$idx){
        if (!!($location_rights[$idx] & $required) == True){
          $location_rights[] = $this->data[$idx]['location'];
        }
      }
      return $locations;
    }
*/

    /**************************************************************************\
    * These are the generic functions. Not specific to $this->account_id       *
    \**************************************************************************/

    function add_repository($app, $location, $account_id, $rights)
    {
      $this->delete_repository($app, $location, $account_id);
      $sql = 'insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights)';
      $sql .= " values ('" . $app . "','" . $location . "','" . $account_id . "','" . $rights . "')";
      $this->db->query($sql ,__LINE__,__FILE__);
      return True;
    }

    function delete_repository($app, $location, $account_id){
      $sql = "delete from phpgw_acl where acl_appname like '".$app."'"
           . " and acl_location like '".$location."' and "
           . " acl_account = ".$account_id;
      $this->db->query($sql ,__LINE__,__FILE__);
      return $this->db->num_rows();
    }


    function get_app_list_for_id($location, $required, $account_id = False){
      global $phpgw, $phpgw_info;
      if ($account_id == False){ $account_id = $this->account_id; }
      $sql = "select acl_appname, acl_rights from phpgw_acl where acl_location = '$location' and ";
      $sql .= 'acl_account = '.$account_id;
      $this->db->query($sql ,__LINE__,__FILE__);
      $rights = 0;
      if ($this->db->num_rows() == 0 ){ return False; }
      while ($this->db->next_record()) {
        if ($this->db->f('acl_rights') == 0){ return False; }
        $rights |= $this->db->f('acl_rights');
        if (!!($rights & $required) == True){
          $apps[] = $this->db->f('acl_appname');
        }
      }
      return $apps;
    }

    function get_location_list_for_id($app, $required, $account_id = False){
      global $phpgw, $phpgw_info;
      if ($account_id == False){ $account_id = $phpgw_info['user']['account_id']; }
      $sql = "select acl_location, acl_rights from phpgw_acl where acl_appname = '$app' and ";
      $sql .= "acl_account = '".$account_id."'";
      $this->db->query($sql ,__LINE__,__FILE__);
      $rights = 0;
      if ($this->db->num_rows() == 0 ){ return False; }
      while ($this->db->next_record()) {
        if ($this->db->f('acl_rights')) {
          $rights |= $this->db->f('acl_rights');
          if (!!($rights & $required) == True){
            $locations[] = $this->db->f('acl_location');
          }
        }
      }
      return $locations;
    }

    function get_ids_for_location($location, $required, $app = False){
      global $phpgw, $phpgw_info;
      if ($app == False){
        $app = $phpgw_info['flags']['currentapp'];
      }
      $sql = "select acl_account, acl_rights from phpgw_acl where acl_appname = '$app' and ";
      $sql .= "acl_location = '".$location."'";
      $this->db->query($sql ,__LINE__,__FILE__);
      $rights = 0;
      if ($this->db->num_rows() == 0 ){ return False; }
      while ($this->db->next_record()) {
        $rights |= $this->db->f('acl_rights');
        if (!!($rights & $required) == True){
          $accounts[] = $this->db->f('acl_account');
        }
      }
      return $accounts;
    }

	function get_grants($app=False){
      global $phpgw, $phpgw_info;
      
      $db2 = $this->db;
      
      if ($app==False)
      {
        $app = $phpgw_info['flags']['currentapp'];
      }

      $sql = "select acl_account, acl_rights from phpgw_acl where acl_appname = '$app' and "
           . "acl_location in ";
      $security = "('". $phpgw_info['user']['account_id'] ."'";
      $my_memberships = $phpgw->accounts->memberships($phpgw_info['user']['account_id']);
      while($groups = each($my_memberships))
      {
        $group = each($groups);
        $security .= ",'" . $group[1]['account_id'] . "'";
      }
	  $security .= ')';
	  $db2->query($sql . $security ,__LINE__,__FILE__);
	  $rights = 0;
	  $accounts = Array();
	  if ($db2->num_rows() == 0){ return False; }
	  while ($db2->next_record())
	  {
	    $grantor = $db2->f('acl_account');
	    $rights = $db2->f('acl_rights');
	    
//	    if($grantor == $phpgw_info['user']['account_id'])
//	    {
//	      continue;
//	    }
	    
		if(!isset($accounts[$grantor]))
	    {
          $accounts[$grantor] = 0;
        }
        $accounts[$grantor] |= $rights;
      }
      return $accounts;
    }
  } //end of acl class
?>
