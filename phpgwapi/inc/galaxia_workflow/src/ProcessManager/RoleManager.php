<?php
include_once(GALAXIA_LIBRARY.'/src/ProcessManager/BaseManager.php');
//!! RoleManager
//! A class to maniplate roles.
/*!
  This class is used to add,remove,modify and list
  roles used in the Workflow engine.
  Roles are managed in a per-process level, each
  role belongs to some process.
*/

/*!TODO
  Add a method to check if a role name exists in a process (to be used
  to prevent duplicate names)
*/

class RoleManager extends BaseManager {
    
  /*!
    Constructor takes a PEAR::Db object to be used
    to manipulate roles in the database.
  */
  function RoleManager($db) 
  {
    if(!$db) {
      die("Invalid db object passed to RoleManager constructor");  
    }
    $this->db = $db;  
  }
  
  function get_role_id($pid,$name)
  {
    $name = addslashes($name);
    return ($this->getOne("select wf_role_id from ".GALAXIA_TABLE_PREFIX."roles where wf_name='$name' and wf_p_id=$pid"));
  }
  
  /*!
    Gets a role fields are returned as an asociative array
  */
  function get_role($pId, $roleId)
  {
    $query = "select * from `".GALAXIA_TABLE_PREFIX."roles` where `wf_p_id`=? and `wf_role_id`=?";
  $result = $this->query($query,array($pId, $roleId));
  $res = $result->fetchRow();
  return $res;
  }
  
  /*!
    Indicates if a role exists
  */
  function role_name_exists($pid,$name)
  {
    $name = addslashes($name);
    return ($this->getOne("select count(*) from ".GALAXIA_TABLE_PREFIX."roles where wf_p_id=$pid and wf_name='$name'"));
  }
  
  /*!
    Maps a user to a role
  */
  function map_user_to_role($pId,$user,$roleId)
  {
  $query = "delete from `".GALAXIA_TABLE_PREFIX."user_roles` where `wf_role_id`=? and `wf_user`=?";
  $this->query($query,array($roleId, $user));
  $query = "insert into `".GALAXIA_TABLE_PREFIX."user_roles`(`wf_p_id`, `wf_user`, `wf_role_id`) values(?,?,?)";
  $this->query($query,array($pId,$user,$roleId));
  }
  
  /*!
    Removes a mapping
  */
  function remove_mapping($user,$roleId)
  { 
  $query = "delete from `".GALAXIA_TABLE_PREFIX."user_roles` where `wf_user`=? and `wf_role_id`=?";
  $this->query($query,array($user, $roleId));
  }
  
  /*!
    List mappings
  */
  function list_mappings($pId,$offset,$maxRecords,$sort_mode,$find)  {
    $sort_mode = $this->convert_sortmode($sort_mode);
    if($find) {
      // no more quoting here - this is done in bind vars already
      $findesc = '%'.$find.'%';
      $query = "select `wf_name`,`gr`.`wf_role_id`,`wf_user` from `".GALAXIA_TABLE_PREFIX."roles` gr, `".GALAXIA_TABLE_PREFIX."user_roles` gur where `gr`.`wf_role_id`=`gur`.`wf_role_id` and `gur`.`wf_p_id`=? and ((`wf_name` like ?) or (`wf_user` like ?) or (`wf_description` like ?)) order by $sort_mode";
      $result = $this->query($query,array($pId,$findesc,$findesc,$findesc), $maxRecords, $offset);
      $query_cant = "select count(*) from `".GALAXIA_TABLE_PREFIX."roles` gr, `".GALAXIA_TABLE_PREFIX."user_roles` gur where `gr`.`wf_role_id`=`gur`.`wf_role_id` and `gur`.`wf_p_id`=? and ((`wf_name` like ?) or (`wf_user` like ?) or (`wf_description` like ?))";
      $cant = $this->getOne($query_cant,array($pId,$findesc,$findesc,$findesc));
    } else {
      $query = "select `wf_name`,`gr`.`wf_role_id`,`wf_user` from `".GALAXIA_TABLE_PREFIX."roles` gr, `".GALAXIA_TABLE_PREFIX."user_roles` gur where `gr`.`wf_role_id`=`gur`.`wf_role_id` and `gur`.`wf_p_id`=? order by $sort_mode";
      $result = $this->query($query,array($pId), $maxRecords, $offset);
      $query_cant = "select count(*) from `".GALAXIA_TABLE_PREFIX."roles` gr, `".GALAXIA_TABLE_PREFIX."user_roles` gur where `gr`.`wf_role_id`=`gur`.`wf_role_id` and `gur`.`wf_p_id`=?";
      $cant = $this->getOne($query_cant,array($pId));
    }
    $ret = Array();
    while($res = $result->fetchRow()) {
      $ret[] = $res;
    }
    $retval = Array();
    $retval["data"] = $ret;
    $retval["cant"] = $cant;
    return $retval;
  }
  
  /*!
    Lists roles at a per-process level
  */
  function list_roles($pId,$offset,$maxRecords,$sort_mode,$find,$where='')
  {
    $sort_mode = $this->convert_sortmode($sort_mode);
    if($find) {
      // no more quoting here - this is done in bind vars already
      $findesc = '%'.$find.'%';
      $mid=" where wf_p_id=? and ((wf_name like ?) or (wf_description like ?))";
      $bindvars = array($pId,$findesc,$findesc);
    } else {
      $mid=" where wf_p_id=? ";
      $bindvars = array($pId);
    }
    if($where) {
      $mid.= " and ($where) ";
    }
    $query = "select * from ".GALAXIA_TABLE_PREFIX."roles $mid order by $sort_mode";
    $query_cant = "select count(*) from ".GALAXIA_TABLE_PREFIX."roles $mid";
    $result = $this->query($query,$bindvars,$maxRecords,$offset);
    $cant = $this->getOne($query_cant,$bindvars);
    $ret = Array();
    while($res = $result->fetchRow()) {
      $ret[] = $res;
    }
    $retval = Array();
    $retval["data"] = $ret;
    $retval["cant"] = $cant;
    return $retval;
  }
  
  
  
  /*! 
    Removes a role.
  */
  function remove_role($pId, $roleId)
  {
    $query = "delete from `".GALAXIA_TABLE_PREFIX."roles` where `wf_p_id`=? and `wf_role_id`=?";
    $this->query($query,array($pId, $roleId));
    $query = "delete from `".GALAXIA_TABLE_PREFIX."activity_roles` where `wf_role_id`=?";
    $this->query($query,array($roleId));
    $query = "delete from `".GALAXIA_TABLE_PREFIX."user_roles` where `wf_role_id`=?";
    $this->query($query,array($roleId));
  }
  
  /*!
    Updates or inserts a new role in the database, $vars is an asociative
    array containing the fields to update or to insert as needed.
    $pId is the processId
    $roleId is the roleId  
  */
  function replace_role($pId, $roleId, $vars)
  {
    $TABLE_NAME = GALAXIA_TABLE_PREFIX."roles";
    $now = date("U");
    $vars['wf_last_modif']=$now;
    $vars['wf_p_id']=$pId;
    
    foreach($vars as $key=>$value)
    {
      $vars[$key]=addslashes($value);
    }
  
    if($roleId) {
      // update mode
      $first = true;
      $query ="update $TABLE_NAME set";
      foreach($vars as $key=>$value) {
        if(!$first) $query.= ',';
        if(!is_numeric($value)) $value="'".$value."'";
        $query.= " $key=$value ";
        $first = false;
      }
      $query .= " where wf_p_id=$pId and wf_role_id=$roleId ";
      $this->query($query);
    } else {
      $name = $vars['wf_name'];
      if ($this->getOne("select count(*) from ".GALAXIA_TABLE_PREFIX."roles where wf_p_id=$pId and wf_name='$name'")) {
        return false;
      }
      unset($vars['wf_role_id']);
      // insert mode
      $first = true;
      $query = "insert into $TABLE_NAME(";
      foreach(array_keys($vars) as $key) {
        if(!$first) $query.= ','; 
        $query.= "$key";
        $first = false;
      } 
      $query .=") values(";
      $first = true;
      foreach(array_values($vars) as $value) {
        if(!$first) $query.= ','; 
        if(!is_numeric($value)) $value="'".$value."'";
        $query.= "$value";
        $first = false;
      } 
      $query .=")";
      $this->query($query);
      $roleId = $this->getOne("select max(wf_role_id) from $TABLE_NAME where wf_p_id=$pId and wf_last_modif=$now"); 
    }
    // Get the id
    return $roleId;
  }
}

?>
