<?php
  // Change the follow to reflect on your settings.  If you have any problems using this script,
  // simply resubmit all of your accounts.  (Go into admin -> accounts -> edit -> submit)

  include("/home/httpd/html/phpgroupware/inc/globalconfig.inc.php");
  include("/home/httpd/html/phpgroupware/inc/phpgwapi/phpgw_db_pgsql.inc.php");

  $db	         = new db;
  $db->Host	    = $phpgw_info["server"]["db_host"];
  $db->Type	    = $phpgw_info["server"]["db_type"];
//  $db->Database    = $phpgw_info["server"]["db_name"];
  $db->Database    = "phpgroupware";
  $db->User	    = $phpgw_info["server"]["db_user"];
  $db->Password    = $phpgw_info["server"]["db_pass"];
  
  $i=0;
  $db->query("select * from accounts");
  while ($db->next_record()) {
    $old_groups[$i]["con"] = $db->f("con");
    $old_groups[$i]["groups"] = $db->f("groups");
    $i++;
  }

  for ($j=0; $j<count($old_groups); $j++) {
     $gl = explode(",",$old_groups[$j]["groups"]);
     $new_groups = array();
     for ($i=1, $k=0; $i<(count($gl)-1); $i++, $k++) {
        $new_groups[$k] = $gl[$i];
     }
     $new_string = "";
     for ($l=0; $l<count($new_groups); $l++) {
        $new_string .= "," . $new_groups[$l] . ":0";
     }
     $new_string .= ",";
     $db->query("update accounts set groups='$new_string' where con='" . $old_groups[$j]["con"] . "'");
  }
  echo "Finished upgrading";
