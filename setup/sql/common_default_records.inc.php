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
    global $phpgw_info, $phpgw_setup;
    $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('template_set', 'user_choice')");
    $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('temp_dir', '/path/to/tmp')");
    $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('files_dir', '/path/to/dir/phpgroupware/files')");
    $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('encryptkey', 'change this phrase 2 something else')");
    $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('site_title', 'phpGroupWare')");
    $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('hostname', 'local.machine.name')");
    $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('webserver_url', 'http://www.domain.com/phpgroupware')");
    $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('auth_type', 'sql')");
    $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('ldap_host', 'localhost')");
    $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('ldap_context', 'ou=People,dc=my-domain,dc=com')");
    $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('ldap_encryption_type', 'DES')");
    $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('ldap_root_dn', 'cn=Manager,dc=my-domain,dc=com')");
    $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('ldap_root_pw', 'secret')");
    $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('usecookies', 'True')");
    $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('mail_server', 'localhost')");
    $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('mail_server_type', 'imap')");
    $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('imap_server_type', 'Cyrus')");
    $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('mail_suffix', 'yourdomain.com')");         
    $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('mail_login_type', 'standard')");
    $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('smtp_server', 'localhost')");
    $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('smtp_port', '25')");
    $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('nntp_server', 'yournewsserver.com')");
    $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('nntp_port', '119')");
    $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('nntp_sender', 'complaints@yourserver.com')");
    $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('nntp_organization', 'phpGroupWare')");
    $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('nntp_admin', 'admin@yourserver.com')");
    $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('nntp_login_username', '')");
    $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('nntp_login_password', '')");
    $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('charset', 'iso-8859-1')");
    $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('default_ftp_server', 'localhost')");
    $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('httpproxy_server', '')");
    $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('httpproxy_port', '')");
    $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('showpoweredbyon', 'bottom')");
    $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('htmlcompliant', 'False')");
    $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('checkfornewversion', 'False')");
    $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('freshinstall', 'True')");
  }

  if ($useglobalconfigsettings == "on"){
    if (is_file($basedir)){
      include (PHPGW_INCLUDE_ROOT."/globalconfig.inc.php");
      $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('template_set', '".$phpgw_info["server"]["template_set"]."')");
      $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('temp_dir', '".$phpgw_info["server"]["temp_dir"]."')");
      $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('files_dir', '".$phpgw_info["server"]["files_dir"]."')");
      $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('encryptkey', '".$phpgw_info["server"]["encryptkey"]."')");
      $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('site_title', '".$phpgw_info["server"]["site_title"]."')");
      $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('hostname', '".$phpgw_info["server"]["hostname"]."')");
      $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('webserver_url', '".$phpgw_info["server"]["webserver_url"].")");
      $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('auth_type', '".$phpgw_info["server"]["auth_type"]."')");
      $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('ldap_host', '".$phpgw_info["server"]["ldap_host"]."')");
      $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('ldap_context', '".$phpgw_info["server"]["ldap_context"]."')");
      $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('usecookies', '".$phpgw_info["server"]["usecookies"]."')");
      $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('mail_server', '".$phpgw_info["server"]["mail_server"]."')");
      $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('mail_server_type', '".$phpgw_info["server"]["mail_server_type"]."')");
      $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('imap_server_type', '".$phpgw_info["server"]["imap_server_type"]."')");
      $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('mail_suffix', '".$phpgw_info["server"]["mail_suffix"]."')");         
      $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('mail_login_type', '".$phpgw_info["server"]["mail_login_type"]."')");
      $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('smtp_server', '".$phpgw_info["server"]["smtp_server"]."')");
      $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('smtp_port', '".$phpgw_info["server"]["smtp_port"]."')");
      $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('nntp_server', '".$phpgw_info["server"]["nntp_server"]."')");
      $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('nntp_port', '".$phpgw_info["server"]["nntp_port"]."')");
      $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('nntp_sender', '".$phpgw_info["server"]["nntp_sender"]."')");
      $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('nntp_organization', '".$phpgw_info["server"]["nntp_organization"]."')");
      $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('nntp_admin', '".$phpgw_info["server"]["nntp_admin"]."')");
      $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('nntp_login_username', '".$phpgw_info["server"]["nntp_login_username"]."')");
      $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('nntp_login_password', '".$phpgw_info["server"]["nntp_login_password"]."')");
      $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('charset', '".$phpgw_info["server"]["charset"]."')");
      $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('default_ftp_server', '".$phpgw_info["server"]["default_ftp_server"]."')");
      $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('httpproxy_server', '".$phpgw_info["server"]["httpproxy_server"]."')");
      $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('httpproxy_port', '".$phpgw_info["server"]["httpproxy_port"]."')");
      $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('showpoweredbyon', '".$phpgw_info["server"]["showpoweredbyon"]."')");
      $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('checkfornewversion', '".$phpgw_info["server"]["checkfornewversion"]."')");
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

  include(PHPGW_SERVER_ROOT . "/setup/sql/default_applications.inc.php");
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

  $defaultprefs = 'a:5:{s:6:"common";a:1:{s:0:"";s:2:"en";}s:11:"addressbook";a:1:{s:0:"";s:4:"True";}i:8;a:1:{s:0:"";s:13:"workdaystarts";}i:15;a:1:{s:0:"";s:11:"workdayends";}s:6:"Monday";a:1:{s:0:"";s:13:"weekdaystarts";}}';
	$accountid = mt_rand (100, 600000);
  $sql = "insert into phpgw_accounts";
  $sql .= "(account_id, account_lid, account_type, account_pwd, account_firstname, account_lastname, account_lastpwd_change, account_status)";
  $sql .= "values (".$accountid.", 'demo', 'u', '81dc9bdb52d04dc20036dbd8313ed055', 'Demo', 'Account', ".time().", 'A')";
  $phpgw_setup->db->query($sql);
  $phpgw_setup->db->query("insert into phpgw_preferences (preference_owner, preference_value) values ('4', '$defaultprefs')");
  $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('phpgw_group', '".$defaultgroupid."', $accountid,  1)");
  $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('phpgw_group', '".$admingroupid."', $accountid, 1)");
  $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('admin', 'run', $accountid, 1)");
  $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('addressbook', 'run', $accountid, 1)");
  $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('filemanager', 'run', $accountid, 1)");
  $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('calendar', 'run', $accountid, 1)");
  $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('email', 'run', $accountid, 1)");
  $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('notes', 'run', $accountid, 1)");
  $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('nntp', 'run', $accountid, 1)");
  $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('todo', 'run', $accountid, 1)");
  $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('transy', 'run', $accountid, 1)");
  $phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('manual', 'run', $accountid, 1)");

  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('aa','Afar','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ab','Abkhazian','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('af','Afrikaans','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('am','Amharic','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ar','Arabic','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('as','Assamese','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ay','Aymara','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('az','Azerbaijani','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ba','Bashkir','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('be','Byelorussian','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('bg','Bulgarian','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('bh','Bihari','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('bi','Bislama','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('bn','Bengali / Bangla','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('bo','Tibetan','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('br','Breton','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ca','Catalan','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('co','Corsican','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('cs','Czech','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('cy','Welsh','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('da','Danish','Yes')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('de','German','Yes')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('dz','Bhutani','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('el','Greek','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('en','English / American','Yes')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('eo','Esperanto','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('es','Spanish','Yes')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('et','Estonian','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('eu','Basque','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('fa','Persian','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('fi','Finnish','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('fj','Fiji','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('fo','Faeroese','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('fr','French','Yes')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('fy','Frisian','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ga','Irish','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('gd','Gaelic / Scots Gaelic','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('gl','Galician','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('gn','Guarani','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('gu','Gujarati','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ha','Hausa','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('hi','Hindi','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('hr','Croatian','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('hu','Hungarian','Yes')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('hy','Armenian','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ia','Interlingua','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ie','Interlingue','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ik','Inupiak','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('in','Indonesian','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('is','Icelandic','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('it','Italian','Yes')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('iw','Hebrew','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ja','Japanese','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ji','Yiddish','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('jw','Javanese','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ka','Georgian','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('kk','Kazakh','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('kl','Greenlandic','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('km','Cambodian','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('kn','Kannada','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ko','Korean','Yes')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ks','Kashmiri','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ku','Kurdish','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ky','Kirghiz','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('la','Latin','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ln','Lingala','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('lo','Laothian','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('lt','Lithuanian','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('lv','Latvian / Lettish','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('mg','Malagasy','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('mi','Maori','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('mk','Macedonian','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ml','Malayalam','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('mn','Mongolian','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('mo','Moldavian','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('mr','Marathi','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ms','Malay','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('mt','Maltese','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('my','Burmese','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('na','Nauru','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ne','Nepali','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('nl','Dutch','Yes')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('no','Norwegian','Yes')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('oc','Occitan','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('om','Oromo / Afan','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('or','Oriya','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('pa','Punjabi','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('pl','Polish','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ps','Pashto / Pushto','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('pt','Portuguese','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('qu','Quechua','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('rm','Rhaeto-Romance','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('rn','Kirundi','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ro','Romanian','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ru','Russian','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('rw','Kinyarwanda','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('sa','Sanskrit','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('sd','Sindhi','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('sg','Sangro','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('sh','Serbo-Croatian','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('si','Singhalese','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('sk','Slovak','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('sl','Slovenian','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('sm','Samoan','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('sn','Shona','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('so','Somali','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('sq','Albanian','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('sr','Serbian','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ss','Siswati','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('st','Sesotho','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('su','Sudanese','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('sv','Swedish','Yes')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('sw','Swahili','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ta','Tamil','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('te','Tegulu','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('tg','Tajik','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('th','Thai','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ti','Tigrinya','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('tk','Turkmen','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('tl','Tagalog','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('tn','Setswana','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('to','Tonga','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('tr','Turkish','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ts','Tsonga','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('tt','Tatar','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('tw','Twi','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('uk','Ukrainian','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ur','Urdu','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('uz','Uzbek','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('vi','Vietnamese','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('vo','Volapuk','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('wo','Wolof','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('xh','Xhosa','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('yo','Yoruba','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('zh','Chinese','No')");
  @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('zu','Zulu','No')"); 
?>
