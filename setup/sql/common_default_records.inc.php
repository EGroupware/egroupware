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
    $db->query("insert into config (config_name, config_value) values ('default_tplset', 'default')");
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
    $db->query("insert into config (config_name, config_value) values ('checkfornewversion', 'False')");
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
  }else{
    add_default_server_config();
  }

  include($phpgw_info["server"]["server_root"] . "/setup/inc/default_applications.inc.php");

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

  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('AA','Afar','No')");        
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('AB','Abkhazian','No')");   
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('AF','Afrikaans','No')");   
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('AM','Amharic','No')");     
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('AR','Arabic','No')");      
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('AS','Assamese','No')");    
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('AY','Aymara','No')");      
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('AZ','Azerbaijani','No')"); 
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('BA','Bashkir','No')");     
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('BE','Byelorussian','No')");        
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('BG','Bulgarian','No')");   
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('BH','Bihari','No')");      
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('BI','Bislama','No')");     
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('BN','Bengali / Bangla','No')");    
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('BO','Tibetan','No')");     
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('BR','Breton','No')");      
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('CA','Catalan','No')");     
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('CO','Corsican','No')");    
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('CS','Czech','No')");       
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('CY','Welsh','No')");       
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('DA','Danish','Yes')");     
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('DE','German','Yes')");     
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('DZ','Bhutani','No')");     
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('EL','Greek','No')");       
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('EN','English / American','Yes')"); 
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('EO','Esperanto','No')");   
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ES','Spanish','Yes')");    
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ET','Estonian','No')");    
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('EU','Basque','No')");      
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('FA','Persian','No')");     
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('FI','Finnish','No')");     
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('FJ','Fiji','No')");        
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('FO','Faeroese','No')");    
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('FR','French','Yes')");     
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('FY','Frisian','No')");     
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('GA','Irish','No')");       
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('GD','Gaelic / Scots Gaelic','No')");       
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('GL','Galician','No')");    
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('GN','Guarani','No')");     
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('GU','Gujarati','No')");    
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('HA','Hausa','No')");       
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('HI','Hindi','No')");       
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('HR','Croatian','No')");    
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('HU','Hungarian','No')");   
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('HY','Armenian','No')");    
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('IA','Interlingua','No')"); 
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('IE','Interlingue','No')"); 
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('IK','Inupiak','No')");     
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('IN','Indonesian','No')");  
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('IS','Icelandic','No')");   
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('IT','Italian','Yes')");    
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('IW','Hebrew','No')");      
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('JA','Japanese','No')");    
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('JI','Yiddish','No')");     
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('JW','Javanese','No')");    
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('KA','Georgian','No')");    
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('KK','Kazakh','No')");      
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('KL','Greenlandic','No')"); 
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('KM','Cambodian','No')");   
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('KN','Kannada','No')");     
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('KO','Korean','Yes')");     
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('KS','Kashmiri','No')");    
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('KU','Kurdish','No')");     
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('KY','Kirghiz','No')");     
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('LA','Latin','No')");       
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('LN','Lingala','No')");     
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('LO','Laothian','No')");    
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('LT','Lithuanian','No')");  
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('LV','Latvian / Lettish','No')");   
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('MG','Malagasy','No')");    
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('MI','Maori','No')");       
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('MK','Macedonian','No')");  
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ML','Malayalam','No')");   
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('MN','Mongolian','No')");   
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('MO','Moldavian','No')");   
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('MR','Marathi','No')");     
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('MS','Malay','No')");       
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('MT','Maltese','No')");     
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('MY','Burmese','No')");     
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('NA','Nauru','No')");       
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('NE','Nepali','No')");      
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('NL','Dutch','Yes')");      
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('NO','Norwegian','Yes')");  
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('OC','Occitan','No')");     
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('OM','Oromo / Afan','No')");        
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('OR','Oriya','No')");       
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('PA','Punjabi','No')");     
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('PL','Polish','No')");      
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('PS','Pashto / Pushto','No')");     
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('PT','Portuguese','Yes')"); 
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('QU','Quechua','No')");     
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('RM','Rhaeto-Romance','No')");      
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('RN','Kirundi','No')");     
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('RO','Romanian','No')");    
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('RU','Russian','No')");     
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('RW','Kinyarwanda','No')"); 
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('SA','Sanskrit','No')");    
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('SD','Sindhi','No')");      
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('SG','Sangro','No')");      
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('SH','Serbo-Croatian','No')");      
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('SI','Singhalese','No')");  
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('SK','Slovak','No')");      
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('SL','Slovenian','No')");   
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('SM','Samoan','No')");      
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('SN','Shona','No')");       
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('SO','Somali','No')");      
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('SQ','Albanian','No')");    
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('SR','Serbian','No')");     
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('SS','Siswati','No')");     
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ST','Sesotho','No')");     
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('SU','Sudanese','No')");    
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('SV','Swedish','Yes')");     
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('SW','Swahili','No')");     
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('TA','Tamil','No')");       
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('TE','Tegulu','No')");      
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('TG','Tajik','No')");       
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('TH','Thai','No')");        
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('TI','Tigrinya','No')");    
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('TK','Turkmen','No')");     
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('TL','Tagalog','No')");     
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('TN','Setswana','No')");    
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('TO','Tonga','No')");       
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('TR','Turkish','No')");     
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('TS','Tsonga','No')");      
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('TT','Tatar','No')");       
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('TW','Twi','No')"); 
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('UK','Ukrainian','No')");   
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('UR','Urdu','No')");        
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('UZ','Uzbek','No')");       
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('VI','Vietnamese','No')");  
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('VO','Volapuk','No')");     
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('WO','Wolof','No')");       
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('XH','Xhosa','No')");       
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('YO','Yoruba','No')");      
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ZH','Chinese','No')");     
  @$db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ZU','Zulu','No')");   

?>
