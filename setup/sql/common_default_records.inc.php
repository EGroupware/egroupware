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

  function add_default_server_config(){
    global $db, $phpgw_info, $currentver;

    $phpgw_info["server"]["default_tplset"] = "default";
          $phpgw_info["server"]["temp_dir"]="/path/to/tmp";
          $phpgw_info["server"]["files_dir"]="/path/to/phpgroupware/files";
          $phpgw_info["server"]["webserver_url"]="/phpgroupware";
          $phpgw_info["server"]["mail_server"]="localhost";
          $phpgw_info["server"]["mail_server_type"]="imap";
          $phpgw_info["server"]["imap_server_type"]="UWash";
          $phpgw_info["server"]["mail_suffix"]="yourdomain.com";
          $phpgw_info["server"]["mail_login_type"]="standard";
          $phpgw_info["server"]["smtp_server"]="localhost";
          $phpgw_info["server"]["smtp_port"]="25";
          $phpgw_info["server"]["auth_type"]="sql";
          $phpgw_info["server"]["account_repository"]="sql";
          $phpgw_info["server"]["ldap_host"]="localhost";
          $phpgw_info["server"]["ldap_context"]="ou=People,dc=my-domain,dc=com";
          $phpgw_info["server"]["ldap_root_dn"]="cn=Manager,dc=my-domain,dc=com";
          $phpgw_info["server"]["ldap_root_pw"]="secret";
          $phpgw_info["server"]["ldap_encryption_type"]="DES";
          $phpgw_info["server"]["usecookies"]="True";
          $phpgw_info["server"]["encryptkey"]="change this phrase 2 something else";
          $phpgw_info["server"]["default_ftp_server"]="localhost";
          $phpgw_info["server"]["httpproxy_server"]="";
          $phpgw_info["server"]["httpproxy_port"]="";
          $phpgw_info["server"]["showpoweredbyon"]="top";
          $phpgw_info["server"]["site_title"]="phpGroupWare";
          $phpgw_info["server"]["hostname"]="localhost";
          $phpgw_info["server"]["nntp_server"]="yournewsserver.com";
          $phpgw_info["server"]["nntp_port"]="119";
          $phpgw_info["server"]["nntp_sender"]="complaints@yourserver.com";
          $phpgw_info["server"]["nntp_organization"]="phpGroupWare";
          $phpgw_info["server"]["nntp_admin"]="admin@yourserver.com";
          $phpgw_info["server"]["nntp_login_username"]="";
          $phpgw_info["server"]["nntp_login_password"]="";
    
    $db->query("insert into config values('" . serialize($phpgw_info["server"]) . "')");

    // I disabled a lot of this for temp. (jengo)

/*    $db->query("insert into config (config_name, config_value) values ('default_tplset', 'default')");
    $db->query("insert into config (config_name, config_value) values ('temp_dir', '/path/to/tmp')");
    $db->query("insert into config (config_name, config_value) values ('files_dir', '/path/to/dir/phpgroupware/files')");
    $db->query("insert into config (config_name, config_value) values ('encryptkey', 'change this phrase 2 something else')");
    $db->query("insert into config (config_name, config_value) values ('site_title', 'phpGroupWare')");
    $db->query("insert into config (config_name, config_value) values ('hostname', 'local.machine.name')");
    $db->query("insert into config (config_name, config_value) values ('webserver_url', '/phpgroupware')");
    $db->query("insert into config (config_name, config_value) values ('auth_type', 'sql')");
    $db->query("insert into config (config_name, config_value) values ('ldap_host', 'localhost')");
    $db->query("insert into config (config_name, config_value) values ('ldap_context', 'ou=People,dc=my-domain,dc=com')");
    $db->query("insert into config (config_name, config_value) values ('ldap_encryption_type', 'DES')");
    $db->query("insert into config (config_name, config_value) values ('ldap_root_dn', 'cn=Manager,dc=my-domain,dc=com')");
    $db->query("insert into config (config_name, config_value) values ('ldap_root_pw', 'secret')");
    $db->query("insert into config (config_name, config_value) values ('usecookies', 'True')");
    $db->query("insert into config (config_name, config_value) values ('mail_server', 'localhost')");
    $db->query("insert into config (config_name, config_value) values ('mail_server_type', 'imap')");
    $db->query("insert into config (config_name, config_value) values ('imap_server_type', 'Cyrus')");
    $db->query("insert into config (config_name, config_value) values ('mail_suffix', 'yourdomain.com')");         
    $db->query("insert into config (config_name, config_value) values ('mail_login_type', 'standard')");
    $db->query("insert into config (config_name, config_value) values ('smtp_server', 'localhost')");
    $db->query("insert into config (config_name, config_value) values ('smtp_port', '25')");
    $db->query("insert into config (config_name, config_value) values ('nntp_server', 'yournewsserver.com')");
    $db->query("insert into config (config_name, config_value) values ('nntp_port', '119')");
    $db->query("insert into config (config_name, config_value) values ('nntp_sender', 'complaints@yourserver.com')");
    $db->query("insert into config (config_name, config_value) values ('nntp_organization', 'phpGroupWare')");
    $db->query("insert into config (config_name, config_value) values ('nntp_admin', 'admin@yourserver.com')");
    $db->query("insert into config (config_name, config_value) values ('nntp_login_username', '')");
    $db->query("insert into config (config_name, config_value) values ('nntp_login_password', '')");
    $db->query("insert into config (config_name, config_value) values ('charset', 'iso-8859-1')");
    $db->query("insert into config (config_name, config_value) values ('default_ftp_server', 'localhost')");
    $db->query("insert into config (config_name, config_value) values ('httpproxy_server', '')");
    $db->query("insert into config (config_name, config_value) values ('httpproxy_port', '')");
    $db->query("insert into config (config_name, config_value) values ('showpoweredbyon', 'bottom')");
    $db->query("insert into config (config_name, config_value) values ('htmlcompliant', 'False')");
    $db->query("insert into config (config_name, config_value) values ('checkfornewversion', 'False')");
    $db->query("insert into config (config_name, config_value) values ('freshinstall', 'True')");
  }

  if ($useglobalconfigsettings == "on"){
    if (is_file($basedir)){
      include ($phpgw_info["server"]["include_root"]."/globalconfig.inc.php");
      $db->query("insert into config (config_name, config_value) values ('default_tplset', '".$phpgw_info["server"]["default_tplset"]."')");
      $db->query("insert into config (config_name, config_value) values ('temp_dir', '".$phpgw_info["server"]["temp_dir"]."')");
      $db->query("insert into config (config_name, config_value) values ('files_dir', '".$phpgw_info["server"]["files_dir"]."')");
      $db->query("insert into config (config_name, config_value) values ('encryptkey', '".$phpgw_info["server"]["encryptkey"]."')");
      $db->query("insert into config (config_name, config_value) values ('site_title', '".$phpgw_info["server"]["site_title"]."')");
      $db->query("insert into config (config_name, config_value) values ('hostname', '".$phpgw_info["server"]["hostname"]."')");
      $db->query("insert into config (config_name, config_value) values ('webserver_url', '".$phpgw_info["server"]["webserver_url"].")");
      $db->query("insert into config (config_name, config_value) values ('auth_type', '".$phpgw_info["server"]["auth_type"]."')");
      $db->query("insert into config (config_name, config_value) values ('ldap_host', '".$phpgw_info["server"]["ldap_host"]."')");
      $db->query("insert into config (config_name, config_value) values ('ldap_context', '".$phpgw_info["server"]["ldap_context"]."')");
      $db->query("insert into config (config_name, config_value) values ('usecookies', '".$phpgw_info["server"]["usecookies"]."')");
      $db->query("insert into config (config_name, config_value) values ('mail_server', '".$phpgw_info["server"]["mail_server"]."')");
      $db->query("insert into config (config_name, config_value) values ('mail_server_type', '".$phpgw_info["server"]["mail_server_type"]."')");
      $db->query("insert into config (config_name, config_value) values ('imap_server_type', '".$phpgw_info["server"]["imap_server_type"]."')");
      $db->query("insert into config (config_name, config_value) values ('mail_suffix', '".$phpgw_info["server"]["mail_suffix"]."')");         
      $db->query("insert into config (config_name, config_value) values ('mail_login_type', '".$phpgw_info["server"]["mail_login_type"]."')");
      $db->query("insert into config (config_name, config_value) values ('smtp_server', '".$phpgw_info["server"]["smtp_server"]."')");
      $db->query("insert into config (config_name, config_value) values ('smtp_port', '".$phpgw_info["server"]["smtp_port"]."')");
      $db->query("insert into config (config_name, config_value) values ('nntp_server', '".$phpgw_info["server"]["nntp_server"]."')");
      $db->query("insert into config (config_name, config_value) values ('nntp_port', '".$phpgw_info["server"]["nntp_port"]."')");
      $db->query("insert into config (config_name, config_value) values ('nntp_sender', '".$phpgw_info["server"]["nntp_sender"]."')");
      $db->query("insert into config (config_name, config_value) values ('nntp_organization', '".$phpgw_info["server"]["nntp_organization"]."')");
      $db->query("insert into config (config_name, config_value) values ('nntp_admin', '".$phpgw_info["server"]["nntp_admin"]."')");
      $db->query("insert into config (config_name, config_value) values ('nntp_login_username', '".$phpgw_info["server"]["nntp_login_username"]."')");
      $db->query("insert into config (config_name, config_value) values ('nntp_login_password', '".$phpgw_info["server"]["nntp_login_password"]."')");
      $db->query("insert into config (config_name, config_value) values ('charset', '".$phpgw_info["server"]["charset"]."')");
      $db->query("insert into config (config_name, config_value) values ('default_ftp_server', '".$phpgw_info["server"]["default_ftp_server"]."')");
      $db->query("insert into config (config_name, config_value) values ('httpproxy_server', '".$phpgw_info["server"]["httpproxy_server"]."')");
      $db->query("insert into config (config_name, config_value) values ('httpproxy_port', '".$phpgw_info["server"]["httpproxy_port"]."')");
      $db->query("insert into config (config_name, config_value) values ('showpoweredbyon', '".$phpgw_info["server"]["showpoweredbyon"]."')");
      $db->query("insert into config (config_name, config_value) values ('checkfornewversion', '".$phpgw_info["server"]["checkfornewversion"]."')");
    }else{
      echo "<table border=\"0\" align=\"center\">\n";
      echo "  <tr bgcolor=\"486591\">\n";
      echo "    <td colspan=\"2\"><font color=\"fefefe\">&nbsp;<b>Error</b></font></td>\n";
      echo "  </tr>\n";
      echo "  <tr bgcolor=\"e6e6e6\">\n";
      echo "    <td>Could not find your old globalconfig.inc.php.<br> You will be required to configure your installation manually.</td>\n";
      echo "  </tr>\n";
      echo "</table>\n";
      add_default_server_config();
    } 
  }else{ */
  }
    add_default_server_config();
//  }

  include($phpgw_info["server"]["server_root"] . "/setup/sql/default_applications.inc.php");

  $db->query("insert into groups (group_name) values ('Default')");  
  $db->query("insert into accounts (account_lid,account_pwd,account_firstname,account_lastname,account_permissions,account_groups,account_status) values ('demo','81dc9bdb52d04dc20036dbd8313ed055','Demo','Account',':admin:email:todo:addressbook:calendar:',',1:0,','A')");
  
  $db->query("insert into preferences (preference_owner, preference_name, preference_value, preference_appname) values ('1','maxmatchs','10','common')");
  $db->query("insert into preferences (preference_owner, preference_name, preference_value, preference_appname) values ('1','timeformat','12','common')");
  $db->query("insert into preferences (preference_owner, preference_name, preference_value, preference_appname) values ('1','dateformat','m/d/Y','common')");
  $db->query("insert into preferences (preference_owner, preference_name, preference_value, preference_appname) values ('1','theme','default','common')");
  $db->query("insert into preferences (preference_owner, preference_name, preference_value, preference_appname) values ('1','tz_offset','0','common')");
  $db->query("insert into preferences (preference_owner, preference_name, preference_value, preference_appname) values ('1','lang','en','common')");
  $db->query("insert into preferences (preference_owner, preference_name, preference_value, preference_appname) values ('1','firstname','True','addressbook')");
  $db->query("insert into preferences (preference_owner, preference_name, preference_value, preference_appname) values ('1','lastname','True','addressbook')");
  $db->query("insert into preferences (preference_owner, preference_name, preference_value, preference_appname) values ('1','company','True','addressbook')");
  $db->query("insert into preferences (preference_owner, preference_name, preference_value, preference_appname) values ('1','calendar','workdaystarts','8')");
  $db->query("insert into preferences (preference_owner, preference_name, preference_value, preference_appname) values ('1','calendar','workdayends','15')");
  $db->query("insert into preferences (preference_owner, preference_name, preference_value, preference_appname) values ('1','calendar','weekdaystarts','Monday')");

  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('aa','Afar','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ab','Abkhazian','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('af','Afrikaans','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('am','Amharic','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ar','Arabic','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('as','Assamese','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ay','Aymara','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('az','Azerbaijani','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ba','Bashkir','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('be','Byelorussian','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('bg','Bulgarian','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('bh','Bihari','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('bi','Bislama','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('bn','Bengali / Bangla','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('bo','Tibetan','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('br','Breton','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ca','Catalan','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('co','Corsican','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('cs','Czech','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('cy','Welsh','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('da','Danish','Yes')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('de','German','Yes')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('dz','Bhutani','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('el','Greek','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('en','English / American','Yes')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('eo','Esperanto','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('es','Spanish','Yes')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('et','Estonian','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('eu','Basque','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('fa','Persian','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('fi','Finnish','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('fj','Fiji','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('fo','Faeroese','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('fr','French','Yes')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('fy','Frisian','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ga','Irish','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('gd','Gaelic / Scots Gaelic','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('gl','Galician','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('gn','Guarani','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('gu','Gujarati','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ha','Hausa','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('hi','Hindi','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('hr','Croatian','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('hu','Hungarian','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('hy','Armenian','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ia','Interlingua','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ie','Interlingue','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ik','Inupiak','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('in','Indonesian','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('is','Icelandic','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('it','Italian','Yes')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('iw','Hebrew','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ja','Japanese','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ji','Yiddish','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('jw','Javanese','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ka','Georgian','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('kk','Kazakh','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('kl','Greenlandic','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('km','Cambodian','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('kn','Kannada','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ko','Korean','Yes')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ks','Kashmiri','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ku','Kurdish','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ky','Kirghiz','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('la','Latin','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ln','Lingala','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('lo','Laothian','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('lt','Lithuanian','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('lv','Latvian / Lettish','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('mg','Malagasy','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('mi','Maori','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('mk','Macedonian','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ml','Malayalam','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('mn','Mongolian','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('mo','Moldavian','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('mr','Marathi','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ms','Malay','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('mt','Maltese','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('my','Burmese','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('na','Nauru','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ne','Nepali','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('nl','Dutch','Yes')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('no','Norwegian','Yes')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('oc','Occitan','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('om','Oromo / Afan','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('or','Oriya','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('pa','Punjabi','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('pl','Polish','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ps','Pashto / Pushto','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('pt','Portuguese','Yes')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('qu','Quechua','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('rm','Rhaeto-Romance','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('rn','Kirundi','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ro','Romanian','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ru','Russian','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('rw','Kinyarwanda','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('sa','Sanskrit','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('sd','Sindhi','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('sg','Sangro','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('sh','Serbo-Croatian','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('si','Singhalese','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('sk','Slovak','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('sl','Slovenian','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('sm','Samoan','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('sn','Shona','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('so','Somali','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('sq','Albanian','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('sr','Serbian','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ss','Siswati','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('st','Sesotho','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('su','Sudanese','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('sv','Swedish','Yes')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('sw','Swahili','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ta','Tamil','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('te','Tegulu','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('tg','Tajik','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('th','Thai','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ti','Tigrinya','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('tk','Turkmen','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('tl','Tagalog','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('tn','Setswana','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('to','Tonga','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('tr','Turkish','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ts','Tsonga','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('tt','Tatar','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('tw','Twi','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('uk','Ukrainian','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ur','Urdu','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('uz','Uzbek','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('vi','Vietnamese','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('vo','Volapuk','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('wo','Wolof','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('xh','Xhosa','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('yo','Yoruba','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('zh','Chinese','No')");
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('zu','Zulu','No')"); 

?>
