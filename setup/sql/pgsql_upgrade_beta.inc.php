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

  function v0_9_1to0_9_2(){
    global $currentver, $phpgw_info, $db;
    $didupgrade = True;
    if ($currentver == "0.9.1"){

      $db->query("alter table access_log change lo lo varchar(255)");
      $db->query("alter table addressbook  change ab_id ab_id int(11) NOT NULL auto_increment");
      $db->query("alter table addressbook add ab_company_id int(10) unsigned");
      $db->query("alter table addressbook add ab_title varchar(60)");
      $db->query("alter table addressbook add ab_address2 varchar(60)");

      $sql = "CREATE TABLE customers (
          company_id int(10) unsigned NOT NULL auto_increment,
          company_name varchar(255),
          website varchar(80),
          ftpsite varchar(80),
          industry_type varchar(50),
          status varchar(30),
          software varchar(40),
          lastjobnum int(10) unsigned,
          lastjobfinished date,
          busrelationship varchar(30),
          notes text,
          PRIMARY KEY (company_id)
        );";
      $db->query($sql);  

      $db->query("update lang set lang='da' where lang='dk'");
      $db->query("update lang set lang='ko' where lang='kr'");

      $db->query("update preferences set preference_name='da' where preference_name='dk'");
      $db->query("update preferences set preference_name='ko' where preference_name='kr'");

	//add weather support
      $db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('weather', 'Weather', 1, 12, NULL, '".$phpgw_info["server"]["version"]."')");
      $db->query("INSERT INTO lang (message_id, app_name, lang, content) VALUES( 'weather','Weather','en','weather')");

      echo "  <tr bgcolor=\"e6e6e6\">\n";
      echo "    <td>Upgrade from 0.9.1 to 0.9.2 is completed.</td>\n";
      echo "  </tr>\n";
      $currentver = "0.9.2";
    }
  }

  function update_owner($table,$field){
    global $db;
    $db->query("select distinct($field) from $table");
    if ($db->num_rows()) {
      while($db->next_record()) {
	$owner[count($owner)] = $db->f($field);
      }
      for($i=0;$i<count($owner);$i++) {
        $db->query("select account_id from accounts where account_lid='".$owner[$i]."'");
	$db->next_record();
	$db->query("update $table set $field=".$db->f("account_id")." where $field='".$owner[$i]."'");
      }
    }
    $db->query("alter table $table change $field $field int(11) NOT NULL");
  }

  function v0_9_2to0_9_3()
  {
    global $currentver, $phpgw_info, $db;

    // The 0.9.3pre1 is only temp until release
    if ($currentver == "0.9.2" || ereg ("^0\.9\.3pre", $currentver)){
       if ($currentver == "0.9.2" || $currentver == "0.9.3pre1") {
	      update_owner("addressbook","ab_owner");
      	update_owner("todo","todo_owner");
      	update_owner("webcal_entry","cal_create_by");
      	update_owner("webcal_entry_user","cal_login");
      	$currentver = "0.9.3pre2";
        update_version_table();
     }
       if ($currentver == "0.9.3pre2") {
      	$db->query("select owner, newsgroup from users_newsgroups");
      	if ($db->num_rows()) {
   	   while($db->next_record()) {
	        $owner[count($owner)] = $db->f("owner");
   	     $newsgroup[count($newsgroup)] = $db->f("newsgroup");
 	     }
   	   for ($i=0;$i<count($owner);$i++) {
	         $db->query("insert into preferences (preference_owner,preference_name,"
		               ."preference_value,preference_appname) values ('".$owner[$i]."','".$newsgroup[$i]."','True',"
		               ."'nntp')");
    	  }
 	     $db->query("drop table users_newsgroups");
   	   $db->query("update applications set app_tables='newsgroups' where app_name='nntp'");
  	}
        $currentver = "0.9.3pre3";
        update_version_table();
    }

    if ($currentver == "0.9.3pre3") {
   	$db->query("alter table todo add todo_id_parent int DEFAULT 0 NOT NULL");
       $currentver = "0.9.3pre4";
       update_version_table();
   }

    if ($currentver == "0.9.3pre4") {
   	$db->query("create table temp as select * from config");
   	$db->query("drop table config");
   	$db->query("create table config config_name varchar(255) NOT NULL UNIQUE, config_value varchar(100) NOT NULL");
   	$db->query("insert into config select * from temp");
   	$db->query("drop table config");
       $currentver = "0.9.3pre5";
       update_version_table();
    }

    if ($currentver == "0.9.3pre5") {
       $db->query("CREATE TABLE categories (
                    cat_id          serial,
                    account_id      int DEFAULT '0' NOT NULL,
                    app_name        varchar(25) NOT NULL,
                    cat_name        varchar(150) NOT NULL,
                    cat_description text NOT NULL)"
                 );
       $currentver = "0.9.3pre6";
       update_version_table();
    }

    if ($currentver == "0.9.3pre6") {
       $db->query("alter table addressbook add ab_url varchar(255)");
       $db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('transy', 'Translation Management', 0, 13, NULL, '".$phpgw_info["server"]["version"]."')");
       $currentver = "0.9.3pre7";
       update_version_table();
    }
    
    if ($currentver == "0.9.3pre7") {
       $db->query("CREATE TABLE languages (
                    lang_id         varchar(2) NOT NULL,
                    lang_name       varchar(50) NOT NULL,
                    available       varchar(3) NOT NULL DEFAULT 'No'
                 )");
                 
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

      $currentver = "0.9.3pre8";
      update_version_table();
    }

      if ($currentver == "0.9.3pre8") {
         $currentver = "0.9.3pre9";
         update_version_table();
      }

      if ($currentver == "0.9.3pre9") {
         $currentver = "0.9.3pre10";
         update_version_table();
      }

      
    echo "  <tr bgcolor=\"e6e6e6\">\n";
    echo "    <td>Upgrade from 0.9.2 to $currentver is completed.</td>\n";
    echo "  </tr>\n";
    }
  }

  function v0_9_3to0_9_4(){
    global $currentver, $phpgw_info, $db;

    // The 0.9.3pre1 is only temp until release
    if ($currentver == "0.9.3" || ereg ("^0\.9\.4pre", $currentver)){
      if ($currentver == "0.9.3") {
         $currentver = "0.9.4pre1";
         update_version_table();
      }
      if ($currentver == "0.9.4pre1") {
         $sql = "CREATE TABLE notes (
                  note_id        serial, 
                  note_owner     int,
                  note_date      int,
                  note_content   text
                )";
        $db->query($sql);
        $db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('notes', 'Notes', 1, 13, NULL, '".$phpgw_info["server"]["version"]."')");
        $currentver = "0.9.4pre2";
        update_version_table();
      }
      if ($currentver == "0.9.4pre2") {
	      $db->query("alter table webcal_entry change cal_create_by cal_owner int NOT NULL");
        $currentver = "0.9.4pre3";
        update_version_table();
      }
      if ($currentver == "0.9.4pre3") {
   	    $sql = "ALTER TABLE todo ADD todo_startdate int not null";
   	    $db->query($sql);
		  	$sql = "ALTER TABLE todo CHANGE todo_datedue todo_enddate int not null";
	      $db->query($sql);	
        $currentver = "0.9.4pre4";
	      update_version_table();
      }
      if ($currentver == "0.9.4pre4") {
   	    $sql = "DROP TABLE sessions";
   	    $db->query($sql);
        $sql = "create table sessions (
          session_id         varchar(255),
          session_lid        varchar(255),
          session_pwd        varchar(255),
          session_ip         varchar(255),
          session_logintime	 int,
          session_dla        int,
          unique(session_id)
        )";
        $db->query($sql);
        $currentver = "0.9.4pre5";
	      update_version_table();
      }
      if ($currentver == "0.9.4pre5") {
        $currentver = "0.9.4";
        update_version_table();
      }
      echo "  <tr bgcolor=\"e6e6e6\">\n";
      echo "    <td>Upgrade from 0.9.3 to $currentver is completed.</td>\n";
      echo "  </tr>\n";
    }
  }
  function v0_9_4to0_9_5(){
    global $currentver, $phpgw_info, $db;

    // The 0.9.4pre1 is only temp until release
    if ($currentver == "0.9.4" || ereg ("^0\.9\.5pre", $currentver)){
      if ($currentver == "0.9.4") {
        $currentver = "0.9.4pre1";
        update_version_table();
      }
      echo "  <tr bgcolor=\"e6e6e6\">\n";
      echo "    <td>Upgrade from 0.9.4 to $currentver is completed.</td>\n";
      echo "  </tr>\n";
    }
  }

  v0_9_1to0_9_2();
  v0_9_2to0_9_3();
  v0_9_3to0_9_4();
//  v0_9_4to0_9_5();
  
?>
