<?php
  if ($download) {
    header("Content-disposition: attachment; filename=header.inc.php");
    header("Content-type: application/octet-stream");
    header("Pragma: no-cache");
    header("Expires: 0");
    $ftemplate = fopen(dirname($SCRIPT_FILENAME)."../header.inc.php.template","r");
    $template = fread($ftemplate,filesize(dirname($SCRIPT_FILENAME)."../header.inc.php.template"));
    fclose($ftemplate);
    while(list($k,$v) = each($HTTP_POST_VARS)) {
      $template = ereg_replace("__".strtoupper($k)."__",$v,$template);
    }
    echo $template;
    exit;
  }else{
?>
<html><head></head><body bgcolor="#ffffff">
if you are done and wrote the config without errors you can go <a href="./">here</a> to 
finish the setup
<table>
<tr bgcolor="486591">
<th colspan=2><font color="fefefe"> Analysis </font></th></tr>
<tr><td colspan=2>
<?
  // Hardly try to find what DB-support is compiled in
  // this dont work with PHP 3.0.10 and lower !
  if(isset($write_config) && !empty($write_config)) {
    $fsetup = true;
    $ftemplate = fopen(dirname($SCRIPT_FILENAME)."../header.inc.php.template","r");
    if($ftemplate){
      $fsetup = fopen($server_root."/header.inc.php","w");
      if(!$fsetup){
        echo "could not open header.inc.php for writing !<br>";
        echo "please check read/write permissions on directories or back up and download the file. Then save it to the correct location<br>";
        echo "</td></tr></table></body></html>";
        exit;
      }else{
        $template = fread($ftemplate,filesize(dirname($SCRIPT_FILENAME)."../header.inc.php.template"));
        fclose($ftemplate);
        while(list($k,$v) = each($HTTP_POST_VARS)) {
          echo "Replace token '__".strtoupper($k)."__' with value '$v'<br>\n";
          $template = ereg_replace("__".strtoupper($k)."__",$v,$template);
        }
        fwrite($fsetup,$template);
        fclose($fsetup);
        echo "Created header.inc.php!<br>";
      }
    }else{
      echo "could not open template header for reading !<br>";
      exit;
    }
  }

  $supported_db = array();
  if(extension_loaded("mysql")) {
     echo "You appear to have MySQL support enabled<br>\n";
     $supported_db[] = "mysql";
  } else {
     echo "No MySQL support found. Disabling<br>\n";
  }
  if(extension_loaded("pgsql")) {
     echo "You appear to have Postgres-DB support enabled<br>\n";
     $supported_db[]  = "pgsql";
  } else {
     echo "No Postgres-DB support found. Disabling<br>\n";
  }
  if(extension_loaded("oci8")) {
    echo "You appear to have Oracle V8 (OCI) support enabled<br>\n";
    $supported_db[] = "oracle";
  } else {
    if(extension_loaded("oracle")) {
      echo "You appear to have Oracle support enabled<br>\n";
      $supported_db[] = "oracle";
    } else {
      echo "No Oracle-DB support found. Disabling<br>\n";
    }
  }
  if(!count($supported_db)) {
    echo "<b><p align=center><font size=+2 color=red>did not found any valid DB support !<br>try to configure your php to support one of the above mentioned dbs or install phpgroupware by hand </font></p></b><td></tr></table></body></html>";
    exit;
  }

  $no_guess = false;
  $may_test = false;
  if(file_exists("../header.inc.php") && is_file("../header.inc.php")) {
    echo "found configuration. using this for defaults<br>\n";
    $phpgw_info["flags"]["noapi"] = True;
    include("../header.inc.php");
    $no_guess = true;
    $may_test  = true;
  } else {      
    echo "sample configuration not found. using built in defaults<br>\n";
    $phpgw_info["server"]["server_root"] = "/path/to/phpgroupware";
    $phpgw_info["server"]["include_root"] = "/path/to/phpgroupware/inc";
    /* This is the basic include needed on each page for phpGroupWare application compliance */
    $phpgw_info["flags"]["htmlcompliant"] = True;

    /* These are the settings for the database system */
    $phpgw_info["server"]["db_host"] = "localhost";
    $phpgw_info["server"]["db_name"] = "phpgroupware";
    $phpgw_info["server"]["db_user"] = "phpgroupware";
    $phpgw_info["server"]["db_pass"] = "your_password";
    $phpgw_info["server"]["db_type"] = "mysql"; //mysql, pgsql (for postgresql), or oracle

    /* These are a few of the advanced settings */
    $phpgw_info["server"]["config_passwd"] = "changeme";
    $phpgw_info["server"]["mcrypt_enabled"] = False;
    $phpgw_info["server"]["mcrypt_version"] = "2.6.3";
    $phpgw_info["server"]["mcrypt_iv"] = "cwjasud83l;la-0d.e/lc;[-%kl)ls,lf0;sa-;921kx;90flwl,skfcujd,wsodsp";
  }  

  // now guessing better settings then the default ones 
  if(!$no_guess) {
    echo "Now guessing better values for defaults <br>\n";
    $this_dir = dirname($SCRIPT_FILENAME);
    $updir    = ereg_replace("/setup","",$this_dir);
    $phpgw_info["server"]["server_root"] = $updir; 
    $phpgw_info["server"]["include_root"] = $updir."/inc"; 
  }
?>
</td></tr>
<?
  if($may_test) {
?>
<tr bgcolor=486591><th colspan=2><font color="fefefe">Test DB Connection</font></th></tr>
<tr><td colspan=2 align=center><form action="<? echo $PHP_SELF ?>" method=post>
<input type=hidden name=test_con value=1>
<input type=submit value="Test Connection Now">
</form>
<?
    if(isset($test_con) && $test_con == 1) {
      echo "Not yet implemented !<br>\n";
    }
    echo "</td></tr>\n";
  }
?>
<tr bgcolor=486591><th colspan=2><font color="fefefe">Settings</font></th></tr>
<form action="<? echo $PHP_SELF ?>"  method=post>
<input type=hidden name=write_config value=true>
  <tr><td colspan=2><b>Server Root</b><br><input type=text name=server_root size=80 value="<? echo $phpgw_info["server"]["server_root"] ?>"></td></tr>
  <tr><td colspan=2><b>Include Root</b><br><input type=text name=include_root size=80 value="<? echo $phpgw_info["server"]["include_root"] ?>"></td></tr>
<tr><td colspan=2><b>htmlcompliant<br>
<select name=htmlcompliant >
<?
  if($phpgw_info["flags"]["htmlcompliant"] == True) {
?>
<option value=True selected>True
<option value=False>False
<?
  } else {
?>
<option value=True>True
<option value=False selected>False     
<?
  }
?>
</select>
</td></tr>
  <tr><td><b>DB Host</b><br><input type=text name=db_host value="<? echo $phpgw_info["server"]["db_host"] ?>"></td><td>Hostname/IP of Databaseserver</td></tr>
  <tr><td><b>DB Name</b><br><input type=text name=db_name value="<? echo $phpgw_info["server"]["db_name"] ?>"></td><td>Name of Database</td></tr>
  <tr><td><b>DB User</b><br><input type=text name=db_user value="<? echo $phpgw_info["server"]["db_user"] ?>"></td><td>Name of DB User as phpgroupware has to connect as</td></tr>
  <tr><td><b>DB Password</b><br><input type=text name=db_pass value="<? echo $phpgw_info["server"]["db_pass"] ?>"></td><td>Password of DB User</td></tr>
  <tr><td><b>DB Type</b><br><select name=db_type>
<?
  $selected = "";
  $found_dbtype = false;
  while(list($k,$v) = each($supported_db)) {
    if($v == $phpgw_info["server"]["db_type"]) {
      $selected = " selected ";
      $found_dbtype = true;
    } else {
      $selected = "";
    }
    print "<option $selected value=\"$v\">$v\n";
  }
?>
  </select>
  </td><td>What Database do you want to use with PHPGroupWare?

  <tr><td><b>Configuration Password</b><br><input type=text name=config_pass value="<? echo $phpgw_info["server"]["config_passwd"] ?>"></td><td>Password needed for configuration</td></tr>
  <tr><td colspan=2><b>Enable MCrypt<br>
  <select name=mcrypt_enabled >
  <? if($phpgw_info["flags"]["mcrypt_enabled"] == True) { ?>
  <option value=True selected>True
  <option value=False>False
  <? } else { ?>
  <option value=True>True
  <option value=False selected>False     
  <? } ?>
  </select>
  </td></tr>
  <tr><td><b>MCrypt version</b><br><input type=text name=mcrypt_version value="<? echo $phpgw_info["server"]["mcrypt_version"] ?>"></td><td>Set this to "old" for versions < 2.4, otherwise the exact mcrypt version you use</td></tr>
  <tr><td><b>MCrypt initilazation vector</b><br><input type=text name=mcrypt_iv value="<? echo $phpgw_info["server"]["mcrypt_iv"] ?>"></td><td>It should be around 30 bytes in length</td></tr>
<?
  if(!$found_dbtype) {
    echo "<br><font color=red>Warning!<br>The db_type in defaults (".$phpgw_info["server"]["db_type"].") is not supported on this server. using first supported type.</font>";
  }
?>
  </td></tr>
  <tr><th colspan=2 align=center><input type=submit value="write config"> or <input type=submit name="download" value="download"></th></tr>
</form>
</td></tr>
</table>
</body>
</html>
<?php } ?>