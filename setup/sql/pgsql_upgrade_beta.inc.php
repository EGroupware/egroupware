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

  $test[] = "0.9.1";
  function upgrade0_9_1(){
    global $currentver, $oldversion, $phpgw_info, $db;
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
      $db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('weather', 'Weather', 1, 12, NULL, '".$phpgw_info["server"]["versions"]["phpgwapi"]."')");
      $db->query("INSERT INTO lang (message_id, app_name, lang, content) VALUES( 'weather','Weather','en','weather')");
    $currentver = "0.9.2";
  }

  function v0_9_2to0_9_3update_owner($table,$field){
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

  $test[] = "0.9.2";
  function upgrade0_9_2(){
    global $currentver, $oldversion, $phpgw_info, $db;
	  v0_9_2to0_9_3update_owner("addressbook","ab_owner");
	  v0_9_2to0_9_3update_owner("todo","todo_owner");
	  v0_9_2to0_9_3update_owner("webcal_entry","cal_create_by");
	  v0_9_2to0_9_3update_owner("webcal_entry_user","cal_login");
    $currentver = "0.9.3pre1";
  }

  $test[] = "0.9.3pre1";
  function upgrade0_9_3pre1(){
    global $currentver, $oldversion, $phpgw_info, $db;
	  v0_9_2to0_9_3update_owner("addressbook","ab_owner");
	  v0_9_2to0_9_3update_owner("todo","todo_owner");
	  v0_9_2to0_9_3update_owner("webcal_entry","cal_create_by");
	  v0_9_2to0_9_3update_owner("webcal_entry_user","cal_login");
    $currentver = "0.9.3pre2";
  }

  $test[] = "0.9.3pre2";
  function upgrade0_9_3pre2(){
    global $currentver, $oldversion, $phpgw_info, $db;
	  $db->query("select owner, newsgroup from users_newsgroups");
	  if($db->num_rows()) {
	    while($db->next_record()) {
	      $owner[count($owner)] = $db->f("owner");
	      $newsgroup[count($newsgroup)] = $db->f("newsgroup");
  	  }
  	  for($i=0;$i<count($owner);$i++) {
  	    $db->query("insert into preferences (preference_owner,preference_name,"
  	     ."preference_value,preference_appname) values ('".$owner[$i]."','".$newsgroup[$i]."','True',"
  	     ."'nntp')");
   	  }
  	  $db->query("drop table users_newsgroups");
  	  $db->query("update applications set app_tables='newsgroups' where app_name='nntp'");
  	}
    $currentver = "0.9.3pre3";
  }

  $test[] = "0.9.3pre3";
  function upgrade0_9_3pre3(){
    global $currentver, $oldversion, $phpgw_info, $db;
    $db->query("alter table todo add todo_id_parent int(11) DEFAULT '0' NOT NULL");
    $currentver = "0.9.3pre4";
  }
     
  $test[] = "0.9.3pre4";
  function upgrade0_9_3pre4(){
    global $currentver, $oldversion, $phpgw_info, $db;
   	$db->query("create table temp as select * from config");
   	$db->query("drop table config");
   	$db->query("create table config config_name varchar(255) NOT NULL UNIQUE, config_value varchar(100) NOT NULL");
   	$db->query("insert into config select * from temp");
   	$db->query("drop table config");
    $currentver = "0.9.3pre5";
  }

  $test[] = "0.9.3pre5";
  function upgrade0_9_3pre5(){
    global $currentver, $oldversion, $phpgw_info, $db;
      $db->query("CREATE TABLE categories (
        cat_id          serial,
        account_id      int DEFAULT '0' NOT NULL,
        app_name        varchar(25) NOT NULL,
        cat_name        varchar(150) NOT NULL,
        cat_description text NOT NULL)"
      );
    $currentver = "0.9.3pre6";
  }

  $test[] = "0.9.3pre6";
  function upgrade0_9_3pre6(){
    global $currentver, $oldversion, $phpgw_info, $db;
      $db->query("alter table addressbook add ab_url varchar(255)");
      $db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('transy', 'Translation Management', 0, 13, NULL, '".$phpgw_info["server"]["versions"]["phpgwapi"]."')");
    $currentver = "0.9.3pre7";
  }

  $test[] = "0.9.3pre7";
  function upgrade0_9_3pre7(){
    global $currentver, $oldversion, $phpgw_info, $db;
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
  }

 
  $test[] = "0.9.3pre8";
  function upgrade0_9_3pre8(){
    global $currentver, $oldversion, $phpgw_info, $db;
    $currentver = "0.9.3pre9";
  }

  $test[] = "0.9.3pre9";
  function upgrade0_9_3pre9(){
    global $currentver, $oldversion, $phpgw_info, $db;
    $currentver = "0.9.3pre10";
  }

  $test[] = "0.9.3pre10";
  function upgrade0_9_3pre10(){
    global $currentver, $oldversion, $phpgw_info, $db;
    $currentver = "0.9.3";
  }

  $test[] = "0.9.3";
  function upgrade0_9_3(){
    global $currentver, $oldversion, $phpgw_info, $db;
    $currentver = "0.9.4pre1";
  }

  $test[] = "0.9.4pre1";
  function upgrade0_9_4pre1(){
    global $currentver, $oldversion, $phpgw_info, $db;
         $sql = "CREATE TABLE notes (
                  note_id        serial, 
                  note_owner     int,
                  note_date      int,
                  note_content   text
                )";
        $db->query($sql);
        $db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('notes', 'Notes', 1, 13, NULL, '".$phpgw_info["server"]["versions"]["phpgwapi"]."')");
    $currentver = "0.9.4pre2";
  }

  $test[] = "0.9.4pre2";
  function upgrade0_9_4pre2(){
    global $currentver, $oldversion, $phpgw_info, $db;
	      $db->query("alter table webcal_entry change cal_create_by cal_owner int NOT NULL");
    $currentver = "0.9.4pre3";
  }

  $test[] = "0.9.4pre3";
  function upgrade0_9_4pre3(){
    global $currentver, $oldversion, $phpgw_info, $db;
   	    $sql = "ALTER TABLE todo ADD todo_startdate int not null";
   	    $db->query($sql);
		  	$sql = "ALTER TABLE todo CHANGE todo_datedue todo_enddate int not null";
	      $db->query($sql);	
    $currentver = "0.9.4pre4";
  }

  $test[] = "0.9.4pre4";
  function upgrade0_9_4pre4(){
    global $currentver, $oldversion, $phpgw_info, $db;
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
  }

  $test[] = "0.9.4pre5";
  function upgrade0_9_4pre5(){
    global $currentver, $oldversion, $phpgw_info, $db;
    $currentver = "0.9.4";
  }

  $test[] = "0.9.4";
  function upgrade0_9_4(){
    global $currentver, $oldversion, $phpgw_info, $db;
        $db->query("delete from languages");
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
    $currentver = "0.9.5pre1";
  }

  $test[] = "0.9.5pre1";
  function upgrade0_9_5pre1(){
    global $currentver, $oldversion, $phpgw_info, $db;
        $db->query("DROP TABLE sessions");
        $sql = "create table phpgw_sessions (
          session_id         varchar(255),
          session_lid        varchar(255),
          session_pwd        varchar(255),
          session_ip         varchar(255),
          session_logintime	 int,
          session_dla        int,
          unique(session_id)
        )";
        $db->query($sql);
      
        $sql = "CREATE TABLE phpgw_acl (
          acl_appname       varchar(50),
          acl_location      varchar(255),
          acl_account       int,
          acl_account_type  char(1),
          acl_rights        int
        )";
        $db->query($sql);  
      
        $db->query("DROP TABLE app_sessions");
        $sql = "CREATE TABLE phpgw_app_sessions (
          sessionid	varchar(255) NOT NULL,
          loginid	varchar(20),
          app	        varchar(20),
          content	text
        )";
        $db->query($sql);
      
        $db->query("DROP TABLE access_log");
        $sql = "create table phpgw_access_log (
          sessionid    varchar(255),
          loginid      varchar(30),
          ip           varchar(30),
          li           int,
          lo           varchar(255)
        )";
        $db->query($sql);
    $currentver = "0.9.5pre2";
  }

  $test[] = "0.9.5pre2";
  function upgrade0_9_5pre2(){
    global $currentver, $oldversion, $phpgw_info, $db;
    $currentver = "0.9.5";
  }

  $test[] = "0.9.5";
  function upgrade0_9_5(){
    global $currentver, $oldversion, $phpgw_info, $db;
    $currentver = "0.9.6";
  }

  $test[] = "0.9.6";
  function upgrade0_9_6(){
    global $currentver, $oldversion, $phpgw_info, $db;
    $currentver = "0.9.7pre1";
  }

  $test[] = "0.9.7pre1";
  function upgrade0_9_7pre1(){
    global $currentver, $oldversion, $phpgw_info, $db;
  	    $db2 = $db;
  	    $db3 = $db;
  	    $sql = "CREATE TABLE calendar_entry (
           cal_id		serial,
           cal_owner	int DEFAULT 0 NOT NULL,
           cal_group	varchar(255) NULL,
           cal_datetime	int4,
           cal_mdatetime	int4,
           cal_duration	int DEFAULT 0 NOT NULL,
           cal_priority	int DEFAULT 2,
           cal_type	varchar(10),
           cal_access	varchar(10),
           cal_name	varchar(80) NOT NULL,
           cal_description	text)";
  	    $db->query($sql,__LINE__,__FILE__);
  	    $db->query("SELECT count(*) FROM webcal_entry",__LINE__,__FILE__);
  	    $db->next_record();
  	    if($db->f(0)) {
      	  $db->query("SELECT cal_id,cal_owner,cal_duration,cal_priority,cal_type,cal_access,cal_name,cal_description,cal_id,cal_date,cal_time,cal_mod_date,cal_mod_time FROM webcal_entry ORDER BY cal_id",__LINE__,__FILE__);
      	  while($db->next_record()) {
      	    $cal_id = $db->f("cal_id");
      	    $cal_owner = $db->f("cal_owner");
      	    $cal_duration = $db->f("cal_duration");
      	    $cal_priority = $db->f("cal_priority");
      	    $cal_type = $db->f("cal_type");
      	    $cal_access = $db->f("cal_access");
      	    $cal_name = $db->f("cal_name");
      	    $cal_description = $db->f("cal_description");
      	    $datetime = mktime(intval(strrev(substr(strrev($db->f("cal_time")),4))),intval(strrev(substr(strrev($db->f("cal_time")),2,2))),intval(strrev(substr(strrev($db->f("cal_time")),0,2))),intval(substr($db->f("cal_date"),4,2)),intval(substr($db->f("cal_date"),6,2)),intval(substr($db->f("cal_date"),0,4)));
      	    $moddatetime = mktime(intval(strrev(substr(strrev($db->f("cal_mod_time")),4))),intval(strrev(substr(strrev($db->f("cal_mod_time")),2,2))),intval(strrev(substr(strrev($db->f("cal_mod_time")),0,2))),intval(substr($db->f("cal_mod_date"),4,2)),intval(substr($db->f("cal_mod_date"),6,2)),intval(substr($db->f("cal_mod_date"),0,4)));
      	    $db2->query("SELECT groups FROM webcal_entry_groups WHERE cal_id=".$cal_id,__LINE__,__FILE__);
      	    $db2->next_record();
      	    $cal_group = $db2->f("groups");
      	    $db2->query("INSERT INTO calendar_entry(cal_id,cal_owner,cal_group,cal_datetime,cal_mdatetime,cal_duration,cal_priority,cal_type,cal_access,cal_name,cal_description) "
      		       ."VALUES(".$cal_id.",'".$cal_owner."','".$cal_group."',".$datetime.",".$moddatetime.",".$cal_duration.",".$cal_priority.",'".$cal_type."','".$cal_access."','".$cal_name."','".$cal_description."')",__LINE__,__FILE__);
      	  }
    	  }
  	    $db->query("DROP TABLE webcal_entry_groups");
  	    $db->query("DROP TABLE webcal_entry");

    	  $sql = "CREATE TABLE calendar_entry_user (
    			cal_id		int DEFAULT 0 NOT NULL,
    			cal_login	int DEFAULT 0 NOT NULL,
    			cal_status	char(1) DEFAULT 'A')";
  	    $db->query($sql,__LINE__,__FILE__);
      	$db->query("SELECT count(*) FROM webcal_entry_user",__LINE__,__FILE__);
      	$db->next_record();
      	if($db->f(0)) {
      	  $db->query("SELECT cal_id,cal_login,cal_status FROM webcal_entry_user ORDER BY cal_id",__LINE__,__FILE__);
      	  while($db->next_record()) {
      	    $cal_id = $db->f("cal_id");
      	    $cal_login = $db->f("cal_login");
      	    $cal_status = $db->f("cal_status");
      	    $db2->query("INSERT INTO calendar_entry_user(cal_id,cal_login,cal_status) VALUES(".$cal_id.",".$cal_login.",'".$cal_status."')",__LINE__,__FILE__);
      	  }
  	    }
	      $db->query("DROP TABLE webcal_entry_user",__LINE__,__FILE__);

    	  $sql = "CREATE TABLE calendar_entry_repeats (
    			cal_id		int DEFAULT 0 NOT NULL,
    			cal_type	varchar(20),
    			cal_end		int4,
    			cal_frequency	int default 1,
    			cal_days	char(7))";
      	$db->query($sql,__LINE__,__FILE__);
      	$db->query("SELECT count(*) FROM webcal_entry_repeats",__LINE__,__FILE__);
      	$db->next_record();
      	if($db->f(0)) {
      	  $db->query("SELECT cal_id,cal_type,cal_end,cal_frequency,cal_days FROM webcal_entry_repeats ORDER BY cal_id",__LINE__,__FILE__);
      	  while($db->next_record()) {
      	    $cal_id = $db->f("cal_id");
      	    $cal_type = $db->f("cal_type");
      	    if(isset($db->Record["cal_end"])) {
      	      $enddate = mktime(0,0,0,intval(substr($db->f("cal_end"),4,2)),intval(substr($db->f("cal_end"),6,2)),intval(substr($db->f("cal_end"),0,4)));
      	      $useend = 1;
      	    } else {
      	      $enddate = 0;
      	      $useend = 0;
      	    }
      	    $cal_frequency = $db->f("cal_frequency");
      	    $cal_days = $db->f("cal_days");
      	    $db2->query("INSERT INTO calendar_entry_repeats(cal_id,cal_type,cal_use_end,cal_end,cal_frequency,cal_days) VALUES(".$cal_id.",'".$cal_type."',".$useend.",".$enddate.",".$cal_frequency.",'".$cal_days."')",__LINE__,__FILE__);
      	  }
      	}
      	$db->query("DROP TABLE webcal_entry_repeats",__LINE__,__FILE__);
	      $db->query("UPDATE applications SET app_tables='calendar_entry,calendar_entry_user,calendar_entry_repeats' WHERE app_name='calendar'",__LINE__,__FILE__);

    $currentver = "0.9.7pre2";
  }

  $test[] = "0.9.7pre2";
  function upgrade0_9_7pre2(){
    global $currentver, $oldversion, $phpgw_info, $db;
      	$db2 = $db;
      	$sql = "CREATE TABLE TEMP AS SELECT * FROM calendar_entry";
      	$db->query($sql,__LINE__,__FILE__);
      
      	$sql = "DROP TABLE calendar_entry";
      	$db->query($sql,__LINE__,__FILE__);
      
      	$sql = "CREATE TABLE calendar_entry (
      			cal_id		serial,
      			cal_owner	int DEFAULT 0 NOT NULL,
      			cal_group	varchar(255) NULL,
      			cal_datetime	int4,
      			cal_mdatetime	int4,
      			cal_edatetime	int4,
      			cal_priority	int DEFAULT 2,
      			cal_type	varchar(10),
      			cal_access	varchar(10),
      			cal_name	varchar(80) NOT NULL,
      			cal_description	text)";
      	$db->query($sql,__LINE__,__FILE__);
      	$db->query("SELECT cal_id,cal_owner,cal_group,cal_datetime,cal_mdatetime,cal_duration,cal_priority,cal_type,cal_access,cal_name,cal_description FROM TEMP ORDER BY cal_id",__LINE__,__FILE__);
	while($db->next_record()) {
	  $db2->query("SELECT preference_value FROM preferences WHERE preference_name='tz_offset' AND preference_appname='common' AND preference_owner=".$db->f("cal_owner"),__LINE__,__FILE__);
	  $db2->next_record();
	  $tz = $db2->f("preference_value");
	  $cal_id = $db->f("cal_id");
	  $cal_owner = $db->f("cal_owner");
	  $cal_group = $db->f("cal_group");
	  $cal_datetime = $db->f("cal_datetime") - ((60 * 60) * $tz);
	  $cal_mdatetime = $db->f("cal_mdatetime") - ((60 * 60) * $tz);
	  $cal_edatetime = $cal_datetime + (60 * $db->f("cal_duration"));
	  $cal_priority = $db->f("cal_priority");
	  $cal_type = $db->f("cal_type");
	  $cal_access = $db->f("cal_access");
	  $cal_name = $db->f("cal_name");
	  $cal_description = $db->f("cal_description");
	  $db2->query("INSERT INTO calendar_entry(cal_id,cal_owner,cal_group,cal_datetime,cal_mdatetime,cal_edatetime,cal_priority,cal_type,cal_access,cal_name,cal_description) VALUES(".$cal_id.",".$cal_owner.",'".$cal_group."',".cal_datetime.",".$cal_mdatetime.",".$cal_edatetime.",".$cal_priority.",'".$cal_type."','".$cal_access."','".$cal_name."','".$cal_description."')",__LINE__,__FILE__);
      	}
      	$sql = "DROP TABLE TEMP";
	      $db->query($sql,__LINE__,__FILE__);
    $currentver = "0.9.7pre3";
  }

  $test[] = "0.9.7pre3";
  function upgrade0_9_7pre3(){
    global $currentver, $oldversion, $phpgw_info, $db;
    $currentver = "0.9.7";
  }

  $test[] = "0.9.7";
  function upgrade0_9_7(){
    global $currentver, $oldversion, $phpgw_info, $db;
    $currentver = "0.9.8pre1";
  }

  $test[] = "0.9.8pre1";
  function upgrade0_9_8pre1(){
    global $currentver, $oldversion, $phpgw_info, $db;
        $db->query("select * from preferences order by preference_owner");
        while ($db->next_record()) {
           $t[$db->f("preference_owner")][$db->f("preference_appname")][$db->f("preference_var")] = $db->f("preference_value");
        }

        $db->query("drop table preferences");
        $sql = "create table preferences ( 
          preference_owner       int,
          preference_value       text
        )";
        $db->query($sql);         

        while ($tt = each($t)) {
           $db->query("insert into preferences values ('$tt[0]','" . serialize($tt[1]) . "')");
        }
    $currentver = "0.9.8pre2";
  }

  $test[] = "0.9.8pre2";
  function upgrade0_9_8pre2(){
    global $currentver, $oldversion, $phpgw_info, $db;
        $sql = "CREATE TABLE config (
          config_name     varchar(255) NOT NULL UNIQUE,
          config_value    varchar(100) NOT NULL
        )";
        @$db->query($sql);
    $currentver = "0.9.8pre3";
  }

  $test[] = "0.9.8pre3";
  function upgrade0_9_8pre3(){
    global $currentver, $oldversion, $phpgw_info, $db;

      	$db->query("DROP TABLE phpgw_sessions",__LINE__,__FILE__);
        $sql = "create table phpgw_sessions (
          session_id         varchar(255),
          session_lid        varchar(255),
          session_ip         varchar(255),
          session_logintime  int,
          session_dla        int,
          session_info       text,
          unique(session_id)
        )";
        $db->query($sql);
    $currentver = "0.9.8pre4";
  }

  $test[] = "0.9.8pre4";
  function upgrade0_9_8pre4(){
    global $currentver, $oldversion, $phpgw_info, $db;

    $sql = "create table phpgw_hooks (
              hook_id       serial,
              hook_appname  varchar(255),
              hook_location varchar(255),
              hook_filename varchar(255)
            );";
    $db->query($sql);  
    $currentver = "0.9.8pre5";
  }

  reset ($test);
  while (list ($key, $value) = each ($test)){
    if ($currentver == $value) {
      $ver = "upgrade".ereg_replace("\.","_",$value);
      $ver();
      echo "<table>";
      echo "  <tr bgcolor=\"e6e6e6\">\n";
      echo "    <td>Upgrade from $value to $currentver is completed.</td>\n";
      echo "  </tr>\n";
      echo "</table>";
      if ($tableschanged == True){$tablechanges = True;}
      if (!$prebeta){
        $db->query("update applications set app_version='".$currentver."' where (app_name='admin' or app_name='filemanager' or app_name='addressbook' or app_name='todo' or app_name='calendar' or app_name='email' or app_name='nntp' or app_name='cron_apps' or app_name='notes')");
      }
    }
  }

?>