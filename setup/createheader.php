<?php
  include("./inc/functions.inc.php");
  include("../version.inc.php");

  switch($action){
    case "download":
      header("Content-disposition: attachment; filename=\"header.inc.php\"");
      header("Content-type: application/octet-stream");
      header("Pragma: no-cache");
      header("Expires: 0");
      $newheader = generate_header();
      echo $newheader;
      break;
    case "view":
      show_header("Generated header.inc.php");
      echo "<br>Save this text as contents of your header.inc.php<br><hr>";
      $newheader = generate_header();
      echo "<pre>";
      echo htmlentities($newheader);
      echo "</pre></body></html>";
      break;
    case "write config":
      if(is_writeable ("../header.inc.php")|| (!file_exists ("../header.inc.php") && is_writeable ("../"))){
        show_header("Saved header.inc.php");
        $newheader = generate_header();
        $fsetup = fopen("../header.inc.php","w");
        fwrite($fsetup,$newheader);
        fclose($fsetup);
        echo "Created header.inc.php!<br>";
      }else{
        show_header("Error generating header.inc.php");
        echo "Could not open header.inc.php for writing!<br>\n";
        echo "Please check read/write permissions on directories or back up and use another option.<br>";
        echo "</td></tr></table></body></html>";
      }
      break;
    default:
      show_header("Create/Edit your header.inc.php");
      echo '<table>
          <tr bgcolor="486591"><th colspan=2><font color="fefefe"> Analysis </font></th></tr>
          <tr><td colspan=2>';
      // Hardly try to find what DB-support is compiled in
      // this dont work with PHP 3.0.10 and lower !
    
      $supported_db = array();
      if (extension_loaded("mysql") || function_exists("mysql_connect")) {
         echo "You appear to have MySQL support enabled<br>\n";
         $supported_db[] = "mysql";
      } else {
         echo "No MySQL support found. Disabling<br>\n";
      }
      if (extension_loaded("pgsql") || function_exists("pg_connect")) {
         echo "You appear to have Postgres-DB support enabled<br>\n";
         $supported_db[]  = "pgsql";
      } else {
         echo "No Postgres-DB support found. Disabling<br>\n";
      }
      if (extension_loaded("oci8")) {
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
      if(file_exists("../header.inc.php") && is_file("../header.inc.php")) {
        echo "Found existing configuration file. Loading settings from the file...<br>\n";
        $phpgw_info["flags"]["noapi"] = True;
        include("../header.inc.php");
        $no_guess = true;
        /* This code makes sure the newer multi-domain supporting header.inc.php is being used */
        if (!isset($phpgw_domain)) {
          echo "Your using an old configuration file format...<br>\n";
          echo "Importing old settings into the new format....<br>\n";
        }else{
          if ($phpgw_info["server"]["header_version"] != $phpgw_info["server"]["current_header_version"]) {
            echo "Your using an old header.inc.php version...<br>\n";
            echo "Importing old settings into the new format....<br>\n";
          }
          reset($phpgw_domain);
          $default_domain = each($phpgw_domain);
          $phpgw_info["server"]["default_domain"] = $default_domain[0];
          unset ($default_domain); // we kill this for security reasons
          $phpgw_info["server"]["db_host"] = $phpgw_domain[$phpgw_info["server"]["default_domain"]]["db_host"];
          $phpgw_info["server"]["db_name"] = $phpgw_domain[$phpgw_info["server"]["default_domain"]]["db_name"];
          $phpgw_info["server"]["db_user"] = $phpgw_domain[$phpgw_info["server"]["default_domain"]]["db_user"];
          $phpgw_info["server"]["db_pass"] = $phpgw_domain[$phpgw_info["server"]["default_domain"]]["db_pass"];
          $phpgw_info["server"]["db_type"] = $phpgw_domain[$phpgw_info["server"]["default_domain"]]["db_type"];
          $phpgw_info["server"]["config_passwd"] = $phpgw_domain[$phpgw_info["server"]["default_domain"]]["config_passwd"];
        }
        if (!isset($phpgw_info["server"]["include_root"]) && $phpgw_info["server"]["header_version"] <= 1.6) {
          $phpgw_info["server"]["include_root"] = $phpgw_info["server"]["server_root"];
        }elseif (!isset($phpgw_info["server"]["header_version"]) && $phpgw_info["server"]["header_version"] <= 1.6) {
          $phpgw_info["server"]["include_root"] = $phpgw_info["server"]["server_root"];
        }
      } else {      
        echo "sample configuration not found. using built in defaults<br>\n";
        $phpgw_info["server"]["server_root"] = "/path/to/phpgroupware";
        $phpgw_info["server"]["include_root"] = "/path/to/phpgroupware";
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

        srand((double)microtime()*1000000);
        $random_char = array("0","1","2","3","4","5","6","7","8","9","a","b","c","d","e","f",
                             "g","h","i","j","k","l","m","n","o","p","q","r","s","t","u","v",
                             "w","x","y","z","A","B","C","D","E","F","G","H","I","J","K","L",
                             "M","N","O","P","Q","R","S","T","U","V","W","X","Y","Z","/",";",
                             ",","%","$","!","@","#","^","&","*","(",")","-","_","+","=","|",
                             "\\","[","]","{","}",";",":",'"',"'","<",">",".","?");
  
        for ($i=0; $i<30; $i++) {
            $phpgw_info["server"]["mcrypt_iv"] .= $random_char[rand(1,count($random_char))];
        }
      }  
    
      // now guessing better settings then the default ones 
      if(!$no_guess) {
        echo "Now guessing better values for defaults <br>\n";
        $this_dir = dirname($SCRIPT_FILENAME);
        $updir    = ereg_replace("/setup","",$this_dir);
        $phpgw_info["server"]["server_root"] = $updir; 
        $phpgw_info["server"]["include_root"] = $updir; 
      }
      ?>
      </td></tr>
      <tr bgcolor=486591><th colspan=2><font color="fefefe">Settings</font></th></tr>
      <form action="<? echo $PHP_SELF ?>"  method=post>
      <input type=hidden name=write_config value=true>
        <tr><td colspan=2><b>Server Root</b><br><input type=text name=server_root size=80 value="<? echo $phpgw_info["server"]["server_root"] ?>"></td></tr>
        <tr><td colspan=2><b>Include Root (this should be the same as Server Root unless you know what you are doing)</b><br><input type=text name=include_root size=80 value="<? echo $phpgw_info["server"]["include_root"] ?>"></td></tr>
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
        <select name=enable_mcrypt >
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
      </table>
      <?
      if(!$found_dbtype) {
        echo "<br><font color=red>Warning!<br>The db_type in defaults (".$phpgw_info["server"]["db_type"].") is not supported on this server. using first supported type.</font>";
      }
      echo "<br>";
      echo "<form>";
    
      if(is_writeable ("../header.inc.php")|| (!file_exists ("../header.inc.php") && is_writeable ("../"))){
        echo '<input type=submit name="action" value="write config">';
        echo' or <input type=submit name="action" value="download"> or <input type=submit name="action" value="view"> the file.</form>';
      }else{
        echo 'Cannot create the header.inc.php due to file permission restrictions.<br> Instead you can ';
        echo'<input type=submit name="action" value="download">or <input type=submit name="action" value="view"> the file.</form>';
      }
      echo '<form action="index.php" method=post>';
      echo'<br> After retrieving the file put it into place as the header.inc.php, then click continue.<br>';
      echo'<input type=submit name="junk" value="continue">';
      echo "</form>";
      echo "</body>";
      echo "</html>";
  } 
?>
