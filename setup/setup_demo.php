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
    $defaultprefs = 'a:5:{s:6:"common";a:1:{s:0:"";s:2:"en";}s:11:"addressbook";a:1:{s:0:"";s:4:"True";}i:8;a:1:{s:0:"";s:13:"workdaystarts";}i:15;a:1:{s:0:"";s:11:"workdayends";}s:6:"Monday";a:1:{s:0:"";s:13:"weekdaystarts";}}';
    $phpgw_setup->db->query("delete from accounts");
    $phpgw_setup->db->query("delete from preferences");
    $phpgw_setup->db->query("delete from phpgw_acl");
  
    /* Create records for demo accounts */
    $sql = "insert into accounts";
    $sql .= "(account_id, account_lid, account_pwd, account_firstname, account_lastname, account_permissions, account_groups, account_lastpwd_change, account_status)";
    $sql .= "values (1, 'demo', '084e0343a0486ff05530df6c705c8bb4', 'Demo', 'Account', ':addressbook:filemanager:calendar:email:notes:todo:', ',1:0,', ".time().", 'A')";
    $phpgw_setup->db->query($sql);
    $phpgw_setup->db->query("insert into preferences (preference_owner, preference_value) values ('1', '$defaultprefs')");
    $sql = "insert into phpgw_acl";
    $sql .= "(acl_appname, acl_location, acl_account, acl_account_type, acl_rights)";
    $sql .= "values('preferences', 'changepassword', 1, 'u', 0)";
    $phpgw_setup->db->query($sql);
  
    $sql = "insert into accounts";
    $sql .= "(account_id, account_lid, account_pwd, account_firstname, account_lastname, account_permissions, account_groups, account_lastpwd_change, account_status)";
    $sql .= "values (2, 'demo2', '084e0343a0486ff05530df6c705c8bb4', 'Demo2', 'Account', ':addressbook:filemanager:calendar:email:notes:todo:manual:', ',1:0,', ".time().", 'A')";
    $phpgw_setup->db->query($sql);
    $phpgw_setup->db->query("insert into preferences (preference_owner, preference_value) values ('1', '$defaultprefs')");
    $sql = "insert into phpgw_acl";
    $sql .= "(acl_appname, acl_location, acl_account, acl_account_type, acl_rights)";
    $sql .= "values('preferences', 'changepassword', 2, 'u', 0)";
    $phpgw_setup->db->query($sql);
  
    $sql = "insert into accounts";
    $sql .= "(account_id, account_lid, account_pwd, account_firstname, account_lastname, account_permissions, account_groups, account_lastpwd_change, account_status)";
    $sql .= "values (3, 'demo3', '084e0343a0486ff05530df6c705c8bb4', 'Demo3', 'Account', ':addressbook:filemanager:calendar:email:notes:todo:transy:manual:', ',1:0,', ".time().", 'A')";
    $phpgw_setup->db->query($sql);
    $phpgw_setup->db->query("insert into preferences (preference_owner, preference_value) values ('1', '$defaultprefs')");
    $sql = "insert into phpgw_acl";
    $sql .= "(acl_appname, acl_location, acl_account, acl_account_type, acl_rights)";
    $sql .= "values('preferences', 'changepassword', 3, 'u', 0)";
    $phpgw_setup->db->query($sql);
  
    /* Create records for administrator account */
    $sql = "insert into accounts";
    $sql .= "(account_id, account_lid, account_pwd, account_firstname, account_lastname, account_permissions, account_groups, account_lastpwd_change, account_status)";
    $sql .= "values (4, '$username', '".md5($passwd)."', '$fname', '$lname', ':admin:addressbook:filemanager:calendar:email:nntp:notes:todo:transy:manual:', ',1:0,', ".time().", 'A')";
    $phpgw_setup->db->query($sql);
    $phpgw_setup->db->query("insert into preferences (preference_owner, preference_value) values ('1', '$defaultprefs')");
  
    Header("Location: index.php");
    exit;
  }
?>