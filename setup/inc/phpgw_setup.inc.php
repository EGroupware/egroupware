<?php
  /**************************************************************************\
  * phpGroupWare - Setup                                                     *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  class phpgw_setup
  {
    var $db;
    var $template;

    function show_header($title = "",$nologoutbutton = False, $logoutfrom = "config") 
    {
      global $phpgw_info, $PHP_SELF;
      echo '
        <html>
        <head>
          <title>phpGroupWare Setup
      ';
      if ($title != ""){echo " - ".$title;}
      echo'
        </title>
        <style type="text/css"><!-- .link { color: #FFFFFF; } --></style>
        </head>
        <BODY BGCOLOR="FFFFFF" margintop="0" marginleft="0" marginright="0" marginbottom="0">
        <table border="0" width="100%" cellspacing="0" cellpadding="2">
        <tr>
          <td align="left" bgcolor="486591">&nbsp;<font color="fefefe">phpGroupWare version '.$phpgw_info["server"]["versions"]["phpgwapi"].' setup</font>
        </td>
        <td align="right" bgcolor="486591">
      ';
      if ($nologoutbutton) {
        echo "&nbsp;";
      } else {
        echo '<a href="' . $PHP_SELF . '?FormLogout='.$logoutfrom.'" class="link">Logout</a>&nbsp;';
      }
      echo "</td></tr></table>";
    }
    function login_form()
    {
     	global $phpgw_info, $phpgw_domain, $PHP_SELF;

      echo "<p><body bgcolor='#ffffff'>\n";
      echo "<table border=\"0\" align=\"center\">\n";
      if ($phpgw_info["setup"]["stage"]["header"] == 10){
        echo "  <tr bgcolor=\"486591\">\n";
        echo "    <td colspan=\"2\"><font color=\"fefefe\">&nbsp;<b>Setup/Config Admin Login</b></font></td>\n";
        echo "  </tr>\n";
        echo "   <tr bgcolor='#e6e6e6'><td colspan='2'><font color='#ff0000'>".$phpgw_info["setup"]["ConfigLoginMSG"]."</font></td></tr>\n";
        echo "  <tr bgcolor=\"e6e6e6\">\n";
        echo "    <td><form action='index.php' method='POST'>\n";
        if (count($phpgw_domain) > 1){
          echo "      <table><tr><td>Domain: </td><td><input type='text' name='FormDomain' value=''></td></tr>\n";
          echo "      <tr><td>Password: </td><td><input type='password' name='FormPW' value=''></td></tr></table>\n";
        }else{
          reset($phpgw_domain);
          $default_domain = each($phpgw_domain);
          echo "      <input type='password' name='FormPW' value=''>\n";
          echo "      <input type='hidden' name='FormDomain' value='".$default_domain[0]."'>\n";
        }
        echo "      <input type='submit' name='ConfigLogin' value='Login'>\n";
        echo "    </form></td>\n";
        echo "  </tr>\n";
      }

      echo "  <tr bgcolor=\"486591\">\n";
      echo "    <td colspan=\"2\"><font color=\"fefefe\">&nbsp;<b>Header Admin Login</b></font></td>\n";
      echo "  </tr>\n";
      echo "   <tr bgcolor='#e6e6e6'><td colspan='2'><font color='#ff0000'>".$phpgw_info["setup"]["HeaderLoginMSG"]."</font></td></tr>\n";
      echo "  <tr bgcolor=\"e6e6e6\">\n";
      echo "    <td><form action='manageheader.php' method='POST'>\n";
      echo "      <input type='password' name='FormPW' value=''>\n";
      echo "      <input type='submit' name='HeaderLogin' value='Login'>\n";
      echo "    </form></td>\n";
      echo "  </tr>\n";
  
      echo "</table>\n";
   	  echo "</body></html>\n";
    }
  
    function check_header()
    {
      global $phpgw_domain, $phpgw_info;
      if(!file_exists("../header.inc.php") || !is_file("../header.inc.php")) {
        $phpgw_info["setup"]["header_msg"] = "Stage One";
        return 1;
      }else{
        include("../header.inc.php");
        if (!isset($phpgw_info["server"]["header_admin_password"])){
          $phpgw_info["setup"]["header_msg"] = "Stage One (No header admin password set)";
          return 2;
        }elseif (!isset($phpgw_domain) || $phpgw_info["server"]["versions"]["header"] != $phpgw_info["server"]["versions"]["current_header"]) {
          $phpgw_info["setup"]["header_msg"] = "Stage One (Upgrade your header.inc.php)";
          return 3;
        }else{ /* header.inc.php part settled. Moving to authentication */
          $phpgw_info["setup"]["header_msg"] = "Stage One (Completed)";
          return 10;
        }
      }
    }
  
    function generate_header()
    {
      global $setting, $phpgw_setup, $phpgw_info;
      
      $phpgw_setup->template->set_file(array("header" => "header.inc.php.template"));
      while(list($k,$v) = each($setting)) {
        $phpgw_setup->template->set_var(strtoupper($k),$v);
      }
      return $phpgw_setup->template->parse("out","header");
    }
  
    function auth($auth_type = "Config")
    {
      global $phpgw_domain, $phpgw_info, $HTTP_POST_VARS, $FormLogout, $ConfigLogin, $HeaderLogin, $FormDomain, $FormPW, $ConfigDomain, $ConfigPW, $HeaderPW;
      if (isset($FormLogout)) {
        if ($FormLogout == "config"){
          setcookie("ConfigPW");  // scrub the old one
          setcookie("ConfigDomain");  // scrub the old one
          $phpgw_info["setup"]["ConfigLoginMSG"] = "You have sucessfully logged out";
          return False;
        }elseif($FormLogout == "header"){
          setcookie("HeaderPW");  // scrub the old one
          $phpgw_info["setup"]["HeaderLoginMSG"] = "You have sucessfully logged out";
          return False;
        }
      } elseif (isset($ConfigPW)) {
        if ($ConfigPW != $phpgw_domain[$ConfigDomain]["config_passwd"] && $auth_type == "Config") {
          setcookie("ConfigPW");  // scrub the old one
          setcookie("ConfigDomain");  // scrub the old one
          $phpgw_info["setup"]["ConfigLoginMSG"] = "Invalid session cookie (cookies must be enabled)";
          return False;
        }else{
          return True;
        }
      } elseif (isset($HeaderPW)) {
        if ($HeaderPW != $phpgw_info["server"]["header_admin_password"] && $auth_type == "Header") {
          setcookie("HeaderPW");  // scrub the old one
          $phpgw_info["setup"]["HeaderLoginMSG"] = "Invalid session cookie (cookies must be enabled)";
          return False;
        }else{
          return True;
        }
      } elseif (isset($FormPW)) {
        if (isset($ConfigLogin)){
          if ($FormPW == $phpgw_domain[$FormDomain]["config_passwd"] && $auth_type == "Config") {
            setcookie("ConfigPW",$FormPW);
            setcookie("ConfigDomain",$FormDomain);
            $ConfigDomain = $FormDomain;
            return True;
          }else{
            $phpgw_info["setup"]["ConfigLoginMSG"] = "Invalid password";
            return False;
          }
        }elseif (isset($HeaderLogin)){
          if ($FormPW == $phpgw_info["server"]["header_admin_password"] && $auth_type == "Header") {
            setcookie("HeaderPW",$FormPW);
            return True;
          }else{
            $phpgw_info["setup"]["HeaderLoginMSG"] = "Invalid password";
            return False;
          }
        }
      } else {
        return False;
      }
    }
  
    function loaddb()
    {
      global $phpgw_info, $phpgw_domain, $ConfigDomain;
      /* Database setup */
      if (!isset($phpgw_info["server"]["api_inc"])) {
        $phpgw_info["server"]["api_inc"] = $phpgw_info["server"]["server_root"]."/phpgwapi/inc";
      }
      include($phpgw_info["server"]["api_inc"] . "/phpgw_db_".$phpgw_domain[$ConfigDomain]["db_type"].".inc.php");
      $this->db	          = new db;
      $this->db->Host       = $phpgw_domain[$ConfigDomain]["db_host"];
      $this->db->Type       = $phpgw_domain[$ConfigDomain]["db_type"];
      $this->db->Database   = $phpgw_domain[$ConfigDomain]["db_name"];
      $this->db->User       = $phpgw_domain[$ConfigDomain]["db_user"];
      $this->db->Password   = $phpgw_domain[$ConfigDomain]["db_pass"];

      $phpgw_schema_proc = new phpgw_schema_proc($phpgw_domain[$ConfigDomain]["db_type"]);
    }
  
    function check_db()
    {
      global $phpgw_info;
      $this->db->Halt_On_Error = "no";
      $tables = $this->db->table_names();
      if (is_array($tables) && count($tables) > 0){
        /* tables exists. checking for post beta version */
        $this->db->query("select * from applications");
        while (@$this->db->next_record()) {
          if ($this->db->f("app_name") == "admin"){$phpgw_info["setup"]["oldver"]["phpgwapi"] = $this->db->f("app_version");}
          $phpgw_info["setup"]["oldver"][$this->db->f("app_name")] = $this->db->f("app_version");
          $phpgw_info["setup"][$this->db->f("app_name")]["title"] = $this->db->f("app_title");
        }
        if (isset($phpgw_info["setup"]["oldver"]["phpgwapi"])){
          if ($phpgw_info["setup"]["oldver"]["phpgwapi"] == $phpgw_info["server"]["versions"]["phpgwapi"]){
            $phpgw_info["setup"]["header_msg"] = "Stage 2 (Tables Complete)";
            return 10;
          }else{
            $phpgw_info["setup"]["header_msg"] = "Stage 2 (Tables need upgrading)";
            return 4;
          }
        }else{
          $phpgw_info["setup"]["header_msg"] = "Stage 2 (Tables appear to be pre-beta)";
          return 2;
        }
      }else{
        /* no tables, so checking if we can create them */
  
        /* I cannot get either to work properly
        $isdb = $this->db->connect("kljkjh", "localhost", "phpgroupware", "phpgr0upwar3");
        */
        
        $db_rights = $this->db->query("CREATE TABLE phpgw_testrights ( testfield varchar(5) NOT NULL )");
        $this->db->query("DROP TABLE phpgw_testrights");
  
        if (isset($db_rights)){
        //if (isset($isdb)){
          $phpgw_info["setup"]["header_msg"] = "Stage 2 (Create tables)";
          return 3;
        }else{
          $phpgw_info["setup"]["header_msg"] = "Stage 2 (Create Database)";
          return 1;
        }
      }
    }

    function check_config()
    {
      global $phpgw_info;
      $this->db->Halt_On_Error = "no";
      if ($phpgw_info["setup"]["stage"]["db"] == 10){
        $this->db->query("select config_value from config where config_name='freshinstall'");
        $this->db->next_record();
        $configed = $this->db->f("config_value");
        if ($configed){
          $phpgw_info["setup"]["header_msg"] = "Stage 3 (Needs Configuration)";
          return 1;
        }else{
          $phpgw_info["setup"]["header_msg"] = "Stage 3 (Configuration OK)";
          return 10;
        }
      }
    }
  
    function app_setups($appname = ""){
      global $phpgw_info;
      $d = dir($phpgw_info["server"]["server_root"]);
      while($entry=$d->read()) {
        if (is_dir ($phpgw_info["server"]["server_root"]."/".$entry."/setup")){
          echo $entry."<br>\n";
        }
      }
      $d->close();
    }
  
    function execute_script($script, $appname = ""){
      global $phpgw_info, $phpgw_domain;
      if ($appname == ""){
        $d = dir($phpgw_info["server"]["server_root"]);
        while($entry=$d->read()) {
          $f = $phpgw_info["server"]["server_root"]."/".$entry."/setup/version.inc.php";
          if (file_exists ($f)){
            include($f);
          }else{
            $phpgw_info["server"]["versions"][$entry] = $phpgw_info["setup"]["currentver"]["phpgwapi"];
          }
  
          $f = $phpgw_info["server"]["server_root"]."/".$entry."/setup/".$script.".inc.php";
          if (file_exists ($f)){include($f);}
        }
        $d->close();
      }else{
        $f = $phpgw_info["server"]["server_root"]."/".$appname."/setup/".$script.".inc.php";
        if (file_exists ($f)){include($f);}
      }
    }
  
    function update_app_version($appname, $tableschanged = True){
      global $phpgw_info;
      if ($tableschanged == True){$phpgw_info["setup"]["tableschanged"] = True;}
      $this->db->query("update applications set app_version='".$phpgw_info["setup"]["currentver"]["phpgwapi"]."' where app_name='".$appname."'");
    }
  
    function manage_tables(){
      global $phpgw_domain, $phpgw_info;
      if ($phpgw_info["setup"]["currentver"]["phpgwapi"] == "drop"){
        $this->execute_script("droptables");
      }
      if ($phpgw_info["setup"]["currentver"]["phpgwapi"] == "new") {
        $this->execute_script("newtables");
        $this->execute_script("common_default_records");
        $this->execute_script("lang");
      }
    
      if ($phpgw_info["setup"]["currentver"]["phpgwapi"] == "oldversion") {
        $phpgw_info["setup"]["currentver"]["phpgwapi"] = $phpgw_info["setup"]["oldver"]["phpgwapi"];
        $this->execute_script("upgradetables");
      }
    
      /* Not yet implemented
      if (!$phpgw_info["setup"]["tableschanged"] == True){
        echo "  <tr bgcolor=\"e6e6e6\">\n";
        echo "    <td>No table changes were needed. The script only updated your version setting.</td>\n";
        echo "  </tr>\n";
      }
      */
    }
  
    function setup_header($title = "",$nologoutbutton = False) {
      global $phpgw_info, $PHP_SELF;
  
      // Ok, so it isn't the greatest idea, but it works for now.  Setup needs to be rewritten.
      if ($phpgw_info["setup"]["dontshowtheheaderagain"]) {
         return False;
      }
  
      $phpgw_info["setup"]["dontshowtheheaderagain"] = True;
?>
      <head>
       <title>phpGroupWare setup <?php echo $title; ?></title>
        <style type="text/css">
         <!--
           .link
           { 
              color: #FFFFFF;
           }
         -->
        </style>
      </head>
      <BODY BGCOLOR="FFFFFF" margintop="0" marginleft="0" marginright="0" marginbottom="0">
      <table border="0" width="100%" cellspacing="0" cellpadding="2">
       <tr>
        <td align="left" bgcolor="486591">&nbsp;<font color="fefefe">phpGroupWare version <?php 
         echo $phpgw_info["server"]["versions"]["phpgwapi"]; ?> setup</font>
        </td>
        <td align="right" bgcolor="486591">
<?php
      if ($nologoutbutton) {
        echo "&nbsp;";
      } else {
        echo '<a href="' . $PHP_SELF . '?FormLogout=True" class="link">Logout</a>&nbsp;';
      }
      echo "</td></tr></table>";
    }
  }
?>