<?php
  // Little file to setup a demo install
  /* $Id  $ */

  $phpgw_info["flags"] = array("noheader" => True, "nonavbar" => True, "currentapp" => "home", "noapi" => True);
  include("./inc/functions.inc.php");
  include("../header.inc.php");

  // Authorize the user to use setup app and load the database
  // Does not return unless user is authorized
  if (!$phpgw_setup->auth("Config")){
    Header("Location: index.php");
    exit;
  }

  if (! $submit) {
    $phpgw_setup->show_header("Demo Server Setup");
?>
    <table border="1" width="100%" cellspacing="0" cellpadding="2">
    <tr><td>
    This will create 1 admin account and 3 demo accounts<br>
    The username/passwords are: demo/guest, demo2/guest and demo3/guest.<br>
    <b>!!!THIS WILL DELETE ALL EXISTING ACCOUNTS!!!</b><br>
    </td></tr>
    <tr><td align="left" bgcolor="486591"><font color="fefefe">Details for Admin account</td><td align="right" bgcolor="486591">&nbsp;</td></tr>
    <tr><td>
      <form method="POST" acion="<?php echo $PHP_SELF; ?>">
        <table border="0">
          <tr>
            <td>Admin username</td>
            <td><input type="text" name="username"></td>
          </tr>
          <tr>
            <td>Admin first name</td>
            <td><input type="text" name="fname"></td>
          </tr>
          <tr>
            <td>Admin last name</td>
            <td><input type="text" name="lname"></td>
          </tr>
          <tr>
            <td>Admin password</td>
            <td><input type="password" name="passwd"></td>
          </tr>
          <tr>
            <td colspan="2"><input type="submit" name="submit" value="Submit"> </td>
          </tr>
        </table>
      </form>
    </td></tr></table>
<?php
  }else{
    $phpgw_setup->loaddb();
    /* First clear out exsisting tables */
    $phpgw_setup->db->query("delete from phpgw_accounts");
    $phpgw_setup->db->query("delete from phpgw_preferences");
    $phpgw_setup->db->query("delete from phpgw_acl");
    $defaultprefs = 'a:5:{s:6:"common";a:10:{s:9:"maxmatchs";s:2:"15";s:12:"template_set";s:8:"verdilak";s:5:"theme";s:6:"purple";s:13:"navbar_format";s:5:"icons";s:9:"tz_offset";N;s:10:"dateformat";s:5:"m/d/Y";s:10:"timeformat";s:2:"12";s:4:"lang";s:2:"en";s:11:"default_app";N;s:8:"currency";s:1:"$";}s:11:"addressbook";a:1:{s:0:"";s:4:"True";}:s:8:"calendar";a:4:{s:13:"workdaystarts";s:1:"7";s:11:"workdayends";s:2:"15";s:13:"weekdaystarts";s:6:"Monday";s:15:"defaultcalendar";s:9:"month.php";}}';
//    $defaultprefs = 'a:5:{s:6:"common";a:1:{s:0:"";s:2:"en";}s:11:"addressbook";a:1:{s:0:"";s:4:"True";}i:8;a:1:{s:0:"";s:13:"workdaystarts";}i:15;a:1:{s:0:"";s:11:"workdayends";}s:6:"Monday";a:1:{s:0:"";s:13:"weekdaystarts";}}';

		$defaultgroupid = mt_rand (100, 600000);
	  $sql = "insert into phpgw_accounts";
	  $sql .= "(account_id, account_lid, account_type, account_pwd, account_firstname, account_lastname, account_lastpwd_change, account_status)";
	  $sql .= "values (".$defaultgroupid.", 'Default', 'g', '".md5($passwd)."', 'Default', 'Group', ".time().", 'A')";
	  $phpgw_setup->db->query($sql);
	
		$admingroupid = mt_rand (100, 600000);
	  $sql = "insert into phpgw_accounts";
	  $sql .= "(account_id, account_lid, account_type, account_pwd, account_firstname, account_lastname, account_lastpwd_change, account_status)";
	  $sql .= "values (".$admingroupid.", 'Admins', 'g', '".md5($passwd)."', 'Admin', 'Group', ".time().", 'A')";
	  $phpgw_setup->db->query($sql);

    /* Create records for demo accounts */
		$accountid = mt_rand (100, 600000);
    $sql = "insert into phpgw_accounts";
    $sql .= "(account_id, account_lid, account_type, account_pwd, account_firstname, account_lastname, account_lastpwd_change, account_status)";
    $sql .= "values (".$accountid.", 'demo', 'u', '084e0343a0486ff05530df6c705c8bb4', 'Demo', 'Account', ".time().", 'A')";
    $phpgw_setup->db->query($sql);
    $phpgw_setup->db->query("insert into phpgw_preferences (preference_owner, preference_value) values ('$accountid', '$defaultprefs')");
    $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights)values('preferences', 'changepassword', ".$accountid.", 'u', 0)");
 	 	$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('phpgw_group', '".$defaultgroupid."', $accountid, 'u', 1)");
    $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('addressbook', 'run', ".$accountid.", 'u', 1)");
    $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('filemanager', 'run', ".$accountid.", 'u', 1)");
    $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('calendar', 'run', ".$accountid.", 'u', 1)");
    $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('email', 'run', ".$accountid.", 'u', 1)");
    $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('notes', 'run', ".$accountid.", 'u', 1)");
    $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('todo', 'run', ".$accountid.", 'u', 1)");

		$accountid = mt_rand (100, 600000);
    $sql = "insert into phpgw_accounts";
    $sql .= "(account_id, account_lid, account_type, account_pwd, account_firstname, account_lastname, account_lastpwd_change, account_status)";
    $sql .= "values (".$accountid.", 'demo2', 'u', '084e0343a0486ff05530df6c705c8bb4', 'Demo2', 'Account', ".time().", 'A')";
    $phpgw_setup->db->query($sql);
    $phpgw_setup->db->query("insert into phpgw_preferences (preference_owner, preference_value) values ('$accountid', '$defaultprefs')");
    $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights)values('preferences', 'changepassword', ".$accountid.", 'u', 0)");
 	 	$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('phpgw_group', '".$defaultgroupid."', $accountid, 'u', 1)");
    $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('addressbook', 'run', ".$accountid.", 'u', 1)");
    $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('filemanager', 'run', ".$accountid.", 'u', 1)");
    $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('calendar', 'run', ".$accountid.", 'u', 1)");
    $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('email', 'run', ".$accountid.", 'u', 1)");
    $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('notes', 'run', ".$accountid.", 'u', 1)");
    $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('todo', 'run', ".$accountid.", 'u', 1)");
  
		$accountid = mt_rand (100, 600000);
    $sql = "insert into phpgw_accounts";
    $sql .= "(account_id, account_lid, account_type, account_pwd, account_firstname, account_lastname, account_lastpwd_change, account_status)";
    $sql .= "values (".$accountid.", 'demo3', 'u', '084e0343a0486ff05530df6c705c8bb4', 'Demo3', 'Account', ".time().", 'A')";
    $phpgw_setup->db->query($sql);
    $phpgw_setup->db->query("insert into phpgw_preferences (preference_owner, preference_value) values ('$accountid', '$defaultprefs')");
    $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights)values('preferences', 'changepassword', ".$accountid.", 'u', 0)");
		$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('phpgw_group', '".$defaultgroupid."', $accountid, 'u', 1)");
    $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('addressbook', 'run', ".$accountid.", 'u', 1)");
    $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('filemanager', 'run', ".$accountid.", 'u', 1)");
    $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('calendar', 'run', ".$accountid.", 'u', 1)");
    $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('email', 'run', ".$accountid.", 'u', 1)");
    $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('notes', 'run', ".$accountid.", 'u', 1)");
    $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('todo', 'run', ".$accountid.", 'u', 1)");
  
    /* Create records for administrator account */
		$accountid = mt_rand (100, 600000);
    $sql = "insert into phpgw_accounts";
    $sql .= "(account_id, account_lid, account_type, account_pwd, account_firstname, account_lastname, account_lastpwd_change, account_status)";
    $sql .= "values (".$accountid.", '$username', 'u', '".md5($passwd)."', '$fname', '$lname', ".time().", 'A')";
    $phpgw_setup->db->query($sql);
    $phpgw_setup->db->query("insert into phpgw_preferences (preference_owner, preference_value) values ('$accountid', '$defaultprefs')");
 	 	$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('phpgw_group', '".$defaultgroupid."', $accountid, 'u', 1)");
 	 	$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('phpgw_group', '".$admingroupid."', $accountid, 'u', 1)");
    $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('admin', 'run', ".$accountid.", 'u', 1)");
    $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('addressbook', 'run', ".$accountid.", 'u', 1)");
    $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('filemanager', 'run', ".$accountid.", 'u', 1)");
    $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('calendar', 'run', ".$accountid.", 'u', 1)");
    $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('email', 'run', ".$accountid.", 'u', 1)");
    $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('notes', 'run', ".$accountid.", 'u', 1)");
    $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('nntp', 'run', ".$accountid.", 'u', 1)");
    $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('todo', 'run', ".$accountid.", 'u', 1)");
//    $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('transy', 'run', ".$accountid.", 'u', 1)");
    $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('manual', 'run', ".$accountid.", 'u', 1)");
  
    Header("Location: index.php");
    exit;
  }
?>
