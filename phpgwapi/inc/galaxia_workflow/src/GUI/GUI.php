<?php
include_once(GALAXIA_LIBRARY.'/src/common/Base.php');
//!! GUI
//! A GUI class for use in typical user interface scripts
/*!
This class provides methods for use in typical user interface scripts
*/
class GUI extends Base {

  /*!
  List user processes, user processes should follow one of these conditions:
  1) The process has an instance assigned to the user
  2) The process has a begin activity with a role compatible to the
     user roles
  3) The process has an instance assigned to '*' and the
     roles for the activity match the roles assigned to
     the user
  The method returns the list of processes that match this
  and it also returns the number of instances that are in the
  process matching the conditions.
  */
  function gui_list_user_processes($user,$offset,$maxRecords,$sort_mode,$find,$where='')
  {
    // FIXME: this doesn't support multiple sort criteria
    //$sort_mode = $this->convert_sortmode($sort_mode);
    $sort_mode = str_replace("_"," ",$sort_mode);

    $mid = "where gp.isActive=? and gur.user=?";
    $bindvars = array('y',$user);
    if($find) {
      $findesc = '%'.$find.'%';
      $mid .= " and ((gp.name like ?) or (gp.description like ?))";
      $bindvars[] = $findesc;
      $bindvars[] = $findesc;
    }
    if($where) {
      $mid.= " and ($where) ";
    }
    
    $query = "select distinct(gp.pId), 
                     gp.isActive,                    
                     gp.name as procname, 
                     gp.normalized_name as normalized_name, 
                     gp.version as version
              from ".GALAXIA_TABLE_PREFIX."processes gp
                INNER JOIN ".GALAXIA_TABLE_PREFIX."activities ga ON gp.pId=ga.pId
                INNER JOIN ".GALAXIA_TABLE_PREFIX."activity_roles gar ON gar.activityId=ga.activityId
                INNER JOIN ".GALAXIA_TABLE_PREFIX."roles gr ON gr.roleId=gar.roleId
                INNER JOIN ".GALAXIA_TABLE_PREFIX."user_roles gur ON gur.roleId=gr.roleId
              $mid order by $sort_mode";
    $query_cant = "select count(distinct(gp.pId))
              from ".GALAXIA_TABLE_PREFIX."processes gp
                INNER JOIN ".GALAXIA_TABLE_PREFIX."activities ga ON gp.pId=ga.pId
                INNER JOIN ".GALAXIA_TABLE_PREFIX."activity_roles gar ON gar.activityId=ga.activityId
                INNER JOIN ".GALAXIA_TABLE_PREFIX."roles gr ON gr.roleId=gar.roleId
                INNER JOIN ".GALAXIA_TABLE_PREFIX."user_roles gur ON gur.roleId=gr.roleId
              $mid";
    $result = $this->query($query,$bindvars,$maxRecords,$offset);
    $cant = $this->getOne($query_cant,$bindvars);
    $ret = Array();
    while($res = $result->fetchRow()) {
      // Get instances per activity
      $pId=$res['pId'];
      $res['activities']=$this->getOne("select count(distinct(ga.activityId))
              from ".GALAXIA_TABLE_PREFIX."processes gp
                INNER JOIN ".GALAXIA_TABLE_PREFIX."activities ga ON gp.pId=ga.pId
                INNER JOIN ".GALAXIA_TABLE_PREFIX."activity_roles gar ON gar.activityId=ga.activityId
                INNER JOIN ".GALAXIA_TABLE_PREFIX."roles gr ON gr.roleId=gar.roleId
                INNER JOIN ".GALAXIA_TABLE_PREFIX."user_roles gur ON gur.roleId=gr.roleId
              where gp.pId=? and gur.user=?",
              array($pId,$user));
      $res['instances']=$this->getOne("select count(distinct(gi.instanceId))
              from ".GALAXIA_TABLE_PREFIX."instances gi
                INNER JOIN ".GALAXIA_TABLE_PREFIX."instance_activities gia ON gi.instanceId=gia.instanceId
                INNER JOIN ".GALAXIA_TABLE_PREFIX."activity_roles gar ON gia.activityId=gar.activityId
                INNER JOIN ".GALAXIA_TABLE_PREFIX."user_roles gur ON gar.roleId=gur.roleId
              where gi.pId=? and ((gia.user=?) or (gia.user=? and gur.user=?))",
              array($pId,$user,'*',$user));
      $ret[] = $res;
    }
    $retval = Array();
    $retval["data"] = $ret;
    $retval["cant"] = $cant;
    return $retval;
  }


  function gui_list_user_activities($user,$offset,$maxRecords,$sort_mode,$find,$where='')
  {
    // FIXME: this doesn't support multiple sort criteria
    //$sort_mode = $this->convert_sortmode($sort_mode);
    $sort_mode = str_replace("_"," ",$sort_mode);

    $mid = "where gp.isActive=? and gur.user=?";
    $bindvars = array('y',$user);
    if($find) {
      $findesc = '%'.$find.'%';
      $mid .= " and ((ga.name like ?) or (ga.description like ?))";
      $bindvars[] = $findesc;
      $bindvars[] = $findesc;
    }
    if($where) {
      $mid.= " and ($where) ";
    }
    
    $query = "select distinct(ga.activityId),                     
                     ga.name,
                     ga.type,
                     gp.name as procname, 
                     ga.isInteractive,
                     ga.isAutoRouted,
                     ga.activityId,
                     gp.version as version,
                     gp.pId,
                     gp.isActive
              from ".GALAXIA_TABLE_PREFIX."processes gp
                INNER JOIN ".GALAXIA_TABLE_PREFIX."activities ga ON gp.pId=ga.pId
                INNER JOIN ".GALAXIA_TABLE_PREFIX."activity_roles gar ON gar.activityId=ga.activityId
                INNER JOIN ".GALAXIA_TABLE_PREFIX."roles gr ON gr.roleId=gar.roleId
                INNER JOIN ".GALAXIA_TABLE_PREFIX."user_roles gur ON gur.roleId=gr.roleId
              $mid order by $sort_mode";
    $query_cant = "select count(distinct(ga.activityId))
              from ".GALAXIA_TABLE_PREFIX."processes gp
                INNER JOIN ".GALAXIA_TABLE_PREFIX."activities ga ON gp.pId=ga.pId
                INNER JOIN ".GALAXIA_TABLE_PREFIX."activity_roles gar ON gar.activityId=ga.activityId
                INNER JOIN ".GALAXIA_TABLE_PREFIX."roles gr ON gr.roleId=gar.roleId
                INNER JOIN ".GALAXIA_TABLE_PREFIX."user_roles gur ON gur.roleId=gr.roleId
              $mid";
    $result = $this->query($query,$bindvars,$maxRecords,$offset);
    $cant = $this->getOne($query_cant,$bindvars);
    $ret = Array();
    while($res = $result->fetchRow()) {
      // Get instances per activity
      $res['instances']=$this->getOne("select count(distinct(gi.instanceId))
              from ".GALAXIA_TABLE_PREFIX."instances gi
                INNER JOIN ".GALAXIA_TABLE_PREFIX."instance_activities gia ON gi.instanceId=gia.instanceId
                INNER JOIN ".GALAXIA_TABLE_PREFIX."activity_roles gar ON gia.activityId=gar.activityId
                INNER JOIN ".GALAXIA_TABLE_PREFIX."user_roles gur ON gar.roleId=gur.roleId
              where gia.activityId=? and ((gia.user=?) or (gia.user=? and gur.user=?))",
              array($res['activityId'],$user,'*',$user));
      $ret[] = $res;
    }
    $retval = Array();
    $retval["data"] = $ret;
    $retval["cant"] = $cant;
    return $retval;
  }


  function gui_list_user_instances($user,$offset,$maxRecords,$sort_mode,$find,$where='')
  {
    // FIXME: this doesn't support multiple sort criteria
    //$sort_mode = $this->convert_sortmode($sort_mode);
    $sort_mode = str_replace("_"," ",$sort_mode);

    $mid = "where (gia.user=? or (gia.user=? and gur.user=?))";
    $bindvars = array($user,'*',$user);
    if($find) {
      $findesc = '%'.$find.'%';
      $mid .= " and ((ga.name like ?) or (ga.description like ?))";
      $bindvars[] = $findesc;
      $bindvars[] = $findesc;
    }
    if($where) {
      $mid.= " and ($where) ";
    }
    
    $query = "select distinct(gi.instanceId),                     
                     gi.started,
                     gi.owner,
                     gia.user,
                     gi.status,
                     gia.status as actstatus,
                     ga.name,
                     ga.type,
                     gp.name as procname, 
                     ga.isInteractive,
                     ga.isAutoRouted,
                     ga.activityId,
                     gp.version as version,
                     gp.pId
              from ".GALAXIA_TABLE_PREFIX."instances gi 
                INNER JOIN ".GALAXIA_TABLE_PREFIX."instance_activities gia ON gi.instanceId=gia.instanceId
                INNER JOIN ".GALAXIA_TABLE_PREFIX."activities ga ON gia.activityId = ga.activityId
                INNER JOIN ".GALAXIA_TABLE_PREFIX."activity_roles gar ON gia.activityId=gar.activityId
                INNER JOIN ".GALAXIA_TABLE_PREFIX."user_roles gur ON gur.roleId=gar.roleId
                INNER JOIN ".GALAXIA_TABLE_PREFIX."processes gp ON gp.pId=ga.pId
              $mid order by $sort_mode";
    $query_cant = "select count(distinct(gi.instanceId))
              from ".GALAXIA_TABLE_PREFIX."instances gi 
                INNER JOIN ".GALAXIA_TABLE_PREFIX."instance_activities gia ON gi.instanceId=gia.instanceId
                INNER JOIN ".GALAXIA_TABLE_PREFIX."activities ga ON gia.activityId = ga.activityId
                INNER JOIN ".GALAXIA_TABLE_PREFIX."activity_roles gar ON gia.activityId=gar.activityId
                INNER JOIN ".GALAXIA_TABLE_PREFIX."user_roles gur ON gur.roleId=gar.roleId
                INNER JOIN ".GALAXIA_TABLE_PREFIX."processes gp ON gp.pId=ga.pId
              $mid";
    $result = $this->query($query,$bindvars,$maxRecords,$offset);
    $cant = $this->getOne($query_cant,$bindvars);
    $ret = Array();
    while($res = $result->fetchRow()) {
      // Get instances per activity
      $ret[] = $res;
    }
    $retval = Array();
    $retval["data"] = $ret;
    $retval["cant"] = $cant;
    return $retval;
  }

  /*!
  Abort an instance - this terminates the instance with status 'aborted', and removes all running activities
  */
  function gui_abort_instance($user,$activityId,$instanceId)
  {
    // Users can only abort instances they're currently running, or instances that they're the owner of
    if(!$this->getOne("select count(*)
                       from ".GALAXIA_TABLE_PREFIX."instance_activities gia, ".GALAXIA_TABLE_PREFIX."instances gi
                       where gia.instanceId=gi.instanceId and activityId=? and gia.instanceId=? and (user=? or owner=?)",
                       array($activityId,$instanceId,$user,$user)))
      return false;
    include_once(GALAXIA_LIBRARY.'/src/API/Instance.php');
    $instance = new Instance($this->db);
    $instance->getInstance($instanceId);
    if (!empty($instance->instanceId)) {
        $instance->abort($activityId,$user);
    }
    unset($instance);
  }
  
  /*!
  Exception handling for an instance - this sets the instance status to 'exception', but keeps all running activities.
  The instance can be resumed afterwards via gui_resume_instance().
  */
  function gui_exception_instance($user,$activityId,$instanceId)
  {
    // Users can only do exception handling for instances they're currently running, or instances that they're the owner of
    if(!$this->getOne("select count(*)
                       from ".GALAXIA_TABLE_PREFIX."instance_activities gia, ".GALAXIA_TABLE_PREFIX."instances gi
                       where gia.instanceId=gi.instanceId and activityId=? and gia.instanceId=? and (user=? or owner=?)",
                       array($activityId,$instanceId,$user,$user)))
      return false;
    $query = "update ".GALAXIA_TABLE_PREFIX."instances
              set status=?
              where instanceId=?";
    $this->query($query, array('exception',$instanceId));
  }

  /*!
  Resume an instance - this sets the instance status from 'exception' back to 'active'
  */
  function gui_resume_instance($user,$activityId,$instanceId)
  {
    // Users can only resume instances they're currently running, or instances that they're the owner of
    if(!$this->getOne("select count(*)
                       from ".GALAXIA_TABLE_PREFIX."instance_activities gia, ".GALAXIA_TABLE_PREFIX."instances gi
                       where gia.instanceId=gi.instanceId and activityId=? and gia.instanceId=? and (user=? or owner=?)",
                       array($activityId,$instanceId,$user,$user)))
      return false;
    $query = "update ".GALAXIA_TABLE_PREFIX."instances
              set status=?
              where instanceId=?";
    $this->query($query, array('active',$instanceId));
  }

  
  function gui_send_instance($user,$activityId,$instanceId)
  {
    if(!
      ($this->getOne("select count(*)
                      from ".GALAXIA_TABLE_PREFIX."instance_activities
                      where activityId=? and instanceId=? and user=?",
                      array($activityId,$instanceId,$user)))
      ||
      ($this->getOne("select count(*) 
                      from ".GALAXIA_TABLE_PREFIX."instance_activities gia
                      INNER JOIN ".GALAXIA_TABLE_PREFIX."activity_roles gar ON gar.activityId=gia.activityId
                      INNER JOIN ".GALAXIA_TABLE_PREFIX."user_roles gur ON gar.roleId=gur.roleId
                      where gia.instanceId=? and gia.activityId=? and gia.user=? and gur.user=?",
                      array($instanceId,$activityId,'*',$user)))
      ) return false;
    include_once(GALAXIA_LIBRARY.'/src/API/Instance.php');
    $instance = new Instance($this->db);
    $instance->getInstance($instanceId);
    $instance->complete($activityId,true,false);
    unset($instance);  
  }
  
  function gui_release_instance($user,$activityId,$instanceId)
  {
    if(!$this->getOne("select count(*)
                       from ".GALAXIA_TABLE_PREFIX."instance_activities
                       where activityId=? and instanceId=? and user=?",
                       array($activityId,$instanceId,$user))) return false;
    $query = "update ".GALAXIA_TABLE_PREFIX."instance_activities
              set user=?
              where instanceId=? and activityId=?";
    $this->query($query, array('*',$instanceId,$activityId));
  }
  
  function gui_grab_instance($user,$activityId,$instanceId)
  {
    // Grab only if roles are ok  
    if(!$this->getOne("select count(*) 
                      from ".GALAXIA_TABLE_PREFIX."instance_activities gia
                      INNER JOIN ".GALAXIA_TABLE_PREFIX."activity_roles gar ON gar.activityId=gia.activityId
                      INNER JOIN ".GALAXIA_TABLE_PREFIX."user_roles gur ON gar.roleId=gur.roleId
                      where gia.instanceId=? and gia.activityId=? and gia.user=? and gur.user=?",
                      array($instanceId,$activityId,'*',$user)))  return false;
    $query = "update ".GALAXIA_TABLE_PREFIX."instance_activities
              set user=?
              where instanceId=? and activityId=?";
    $this->query($query, array($user,$instanceId,$activityId));
  }
}
?>
