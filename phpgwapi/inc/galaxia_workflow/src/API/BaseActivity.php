<?php
include_once (GALAXIA_LIBRARY.'/src/common/Base.php');
//!! Abstract class representing activities
//! An abstract class representing activities
/*!
This class represents activities, and must be derived for
each activity type supported in the system. Derived activities extending this
class can be found in the activities subfolder.
This class is observable.
*/
class BaseActivity extends Base {
  var $name;
  var $normalizedName;
  var $description;
  var $isInteractive;
  var $isAutoRouted;
  var $roles=Array();
  var $outbound=Array();
  var $inbound=Array();
  var $pId;
  var $activityId;
  var $type;
  
  function setDb($db)
  {
    $this->db=$db;
  }
  
  function BaseActivity($db)
  {
    $this->db=$db;
    $this->type='base';
  }
  
  
  /*!
  Factory method returning an activity of the desired type
  loading the information from the database.
  */
  function getActivity($activityId) 
  {
    $query = "select * from `".GALAXIA_TABLE_PREFIX."activities` where `activityId`=?";
    $result = $this->query($query,array($activityId));
    if(!$result->numRows()) return false;
    $res = $result->fetchRow();
    switch($res['type']) {
      case 'start':
        $act = new Start($this->db);  
        break;
      case 'end':
        $act = new End($this->db);
        break;
      case 'join':
        $act = new Join($this->db);
        break;
      case 'split':
        $act = new Split($this->db);
        break;
      case 'standalone':
        $act = new Standalone($this->db);
        break;
      case 'switch':
        $act = new SwitchActivity($this->db);
        break;
      case 'activity':
        $act = new Activity($this->db);
        break;
      default:
        trigger_error('Unknown activity type:'.$res['type'],E_USER_WARNING);
    }
    
    $act->setName($res['name']);
    $act->setProcessId($res['pId']);
    $act->setNormalizedName($res['normalized_name']);
    $act->setDescription($res['description']);
    $act->setIsInteractive($res['isInteractive']);
    $act->setIsAutoRouted($res['isAutoRouted']);
    $act->setActivityId($res['activityId']);
    $act->setType($res['type']);
    
    //Now get forward transitions 
    
    //Now get backward transitions
    
    //Now get roles
    $query = "select `roleId` from `".GALAXIA_TABLE_PREFIX."activity_roles` where `activityId`=?";
    $result=$this->query($query,array($res['activityId']));
    while($res = $result->fetchRow()) {
      $this->roles[] = $res['roleId'];
    }
    $act->setRoles($this->roles);
    return $act;
  }
  
  /*! Returns an Array of roleIds for the given user */
  function getUserRoles($user) {
    $query = "select `roleId` from `".GALAXIA_TABLE_PREFIX."user_roles` where `user`=?";
    $result=$this->query($query,array($user));
    $ret = Array();
    while($res = $result->fetchRow()) {
      $ret[] = $res['roleId'];
    }
    return $ret;
  }

  /*! Returns an Array of asociative arrays with roleId and name
  for the given user */  
  function getActivityRoleNames() {
    $aid = $this->activityId;
    $query = "select gr.`roleId`, `name` from `".GALAXIA_TABLE_PREFIX."activity_roles` gar, `".GALAXIA_TABLE_PREFIX."roles` gr where gar.`roleId`=gr.`roleId` and gar.`activityId`=?";
    $result=$this->query($query,array($aid));
    $ret = Array();
    while($res = $result->fetchRow()) {
      $ret[] = $res;
    }
    return $ret;
  }
  
  /*! Returns the normalized name for the activity */
  function getNormalizedName() {
    return $this->normalizedName;
  }

  /*! Sets normalized name for the activity */  
  function setNormalizedName($name) {
    $this->normalizedName=$name;
  }
  
  /*! Sets the name for the activity */
  function setName($name) {
    $this->name=$name;
  }
  
  /*! Gets the activity name */
  function getName() {
    return $this->name;
  }
  
  /*! Sets the activity description */
  function setDescription($desc) {
    $this->description=$desc;
  }
  
  /*! Gets the activity description */
  function getDescription() {
    return $this->description;
  }
  
  /*! Sets the type for the activity - this does NOT allow you to change the actual type */
  function setType($type) {
    $this->type=$type;
  }
  
  /*! Gets the activity type */
  function getType() {
    return $this->type;
  }

  /*! Sets if the activity is interactive */
  function setIsInteractive($is) {
    $this->isInteractive=$is;
  }
  
  /*! Returns if the activity is interactive */
  function isInteractive() {
    return $this->isInteractive == 'y';
  }
  
  /*! Sets if the activity is auto-routed */
  function setIsAutoRouted($is) {
    $this->isAutoRouted = $is;
  }
  
  /*! Gets if the activity is auto routed */
  function isAutoRouted() {
    return $this->isAutoRouted == 'y';
  }

  /*! Sets the processId for this activity */
  function setProcessId($pid) {
    $this->pId=$pid;
  }
  
  /*! Gets the processId for this activity*/
  function getProcessId() {
    return $this->pId;
  }

  /*! Gets the activityId */
  function getActivityId() {
    return $this->activityId;
  }  
  
  /*! Sets the activityId */
  function setActivityId($id) {
    $this->activityId=$id;
  }
  
  /*! Gets array with roleIds asociated to this activity */
  function getRoles() {
    return $this->roles;
  }
  
  /*! Sets roles for this activities, shoule receive an
  array of roleIds */
  function setRoles($roles) {
    $this->roles = $roles;
  }
  
  /*! Checks if a user has a certain role (by name) for this activity,
      e.g. $isadmin = $activity->checkUserRole($user,'admin'); */
  function checkUserRole($user,$rolename) {
    $aid = $this->activityId;
    return $this->getOne("select count(*) from `".GALAXIA_TABLE_PREFIX."activity_roles` gar, `".GALAXIA_TABLE_PREFIX."user_roles` gur, `".GALAXIA_TABLE_PREFIX."roles` gr where gar.`roleId`=gr.`roleId` and gur.`roleId`=gr.`roleId` and gar.`activityId`=? and gur.`user`=? and gr.`name`=?",array($aid, $user, $rolename));
  }

}
?>
