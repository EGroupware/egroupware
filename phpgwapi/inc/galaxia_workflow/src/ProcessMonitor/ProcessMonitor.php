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
    $res['active_processes'] = $this->getOne("select count(*) from `".GALAXIA_TABLE_PREFIX."processes` where `wf_is_active`=?",array('y'));
    $res['processes'] = $this->getOne("select count(*) from `".GALAXIA_TABLE_PREFIX."processes`");
    $result = $this->query("select distinct(`wf_p_id`) from `".GALAXIA_TABLE_PREFIX."instances` where `wf_status`=?",array('active'));
    $res['running_processes'] = $result->numRows();
    // get the number of instances per status
    $query = "select wf_status, count(*) as num_instances from ".GALAXIA_TABLE_PREFIX."instances group by wf_status";
    $result = $this->query($query);
    $status = array();
    while($info = $result->fetchRow()) {
      $status[$info['wf_status']] = $info['num_instances'];
    }
    $res['active_instances'] = isset($status['active']) ? $status['active'] : 0;
    $res['completed_instances'] = isset($status['completed']) ? $status['completed'] : 0;
    $res['exception_instances'] = isset($status['exception']) ? $status['exception'] : 0;
    $res['aborted_instances'] = isset($status['aborted']) ? $status['aborted'] : 0;
    return $res;
  }
  
  function update_instance_status($iid,$status) {
    $query = "update `".GALAXIA_TABLE_PREFIX."instances` set `wf_status`=? where `wf_instance_id`=?";
    $this->query($query,array($status,$iid));
  }
  
  function update_instance_activity_status($iid,$activityId,$status) {
    $query = "update `".GALAXIA_TABLE_PREFIX."instance_activities` set `wf_status`=? where `wf_instance_id`=? and `wf_activity_id`=?";
    $this->query($query,array($status,$iid,$activityId));
  }
  
  function remove_instance($iid) {
    $query = "delete from `".GALAXIA_TABLE_PREFIX."workitems` where `wf_instance_id`=?";
    $this->query($query,array($iid));
    $query = "delete from `".GALAXIA_TABLE_PREFIX."instance_activities` where `wf_instance_id`=?";
    $this->query($query,array($iid));
    $query = "delete from `".GALAXIA_TABLE_PREFIX."instances` where `wf_instance_id`=?";
    $this->query($query,array($iid));  
  }
  
  function remove_aborted() {
    $query="select `wf_instance_id` from `".GALAXIA_TABLE_PREFIX."instances` where `wf_status`=?";
    $result = $this->query($query,array('aborted'));
    while($res = $result->fetchRow()) {  
      $iid = $res['wf_instance_id'];
      $query = "delete from `".GALAXIA_TABLE_PREFIX."instance_activities` where `wf_instance_id`=?";
      $this->query($query,array($iid));
      $query = "delete from `".GALAXIA_TABLE_PREFIX."workitems` where `wf_instance_id`=?";
      $this->query($query,array($iid));  
    }
    $query = "delete from `".GALAXIA_TABLE_PREFIX."instances` where `wf_status`=?";
    $this->query($query,array('aborted'));
  }

  function remove_all($pId) {
    $query="select `wf_instance_id` from `".GALAXIA_TABLE_PREFIX."instances` where `wf_p_id`=?";
    $result = $this->query($query,array($pId));
    while($res = $result->fetchRow()) {  
      $iid = $res['wf_instance_id'];
      $query = "delete from `".GALAXIA_TABLE_PREFIX."instance_activities` where `wf_instance_id`=?";
      $this->query($query,array($iid));
      $query = "delete from `".GALAXIA_TABLE_PREFIX."workitems` where `wf_instance_id`=?";
      $this->query($query,array($iid));  
    }
    $query = "delete from `".GALAXIA_TABLE_PREFIX."instances` where `wf_p_id`=?";
    $this->query($query,array($pId));
  }

  
  function monitor_list_processes($offset,$maxRecords,$sort_mode,$find,$where='') {
    $sort_mode = $this->convert_sortmode($sort_mode);
    if($find) {
      $findesc = '%'.$find.'%';
      $mid=" where ((wf_name like ?) or (wf_description like ?))";
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
      $pId = $res['wf_p_id'];
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
    $query = "select wf_p_id, wf_status, count(*) as num_instances,
              min(wf_ended - wf_started) as min_time, avg(wf_ended - wf_started) as avg_time, max(wf_ended - wf_started) as max_time
              from ".GALAXIA_TABLE_PREFIX."instances where wf_p_id in (" . join(', ', array_keys($ret)) . ") group by wf_p_id, wf_status";
    $result = $this->query($query);
    while($res = $result->fetchRow()) {
      $pId = $res['wf_p_id'];
      if (!isset($ret[$pId])) continue;
      switch ($res['wf_status']) {
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
    $query = "select wf_p_id, count(*) as num_activities
              from ".GALAXIA_TABLE_PREFIX."activities
              where wf_p_id in (" . join(', ', array_keys($ret)) . ")
              group by wf_p_id";
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
      $mid=" where ((ga.wf_name like ?) or (ga.wf_description like ?))";
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
    $query = "select gp.`wf_name` as `wf_procname`, gp.`wf_version`, ga.*
              from ".GALAXIA_TABLE_PREFIX."activities ga
                left join ".GALAXIA_TABLE_PREFIX."processes gp on gp.wf_p_id=ga.wf_p_id
              $mid order by $sort_mode";
    $query_cant = "select count(*) from ".GALAXIA_TABLE_PREFIX."activities ga $mid";
    $result = $this->query($query,$bindvars,$maxRecords,$offset);
    $cant = $this->getOne($query_cant,$bindvars);
    $ret = Array();
    while($res = $result->fetchRow()) {
      // Number of active instances
      $aid = $res['wf_activity_id'];
      $res['active_instances']=$this->getOne("select count(gi.wf_instance_id) from ".GALAXIA_TABLE_PREFIX."instances gi,".GALAXIA_TABLE_PREFIX."instance_activities gia where gi.wf_instance_id=gia.wf_instance_id and gia.wf_activity_id=$aid and gi.wf_status='active' and wf_p_id=".$res['wf_p_id']);
    // activities of completed instances are all removed from the instance_activities table for some reason, so we need to look at workitems
      $res['completed_instances']=$this->getOne("select count(distinct gi.wf_instance_id) from ".GALAXIA_TABLE_PREFIX."instances gi,".GALAXIA_TABLE_PREFIX."workitems gw where gi.wf_instance_id=gw.wf_instance_id and gw.wf_activity_id=$aid and gi.wf_status='completed' and wf_p_id=".$res['wf_p_id']);
    // activities of aborted instances are all removed from the instance_activities table for some reason, so we need to look at workitems
      $res['aborted_instances']=$this->getOne("select count(distinct gi.wf_instance_id) from ".GALAXIA_TABLE_PREFIX."instances gi,".GALAXIA_TABLE_PREFIX."workitems gw where gi.wf_instance_id=gw.wf_instance_id and gw.wf_activity_id=$aid and gi.wf_status='aborted' and wf_p_id=".$res['wf_p_id']);
      $res['exception_instances']=$this->getOne("select count(gi.wf_instance_id) from ".GALAXIA_TABLE_PREFIX."instances gi,".GALAXIA_TABLE_PREFIX."instance_activities gia where gi.wf_instance_id=gia.wf_instance_id and gia.wf_activity_id=$aid and gi.wf_status='exception' and wf_p_id=".$res['wf_p_id']);
    $res['act_running_instances']=$this->getOne("select count(gi.wf_instance_id) from ".GALAXIA_TABLE_PREFIX."instances gi,".GALAXIA_TABLE_PREFIX."instance_activities gia where gi.wf_instance_id=gia.wf_instance_id and gia.wf_activity_id=$aid and gia.wf_status='running' and wf_p_id=".$res['wf_p_id']);      
    // completed activities are removed from the instance_activities table unless they're part of a split for some reason, so this won't work
    //  $res['act_completed_instances']=$this->getOne("select count(gi.wf_instance_id) from ".GALAXIA_TABLE_PREFIX."instances gi,".GALAXIA_TABLE_PREFIX."instance_activities gia where gi.wf_instance_id=gia.wf_instance_id and gia.activityId=$aid and gia.status='completed' and pId=".$res['pId']);      
      $res['act_completed_instances'] = 0;
      $ret[$aid] = $res;
    }
    if (count($ret) < 1) {
      $retval = Array();
      $retval["data"] = $ret;
      $retval["cant"] = $cant;
      return $retval;
    }
    $query = "select wf_activity_id, count(distinct wf_instance_id) as num_instances, min(wf_ended - wf_started) as min_time, avg(wf_ended - wf_started) as avg_time, max(wf_ended - wf_started) as max_time
              from ".GALAXIA_TABLE_PREFIX."workitems
              where wf_activity_id in (" . join(', ', array_keys($ret)) . ")
              group by wf_activity_id";
    $result = $this->query($query);
    while($res = $result->fetchRow()) {
      // Number of active instances
      $aid = $res['wf_activity_id'];
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
    $query = "select gp.`wf_p_id`, ga.`wf_is_interactive`, gi.`wf_owner`, gp.`wf_name` as `wf_wf_procname`, gp.`wf_version`, ga.`wf_type`,";
    $query.= " ga.`wf_activity_id`, ga.`wf_name`, gi.`wf_instance_id`, gi.`wf_status`, gia.`wf_activity_id`, gia.`wf_user`, gi.`wf_started`, gi.`wf_ended`, gia.`wf_status` as wf_act_status ";
    $query.=" from `".GALAXIA_TABLE_PREFIX."instances` gi LEFT JOIN `".GALAXIA_TABLE_PREFIX."instance_activities` gia ON gi.`wf_instance_id`=gia.`wf_instance_id` ";
    $query.= "LEFT JOIN `".GALAXIA_TABLE_PREFIX."activities` ga ON gia.`wf_activity_id` = ga.`wf_activity_id` ";
    $query.= "LEFT JOIN `".GALAXIA_TABLE_PREFIX."processes` gp ON gp.`wf_p_id`=gi.`wf_p_id` $mid order by ".$this->convert_sortmode($sort_mode);   

    $query_cant = "select count(*) from `".GALAXIA_TABLE_PREFIX."instances` gi LEFT JOIN `".GALAXIA_TABLE_PREFIX."instance_activities` gia ON gi.`wf_instance_id`=gia.`wf_instance_id` ";
    $query_cant.= "LEFT JOIN `".GALAXIA_TABLE_PREFIX."activities` ga ON gia.`wf_activity_id` = ga.`wf_activity_id` LEFT JOIN `".GALAXIA_TABLE_PREFIX."processes` gp ON gp.`wf_p_id`=gi.`wf_p_id` $mid";
    $result = $this->query($query,$wherevars,$maxRecords,$offset);
    $cant = $this->getOne($query_cant,$wherevars);
    $ret = Array();
    while($res = $result->fetchRow()) {
      $iid = $res['wf_instance_id'];
      $res['workitems']=$this->getOne("select count(*) from `".GALAXIA_TABLE_PREFIX."workitems` where `wf_instance_id`=?",array($iid));
      $ret[$iid] = $res;
    }
    $retval = Array();
    $retval["data"] = $ret;
    $retval["cant"] = $cant;
    return $retval;
  }


  function monitor_list_all_processes($sort_mode = 'wf_name_asc', $where = '') {
    if (!empty($where)) {
      $where = " where ($where) ";
    }
    $query = "select `wf_name`,`wf_version`,`wf_p_id` from `".GALAXIA_TABLE_PREFIX."processes` $where order by ".$this->convert_sortmode($sort_mode);
    $result = $this->query($query);
    $ret = Array();
    while($res = $result->fetchRow()) {
      $pId = $res['wf_p_id'];
      $ret[$pId] = $res;
    }
    return $ret;
  }
  
  function monitor_list_all_activities($sort_mode = 'wf_name_asc', $where = '') {
    if (!empty($where)) {
      $where = " where ($where) ";
    }
    $query = "select `wf_name`,`wf_activity_id` from `".GALAXIA_TABLE_PREFIX."activities` $where order by ".$this->convert_sortmode($sort_mode);
    $result = $this->query($query);
    $ret = Array();
    while($res = $result->fetchRow()) {
      $aid = $res['wf_activity_id'];
      $ret[$aid] = $res;
    }
    return $ret;
  }
  
  function monitor_list_statuses() {
    $query = "select distinct(`wf_status`) from `".GALAXIA_TABLE_PREFIX."instances`";
    $result = $this->query($query);
    $ret = Array();
    while($res = $result->fetchRow()) {
      $ret[] = $res['wf_status'];
    }
    return $ret;
  }
  
  function monitor_list_users() {
    $query = "select distinct(`wf_user`) from `".GALAXIA_TABLE_PREFIX."instance_activities`";
    $result = $this->query($query);
    $ret = Array();
    while($res = $result->fetchRow()) {
      $ret[] = $res['wf_user'];
    }
    return $ret;
  }

  function monitor_list_wi_users() {
    $query = "select distinct(`wf_user`) from `".GALAXIA_TABLE_PREFIX."workitems`";
    $result = $this->query($query);
    $ret = Array();
    while($res = $result->fetchRow()) {
      $ret[] = $res['wf_user'];
    }
    return $ret;
  }

  
  function monitor_list_owners() {
    $query = "select distinct(`wf_owner`) from `".GALAXIA_TABLE_PREFIX."instances`";
    $result = $this->query($query);
    $ret = Array();
    while($res = $result->fetchRow()) {
      $ret[] = $res['wf_owner'];
    }
    return $ret;
  }
  
  
  function monitor_list_activity_types() {
    $query = "select distinct(`wf_type`) from `".GALAXIA_TABLE_PREFIX."activities`";
    $result = $this->query($query);
    $ret = Array();
    while($res = $result->fetchRow()) {
      $ret[] = $res['wf_type'];
    }
    return $ret;  
  }
  
  function monitor_get_workitem($itemId) {
    $query = "select gw.`wf_order_id`,ga.`wf_name`,ga.`wf_type`,ga.`wf_is_interactive`,gp.`wf_name` as `wf_wf_procname`,gp.`wf_version`,";
    $query.= "gw.`wf_item_id`,gw.`wf_properties`,gw.`wf_user`,`wf_started`,`wf_ended`-`wf_started` as wf_duration ";
    $query.= "from `".GALAXIA_TABLE_PREFIX."workitems` gw,`".GALAXIA_TABLE_PREFIX."activities` ga,`".GALAXIA_TABLE_PREFIX."processes` gp where ga.`wf_activity_id`=gw.`wf_activity_id` and ga.`wf_p_id`=gp.`wf_p_id` and `wf_item_id`=?";
    $result = $this->query($query, array($itemId));
    $res = $result->fetchRow();
    $res['wf_properties'] = unserialize($res['wf_properties']);
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
      $mid.=" and (`wf_properties` like $findesc)";
    }
// TODO: retrieve instance status as well
    $query = "select `wf_item_id`,`wf_ended`-`wf_started` as wf_duration,ga.`wf_is_interactive`, ga.`wf_type`,gp.`wf_name` as wf_procname,gp.`wf_version`,ga.`wf_name` as wf_act_name,";
    $query.= "ga.`wf_activity_id`,`wf_instance_id`,`wf_order_id`,`wf_properties`,`wf_started`,`wf_ended`,`wf_user` from `".GALAXIA_TABLE_PREFIX."workitems` gw,`".GALAXIA_TABLE_PREFIX."activities` ga,`".GALAXIA_TABLE_PREFIX."processes` gp ";
    $query.= "where gw.`wf_activity_id`=ga.`wf_activity_id` and ga.`wf_p_id`=gp.`wf_p_id` $mid order by gp.`wf_p_id` desc,".$this->convert_sortmode($sort_mode);
    $query_cant = "select count(*) from `".GALAXIA_TABLE_PREFIX."workitems` gw,`".GALAXIA_TABLE_PREFIX."activities` ga,`".GALAXIA_TABLE_PREFIX."processes` gp where gw.`wf_activity_id`=ga.`wf_activity_id` and ga.`wf_p_id`=gp.`wf_p_id` $mid";
    $result = $this->query($query,$wherevars,$maxRecords,$offset);
    $cant = $this->getOne($query_cant,$wherevars);
    $ret = Array();
    while($res = $result->fetchRow()) {
      $itemId = $res['wf_item_id'];
      $ret[$itemId] = $res;
    }
    $retval = Array();
    $retval["data"] = $ret;
    $retval["cant"] = $cant;
    return $retval;
  }
  

}
?>
