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
    $sort_mode = str_replace("__"," ",$sort_mode);

    $mid = "where gp.wf_is_active=? and gur.wf_user=?";
    $bindvars = array('y',$user);
    if($find) {
      $findesc = '%'.$find.'%';
      $mid .= " and ((gp.wf_name like ?) or (gp.wf_description like ?))";
      $bindvars[] = $findesc;
      $bindvars[] = $findesc;
    }
    if($where) {
      $mid.= " and ($where) ";
    }
    
    $query = "select distinct(gp.wf_p_id), 
                     gp.wf_is_active,                    
                     gp.wf_name as wf_procname, 
                     gp.wf_normalized_name as normalized_name, 
                     gp.wf_version as wf_version,
                     gp.wf_version as version
              from ".GALAXIA_TABLE_PREFIX."processes gp
                INNER JOIN ".GALAXIA_TABLE_PREFIX."activities ga ON gp.wf_p_id=ga.wf_p_id
                INNER JOIN ".GALAXIA_TABLE_PREFIX."activity_roles gar ON gar.wf_activity_id=ga.wf_activity_id
                INNER JOIN ".GALAXIA_TABLE_PREFIX."roles gr ON gr.wf_role_id=gar.wf_role_id
                INNER JOIN ".GALAXIA_TABLE_PREFIX."user_roles gur ON gur.wf_role_id=gr.wf_role_id
              $mid order by $sort_mode";
    $query_cant = "select count(distinct(gp.wf_p_id))
              from ".GALAXIA_TABLE_PREFIX."processes gp
                INNER JOIN ".GALAXIA_TABLE_PREFIX."activities ga ON gp.wf_p_id=ga.wf_p_id
                INNER JOIN ".GALAXIA_TABLE_PREFIX."activity_roles gar ON gar.wf_activity_id=ga.wf_activity_id
                INNER JOIN ".GALAXIA_TABLE_PREFIX."roles gr ON gr.wf_role_id=gar.wf_role_id
                INNER JOIN ".GALAXIA_TABLE_PREFIX."user_roles gur ON gur.wf_role_id=gr.wf_role_id
              $mid";
    $result = $this->query($query,$bindvars,$maxRecords,$offset);
    $cant = $this->getOne($query_cant,$bindvars);
    $ret = Array();
    while($res = $result->fetchRow()) {
      // Get instances per activity
      $pId=$res['wf_p_id'];
      $res['wf_activities']=$this->getOne("select count(distinct(ga.wf_activity_id))
              from ".GALAXIA_TABLE_PREFIX."processes gp
                INNER JOIN ".GALAXIA_TABLE_PREFIX."activities ga ON gp.wf_p_id=ga.wf_p_id
                INNER JOIN ".GALAXIA_TABLE_PREFIX."activity_roles gar ON gar.wf_activity_id=ga.wf_activity_id
                INNER JOIN ".GALAXIA_TABLE_PREFIX."roles gr ON gr.wf_role_id=gar.wf_role_id
                INNER JOIN ".GALAXIA_TABLE_PREFIX."user_roles gur ON gur.wf_role_id=gr.wf_role_id
              where gp.wf_p_id=? and gur.wf_user=?",
              array($pId,$user));
      $res['wf_instances']=$this->getOne("select count(distinct(gi.wf_instance_id))
              from ".GALAXIA_TABLE_PREFIX."instances gi
                INNER JOIN ".GALAXIA_TABLE_PREFIX."instance_activities gia ON gi.wf_instance_id=gia.wf_instance_id
                INNER JOIN ".GALAXIA_TABLE_PREFIX."activity_roles gar ON gia.wf_activity_id=gar.wf_activity_id
                INNER JOIN ".GALAXIA_TABLE_PREFIX."user_roles gur ON gar.wf_role_id=gur.wf_role_id
              where gi.wf_p_id=? and ((gia.wf_user=?) or (gia.wf_user=? and gur.wf_user=?))",
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
    $sort_mode = str_replace("__"," ",$sort_mode);

    $mid = "where gp.wf_is_active=? and gur.wf_user=?";
    $bindvars = array('y',$user);
    if($find) {
      $findesc = '%'.$find.'%';
      $mid .= " and ((ga.wf_name like ?) or (ga.wf_description like ?))";
      $bindvars[] = $findesc;
      $bindvars[] = $findesc;
    }
    if($where) {
      $mid.= " and ($where) ";
    }
    
    $query = "select distinct(ga.wf_activity_id),                     
                     ga.wf_name,
                     ga.wf_type,
                     gp.wf_name as wf_procname, 
                     ga.wf_is_interactive,
                     ga.wf_is_autorouted,
                     ga.wf_activity_id,
                     gp.wf_version as wf_version,
                     gp.wf_p_id,
                     gp.wf_is_active
              from ".GALAXIA_TABLE_PREFIX."processes gp
                INNER JOIN ".GALAXIA_TABLE_PREFIX."activities ga ON gp.wf_p_id=ga.wf_p_id
                INNER JOIN ".GALAXIA_TABLE_PREFIX."activity_roles gar ON gar.wf_activity_id=ga.wf_activity_id
                INNER JOIN ".GALAXIA_TABLE_PREFIX."roles gr ON gr.wf_role_id=gar.wf_role_id
                INNER JOIN ".GALAXIA_TABLE_PREFIX."user_roles gur ON gur.wf_role_id=gr.wf_role_id
              $mid order by $sort_mode";
    $query_cant = "select count(distinct(ga.wf_activity_id))
              from ".GALAXIA_TABLE_PREFIX."processes gp
                INNER JOIN ".GALAXIA_TABLE_PREFIX."activities ga ON gp.wf_p_id=ga.wf_p_id
                INNER JOIN ".GALAXIA_TABLE_PREFIX."activity_roles gar ON gar.wf_activity_id=ga.wf_activity_id
                INNER JOIN ".GALAXIA_TABLE_PREFIX."roles gr ON gr.wf_role_id=gar.wf_role_id
                INNER JOIN ".GALAXIA_TABLE_PREFIX."user_roles gur ON gur.wf_role_id=gr.wf_role_id
              $mid";
    $result = $this->query($query,$bindvars,$maxRecords,$offset);
    $cant = $this->getOne($query_cant,$bindvars);
    $ret = Array();
    while($res = $result->fetchRow()) {
      // Get instances per activity
      $res['wf_instances']=$this->getOne("select count(distinct(gi.wf_instance_id))
              from ".GALAXIA_TABLE_PREFIX."instances gi
                INNER JOIN ".GALAXIA_TABLE_PREFIX."instance_activities gia ON gi.wf_instance_id=gia.wf_instance_id
                INNER JOIN ".GALAXIA_TABLE_PREFIX."activity_roles gar ON gia.wf_activity_id=gar.wf_activity_id
                INNER JOIN ".GALAXIA_TABLE_PREFIX."user_roles gur ON gar.wf_role_id=gur.wf_role_id
              where gia.wf_activity_id=? and ((gia.wf_user=?) or (gia.wf_user=? and gur.wf_user=?))",
              array($res['wf_activity_id'],$user,'*',$user));
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
    $sort_mode = str_replace("__"," ",$sort_mode);

    $mid = "where (gia.wf_user=? or (gia.wf_user=? and gur.wf_user=?))";
    $bindvars = array($user,'*',$user);
    if($find) {
      $findesc = '%'.$find.'%';
      $mid .= " and ((ga.wf_name like ?) or (ga.wf_description like ?))";
      $bindvars[] = $findesc;
      $bindvars[] = $findesc;
    }
    if($where) {
      $mid.= " and ($where) ";
    }
    
    $query = "select distinct(gi.wf_instance_id),                     
                     gi.wf_started,
                     gi.wf_owner,
                     gia.wf_user,
                     gi.wf_status,
                     gia.wf_status as wf_act_status,
                     ga.wf_name,
                     ga.wf_type,
                     gp.wf_name as wf_procname, 
                     ga.wf_is_interactive,
                     ga.wf_is_autorouted,
                     ga.wf_activity_id,
                     gp.wf_version as wf_version,
                     gp.wf_p_id
              from ".GALAXIA_TABLE_PREFIX."instances gi 
                INNER JOIN ".GALAXIA_TABLE_PREFIX."instance_activities gia ON gi.wf_instance_id=gia.wf_instance_id
                INNER JOIN ".GALAXIA_TABLE_PREFIX."activities ga ON gia.wf_activity_id = ga.wf_activity_id
                INNER JOIN ".GALAXIA_TABLE_PREFIX."activity_roles gar ON gia.wf_activity_id=gar.wf_activity_id
                INNER JOIN ".GALAXIA_TABLE_PREFIX."user_roles gur ON gur.wf_role_id=gar.wf_role_id
                INNER JOIN ".GALAXIA_TABLE_PREFIX."processes gp ON gp.wf_p_id=ga.wf_p_id
              $mid order by $sort_mode";
    $query_cant = "select count(distinct(gi.wf_instance_id))
              from ".GALAXIA_TABLE_PREFIX."instances gi 
                INNER JOIN ".GALAXIA_TABLE_PREFIX."instance_activities gia ON gi.wf_instance_id=gia.wf_instance_id
                INNER JOIN ".GALAXIA_TABLE_PREFIX."activities ga ON gia.wf_activity_id = ga.wf_activity_id
                INNER JOIN ".GALAXIA_TABLE_PREFIX."activity_roles gar ON gia.wf_activity_id=gar.wf_activity_id
                INNER JOIN ".GALAXIA_TABLE_PREFIX."user_roles gur ON gur.wf_role_id=gar.wf_role_id
                INNER JOIN ".GALAXIA_TABLE_PREFIX."processes gp ON gp.wf_p_id=ga.wf_p_id
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
                       where gia.wf_instance_id=gi.wf_instance_id and wf_activity_id=? and gia.wf_instance_id=? and (wf_user=? or wf_owner=?)",
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
                       where gia.wf_instance_id=gi.wf_instance_id and wf_activity_id=? and gia.wf_instance_id=? and (wf_user=? or wf_owner=?)",
                       array($activityId,$instanceId,$user,$user)))
      return false;
    $query = "update ".GALAXIA_TABLE_PREFIX."instances
              set wf_status=?
              where wf_instance_id=?";
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
                       where gia.wf_instance_id=gi.wf_instance_id and wf_activity_id=? and gia.wf_instance_id=? and (wf_user=? or wf_owner=?)",
                       array($activityId,$instanceId,$user,$user)))
      return false;
    $query = "update ".GALAXIA_TABLE_PREFIX."instances
              set wf_status=?
              where wf_instance_id=?";
    $this->query($query, array('active',$instanceId));
  }

  
  function gui_send_instance($user,$activityId,$instanceId)
  {
    if(!
      ($this->getOne("select count(*)
                      from ".GALAXIA_TABLE_PREFIX."instance_activities
                      where wf_activity_id=? and wf_instance_id=? and wf_user=?",
                      array($activityId,$instanceId,$user)))
      ||
      ($this->getOne("select count(*) 
                      from ".GALAXIA_TABLE_PREFIX."instance_activities gia
                      INNER JOIN ".GALAXIA_TABLE_PREFIX."activity_roles gar ON gar.wf_activity_id=gia.wf_activity_id
                      INNER JOIN ".GALAXIA_TABLE_PREFIX."user_roles gur ON gar.wf_role_id=gur.wf_role_id
                      where gia.wf_instance_id=? and gia.wf_activity_id=? and gia.wf_user=? and gur.wf_user=?",
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
                       where wf_activity_id=? and wf_instance_id=? and wf_user=?",
                       array($activityId,$instanceId,$user))) return false;
    $query = "update ".GALAXIA_TABLE_PREFIX."instance_activities
              set wf_user=?
              where wf_instance_id=? and wf_activity_id=?";
    $this->query($query, array('*',$instanceId,$activityId));
  }
  
  function gui_grab_instance($user,$activityId,$instanceId)
  {
    // Grab only if roles are ok  
    if(!$this->getOne("select count(*) 
                      from ".GALAXIA_TABLE_PREFIX."instance_activities gia
                      INNER JOIN ".GALAXIA_TABLE_PREFIX."activity_roles gar ON gar.wf_activity_id=gia.wf_activity_id
                      INNER JOIN ".GALAXIA_TABLE_PREFIX."user_roles gur ON gar.wf_role_id=gur.wf_role_id
                      where gia.wf_instance_id=? and gia.wf_activity_id=? and gia.wf_user=? and gur.wf_user=?",
                      array($instanceId,$activityId,'*',$user)))  return false;
    $query = "update ".GALAXIA_TABLE_PREFIX."instance_activities
              set wf_user=?
              where wf_instance_id=? and wf_activity_id=?";
    $this->query($query, array($user,$instanceId,$activityId));
  }
}
?>
