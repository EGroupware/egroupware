<?php
include_once(GALAXIA_LIBRARY.'/src/ProcessManager/BaseManager.php');
//!! ActivityManager
//! A class to maniplate process activities and transitions
/*!
  This class is used to add,remove,modify and list
  activities used in the Workflow engine.
  Activities are managed in a per-process level, each
  activity belongs to some process.
*/
class ActivityManager extends BaseManager {
  var $error='';
      
  /*!
    Constructor takes a PEAR::Db object to be used
    to manipulate activities in the database.
  */
  function ActivityManager($db) {
    if(!$db) {
      die("Invalid db object passed to activityManager constructor");  
    }
    $this->db = $db;  
  }
  
  function get_error() {
    return $this->error;
  }
  
  /*!
   Asociates an activity with a role
  */
  function add_activity_role($activityId, $roleId) {
    $query = "delete from `".GALAXIA_TABLE_PREFIX."activity_roles` where `activityId`=? and `roleId`=?";
    $this->query($query,array($activityId, $roleId));
    $query = "insert into `".GALAXIA_TABLE_PREFIX."activity_roles`(`activityId`,`roleId`) values(?,?)";
    $this->query($query,array($activityId, $roleId));
  }
  
  /*!
   Gets the roles asociated to an activity
  */
  function get_activity_roles($activityId) {
    $query = "select activityId,roles.roleId,roles.name
              from ".GALAXIA_TABLE_PREFIX."activity_roles gar, ".GALAXIA_TABLE_PREFIX."roles roles
              where roles.roleId = gar.roleId and activityId=?";
    $result = $this->query($query,array($activityId));
    $ret = Array();
    while($res = $result->fetchRow()) {  
      $ret[] = $res;
    }
    return $ret;
  }
  
  /*!
   Removes a role from an activity
  */
  function remove_activity_role($activityId, $roleId)
  {
    $query = "delete from ".GALAXIA_TABLE_PREFIX."activity_roles
              where activityId=$activityId and roleId=$roleId";
    $this->query($query);
  }
  
  /*!
   Checks if a transition exists
  */
  function transition_exists($pid,$actFromId,$actToId)
  {
    return($this->getOne("select count(*) from ".GALAXIA_TABLE_PREFIX."transitions where pId=$pid and actFromId=$actFromId and actToId=$actToId"));
  }
  
  /*!
   Adds a transition 
  */
  function add_transition($pId, $actFromId, $actToId)
  {
    // No circular transitions allowed
    if($actFromId == $actToId) return false;
    
    // Rule: if act is not spl-x or spl-a it can't have more than
    // 1 outbound transition.
    $a1 = $this->get_activity($pId, $actFromId);
    $a2 = $this->get_activity($pId, $actToId);
    if(!$a1 || !$a2) return false;
    if($a1['type'] != 'switch' && $a1['type'] != 'split') {
      if($this->getOne("select count(*) from ".GALAXIA_TABLE_PREFIX."transitions where actFromId=$actFromId")) {
        $this->error = tra('Cannot add transition only split activities can have more than one outbound transition');
        return false;
      }
    }
    
    // Rule: if act is standalone no transitions allowed
    if($a1['type'] == 'standalone' || $a2['type']=='standalone') return false;
    // No inbound to start
    if($a2['type'] == 'start') return false;
    // No outbound from end
    if($a1['type'] == 'end') return false;
     
    
    $query = "delete from `".GALAXIA_TABLE_PREFIX."transitions` where `actFromId`=? and `actToId`=?";
    $this->query($query,array($actFromId, $actToId));
    $query = "insert into `".GALAXIA_TABLE_PREFIX."transitions`(`pId`,`actFromId`,`actToId`) values(?,?,?)";
    $this->query($query,array($pId, $actFromId, $actToId));

    return true;
  }
  
  /*!
   Removes a transition
  */
  function remove_transition($actFromId, $actToId)
  {
    $query = "delete from ".GALAXIA_TABLE_PREFIX."transitions where actFromId=$actFromId and actToId=$actToId";
    $this->query($query);
    return true;
  }
  
  /*!
   Removes all the activity transitions
  */
  function remove_activity_transitions($pId, $aid)
  {
    $query = "delete from ".GALAXIA_TABLE_PREFIX."transitions where pId=$pId and (actFromId=$aid or actToId=$aid)";
    $this->query($query);
  }
  
  
  /*!
   Returns all the transitions for a process
  */
  function get_process_transitions($pId,$actid=0)
  {
    if(!$actid) {
        $query = "select a1.name as actFromName, a2.name as actToName, actFromId, actToId from ".GALAXIA_TABLE_PREFIX."transitions gt,".GALAXIA_TABLE_PREFIX."activities a1, ".GALAXIA_TABLE_PREFIX."activities a2 where gt.actFromId = a1.activityId and gt.actToId = a2.activityId and gt.pId = $pId";
    } else {
        $query = "select a1.name as actFromName, a2.name as actToName, actFromId, actToId from ".GALAXIA_TABLE_PREFIX."transitions gt,".GALAXIA_TABLE_PREFIX."activities a1, ".GALAXIA_TABLE_PREFIX."activities a2 where gt.actFromId = a1.activityId and gt.actToId = a2.activityId and gt.pId = $pId and (actFromId = $actid)";
    }
    $result = $this->query($query);
    $ret = Array();
    while($res = $result->fetchRow()) {  
      $ret[] = $res;
    }
    return $ret;
  }
  
  /*!
   Indicates if an activity is autoRouted
  */
  function activity_is_auto_routed($actid)
  {
    return($this->getOne("select count(*) from ".GALAXIA_TABLE_PREFIX."activities where activityId=$actid and isAutoRouted='y'"));
  }
  
  /*!
   Returns all the activities for a process as
   an array
  */
  function get_process_activities($pId)
  {
       $query = "select * from ".GALAXIA_TABLE_PREFIX."activities where pId=$pId";
    $result = $this->query($query);
    $ret = Array();
    while($res = $result->fetchRow()) {  
      $ret[] = $res;
    }
    return $ret;
  }

  /*!
   Builds the graph 
  */
  //\todo build the real graph
  function build_process_graph($pId)
  {
    $attributes = Array(
    
    );
    $graph = new Process_GraphViz(true,$attributes);
    $pm = new ProcessManager($this->db);
    $name = $pm->_get_normalized_name($pId);
    $graph->set_pid($name);
    
    // Nodes are process activities so get
    // the activities and add nodes as needed
    $nodes = $this->get_process_activities($pId);
    
    foreach($nodes as $node)
    {
      if($node['isInteractive']=='y') {
        $color='blue';
      } else {
        $color='black';
      }
      $auto[$node['name']] = $node['isAutoRouted'];
      $graph->addNode($node['name'],array('URL'=>"foourl?activityId=".$node['activityId'],
                                      'label'=>$node['name'],
                                      'shape' => $this->_get_activity_shape($node['type']),
                                      'color' => $color

                                      )
                     );    
    }
    
    // Now add edges, edges are transitions,
    // get the transitions and add the edges
    $edges = $this->get_process_transitions($pId);
    foreach($edges as $edge)
    {
      if($auto[$edge['actFromName']] == 'y') {
        $color = 'red';
      } else {
        $color = 'black';
      }
        $graph->addEdge(array($edge['actFromName'] => $edge['actToName']), array('color'=>$color));    
    }
    
    
    // Save the map image and the image graph
    $graph->image_and_map();
    unset($graph);
    return true;   
  }
  
  
  /*!
   Validates if a process can be activated checking the
   process activities and transitions the rules are:
   0) No circular activities
   1) Must have only one a start and end activity
   2) End must be reachable from start
   3) Interactive activities must have a role assigned
   4) Roles should be mapped
   5) Standalone activities cannot have transitions
   6) Non intractive activities non-auto routed must have some role
      so the user can "send" the activity
  */
  function validate_process_activities($pId)
  {
    $errors = Array();
    // Pre rule no cricular activities
    $cant = $this->getOne("select count(*) from ".GALAXIA_TABLE_PREFIX."transitions where pId=$pId and actFromId=actToId");
    if($cant) {
      $errors[] = tra('Circular reference found some activity has a transition leading to itself');
    }

    // Rule 1 must have exactly one start and end activity
    $cant = $this->getOne("select count(*) from ".GALAXIA_TABLE_PREFIX."activities where pId=$pId and type='start'");
    if($cant < 1) {
      $errors[] = tra('Process does not have a start activity');
    }
    $cant = $this->getOne("select count(*) from ".GALAXIA_TABLE_PREFIX."activities where pId=$pId and type='end'");
    if($cant != 1) {
      $errors[] = tra('Process does not have exactly one end activity');
    }
    
    // Rule 2 end must be reachable from start
    $nodes = Array();
    $endId = $this->getOne("select activityId from ".GALAXIA_TABLE_PREFIX."activities where pId=$pId and type='end'");
    $aux['id']=$endId;
    $aux['visited']=false;
    $nodes[] = $aux;
    
    $startId = $this->getOne("select activityId from ".GALAXIA_TABLE_PREFIX."activities where pId=$pId and type='start'");
    $start_node['id']=$startId;
    $start_node['visited']=true;    
    
    while($this->_list_has_unvisited_nodes($nodes) && !$this->_node_in_list($start_node,$nodes)) {
      for($i=0;$i<count($nodes);$i++) {
        $node=&$nodes[$i];
        if(!$node['visited']) {
          $node['visited']=true;          
          $query = "select actFromId from ".GALAXIA_TABLE_PREFIX."transitions where actToId=".$node['id'];
          $result = $this->query($query);
          $ret = Array();
          while($res = $result->fetchRow()) {  
            $aux['id'] = $res['actFromId'];
            $aux['visited']=false;
            if(!$this->_node_in_list($aux,$nodes)) {
              $nodes[] = $aux;
            }
          }
        }
      }
    }
    
    if(!$this->_node_in_list($start_node,$nodes)) {
      // Start node is NOT reachable from the end node
      $errors[] = tra('End activity is not reachable from start activity');
    }
    
    //Rule 3: interactive activities must have a role
    //assigned.
    //Rule 5: standalone activities can't have transitions
    $query = "select * from ".GALAXIA_TABLE_PREFIX."activities where pId = $pId";
    $result = $this->query($query);
    while($res = $result->fetchRow()) {  
      $aid = $res['activityId'];
      if($res['isInteractive'] == 'y') {
          $cant = $this->getOne("select count(*) from ".GALAXIA_TABLE_PREFIX."activity_roles where activityId=".$res['activityId']);
          if(!$cant) {
            $errors[] = tra('Activity %1 is interactive but has no role assigned', $res['name']);
          }
      } else {
        if( $res['type'] != 'end' && $res['isAutoRouted'] == 'n') {
          $cant = $this->getOne("select count(*) from ".GALAXIA_TABLE_PREFIX."activity_roles where activityId=".$res['activityId']);
            if(!$cant) {
              $errors[] = tra('Activity %1 is non-interactive and non-autorouted but has no role assigned', $res['name']);
            }
        }
      }
      if($res['type']=='standalone') {
        if($this->getOne("select count(*) from ".GALAXIA_TABLE_PREFIX."transitions where actFromId=$aid or actToId=$aid")) {
           $errors[] = tra('Activity %1 is standalone but has transitions', $res['name']);
        }
      }

    }
    
    
    //Rule4: roles should be mapped
    $query = "select * from ".GALAXIA_TABLE_PREFIX."roles where pId = $pId";
    $result = $this->query($query);
    while($res = $result->fetchRow()) {      
        $cant = $this->getOne("select count(*) from ".GALAXIA_TABLE_PREFIX."user_roles where roleId=".$res['roleId']);
        if(!$cant) {
          $errors[] = tra('Role %1 is not mapped', $res['name']);
        }        
    }
    
    
    // End of rules

    // Validate process sources
    $serrors=$this->validate_process_sources($pId);
    $errors = array_merge($errors,$serrors);
    
    $this->error = $errors;
    
    
    
    $isValid = (count($errors)==0) ? 'y' : 'n';

    $query = "update ".GALAXIA_TABLE_PREFIX."processes set isValid='$isValid' where pId=$pId";
    $this->query($query);
    
    $this->_label_nodes($pId);    
    
    return ($isValid=='y');
    
    
  }
  
  /*! 
  Validate process sources
  Rules:
  1) Interactive activities (non-standalone) must use complete()
  2) Standalone activities must not use $instance
  3) Switch activities must use setNextActivity
  4) Non-interactive activities cannot use complete()
  */
  function validate_process_sources($pid)
  {
    $errors=Array();
    $procname= $this->getOne("select normalized_name from ".GALAXIA_TABLE_PREFIX."processes where pId=$pid");
    
    $query = "select * from ".GALAXIA_TABLE_PREFIX."activities where pId=$pid";
    $result = $this->query($query);
    while($res = $result->fetchRow()) {          
      $actname = $res['normalized_name'];
      $source = GALAXIA_PROCESSES."/$procname/code/activities/$actname".'.php';
      if (!file_exists($source)) {
          continue;
      }
      $fp = fopen($source,'r');
      $data='';
      while(!feof($fp)) {
        $data.=fread($fp,8192);
      }
      fclose($fp);
      if($res['type']=='standalone') {
          if(strstr($data,'$instance')) {
            $errors[] = tra('Activity %1 is standalone and is using the $instance object', $res['name']);
          }    
      } else {
        if($res['isInteractive']=='y') {
          if(!strstr($data,'$instance->complete()')) {
            $errors[] = tra('Activity %1 is interactive so it must use the $instance->complete() method', $res['name']);
          }
        } else {
          if(strstr($data,'$instance->complete()')) {
            $errors[] = tra('Activity %1 is non-interactive so it must not use the $instance->complete() method', $res['name']);
          }
        }
        if($res['type']=='switch') {
          if(!strstr($data,'$instance->setNextActivity(')) { 
            $errors[] = tra('Activity %1 is switch so it must use $instance->setNextActivity($actname) method', $res['name']);          
          }
        }
      }    
    }
    return $errors;
  }
  
  /*! 
   Indicates if an activity with the same name exists
  */
  function activity_name_exists($pId,$name)
  {
    $name = addslashes($this->_normalize_name($name));
    return $this->getOne("select count(*) from ".GALAXIA_TABLE_PREFIX."activities where pId=$pId and normalized_name='$name'");
  }
  
  
  /*!
    Gets a activity fields are returned as an asociative array
  */
  function get_activity($pId, $activityId)
  {
      $query = "select * from ".GALAXIA_TABLE_PREFIX."activities where pId=$pId and activityId=$activityId";
    $result = $this->query($query);
    $res = $result->fetchRow();
    return $res;
  }
  
  /*!
   Lists activities at a per-process level
  */
  function list_activities($pId,$offset,$maxRecords,$sort_mode,$find,$where='')
  {
    $sort_mode = str_replace("_"," ",$sort_mode);
    if($find) {
      $findesc = '%'.$find.'%';
      $mid=" where pId=? and ((name like ?) or (description like ?))";
      $bindvars = array($pId,$findesc,$findesc);
    } else {
      $mid=" where pId=? ";
      $bindvars = array($pId);
    }
    if($where) {
      $mid.= " and ($where) ";
    }
    $query = "select * from ".GALAXIA_TABLE_PREFIX."activities $mid order by $sort_mode";
    $query_cant = "select count(*) from ".GALAXIA_TABLE_PREFIX."activities $mid";
    $result = $this->query($query,$bindvars,$maxRecords,$offset);
    $cant = $this->getOne($query_cant,$bindvars);
    $ret = Array();
    while($res = $result->fetchRow()) {
      $res['roles'] = $this->getOne("select count(*) from ".GALAXIA_TABLE_PREFIX."activity_roles where activityId=?",array($res['activityId']));
      $ret[] = $res;
    }
    $retval = Array();
    $retval["data"] = $ret;
    $retval["cant"] = $cant;
    return $retval;
  }
  
  
  
  /*! 
      Removes a activity.
  */
  function remove_activity($pId, $activityId)
  {
    $pm = new ProcessManager($this->db);
    $proc_info = $pm->get_process($pId);
    $actname = $this->_get_normalized_name($activityId);
    $query = "delete from ".GALAXIA_TABLE_PREFIX."activities where pId=$pId and activityId=$activityId";
    $this->query($query);
    $query = "select actFromId,actToId from ".GALAXIA_TABLE_PREFIX."transitions where actFromId=$activityId or actToId=$activityId";
    $result = $this->query($query);
    while($res = $result->fetchRow()) {  
      $this->remove_transition($res['actFromId'], $res['actToId']);
    }
    $query = "delete from ".GALAXIA_TABLE_PREFIX."activity_roles where activityId=$activityId";
    $this->query($query);
    // And we have to remove the user and compiled files
    // for this activity
    $procname = $proc_info['normalized_name'];
    unlink(GALAXIA_PROCESSES."/$procname/code/activities/$actname".'.php'); 
    if (file_exists(GALAXIA_PROCESSES."/$procname/code/templates/$actname".'.tpl')) {
      @unlink(GALAXIA_PROCESSES."/$procname/code/templates/$actname".'.tpl'); 
    }
    unlink(GALAXIA_PROCESSES."/$procname/compiled/$actname".'.php'); 
    return true;
  }
  
  /*!
    Updates or inserts a new activity in the database, $vars is an asociative
    array containing the fields to update or to insert as needed.
    $pId is the processId
    $activityId is the activityId  
  */
  function replace_activity($pId, $activityId, $vars)
  {
    $TABLE_NAME = GALAXIA_TABLE_PREFIX."activities";
    $now = date("U");
    $vars['lastModif']=$now;
    $vars['pId']=$pId;
    $vars['normalized_name'] = $this->_normalize_name($vars['name']);    

    $pm = new ProcessManager($this->db);
    $proc_info = $pm->get_process($pId);
    
    
    foreach($vars as $key=>$value)
    {
      $vars[$key]=addslashes($value);
    }
  
    if($activityId) {
      $oldname = $this->_get_normalized_name($activityId);
      // update mode
      $first = true;
      $query ="update $TABLE_NAME set";
      foreach($vars as $key=>$value) {
        if(!$first) $query.= ',';
        if(!is_numeric($value)) $value="'".$value."'";
        $query.= " $key=$value ";
        $first = false;
      }
      $query .= " where pId=$pId and activityId=$activityId ";
      $this->query($query);
      
      $newname = $vars['normalized_name'];
      // if the activity is changing name then we
      // should rename the user_file for the activity
      // remove the old compiled file and recompile
      // the activity
      
      $user_file_old = GALAXIA_PROCESSES.'/'.$proc_info['normalized_name'].'/code/activities/'.$oldname.'.php';
      $user_file_new = GALAXIA_PROCESSES.'/'.$proc_info['normalized_name'].'/code/activities/'.$newname.'.php';
      rename($user_file_old, $user_file_new);

      $user_file_old = GALAXIA_PROCESSES.'/'.$proc_info['normalized_name'].'/code/templates/'.$oldname.'.tpl';
      $user_file_new = GALAXIA_PROCESSES.'/'.$proc_info['normalized_name'].'/code/templates/'.$newname.'.tpl';
      if ($user_file_old != $user_file_new) {
        @rename($user_file_old, $user_file_new);
      }

      
      $compiled_file = GALAXIA_PROCESSES.'/'.$proc_info['normalized_name'].'/compiled/'.$oldname.'.php';    
      unlink($compiled_file);
      $this->compile_activity($pId,$activityId);
      
      
    } else {
      
      // When inserting activity names can't be duplicated
      if($this->activity_name_exists($pId, $vars['name'])) {
          return false;
      }
      unset($vars['activityId']);
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
      $activityId = $this->getOne("select max(activityId) from $TABLE_NAME where pId=$pId and lastModif=$now"); 
      $ret = $activityId;
      if(!$activityId) {
         print("select max(activityId) from $TABLE_NAME where pId=$pId and lastModif=$now");
         die;      
      }
      // Should create the code file
      $procname = $proc_info["normalized_name"];
        $fw = fopen(GALAXIA_PROCESSES."/$procname/code/activities/".$vars['normalized_name'].'.php','w');
        fwrite($fw,'<'.'?'.'php'."\n".'?'.'>');
        fclose($fw);
        
         if($vars['isInteractive']=='y') {
            $fw = fopen(GALAXIA_PROCESSES."/$procname/code/templates/".$vars['normalized_name'].'.tpl','w');
            if (defined('GALAXIA_TEMPLATE_HEADER') && GALAXIA_TEMPLATE_HEADER) {
              fwrite($fw,GALAXIA_TEMPLATE_HEADER . "\n");
            }
            fclose($fw);
        }

         $this->compile_activity($pId,$activityId);
      
    }
    // Get the id
    return $activityId;
  }
  
  /*!
   Sets if an activity is interactive or not
  */
  function set_interactivity($pId, $actid, $value)
  {
    $query = "update ".GALAXIA_TABLE_PREFIX."activities set isInteractive='$value' where pId=$pId and activityId=$actid";
    $this->query($query);
    // If template does not exist then create template
    $this->compile_activity($pId,$actid);
  }

  /*!
   Sets if an activity is auto routed or not
  */
  function set_autorouting($pId, $actid, $value)
  {
    $query = "update ".GALAXIA_TABLE_PREFIX."activities set isAutoRouted='$value' where pId=$pId and activityId=$actid";
    $this->query($query);
  }

  
  /*!
  Compiles activity
  */
  function compile_activity($pId, $activityId)
  {
    $act_info = $this->get_activity($pId,$activityId);
       $actname = $act_info['normalized_name'];
    $pm = new ProcessManager($this->db);
    $proc_info = $pm->get_process($pId);
    $compiled_file = GALAXIA_PROCESSES.'/'.$proc_info['normalized_name'].'/compiled/'.$act_info['normalized_name'].'.php';    
    $template_file = GALAXIA_PROCESSES.'/'.$proc_info['normalized_name'].'/code/templates/'.$actname.'.tpl';    
    $user_file = GALAXIA_PROCESSES.'/'.$proc_info['normalized_name'].'/code/activities/'.$actname.'.php';
    $pre_file = GALAXIA_LIBRARY.'/compiler/'.$act_info['type'].'_pre.php';
    $pos_file = GALAXIA_LIBRARY.'/compiler/'.$act_info['type'].'_pos.php';
    $fw = fopen($compiled_file,"wb");
    
    // First of all add an include to to the shared code
    $shared_file = GALAXIA_PROCESSES.'/'.$proc_info['normalized_name'].'/code/shared.php';    
    
    fwrite($fw, '<'."?php include_once('$shared_file'); ?".'>'."\n");
    
    // Before pre shared
    $fp = fopen(GALAXIA_LIBRARY.'/compiler/_shared_pre.php',"rb");
    while (!feof($fp)) {
        $data = fread($fp, 4096);
        fwrite($fw,$data);
    }
    fclose($fp);

    // Now get pre and pos files for the activity
    $fp = fopen($pre_file,"rb");
    while (!feof($fp)) {
        $data = fread($fp, 4096);
        fwrite($fw,$data);
    }
    fclose($fp);
    
    // Get the user data for the activity 
    $fp = fopen($user_file,"rb");    
    while (!feof($fp)) {
        $data = fread($fp, 4096);
        fwrite($fw,$data);
    }
    fclose($fp);

    // Get pos and write
    $fp = fopen($pos_file,"rb");
    while (!feof($fp)) {
        $data = fread($fp, 4096);
        fwrite($fw,$data);
    }
    fclose($fp);

    // Shared pos
    $fp = fopen(GALAXIA_LIBRARY.'/compiler/_shared_pos.php',"rb");
    while (!feof($fp)) {
        $data = fread($fp, 4096);
        fwrite($fw,$data);
    }
    fclose($fp);

    fclose($fw);

    //Copy the templates
    
    if($act_info['isInteractive']=='y' && !file_exists($template_file)) {
      $fw = fopen($template_file,'w');
      if (defined('GALAXIA_TEMPLATE_HEADER') && GALAXIA_TEMPLATE_HEADER) {
        fwrite($fw,GALAXIA_TEMPLATE_HEADER . "\n");
      }
      fclose($fw);
    }
    if($act_info['isInteractive']!='y' && file_exists($template_file)) {
      @unlink($template_file);
      if (GALAXIA_TEMPLATES && file_exists(GALAXIA_TEMPLATES.'/'.$proc_info['normalized_name']."/$actname.tpl")) {
        @unlink(GALAXIA_TEMPLATES.'/'.$proc_info['normalized_name']."/$actname.tpl");
      }
    }
    if (GALAXIA_TEMPLATES && file_exists($template_file)) {
      @copy($template_file,GALAXIA_TEMPLATES.'/'.$proc_info['normalized_name']."/$actname.tpl");
    }
  }
  
  /*!
   \private
   Returns activity id by pid,name (activity names are unique)
  */
  function _get_activity_id_by_name($pid,$name)
  {
    $name = addslashes($name);
    if($this->getOne("select count(*) from ".GALAXIA_TABLE_PREFIX."activities where pId=$pid and name='$name'")) {
      return($this->getOne("select activityId from ".GALAXIA_TABLE_PREFIX."activities where pId=$pid and name='$name'"));    
    } else {
      return '';
    }
  }
  
  /*!
   \private Returns the activity shape
  */
  function _get_activity_shape($type)
  {
    switch($type) {
      case "start": 
          return "circle";
      case "end":
          return "doublecircle";
      case "activity":
          return "box";
      case "split":
          return "triangle";
      case "switch":
        return "diamond";
      case "join":
          return "invtriangle";
      case "standalone":
          return "hexagon";
      default:
          return "egg";            
      
    }

  }

  
  /*!
   \private Returns true if a list contains unvisited nodes
   list members are asoc arrays containing id and visited
  */
  function _list_has_unvisited_nodes($list) 
  {
    foreach($list as $node) {
      if(!$node['visited']) return true;
    }
    return false;
  }
  
  /*!
   \private Returns true if a node is in a list
   list members are asoc arrays containing id and visited
  */
  function _node_in_list($node,$list)
  {
    foreach($list as $a_node) {
      if($node['id'] == $a_node['id']) return true;
    }
    return false;
  }
  
  /*!
  \private
  Normalizes an activity name
  */
  function _normalize_name($name)
  {
    $name = str_replace(" ","_",$name);
    $name = preg_replace("/[^A-Za-z_]/",'',$name);
    return $name;
  }
  
  /*!
  \private
  Returns normalized name of an activity
  */
  function _get_normalized_name($activityId)
  {
    return $this->getOne("select normalized_name from ".GALAXIA_TABLE_PREFIX."activities where activityId=$activityId");
  }
  
  /*!
  \private
  Labels nodes 
  */  
  function _label_nodes($pId)
  {
    
    
    ///an empty list of nodes starts the process
    $nodes = Array();
    // the end activity id
    $endId = $this->getOne("select activityId from ".GALAXIA_TABLE_PREFIX."activities where pId=$pId and type='end'");
    // and the number of total nodes (=activities)
    $cant = $this->getOne("select count(*) from ".GALAXIA_TABLE_PREFIX."activities where pId=$pId");
    $nodes[] = $endId;
    $label = $cant;
    $num = $cant;
    
    $query = "update ".GALAXIA_TABLE_PREFIX."activities set flowNum=$cant+1 where pId=$pId";
    $this->query($query);
    
    $seen = array();
    while(count($nodes)) {
      $newnodes = Array();
      foreach($nodes as $node) {
        // avoid endless loops
        if (isset($seen[$node])) continue;
        $seen[$node] = 1;
        $query = "update ".GALAXIA_TABLE_PREFIX."activities set flowNum=$num where activityId=$node";
        $this->query($query);
        $query = "select actFromId from ".GALAXIA_TABLE_PREFIX."transitions where actToId=".$node;
        $result = $this->query($query);
        $ret = Array();
        while($res = $result->fetchRow()) {  
          $newnodes[] = $res['actFromId'];
        }
      }
      $num--;
      $nodes=Array();
      $nodes=$newnodes;
      
    }
    
    $min = $this->getOne("select min(flowNum) from ".GALAXIA_TABLE_PREFIX."activities where pId=$pId");
    $query = "update ".GALAXIA_TABLE_PREFIX."activities set flowNum=flowNum-$min where pId=$pId";
    $this->query($query);
    
    //$query = "update ".GALAXIA_TABLE_PREFIX."activities set flowNum=0 where flowNum=$cant+1";
    //$this->query($query);
  }
    
}


?>
