<?php
include_once(GALAXIA_LIBRARY.'/src/common/Base.php');
//!! ProcessMonitor
//! ProcessMonitor class
/*!
This class provides methods for use in typical monitoring scripts
*/
class ProcessMonitor extends Base {

  function monitor_stats() {
    $res = Array();
    $res['active_processes'] = $this->getOne("select count(*) from `".GALAXIA_TABLE_PREFIX."processes` where `isActive`=?",array('y'));
    $res['processes'] = $this->getOne("select count(*) from `".GALAXIA_TABLE_PREFIX."processes`");
    $result = $this->query("select distinct(`pId`) from `".GALAXIA_TABLE_PREFIX."instances` where `status`=?",array('active'));
    $res['running_processes'] = $result->numRows();
    // get the number of instances per status
    $query = "select status, count(*) as num_instances from ".GALAXIA_TABLE_PREFIX."instances group by status";
    $result = $this->query($query);
    $status = array();
    while($info = $result->fetchRow()) {
      $status[$info['status']] = $info['num_instances'];
    }
    $res['active_instances'] = isset($status['active']) ? $status['active'] : 0;
    $res['completed_instances'] = isset($status['completed']) ? $status['completed'] : 0;
    $res['exception_instances'] = isset($status['exception']) ? $status['exception'] : 0;
    $res['aborted_instances'] = isset($status['aborted']) ? $status['aborted'] : 0;
    return $res;
  }
  
  function update_instance_status($iid,$status) {
    $query = "update `".GALAXIA_TABLE_PREFIX."instances` set `status`=? where `instanceId`=?";
    $this->query($query,array($status,$iid));
  }
  
  function update_instance_activity_status($iid,$activityId,$status) {
    $query = "update `".GALAXIA_TABLE_PREFIX."instance_activities` set `status`=? where `instanceId`=? and `activityId`=?";
    $this->query($query,array($status,$iid,$activityId));
  }
  
  function remove_instance($iid) {
    $query = "delete from `".GALAXIA_TABLE_PREFIX."workitems` where `instanceId`=?";
    $this->query($query,array($iid));
    $query = "delete from `".GALAXIA_TABLE_PREFIX."instance_activities` where `instanceId`=?";
    $this->query($query,array($iid));
    $query = "delete from `".GALAXIA_TABLE_PREFIX."instances` where `instanceId`=?";
    $this->query($query,array($iid));  
  }
  
  function remove_aborted() {
    $query="select `instanceId` from `".GALAXIA_TABLE_PREFIX."instances` where `status`=?";
    $result = $this->query($query,array('aborted'));
    while($res = $result->fetchRow()) {  
      $iid = $res['instanceId'];
      $query = "delete from `".GALAXIA_TABLE_PREFIX."instance_activities` where `instanceId`=?";
      $this->query($query,array($iid));
      $query = "delete from `".GALAXIA_TABLE_PREFIX."workitems` where `instanceId`=?";
      $this->query($query,array($iid));  
    }
    $query = "delete from `".GALAXIA_TABLE_PREFIX."instances` where `status`=?";
    $this->query($query,array('aborted'));
  }

  function remove_all($pId) {
    $query="select `instanceId` from `".GALAXIA_TABLE_PREFIX."instances` where `pId`=?";
    $result = $this->query($query,array($pId));
    while($res = $result->fetchRow()) {  
      $iid = $res['instanceId'];
      $query = "delete from `".GALAXIA_TABLE_PREFIX."instance_activities` where `instanceId`=?";
      $this->query($query,array($iid));
      $query = "delete from `".GALAXIA_TABLE_PREFIX."workitems` where `instanceId`=?";
      $this->query($query,array($iid));  
    }
    $query = "delete from `".GALAXIA_TABLE_PREFIX."instances` where `pId`=?";
    $this->query($query,array($pId));
  }

  
  function monitor_list_processes($offset,$maxRecords,$sort_mode,$find,$where='') {
    $sort_mode = $this->convert_sortmode($sort_mode);
    if($find) {
      $findesc = '%'.$find.'%';
      $mid=" where ((name like ?) or (description like ?))";
      $bindvars = array($findesc,$findesc);
    } else {
      $mid="";
      $bindvars = array();
    }
    if($where) {
      if($mid) {
        $mid.= " and ($where) ";
      } else {
        $mid.= " where ($where) ";
      }
    }
    // get the requested processes
    $query = "select * from ".GALAXIA_TABLE_PREFIX."processes $mid order by $sort_mode";
    $query_cant = "select count(*) from ".GALAXIA_TABLE_PREFIX."processes $mid";
    $result = $this->query($query,$bindvars,$maxRecords,$offset);
    $cant = $this->getOne($query_cant,$bindvars);
    $ret = Array();
    while($res = $result->fetchRow()) {
      $pId = $res['pId'];
      // Number of active instances
      $res['active_instances'] = 0;
      // Number of exception instances
      $res['exception_instances'] = 0;
      // Number of completed instances
      $res['completed_instances'] = 0;
      // Number of aborted instances
      $res['aborted_instances'] = 0;
      $res['all_instances'] = 0;
      // Number of activities
      $res['activities'] = 0;
      $ret[$pId] = $res;
    }
    if (count($ret) < 1) {
      $retval = Array();
      $retval["data"] = $ret;
      $retval["cant"] = $cant;
      return $retval;
    }
    // get number of instances and timing statistics per process and status
    $query = "select pId, status, count(*) as num_instances,
              min(ended - started) as min_time, avg(ended - started) as avg_time, max(ended - started) as max_time
              from ".GALAXIA_TABLE_PREFIX."instances where pId in (" . join(', ', array_keys($ret)) . ") group by pId, status";
    $result = $this->query($query);
    while($res = $result->fetchRow()) {
      $pId = $res['pId'];
      if (!isset($ret[$pId])) continue;
      switch ($res['status']) {
        case 'active':
          $ret[$pId]['active_instances'] = $res['num_instances'];
          $ret[$pId]['all_instances'] += $res['num_instances'];
          break;
        case 'completed':
          $ret[$pId]['completed_instances'] = $res['num_instances'];
          $ret[$pId]['all_instances'] += $res['num_instances'];
          $ret[$pId]['duration'] = array('min' => $res['min_time'], 'avg' => $res['avg_time'], 'max' => $res['max_time']);
          break;
        case 'exception':
          $ret[$pId]['exception_instances'] = $res['num_instances'];
          $ret[$pId]['all_instances'] += $res['num_instances'];
          break;
        case 'aborted':
          $ret[$pId]['aborted_instances'] = $res['num_instances'];
          $ret[$pId]['all_instances'] += $res['num_instances'];
          break;
      }
    }
    // get number of activities per process
    $query = "select pId, count(*) as num_activities
              from ".GALAXIA_TABLE_PREFIX."activities
              where pId in (" . join(', ', array_keys($ret)) . ")
              group by pId";
    $result = $this->query($query);
    while($res = $result->fetchRow()) {
      $pId = $res['pId'];
      if (!isset($ret[$pId])) continue;
      $ret[$pId]['activities'] = $res['num_activities'];
    }
    $retval = Array();
    $retval["data"] = $ret;
    $retval["cant"] = $cant;
    return $retval;
  }

  function monitor_list_activities($offset,$maxRecords,$sort_mode,$find,$where='') {
    $sort_mode = $this->convert_sortmode($sort_mode);
    if($find) {
      $findesc = '%'.$find.'%';
      $mid=" where ((ga.name like ?) or (ga.description like ?))";
      $bindvars = array($findesc,$findesc);
    } else {
      $mid="";
      $bindvars = array();
    }
    if($where) {
      $where = preg_replace('/pId/', 'ga.pId', $where);
      if($mid) {
        $mid.= " and ($where) ";
      } else {
        $mid.= " where ($where) ";
      }
    }
    $query = "select gp.`name` as `procname`, gp.`version`, ga.*
              from ".GALAXIA_TABLE_PREFIX."activities ga
                left join ".GALAXIA_TABLE_PREFIX."processes gp on gp.pId=ga.pId
              $mid order by $sort_mode";
    $query_cant = "select count(*) from ".GALAXIA_TABLE_PREFIX."activities ga $mid";
    $result = $this->query($query,$bindvars,$maxRecords,$offset);
    $cant = $this->getOne($query_cant,$bindvars);
    $ret = Array();
    while($res = $result->fetchRow()) {
      // Number of active instances
      $aid = $res['activityId'];
      $res['active_instances']=$this->getOne("select count(gi.instanceId) from ".GALAXIA_TABLE_PREFIX."instances gi,".GALAXIA_TABLE_PREFIX."instance_activities gia where gi.instanceId=gia.instanceId and gia.activitYId=$aid and gi.status='active' and pId=".$res['pId']);
    // activities of completed instances are all removed from the instance_activities table for some reason, so we need to look at workitems
      $res['completed_instances']=$this->getOne("select count(distinct gi.instanceId) from ".GALAXIA_TABLE_PREFIX."instances gi,".GALAXIA_TABLE_PREFIX."workitems gw where gi.instanceId=gw.instanceId and gw.activityId=$aid and gi.status='completed' and pId=".$res['pId']);
    // activities of aborted instances are all removed from the instance_activities table for some reason, so we need to look at workitems
      $res['aborted_instances']=$this->getOne("select count(distinct gi.instanceId) from ".GALAXIA_TABLE_PREFIX."instances gi,".GALAXIA_TABLE_PREFIX."workitems gw where gi.instanceId=gw.instanceId and gw.activityId=$aid and gi.status='aborted' and pId=".$res['pId']);
      $res['exception_instances']=$this->getOne("select count(gi.instanceId) from ".GALAXIA_TABLE_PREFIX."instances gi,".GALAXIA_TABLE_PREFIX."instance_activities gia where gi.instanceId=gia.instanceId and gia.activityId=$aid and gi.status='exception' and pId=".$res['pId']);
    $res['act_running_instances']=$this->getOne("select count(gi.instanceId) from ".GALAXIA_TABLE_PREFIX."instances gi,".GALAXIA_TABLE_PREFIX."instance_activities gia where gi.instanceId=gia.instanceId and gia.activityId=$aid and gia.status='running' and pId=".$res['pId']);      
    // completed activities are removed from the instance_activities table unless they're part of a split for some reason, so this won't work
    //  $res['act_completed_instances']=$this->getOne("select count(gi.instanceId) from ".GALAXIA_TABLE_PREFIX."instances gi,".GALAXIA_TABLE_PREFIX."instance_activities gia where gi.instanceId=gia.instanceId and gia.activityId=$aid and gia.status='completed' and pId=".$res['pId']);      
      $res['act_completed_instances'] = 0;
      $ret[$aid] = $res;
    }
    if (count($ret) < 1) {
      $retval = Array();
      $retval["data"] = $ret;
      $retval["cant"] = $cant;
      return $retval;
    }
    $query = "select activityId, count(distinct instanceId) as num_instances, min(ended - started) as min_time, avg(ended - started) as avg_time, max(ended - started) as max_time
              from ".GALAXIA_TABLE_PREFIX."workitems
              where activityId in (" . join(', ', array_keys($ret)) . ")
              group by activityId";
    $result = $this->query($query);
    while($res = $result->fetchRow()) {
      // Number of active instances
      $aid = $res['activityId'];
      if (!isset($ret[$aid])) continue;
      $ret[$aid]['act_completed_instances'] = $res['num_instances'] - $ret[$aid]['aborted_instances'];
      $ret[$aid]['duration'] = array('min' => $res['min_time'], 'avg' => $res['avg_time'], 'max' => $res['max_time']);
    }
    $retval = Array();
    $retval["data"] = $ret;
    $retval["cant"] = $cant;
    return $retval;
  }

  function monitor_list_instances($offset,$maxRecords,$sort_mode,$find,$where='',$wherevars='') {
    if($find) {
      $findesc = $this->qstr('%'.$find.'%');
      $mid=" where (`properties` like $findesc)";
    } else {
      $mid="";
    }
    if($where) {
      if($mid) {
        $mid.= " and ($where) ";
      } else {
        $mid.= " where ($where) ";
      }
    }
    $query = "select gp.`pId`, ga.`isInteractive`, gi.`owner`, gp.`name` as `procname`, gp.`version`, ga.`type`,";
    $query.= " ga.`activityId`, ga.`name`, gi.`instanceId`, gi.`status`, gia.`activityId`, gia.`user`, gi.`started`, gi.`ended`, gia.`status` as actstatus ";
    $query.=" from `".GALAXIA_TABLE_PREFIX."instances` gi LEFT JOIN `".GALAXIA_TABLE_PREFIX."instance_activities` gia ON gi.`instanceId`=gia.`instanceId` ";
    $query.= "LEFT JOIN `".GALAXIA_TABLE_PREFIX."activities` ga ON gia.`activityId` = ga.`activityId` ";
    $query.= "LEFT JOIN `".GALAXIA_TABLE_PREFIX."processes` gp ON gp.`pId`=gi.`pId` $mid order by ".$this->convert_sortmode($sort_mode);   

    $query_cant = "select count(*) from `".GALAXIA_TABLE_PREFIX."instances` gi LEFT JOIN `".GALAXIA_TABLE_PREFIX."instance_activities` gia ON gi.`instanceId`=gia.`instanceId` ";
    $query_cant.= "LEFT JOIN `".GALAXIA_TABLE_PREFIX."activities` ga ON gia.`activityId` = ga.`activityId` LEFT JOIN `".GALAXIA_TABLE_PREFIX."processes` gp ON gp.`pId`=gi.`pId` $mid";
    $result = $this->query($query,$wherevars,$maxRecords,$offset);
    $cant = $this->getOne($query_cant,$wherevars);
    $ret = Array();
    while($res = $result->fetchRow()) {
      $iid = $res['instanceId'];
      $res['workitems']=$this->getOne("select count(*) from `".GALAXIA_TABLE_PREFIX."workitems` where `instanceId`=?",array($iid));
      $ret[$iid] = $res;
    }
    $retval = Array();
    $retval["data"] = $ret;
    $retval["cant"] = $cant;
    return $retval;
  }


  function monitor_list_all_processes($sort_mode = 'name_asc', $where = '') {
    if (!empty($where)) {
      $where = " where ($where) ";
    }
    $query = "select `name`,`version`,`pId` from `".GALAXIA_TABLE_PREFIX."processes` $where order by ".$this->convert_sortmode($sort_mode);
    $result = $this->query($query);
    $ret = Array();
    while($res = $result->fetchRow()) {
      $pId = $res['pId'];
      $ret[$pId] = $res;
    }
    return $ret;
  }
  
  function monitor_list_all_activities($sort_mode = 'name_asc', $where = '') {
    if (!empty($where)) {
      $where = " where ($where) ";
    }
    $query = "select `name`,`activityId` from `".GALAXIA_TABLE_PREFIX."activities` $where order by ".$this->convert_sortmode($sort_mode);
    $result = $this->query($query);
    $ret = Array();
    while($res = $result->fetchRow()) {
      $aid = $res['activityId'];
      $ret[$aid] = $res;
    }
    return $ret;
  }
  
  function monitor_list_statuses() {
    $query = "select distinct(`status`) from `".GALAXIA_TABLE_PREFIX."instances`";
    $result = $this->query($query);
    $ret = Array();
    while($res = $result->fetchRow()) {
      $ret[] = $res['status'];
    }
    return $ret;
  }
  
  function monitor_list_users() {
    $query = "select distinct(`user`) from `".GALAXIA_TABLE_PREFIX."instance_activities`";
    $result = $this->query($query);
    $ret = Array();
    while($res = $result->fetchRow()) {
      $ret[] = $res['user'];
    }
    return $ret;
  }

  function monitor_list_wi_users() {
    $query = "select distinct(`user`) from `".GALAXIA_TABLE_PREFIX."workitems`";
    $result = $this->query($query);
    $ret = Array();
    while($res = $result->fetchRow()) {
      $ret[] = $res['user'];
    }
    return $ret;
  }

  
  function monitor_list_owners() {
    $query = "select distinct(`owner`) from `".GALAXIA_TABLE_PREFIX."instances`";
    $result = $this->query($query);
    $ret = Array();
    while($res = $result->fetchRow()) {
      $ret[] = $res['owner'];
    }
    return $ret;
  }
  
  
  function monitor_list_activity_types() {
    $query = "select distinct(`type`) from `".GALAXIA_TABLE_PREFIX."activities`";
    $result = $this->query($query);
    $ret = Array();
    while($res = $result->fetchRow()) {
      $ret[] = $res['type'];
    }
    return $ret;  
  }
  
  function monitor_get_workitem($itemId) {
    $query = "select gw.`orderId`,ga.`name`,ga.`type`,ga.`isInteractive`,gp.`name` as `procname`,gp.`version`,";
    $query.= "gw.`itemId`,gw.`properties`,gw.`user`,`started`,`ended`-`started` as duration ";
    $query.= "from `".GALAXIA_TABLE_PREFIX."workitems` gw,`".GALAXIA_TABLE_PREFIX."activities` ga,`".GALAXIA_TABLE_PREFIX."processes` gp where ga.`activityId`=gw.`activityId` and ga.`pId`=gp.`pId` and `itemId`=?";
    $result = $this->query($query, array($itemId));
    $res = $result->fetchRow();
    $res['properties'] = unserialize($res['properties']);
    return $res;
  }

  // List workitems per instance, remove workitem, update_workitem
  function monitor_list_workitems($offset,$maxRecords,$sort_mode,$find,$where='',$wherevars=array()) {
    $mid = '';
    if ($where) {
      $mid.= " and ($where) ";
    }
    if($find) {
      $findesc = $this->qstr('%'.$find.'%');
      $mid.=" and (`properties` like $findesc)";
    }
// TODO: retrieve instance status as well
    $query = "select `itemId`,`ended`-`started` as duration,ga.`isInteractive`, ga.`type`,gp.`name` as procname,gp.`version`,ga.`name` as actname,";
    $query.= "ga.`activityId`,`instanceId`,`orderId`,`properties`,`started`,`ended`,`user` from `".GALAXIA_TABLE_PREFIX."workitems` gw,`".GALAXIA_TABLE_PREFIX."activities` ga,`".GALAXIA_TABLE_PREFIX."processes` gp ";
    $query.= "where gw.`activityId`=ga.`activityId` and ga.`pId`=gp.`pId` $mid order by gp.`pId` desc,".$this->convert_sortmode($sort_mode);
    $query_cant = "select count(*) from `".GALAXIA_TABLE_PREFIX."workitems` gw,`".GALAXIA_TABLE_PREFIX."activities` ga,`".GALAXIA_TABLE_PREFIX."processes` gp where gw.`activityId`=ga.`activityId` and ga.`pId`=gp.`pId` $mid";
    $result = $this->query($query,$wherevars,$maxRecords,$offset);
    $cant = $this->getOne($query_cant,$wherevars);
    $ret = Array();
    while($res = $result->fetchRow()) {
      $itemId = $res['itemId'];
      $ret[$itemId] = $res;
    }
    $retval = Array();
    $retval["data"] = $ret;
    $retval["cant"] = $cant;
    return $retval;
  }
  

}
?>
