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
    $query = "select ga.type,ga.isInteractive,ga.isAutoRouted,gi.pId,ga.activityId,ga.name,gi.instanceId,gi.status,gia.activityId,gia.user,gi.started,gia.status as actstatus from ".GALAXIA_TABLE_PREFIX."activities ga,".GALAXIA_TABLE_PREFIX."instances gi,".GALAXIA_TABLE_PREFIX."instance_activities gia where ga.activityId=gia.activityId and gi.instanceId=gia.instanceId and gi.instanceId=$iid";
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
    $query = "select * from ".GALAXIA_TABLE_PREFIX."instances gi where instanceId=$iid";
    $result = $this->query($query);
    $res = $result->fetchRow();
    $res['workitems']=$this->getOne("select count(*) from ".GALAXIA_TABLE_PREFIX."workitems where instanceId=$iid");
    return $res;
  }

  function get_instance_properties($iid)
  {
    $prop = unserialize($this->getOne("select properties from ".GALAXIA_TABLE_PREFIX."instances gi where instanceId=$iid"));
    return $prop;
  }
  
  function set_instance_properties($iid,&$prop)
  {
    $props = addslashes(serialize($prop));
    $query = "update ".GALAXIA_TABLE_PREFIX."instances set properties='$props' where instanceId=$iid";
    $this->query($query);
  }
  
  function set_instance_owner($iid,$owner)
  {
    $query = "update ".GALAXIA_TABLE_PREFIX."instances set owner='$owner' where instanceId=$iid";
    $this->query($query);
  }
  
  function set_instance_status($iid,$status)
  {
    $query = "update ".GALAXIA_TABLE_PREFIX."instances set status='$status' where instanceId=$iid";
    $this->query($query); 
  }
  
  function set_instance_destination($iid,$activityId)
  {
    $query = "delete from ".GALAXIA_TABLE_PREFIX."instance_activities where instanceId=$iid";
    $this->query($query);
    $query = "insert into ".GALAXIA_TABLE_PREFIX."instance_activities(instanceId,activityId,user,status)
    values($iid,$activityId,'*','running')";
    $this->query($query);
  }
  
  function set_instance_user($iid,$activityId,$user)
  {
    $query = "update ".GALAXIA_TABLE_PREFIX."instance_activities set user='$user', status='running' where instanceId=$iid and activityId=$activityId";
    $this->query($query);  
  }

}    

?>
