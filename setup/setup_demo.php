<?php
  // Little file to setup a demo install
  /* $Id  $ */

  $phpgw_info["flags"] = array("noheader" => True, "nonavbar" => True, "currentapp" => "home", "noapi" => True);
  include("./inc/functions.inc.php");
  include("../header.inc.php");

  // Authorize the user to use setup app and load the database
  // Does not return unless user is authorized
  if (!auth()){
    Header("Location: index.php");
    exit;
  }

  if (! $submit) {
?>
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
          <td>Mail Suffix</td>
          <td><input type="text" name="mail_suffix" value="phpgroupware.org"></td>
        </tr>
        <tr>
          <td>Mail login type</td>
          <td>
             <select name="mail_login_type">
              <option value="vmailmgr">VMailMGR</option>
              <option value="standard">Standard</option>
             </select>
          </td>
        </tr>
        <tr>
          <td colspan="2"><input type="submit" name="submit" value="Submit"> </td>
        </tr>
      </table>
    </form>
<?php
  }else{
    loaddb();
    /* First clear out exsisting tables */
    $db->query("delete from accounts");
    $db->query("delete from preferences");
    $db->query("delete from phpgw_acl");
  
    /* Create records for demo accounts */
    $sql = "insert into accounts";
    $sql .= "(account_id, account_lid, account_pwd, account_firstname, account_lastname, account_permissions, account_groups, account_lastpwd_change, account_status)";
    $sql .= "values (1, 'demo', '084e0343a0486ff05530df6c705c8bb4', 'Demo', 'Account', ':addressbook:filemanager:calendar:email:notes:todo:', ',1:0,', ".time().", 'A')";
    $db->query($sql);
    $db->query("insert into preferences values(1, 'maxmatchs', '15', 'common')");
    $db->query("insert into preferences values(1, 'theme', 'default', 'common')");
    $db->query("insert into preferences values(1, 'tz_offset', '', 'common')");
    $db->query("insert into preferences values(1, 'dateformat', 'm/d/Y', 'common')");
    $db->query("insert into preferences values(1, 'timeformat', '12', 'common')");
    $db->query("insert into preferences values(1, 'lang', 'en', 'common')");
    $db->query("insert into preferences values(1, 'company', 'True', 'common')");
    $db->query("insert into preferences values(1, 'lastname', 'True', 'common')");
    $db->query("insert into preferences values(1, 'firstname', 'True', 'common')");
    $db->query("insert into preferences values(1, 'weekstarts', 'Monday', 'common')");
    $db->query("insert into preferences values(1, 'workdaystarts', '9', 'common')");
    $db->query("insert into preferences values(1, 'workdayends', '17', 'common')");
    $sql = "insert into phpgw_acl";
    $sql .= "(acl_appname, acl_location, acl_account, acl_account_type, acl_rights)";
    $sql .= "values('preferences', 'changepassword', 1, 'u', 0)";
    $db->query($sql);
  
    $sql = "insert into accounts";
    $sql .= "(account_id, account_lid, account_pwd, account_firstname, account_lastname, account_permissions, account_groups, account_lastpwd_change, account_status)";
    $sql .= "values (2, 'demo2', '084e0343a0486ff05530df6c705c8bb4', 'Demo2', 'Account', ':addressbook:filemanager:calendar:email:notes:todo:manual:', ',1:0,', ".time().", 'A')";
    $db->query($sql);
    $db->query("insert into preferences values(2, 'maxmatchs', '15', 'common')");
    $db->query("insert into preferences values(2, 'theme', 'default', 'common')");
    $db->query("insert into preferences values(2, 'tz_offset', '', 'common')");
    $db->query("insert into preferences values(2, 'dateformat', 'm/d/Y', 'common')");
    $db->query("insert into preferences values(2, 'timeformat', '12', 'common')");
    $db->query("insert into preferences values(2, 'lang', 'en', 'common')");
    $db->query("insert into preferences values(2, 'company', 'True', 'common')");
    $db->query("insert into preferences values(2, 'lastname', 'True', 'common')");
    $db->query("insert into preferences values(2, 'firstname', 'True', 'common')");
    $db->query("insert into preferences values(2, 'weekstarts', 'Monday', 'common')");
    $db->query("insert into preferences values(2, 'workdaystarts', '9', 'common')");
    $db->query("insert into preferences values(2, 'workdayends', '17', 'common')");
    $sql = "insert into phpgw_acl";
    $sql .= "(acl_appname, acl_location, acl_account, acl_account_type, acl_rights)";
    $sql .= "values('preferences', 'changepassword', 2, 'u', 0)";
    $db->query($sql);
  
    $sql = "insert into accounts";
    $sql .= "(account_id, account_lid, account_pwd, account_firstname, account_lastname, account_permissions, account_groups, account_lastpwd_change, account_status)";
    $sql .= "values (3, 'demo3', '084e0343a0486ff05530df6c705c8bb4', 'Demo3', 'Account', ':addressbook:filemanager:calendar:email:notes:todo:transy:manual:', ',1:0,', ".time().", 'A')";
    $db->query($sql);
    $db->query("insert into preferences values(3, 'maxmatchs', '15', 'common')");
    $db->query("insert into preferences values(3, 'theme', 'default', 'common')");
    $db->query("insert into preferences values(3, 'tz_offset', '', 'common')");
    $db->query("insert into preferences values(3, 'dateformat', 'm/d/Y', 'common')");
    $db->query("insert into preferences values(3, 'timeformat', '12', 'common')");
    $db->query("insert into preferences values(3, 'lang', 'en', 'common')");
    $db->query("insert into preferences values(3, 'company', 'True', 'common')");
    $db->query("insert into preferences values(3, 'lastname', 'True', 'common')");
    $db->query("insert into preferences values(3, 'firstname', 'True', 'common')");
    $db->query("insert into preferences values(3, 'weekstarts', 'Monday', 'common')");
    $db->query("insert into preferences values(3, 'workdaystarts', '9', 'common')");
    $db->query("insert into preferences values(3, 'workdayends', '17', 'common')");
    $sql = "insert into phpgw_acl";
    $sql .= "(acl_appname, acl_location, acl_account, acl_account_type, acl_rights)";
    $sql .= "values('preferences', 'changepassword', 3, 'u', 0)";
    $db->query($sql);
  
    /* Create records for administrator account */
    $sql = "insert into accounts";
    $sql .= "(account_id, account_lid, account_pwd, account_firstname, account_lastname, account_permissions, account_groups, account_lastpwd_change, account_status)";
    $sql .= "values (4, '$username', '".md5($passwd)."', '$fname', '$lname', ':admin:addressbook:filemanager:calendar:email:nntp:notes:todo:transy:manual:', ',1:0,', ".time().", 'A')";
    $db->query($sql);
    $db->query("insert into preferences values(4, 'maxmatchs', '15', 'common')");
    $db->query("insert into preferences values(4, 'theme', 'default', 'common')");
    $db->query("insert into preferences values(4, 'tz_offset', '', 'common')");
    $db->query("insert into preferences values(4, 'dateformat', 'm/d/Y', 'common')");
    $db->query("insert into preferences values(4, 'timeformat', '12', 'common')");
    $db->query("insert into preferences values(4, 'lang', 'en', 'common')");
    $db->query("insert into preferences values(4, 'company', 'True', 'common')");
    $db->query("insert into preferences values(4, 'lastname', 'True', 'common')");
    $db->query("insert into preferences values(4, 'firstname', 'True', 'common')");
    $db->query("insert into preferences values(4, 'weekstarts', 'Monday', 'common')");
    $db->query("insert into preferences values(4, 'workdaystarts', '9', 'common')");
    $db->query("insert into preferences values(4, 'workdayends', '17', 'common')");
  
    /* Create system records */
    $this_dir = dirname($SCRIPT_FILENAME);
    $rootdir    = ereg_replace("/setup","",$this_dir);
    $db->query("update config set config_value = '/tmp' where config_name = 'temp_dir'");
    $db->query("update config set config_value = '$rootdir/files' where config_name = 'files_dir'");
    $db->query("update config set config_value = '$mail_suffix' where config_name = 'mail_suffix'");
    $db->query("update config set config_value = '$mail_login_type' where config_name = 'mail_login_type'");
    $db->query("delete from config where config_name = 'freshinstall'");
    echo "Done";
  }

?>