<?php
include_once(GALAXIA_LIBRARY.'/src/ProcessManager/BaseManager.php');
//!! InstanceManager
//! A class to maniplate instances
/*!
  This class is used to add,remove,modify and list
  instances.
*/
class InstanceManager extends BaseManager {
  
  /*!
    Constructor takes a PEAR::Db object to be used
    to manipulate roles in the database.
  */
  function InstanceManager($db) 
  {
    if(!$db) {
      die("Invalid db object passed to InstanceManager constructor");  
    }
    $this->db = $db;  
  }
  
  function get_instance_activities($iid)
  {
    $query = "select ga.wf_type,ga.wf_is_interactive,ga.wf_is_autorouted,gi.wf_p_id,ga.wf_activity_id,ga.wf_name,gi.wf_instance_id,gi.wf_status,gia.wf_activity_id,gia.wf_user,gi.wf_started,gia.wf_status as wf_act_status from ".GALAXIA_TABLE_PREFIX."activities ga,".GALAXIA_TABLE_PREFIX."instances gi,".GALAXIA_TABLE_PREFIX."instance_activities gia where ga.wf_activity_id=gia.wf_activity_id and gi.wf_instance_id=gia.wf_instance_id and gi.wf_instance_id=$iid";
    $result = $this->query($query);
    $ret = Array();
    while($res = $result->fetchRow()) {
      // Number of active instances
      $ret[] = $res;
    }
    return $ret;
  }

  function get_instance($iid)
  {
    $query = "select * from ".GALAXIA_TABLE_PREFIX."instances gi where wf_instance_id=$iid";
    $result = $this->query($query);
    $res = $result->fetchRow();
    $res['wf_workitems']=$this->getOne("select count(*) from ".GALAXIA_TABLE_PREFIX."workitems where wf_instance_id=$iid");
    return $res;
  }

  function get_instance_properties($iid)
  {
    $prop = unserialize($this->getOne("select wf_properties from ".GALAXIA_TABLE_PREFIX."instances gi where wf_instance_id=$iid"));
    return $prop;
  }
  
  function set_instance_properties($iid,&$prop)
  {
    $props = addslashes(serialize($prop));
    $query = "update ".GALAXIA_TABLE_PREFIX."instances set wf_properties='$props' where wf_instance_id=$iid";
    $this->query($query);
  }
  
  function set_instance_owner($iid,$owner)
  {
    $query = "update ".GALAXIA_TABLE_PREFIX."instances set wf_owner='$owner' where wf_instance_id=$iid";
    $this->query($query);
  }
  
  function set_instance_status($iid,$status)
  {
    $query = "update ".GALAXIA_TABLE_PREFIX."instances set wf_status='$status' where wf_instance_id=$iid";
    $this->query($query); 
  }
  
  function set_instance_destination($iid,$activityId)
  {
    $query = "delete from ".GALAXIA_TABLE_PREFIX."instance_activities where wf_instance_id=$iid";
    $this->query($query);
    $query = "insert into ".GALAXIA_TABLE_PREFIX."instance_activities(wf_instance_id,wf_activity_id,wf_user,wf_status)
    values($iid,$activityId,'*','running')";
    $this->query($query);
  }
  
  function set_instance_user($iid,$activityId,$user)
  {
    $query = "update ".GALAXIA_TABLE_PREFIX."instance_activities set wf_user='$user', wf_status='running' where wf_instance_id=$iid and wf_activity_id=$activityId";
    $this->query($query);  
  }

}    

?>
