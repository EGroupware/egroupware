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
    $query = "select * from `".GALAXIA_TABLE_PREFIX."activities` where `wf_activity_id`=?";
    $result = $this->query($query,array($activityId));
    if(!$result->numRows()) return false;
    $res = $result->fetchRow();
    switch($res['wf_type']) {
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
        trigger_error('Unknown activity type:'.$res['wf_type'],E_USER_WARNING);
    }
    
    $act->setName($res['wf_name']);
    $act->setProcessId($res['wf_p_id']);
    $act->setNormalizedName($res['wf_normalized_name']);
    $act->setDescription($res['wf_description']);
    $act->setIsInteractive($res['wf_is_interactive']);
    $act->setIsAutoRouted($res['wf_is_autorouted']);
    $act->setActivityId($res['wf_activity_id']);
    $act->setType($res['wf_type']);
    
    //Now get forward transitions 
    
    //Now get backward transitions
    
    //Now get roles
    $query = "select `wf_role_id` from `".GALAXIA_TABLE_PREFIX."activity_roles` where `wf_activity_id`=?";
    $result=$this->query($query,array($res['wf_activity_id']));
    while($res = $result->fetchRow()) {
      $this->roles[] = $res['wf_role_id'];
    }
    $act->setRoles($this->roles);
    return $act;
  }
  
  /*! Returns an Array of roleIds for the given user */
  function getUserRoles($user) {
    $query = "select `wf_role_id` from `".GALAXIA_TABLE_PREFIX."user_roles` where `wf_user`=?";
    $result=$this->query($query,array($user));
    $ret = Array();
    while($res = $result->fetchRow()) {
      $ret[] = $res['wf_role_id'];
    }
    return $ret;
  }

  /*! Returns an Array of asociative arrays with roleId and name
  for the given user */  
  function getActivityRoleNames() {
    $aid = $this->activityId;
    $query = "select gr.`wf_role_id`, `wf_name` from `".GALAXIA_TABLE_PREFIX."activity_roles` gar, `".GALAXIA_TABLE_PREFIX."roles` gr where gar.`wf_role_id`=gr.`wf_role_id` and gar.`wf_activity_id`=?";
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
    return $this->getOne("select count(*) from `".GALAXIA_TABLE_PREFIX."activity_roles` gar, `".GALAXIA_TABLE_PREFIX."user_roles` gur, `".GALAXIA_TABLE_PREFIX."roles` gr where gar.`wf_role_id`=gr.`roleId` and gur.`wf_role_id`=gr.`roleId` and gar.`wf_activity_id`=? and gur.`wf_user`=? and gr.`wf_name`=?",array($aid, $user, $rolename));
  }

}
?>
