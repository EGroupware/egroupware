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
    global $phpgw_info, $phpgw_setup;
      $phpgw_setup->db->query("alter table access_log change lo lo varchar(255)");
      $phpgw_setup->db->query("alter table addressbook  change ab_id ab_id int(11) NOT NULL auto_increment");
      $phpgw_setup->db->query("alter table addressbook add ab_company_id int(10) unsigned");
      $phpgw_setup->db->query("alter table addressbook add ab_title varchar(60)");
      $phpgw_setup->db->query("alter table addressbook add ab_address2 varchar(60)");

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
      $phpgw_setup->db->query($sql);  

      $phpgw_setup->db->query("update lang set lang='da' where lang='dk'");
      $phpgw_setup->db->query("update lang set lang='ko' where lang='kr'");

      $phpgw_setup->db->query("update preferences set preference_name='da' where preference_name='dk'");
      $phpgw_setup->db->query("update preferences set preference_name='ko' where preference_name='kr'");

	    //add weather support
      $phpgw_setup->db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('weather', 'Weather', 1, 12, NULL, '".$phpgw_info["server"]["versions"]["phpgwapi"]."')");
      $phpgw_setup->db->query("INSERT INTO lang (message_id, app_name, lang, content) VALUES( 'weather','Weather','en','weather')");
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.2";
  }

  function v0_9_2to0_9_3update_owner($table,$field){
    global $phpgw_info, $phpgw_setup;
    $phpgw_setup->db->query("select distinct($field) from $table");
    if ($phpgw_setup->db->num_rows()) {
      while($phpgw_setup->db->next_record()) {
	      $owner[count($owner)] = $phpgw_setup->db->f($field);
      }
      for($i=0;$i<count($owner);$i++) {
        $phpgw_setup->db->query("select account_id from accounts where account_lid='".$owner[$i]."'");
	      $phpgw_setup->db->next_record();
	      $phpgw_setup->db->query("update $table set $field=".$phpgw_setup->db->f("account_id")." where $field='".$owner[$i]."'");
      }
    }
    $phpgw_setup->db->query("alter table $table change $field $field int(11) NOT NULL");
  }

  $test[] = "0.9.2";
  function upgrade0_9_2(){
    global $phpgw_info, $phpgw_setup;
	  v0_9_2to0_9_3update_owner("addressbook","ab_owner");
	  v0_9_2to0_9_3update_owner("todo","todo_owner");
	  v0_9_2to0_9_3update_owner("webcal_entry","cal_create_by");
	  v0_9_2to0_9_3update_owner("webcal_entry_user","cal_login");
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.3pre1";
  }

  $test[] = "0.9.3pre1";
  function upgrade0_9_3pre1(){
    global $phpgw_info, $phpgw_setup;
	  v0_9_2to0_9_3update_owner("addressbook","ab_owner");
	  v0_9_2to0_9_3update_owner("todo","todo_owner");
	  v0_9_2to0_9_3update_owner("webcal_entry","cal_create_by");
	  v0_9_2to0_9_3update_owner("webcal_entry_user","cal_login");
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.3pre2";
  }

  $test[] = "0.9.3pre2";
  function upgrade0_9_3pre2(){
    global $phpgw_info, $phpgw_setup;
	  $phpgw_setup->db->query("select owner, newsgroup from users_newsgroups");
	  if($phpgw_setup->db->num_rows()) {
	    while($phpgw_setup->db->next_record()) {
	      $owner[count($owner)] = $phpgw_setup->db->f("owner");
	      $newsgroup[count($newsgroup)] = $phpgw_setup->db->f("newsgroup");
  	  }
  	  for($i=0;$i<count($owner);$i++) {
  	    $phpgw_setup->db->query("insert into preferences (preference_owner,preference_name,"
  	     ."preference_value,preference_appname) values ('".$owner[$i]."','".$newsgroup[$i]."','True',"
  	     ."'nntp')");
   	  }
  	  $phpgw_setup->db->query("drop table users_newsgroups");
  	  $phpgw_setup->db->query("update applications set app_tables='newsgroups' where app_name='nntp'");
  	}
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.3pre3";
  }

  $test[] = "0.9.3pre3";
  function upgrade0_9_3pre3(){
    global $phpgw_info, $phpgw_setup;
    $phpgw_setup->db->query("alter table todo add todo_id_parent int(11) DEFAULT '0' NOT NULL");
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.3pre4";
  }
     
  $test[] = "0.9.3pre4";
  function upgrade0_9_3pre4(){
    global $phpgw_info, $phpgw_setup;
   	$phpgw_setup->db->query("create table temp as select * from config");
   	$phpgw_setup->db->query("drop table config");
   	$phpgw_setup->db->query("create table config config_name varchar(255) NOT NULL UNIQUE, config_value varchar(100) NOT NULL");
   	$phpgw_setup->db->query("insert into config select * from temp");
   	$phpgw_setup->db->query("drop table temp");
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.3pre5";
  }

  $test[] = "0.9.3pre5";
  function upgrade0_9_3pre5(){
    global $phpgw_info, $phpgw_setup;
      $phpgw_setup->db->query("CREATE TABLE categories (
        cat_id          serial,
        account_id      int DEFAULT '0' NOT NULL,
        app_name        varchar(25) NOT NULL,
        cat_name        varchar(150) NOT NULL,
        cat_description text NOT NULL)"
      );
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.3pre6";
  }

  $test[] = "0.9.3pre6";
  function upgrade0_9_3pre6(){
    global $phpgw_info, $phpgw_setup;
      $phpgw_setup->db->query("alter table addressbook add ab_url varchar(255)");
      $phpgw_setup->db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('transy', 'Translation Management', 0, 13, NULL, '".$phpgw_info["server"]["versions"]["phpgwapi"]."')");
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.3pre7";
  }

  $test[] = "0.9.3pre7";
  function upgrade0_9_3pre7(){
    global $phpgw_info, $phpgw_setup;
       $phpgw_setup->db->query("CREATE TABLE languages (
                    lang_id         varchar(2) NOT NULL,
                    lang_name       varchar(50) NOT NULL,
                    available       varchar(3) NOT NULL DEFAULT 'No'
                 )");
                 
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('AA','Afar','No')");        
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('AB','Abkhazian','No')");   
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('AF','Afrikaans','No')");   
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('AM','Amharic','No')");     
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('AR','Arabic','No')");      
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('AS','Assamese','No')");    
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('AY','Aymara','No')");      
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('AZ','Azerbaijani','No')"); 
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('BA','Bashkir','No')");     
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('BE','Byelorussian','No')");        
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('BG','Bulgarian','No')");   
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('BH','Bihari','No')");      
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('BI','Bislama','No')");     
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('BN','Bengali / Bangla','No')");    
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('BO','Tibetan','No')");     
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('BR','Breton','No')");      
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('CA','Catalan','No')");     
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('CO','Corsican','No')");    
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('CS','Czech','No')");       
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('CY','Welsh','No')");       
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('DA','Danish','Yes')");     
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('DE','German','Yes')");     
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('DZ','Bhutani','No')");     
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('EL','Greek','No')");       
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('EN','English / American','Yes')"); 
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('EO','Esperanto','No')");   
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ES','Spanish','Yes')");    
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ET','Estonian','No')");    
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('EU','Basque','No')");      
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('FA','Persian','No')");     
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('FI','Finnish','No')");     
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('FJ','Fiji','No')");        
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('FO','Faeroese','No')");    
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('FR','French','Yes')");     
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('FY','Frisian','No')");     
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('GA','Irish','No')");       
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('GD','Gaelic / Scots Gaelic','No')");       
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('GL','Galician','No')");    
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('GN','Guarani','No')");     
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('GU','Gujarati','No')");    
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('HA','Hausa','No')");       
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('HI','Hindi','No')");       
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('HR','Croatian','No')");    
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('HU','Hungarian','Yes')");   
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('HY','Armenian','No')");    
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('IA','Interlingua','No')"); 
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('IE','Interlingue','No')"); 
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('IK','Inupiak','No')");     
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('IN','Indonesian','No')");  
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('IS','Icelandic','No')");   
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('IT','Italian','Yes')");    
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('IW','Hebrew','No')");      
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('JA','Japanese','No')");    
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('JI','Yiddish','No')");     
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('JW','Javanese','No')");    
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('KA','Georgian','No')");    
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('KK','Kazakh','No')");      
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('KL','Greenlandic','No')"); 
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('KM','Cambodian','No')");   
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('KN','Kannada','No')");     
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('KO','Korean','Yes')");     
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('KS','Kashmiri','No')");    
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('KU','Kurdish','No')");     
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('KY','Kirghiz','No')");     
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('LA','Latin','No')");       
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('LN','Lingala','No')");     
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('LO','Laothian','No')");    
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('LT','Lithuanian','No')");  
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('LV','Latvian / Lettish','No')");   
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('MG','Malagasy','No')");    
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('MI','Maori','No')");       
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('MK','Macedonian','No')");  
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ML','Malayalam','No')");   
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('MN','Mongolian','No')");   
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('MO','Moldavian','No')");   
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('MR','Marathi','No')");     
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('MS','Malay','No')");       
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('MT','Maltese','No')");     
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('MY','Burmese','No')");     
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('NA','Nauru','No')");       
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('NE','Nepali','No')");      
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('NL','Dutch','Yes')");      
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('NO','Norwegian','Yes')");  
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('OC','Occitan','No')");     
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('OM','Oromo / Afan','No')");        
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('OR','Oriya','No')");       
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('PA','Punjabi','No')");     
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('PL','Polish','No')");      
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('PS','Pashto / Pushto','No')");     
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('PT','Portuguese','Yes')"); 
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('QU','Quechua','No')");     
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('RM','Rhaeto-Romance','No')");      
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('RN','Kirundi','No')");     
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('RO','Romanian','No')");    
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('RU','Russian','No')");     
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('RW','Kinyarwanda','No')"); 
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('SA','Sanskrit','No')");    
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('SD','Sindhi','No')");      
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('SG','Sangro','No')");      
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('SH','Serbo-Croatian','No')");      
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('SI','Singhalese','No')");  
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('SK','Slovak','No')");      
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('SL','Slovenian','No')");   
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('SM','Samoan','No')");      
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('SN','Shona','No')");       
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('SO','Somali','No')");      
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('SQ','Albanian','No')");    
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('SR','Serbian','No')");     
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('SS','Siswati','No')");     
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ST','Sesotho','No')");     
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('SU','Sudanese','No')");    
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('SV','Swedish','Yes')");     
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('SW','Swahili','No')");     
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('TA','Tamil','No')");       
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('TE','Tegulu','No')");      
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('TG','Tajik','No')");       
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('TH','Thai','No')");        
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('TI','Tigrinya','No')");    
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('TK','Turkmen','No')");     
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('TL','Tagalog','No')");     
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('TN','Setswana','No')");    
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('TO','Tonga','No')");       
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('TR','Turkish','No')");     
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('TS','Tsonga','No')");      
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('TT','Tatar','No')");       
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('TW','Twi','No')"); 
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('UK','Ukrainian','No')");   
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('UR','Urdu','No')");        
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('UZ','Uzbek','No')");       
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('VI','Vietnamese','No')");  
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('VO','Volapuk','No')");     
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('WO','Wolof','No')");       
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('XH','Xhosa','No')");       
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('YO','Yoruba','No')");      
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ZH','Chinese','No')");     
      @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('ZU','Zulu','No')");   
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.3pre8";
  }

 
  $test[] = "0.9.3pre8";
  function upgrade0_9_3pre8(){
    global $phpgw_info, $phpgw_setup;
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.3pre9";
  }

  $test[] = "0.9.3pre9";
  function upgrade0_9_3pre9(){
    global $phpgw_info, $phpgw_setup;
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.3pre10";
  }

  $test[] = "0.9.3pre10";
  function upgrade0_9_3pre10(){
    global $phpgw_info, $phpgw_setup;
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.3";
  }

  $test[] = "0.9.3";
  function upgrade0_9_3(){
    global $phpgw_info, $phpgw_setup;
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.4pre1";
  }

  $test[] = "0.9.4pre1";
  function upgrade0_9_4pre1(){
    global $phpgw_info, $phpgw_setup;
         $sql = "CREATE TABLE notes (
                  note_id        serial, 
                  note_owner     int,
                  note_date      int,
                  note_content   text
                )";
        $phpgw_setup->db->query($sql);
        $phpgw_setup->db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('notes', 'Notes', 1, 13, NULL, '".$phpgw_info["server"]["versions"]["phpgwapi"]."')");
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.4pre2";
  }

  $test[] = "0.9.4pre2";
  function upgrade0_9_4pre2(){
    global $phpgw_info, $phpgw_setup;
	      $phpgw_setup->db->query("alter table webcal_entry change cal_create_by cal_owner int NOT NULL");
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.4pre3";
  }

  $test[] = "0.9.4pre3";
  function upgrade0_9_4pre3(){
    global $phpgw_info, $phpgw_setup;
   	    $sql = "ALTER TABLE todo ADD todo_startdate int not null";
   	    $phpgw_setup->db->query($sql);
		  	$sql = "ALTER TABLE todo CHANGE todo_datedue todo_enddate int not null";
	      $phpgw_setup->db->query($sql);	
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.4pre4";
  }

  $test[] = "0.9.4pre4";
  function upgrade0_9_4pre4(){
    global $phpgw_info, $phpgw_setup;
   	    $sql = "DROP TABLE sessions";
   	    $phpgw_setup->db->query($sql);
        $sql = "create table sessions (
          session_id         varchar(255),
          session_lid        varchar(255),
          session_pwd        varchar(255),
          session_ip         varchar(255),
          session_logintime	 int,
          session_dla        int,
          unique(session_id)
        )";
        $phpgw_setup->db->query($sql);
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.4pre5";
  }

  $test[] = "0.9.4pre5";
  function upgrade0_9_4pre5(){
    global $phpgw_info, $phpgw_setup;
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.4";
  }

  $test[] = "0.9.4";
  function upgrade0_9_4(){
    global $phpgw_info, $phpgw_setup;
        $phpgw_setup->db->query("delete from languages");
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
        @$phpgw_setup->db->query("INSERT INTO languages (lang_id, lang_name, available) values ('pt','Portuguese','Yes')");
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
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.5pre1";
  }

  $test[] = "0.9.5pre1";
  function upgrade0_9_5pre1(){
    global $phpgw_info, $phpgw_setup;
        $phpgw_setup->db->query("DROP TABLE sessions");
        $sql = "create table phpgw_sessions (
          session_id         varchar(255),
          session_lid        varchar(255),
          session_pwd        varchar(255),
          session_ip         varchar(255),
          session_logintime	 int,
          session_dla        int,
          unique(session_id)
        )";
        $phpgw_setup->db->query($sql);
      
        $sql = "CREATE TABLE phpgw_acl (
          acl_appname       varchar(50),
          acl_location      varchar(255),
          acl_account       int,
          acl_account_type  char(1),
          acl_rights        int
        )";
        $phpgw_setup->db->query($sql);  
      
        $phpgw_setup->db->query("DROP TABLE app_sessions");
        $sql = "CREATE TABLE phpgw_app_sessions (
          sessionid	varchar(255) NOT NULL,
          loginid	varchar(20),
          app	        varchar(20),
          content	text
        )";
        $phpgw_setup->db->query($sql);
      
        $phpgw_setup->db->query("DROP TABLE access_log");
        $sql = "create table phpgw_access_log (
          sessionid    varchar(255),
          loginid      varchar(30),
          ip           varchar(30),
          li           int,
          lo           varchar(255)
        )";
        $phpgw_setup->db->query($sql);
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.5pre2";
  }

  $test[] = "0.9.5pre2";
  function upgrade0_9_5pre2(){
    global $phpgw_info, $phpgw_setup;
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.5";
  }

  $test[] = "0.9.5";
  function upgrade0_9_5(){
    global $phpgw_info, $phpgw_setup;
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.6";
  }

  $test[] = "0.9.6";
  function upgrade0_9_6(){
    global $phpgw_info, $phpgw_setup;
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.7pre1";
  }

  $test[] = "0.9.7pre1";
  function upgrade0_9_7pre1(){
    global $phpgw_info, $phpgw_setup;
  	    $db2 = $phpgw_setup->db;
  	    $phpgw_setup->db3 = $phpgw_setup->db;
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
  	    $phpgw_setup->db->query($sql,__LINE__,__FILE__);
  	    $phpgw_setup->db->query("SELECT count(*) FROM webcal_entry",__LINE__,__FILE__);
  	    $phpgw_setup->db->next_record();
  	    if($phpgw_setup->db->f(0)) {
      	  $phpgw_setup->db->query("SELECT cal_id,cal_owner,cal_duration,cal_priority,cal_type,cal_access,cal_name,cal_description,cal_id,cal_date,cal_time,cal_mod_date,cal_mod_time FROM webcal_entry ORDER BY cal_id",__LINE__,__FILE__);
      	  while($phpgw_setup->db->next_record()) {
      	    $cal_id = $phpgw_setup->db->f("cal_id");
      	    $cal_owner = $phpgw_setup->db->f("cal_owner");
      	    $cal_duration = $phpgw_setup->db->f("cal_duration");
      	    $cal_priority = $phpgw_setup->db->f("cal_priority");
      	    $cal_type = $phpgw_setup->db->f("cal_type");
      	    $cal_access = $phpgw_setup->db->f("cal_access");
      	    $cal_name = $phpgw_setup->db->f("cal_name");
      	    $cal_description = $phpgw_setup->db->f("cal_description");
      	    $datetime = mktime(intval(strrev(substr(strrev($phpgw_setup->db->f("cal_time")),4))),intval(strrev(substr(strrev($phpgw_setup->db->f("cal_time")),2,2))),intval(strrev(substr(strrev($phpgw_setup->db->f("cal_time")),0,2))),intval(substr($phpgw_setup->db->f("cal_date"),4,2)),intval(substr($phpgw_setup->db->f("cal_date"),6,2)),intval(substr($phpgw_setup->db->f("cal_date"),0,4)));
      	    $moddatetime = mktime(intval(strrev(substr(strrev($phpgw_setup->db->f("cal_mod_time")),4))),intval(strrev(substr(strrev($phpgw_setup->db->f("cal_mod_time")),2,2))),intval(strrev(substr(strrev($phpgw_setup->db->f("cal_mod_time")),0,2))),intval(substr($phpgw_setup->db->f("cal_mod_date"),4,2)),intval(substr($phpgw_setup->db->f("cal_mod_date"),6,2)),intval(substr($phpgw_setup->db->f("cal_mod_date"),0,4)));
      	    $db2->query("SELECT groups FROM webcal_entry_groups WHERE cal_id=".$cal_id,__LINE__,__FILE__);
      	    $db2->next_record();
      	    $cal_group = $db2->f("groups");
      	    $db2->query("INSERT INTO calendar_entry(cal_id,cal_owner,cal_group,cal_datetime,cal_mdatetime,cal_duration,cal_priority,cal_type,cal_access,cal_name,cal_description) "
      		       ."VALUES(".$cal_id.",'".$cal_owner."','".$cal_group."',".$datetime.",".$moddatetime.",".$cal_duration.",".$cal_priority.",'".$cal_type."','".$cal_access."','".$cal_name."','".$cal_description."')",__LINE__,__FILE__);
      	  }
    	  }
  	    $phpgw_setup->db->query("DROP TABLE webcal_entry_groups");
  	    $phpgw_setup->db->query("DROP TABLE webcal_entry");

    	  $sql = "CREATE TABLE calendar_entry_user (
    			cal_id		int DEFAULT 0 NOT NULL,
    			cal_login	int DEFAULT 0 NOT NULL,
    			cal_status	char(1) DEFAULT 'A')";
  	    $phpgw_setup->db->query($sql,__LINE__,__FILE__);
      	$phpgw_setup->db->query("SELECT count(*) FROM webcal_entry_user",__LINE__,__FILE__);
      	$phpgw_setup->db->next_record();
      	if($phpgw_setup->db->f(0)) {
      	  $phpgw_setup->db->query("SELECT cal_id,cal_login,cal_status FROM webcal_entry_user ORDER BY cal_id",__LINE__,__FILE__);
      	  while($phpgw_setup->db->next_record()) {
      	    $cal_id = $phpgw_setup->db->f("cal_id");
      	    $cal_login = $phpgw_setup->db->f("cal_login");
      	    $cal_status = $phpgw_setup->db->f("cal_status");
      	    $db2->query("INSERT INTO calendar_entry_user(cal_id,cal_login,cal_status) VALUES(".$cal_id.",".$cal_login.",'".$cal_status."')",__LINE__,__FILE__);
      	  }
  	    }
	      $phpgw_setup->db->query("DROP TABLE webcal_entry_user",__LINE__,__FILE__);

    	  $sql = "CREATE TABLE calendar_entry_repeats (
    			cal_id		int DEFAULT 0 NOT NULL,
    			cal_type	varchar(20),
    			cal_end		int4,
    			cal_frequency	int default 1,
    			cal_days	char(7))";
      	$phpgw_setup->db->query($sql,__LINE__,__FILE__);
      	$phpgw_setup->db->query("SELECT count(*) FROM webcal_entry_repeats",__LINE__,__FILE__);
      	$phpgw_setup->db->next_record();
      	if($phpgw_setup->db->f(0)) {
      	  $phpgw_setup->db->query("SELECT cal_id,cal_type,cal_end,cal_frequency,cal_days FROM webcal_entry_repeats ORDER BY cal_id",__LINE__,__FILE__);
      	  while($phpgw_setup->db->next_record()) {
      	    $cal_id = $phpgw_setup->db->f("cal_id");
      	    $cal_type = $phpgw_setup->db->f("cal_type");
      	    if(isset($phpgw_setup->db->Record["cal_end"])) {
      	      $enddate = mktime(0,0,0,intval(substr($phpgw_setup->db->f("cal_end"),4,2)),intval(substr($phpgw_setup->db->f("cal_end"),6,2)),intval(substr($phpgw_setup->db->f("cal_end"),0,4)));
      	      $useend = 1;
      	    } else {
      	      $enddate = 0;
      	      $useend = 0;
      	    }
      	    $cal_frequency = $phpgw_setup->db->f("cal_frequency");
      	    $cal_days = $phpgw_setup->db->f("cal_days");
      	    $db2->query("INSERT INTO calendar_entry_repeats(cal_id,cal_type,cal_use_end,cal_end,cal_frequency,cal_days) VALUES(".$cal_id.",'".$cal_type."',".$useend.",".$enddate.",".$cal_frequency.",'".$cal_days."')",__LINE__,__FILE__);
      	  }
      	}
      	$phpgw_setup->db->query("DROP TABLE webcal_entry_repeats",__LINE__,__FILE__);
	      $phpgw_setup->db->query("UPDATE applications SET app_tables='calendar_entry,calendar_entry_user,calendar_entry_repeats' WHERE app_name='calendar'",__LINE__,__FILE__);

    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.7pre2";
  }

  $test[] = "0.9.7pre2";
  function upgrade0_9_7pre2(){
    global $phpgw_info, $phpgw_setup;
      	$db2 = $phpgw_setup->db;
      	$sql = "CREATE TABLE TEMP AS SELECT * FROM calendar_entry";
      	$phpgw_setup->db->query($sql,__LINE__,__FILE__);
      
      	$sql = "DROP TABLE calendar_entry";
      	$phpgw_setup->db->query($sql,__LINE__,__FILE__);
      
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
      	$phpgw_setup->db->query($sql,__LINE__,__FILE__);
      	$phpgw_setup->db->query("SELECT cal_id,cal_owner,cal_group,cal_datetime,cal_mdatetime,cal_duration,cal_priority,cal_type,cal_access,cal_name,cal_description FROM TEMP ORDER BY cal_id",__LINE__,__FILE__);
	while($phpgw_setup->db->next_record()) {
	  $db2->query("SELECT preference_value FROM preferences WHERE preference_name='tz_offset' AND preference_appname='common' AND preference_owner=".$phpgw_setup->db->f("cal_owner"),__LINE__,__FILE__);
	  $db2->next_record();
	  $tz = $db2->f("preference_value");
	  $cal_id = $phpgw_setup->db->f("cal_id");
	  $cal_owner = $phpgw_setup->db->f("cal_owner");
	  $cal_group = $phpgw_setup->db->f("cal_group");
	  $cal_datetime = $phpgw_setup->db->f("cal_datetime") - ((60 * 60) * $tz);
	  $cal_mdatetime = $phpgw_setup->db->f("cal_mdatetime") - ((60 * 60) * $tz);
	  $cal_edatetime = $cal_datetime + (60 * $phpgw_setup->db->f("cal_duration"));
	  $cal_priority = $phpgw_setup->db->f("cal_priority");
	  $cal_type = $phpgw_setup->db->f("cal_type");
	  $cal_access = $phpgw_setup->db->f("cal_access");
	  $cal_name = $phpgw_setup->db->f("cal_name");
	  $cal_description = $phpgw_setup->db->f("cal_description");
	  $db2->query("INSERT INTO calendar_entry(cal_id,cal_owner,cal_group,cal_datetime,cal_mdatetime,cal_edatetime,cal_priority,cal_type,cal_access,cal_name,cal_description) VALUES(".$cal_id.",".$cal_owner.",'".$cal_group."',".cal_datetime.",".$cal_mdatetime.",".$cal_edatetime.",".$cal_priority.",'".$cal_type."','".$cal_access."','".$cal_name."','".$cal_description."')",__LINE__,__FILE__);
      	}
      	$sql = "DROP TABLE TEMP";
	      $phpgw_setup->db->query($sql,__LINE__,__FILE__);
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.7pre3";
  }

  $test[] = "0.9.7pre3";
  function upgrade0_9_7pre3(){
    global $phpgw_info, $phpgw_setup;
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.7";
  }

  $test[] = "0.9.7";
  function upgrade0_9_7(){
    global $phpgw_info, $phpgw_setup;
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.8pre1";
  }

  $test[] = "0.9.8pre1";
  function upgrade0_9_8pre1(){
    global $phpgw_info, $phpgw_setup;
        $phpgw_setup->db->query("select * from preferences order by preference_owner");
        while ($phpgw_setup->db->next_record()) {
           $t[$phpgw_setup->db->f("preference_owner")][$phpgw_setup->db->f("preference_appname")][$phpgw_setup->db->f("preference_var")] = $phpgw_setup->db->f("preference_value");
        }

        $phpgw_setup->db->query("drop table preferences");
        $sql = "create table preferences ( 
          preference_owner       int,
          preference_value       text
        )";
        $phpgw_setup->db->query($sql);         

        while ($tt = each($t)) {
           $phpgw_setup->db->query("insert into preferences values ('$tt[0]','" . serialize($tt[1]) . "')");
        }
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.8pre2";
  }

  $test[] = "0.9.8pre2";
  function upgrade0_9_8pre2(){
    global $phpgw_info, $phpgw_setup;
        $sql = "CREATE TABLE config (
          config_name     varchar(255) NOT NULL UNIQUE,
          config_value    varchar(100) NOT NULL
        )";
        @$phpgw_setup->db->query($sql);
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.8pre3";
  }

  $test[] = "0.9.8pre3";
  function upgrade0_9_8pre3(){
    global $phpgw_info, $phpgw_setup;

      	$phpgw_setup->db->query("DROP TABLE phpgw_sessions",__LINE__,__FILE__);
        $sql = "create table phpgw_sessions (
          session_id         varchar(255),
          session_lid        varchar(255),
          session_ip         varchar(255),
          session_logintime  int,
          session_dla        int,
          session_info       text,
          unique(session_id)
        )";
        $phpgw_setup->db->query($sql);
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.8pre4";
  }

  $test[] = "0.9.8pre4";
  function upgrade0_9_8pre4(){
    global $phpgw_info, $phpgw_setup;

    $sql = "create table phpgw_hooks (
              hook_id       serial,
              hook_appname  varchar(255),
              hook_location varchar(255),
              hook_filename varchar(255)
            );";
    $phpgw_setup->db->query($sql);  
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.8pre5";
  }

  $test[] = "0.9.8pre5";
  function upgrade0_9_8pre5(){
    global $phpgw_info, $phpgw_setup;

    // Since no applications are using it yet.  I am gonna drop it and create a new one.
    // This is becuase I never finished the classes
    $phpgw_setup->db->query("drop table categories");
    $sql = "CREATE TABLE phpgw_categories (
              cat_id          serial,
              cat_parent      int,
              cat_owner       int,
              cat_appname     varchar(50) NOT NULL,
              cat_name        varchar(150) NOT NULL,
              cat_description varchar(255) NOT NULL,
              cat_data        text
           )";
    $phpgw_setup->db->query($sql);

    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.9pre1";
  }

  $test[] = "0.9.9pre1";
  function upgrade0_9_9pre1(){
    global $phpgw_info, $phpgw_setup;
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.9";
  }

  $test[] = "0.9.9";
  function upgrade0_9_9(){
    global $phpgw_info, $phpgw_setup;
    $db2 = $phpgw_setup->db;
    //convert user settings
    $phpgw_setup->db->query("select account_id, account_permissions from accounts",__LINE__,__FILE__);
    if($phpgw_setup->db->num_rows()) {
      while($phpgw_setup->db->next_record()) {
        $apps_perms = explode(":",$phpgw_setup->db->f("account_permissions"));
        for($i=1;$i<count($apps_perms)-1;$i++) {
          if ($apps_perms[$i] != ""){
            $sql = "insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights)";
            $sql .= " values('".$apps_perms[$i]."', 'run', ".$phpgw_setup->db->f("account_id").", 'u', 1)";
            $db2->query($sql ,__LINE__,__FILE__);
          }
        }
      }
    }
    $phpgw_setup->db->query("update accounts set account_permissions = ''",__LINE__,__FILE__);
    //convert group settings
    $phpgw_setup->db->query("select group_id, group_apps from groups",__LINE__,__FILE__);
    if($phpgw_setup->db->num_rows()) {
      while($phpgw_setup->db->next_record()) {
        $apps_perms = explode(":",$phpgw_setup->db->f("group_apps"));
        for($i=1;$i<count($apps_perms)-1;$i++) {
          if ($apps_perms[$i] != ""){
            $sql = "insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights)";
            $sql .= " values('".$apps_perms[$i]."', 'run', ".$phpgw_setup->db->f("group_id").", 'g', 1)";
            $db2->query($sql ,__LINE__,__FILE__);
          }
        }
      }
    }
    $phpgw_setup->db->query("update groups set group_apps = ''",__LINE__,__FILE__);
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.10pre1";
  }

  $test[] = "0.9.10pre1";                                                                                                                                                                        
  function upgrade0_9_10pre1(){                                                                                                                                                                  
    global $phpgw_info, $phpgw_setup;
    $phpgw_setup->db->query("create table temp as select * from phpgw_categories");                                                                
    $phpgw_setup->db->query("drop sequence phpgw_categories_cat_id_seq");                                                                                        
    $phpgw_setup->db->query("drop table phpgw_categories");
    $phpgw_setup->db->query("CREATE TABLE phpgw_categories (                                                                                       
            cat_id          serial,                                                                                                                
            cat_parent      int,                                                                                                                   
            cat_owner       int,                                                                                                                   
            cat_access      varchar(25),                                                                                                           
            cat_appname     varchar(50) NOT NULL,                                                                                                  
            cat_name        varchar(150) NOT NULL,                                                                                                 
            cat_description varchar(255) NOT NULL,                                                                                                 
            cat_data        text                                                                                                                   
            )");                                                                                                                                   
    $phpgw_setup->db->query("insert into phpgw_categories select * from temp");                                                                    
    $phpgw_setup->db->query("drop table temp");
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.10pre2";
  }

  $test[] = "0.9.10pre2";                                                                                                                                                                        
  function upgrade0_9_10pre2(){                                                                                                                                                                  
    global $phpgw_info, $phpgw_setup;
    $db2 = $phpgw_setup->db;
    $phpgw_setup->db->query("select account_groups,account_id from accounts",__LINE__,__FILE__);
    if($phpgw_setup->db->num_rows()) {
      while($phpgw_setup->db->next_record()) {
        $gl = explode(",",$phpgw_setup->db->f("account_groups"));
        for ($i=1; $i<(count($gl)-1); $i++) {
          $ga = explode(":",$gl[$i]);
          $sql = "insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights)";
          $sql .= " values('phpgw_group', '".$ga[0]."', ".$phpgw_setup->db->f("account_id").", 'u', 1)";
          $db2->query($sql ,__LINE__,__FILE__);
        }
      }
    }
    $phpgw_setup->db->query("update accounts set account_groups = ''",__LINE__,__FILE__);
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.10pre3";
  }

  $test[] = "0.9.10pre3";
  function upgrade0_9_10pre3()
  {
     global $phpgw_info, $phpgw_setup; 
     $phpgw_setup->db->query("create table phpgw_temp as select account_id,account_lid,account_pwd,"
                           . "account_firstname,account_lastname,account_lastlogin,account_lastloginfrom,"
                           . "account_lastpwd_change,account_status from accounts",__LINE__,__FILE__);
     $sql = "create table phpgw_accounts (
       account_id             serial,
       account_lid            varchar(25) NOT NULL,
       account_pwd            char(32) NOT NULL,
       account_firstname      varchar(50),
       account_lastname       varchar(50),
       account_lastlogin      int,
       account_lastloginfrom  varchar(255),
       account_lastpwd_change int,
       account_status         char(1),
       account_type           char(1),
       unique(account_lid)
     )";
     $phpgw_setup->db->query($sql);

     $phpgw_setup->db->query("insert into phpgw_accounts select * from phpgw_temp",__LINE__,__FILE__);
     $phpgw_setup->db->query("drop table phpgw_temp",__LINE__,__FILE__);
     $phpgw_setup->db->query("drop sequence accounts_account_id_seq");
     $phpgw_setup->db->query("drop table accounts");

     $phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.10pre4";
  }

  function change_groups($table,$field,$old_id,$new_id,$db2,$db3)
  {
     $sql = $field[0];
     for($i=1;$i<count($field);$i++) {
       $sql .= ", ".$field[$i];
     }
     $db2->query("SELECT $sql FROM $table WHERE $field[0] like '%,".$old_id.",%'",__LINE__,__FILE__);
     if($db2->num_rows()) {
       while($db2->next_record()) {
         $access = $db2->f($field[0]);
         $id = $db2->f($field[1]);
         $access = str_replace(','.$old_id.',' , ','.$new_id.',' , $access);
         $db3->query("UPDATE $table SET ".$field[0]."='".$access."' WHERE ".$field[1]."=".$id,__LINE__,__FILE__);
       }
     }
   }

  $test[] = "0.9.10pre4";
  function upgrade0_9_10pre4()
  {
     global $phpgw_info, $phpgw_setup;
     
     $db2 = $phpgw_setup->db;
     $db3 = $phpgw_setup->db;
     $phpgw_setup->db->query("SELECT MAX(group_id) FROM groups",__LINE__,__FILE__);
     $phpgw_setup->db->next_record();
     $max_group_id = $phpgw_setup->db->f(0);
     
     $tables = Array('addressbook','calendar_entry','f_forums','phpgw_categories','todo');
     $fields["addressbook"] = Array('ab_access','ab_id');
                                $fields["calendar_entry"] = Array('cal_group','cal_id');
     $fields["f_forums"] = Array('groups','id');
     $fields["phpgw_categories"] = Array('cat_access','cat_id');
     $fields["todo"] = Array('todo_access','todo_id');
     
     $phpgw_setup->db->query("SELECT group_id, group_name FROM groups",__LINE__,__FILE__);
     while($phpgw_setup->db->next_record()) {
       $old_group_id = $phpgw_setup->db->f("group_id");
       $group_name = $phpgw_setup->db->f("group_name");
       while(1) {
         $new_group_id = mt_rand ($max_group_id, 60000);
         $db2->query("SELECT account_id FROM phpgw_accounts WHERE account_id=$new_group_id",__LINE__,__FILE__);
         if(!$db2->num_rows()) { break; }
       }
       $db2->query("SELECT account_lid FROM phpgw_accounts WHERE account_lid='$group_name'",__LINE__,__FILE__);
       if($db2->num_rows()) {
         $group_name .= "_group";
       }
       $db2->query("INSERT INTO phpgw_accounts(account_id, account_lid, account_pwd, "
       			  ."account_firstname, account_lastname, account_lastlogin, "
       			  ."account_lastloginfrom, account_lastpwd_change, "
       			  ."account_status, account_type) "
       			  ."VALUES ($new_group_id,'$group_name','x','','',$old_group_id,NULL,NULL,'A','g')");

       for($i=0;$i<count($tables);$i++) {
         change_groups($tables[$i],$fields[$tables[$i]],$old_group_id,$new_group_id,$db2,$db3);
       }
       $db2->query("UPDATE phpgw_acl SET acl_location='$new_group_id' "
                  ."WHERE acl_appname='phpgw_group' AND acl_account_type='u' "
                  ."AND acl_location='$old_group_id'");
       $db2->query("UPDATE phpgw_acl SET acl_account=$new_group_id "
                  ."WHERE acl_location='run' AND acl_account_type='g' "
                  ."AND acl_account=$old_group_id");
     }
     $phpgw_setup->db->query("DROP TABLE groups",__LINE__,__FILE__);
     $phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.10pre5";
  }

  $test[] = "0.9.10pre5";
  function upgrade0_9_10pre5()
  {
		global $phpgw_info, $phpgw_setup;

		// This is only temp data, so we can kill it.
		$phpgw_setup->db->query('drop table phpgw_app_sessions',__LINE__,__FILE__);
    $sql = "CREATE TABLE phpgw_app_sessions (
      sessionid	   varchar(255) NOT NULL,
      loginid	     varchar(20),
      location      varchar(255),
      app	         varchar(20),
      content	     text
    )";
    $phpgw_setup->db->query($sql);  
     
		$phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.10pre6";
  }

  $test[] = "0.9.10pre6";
  function upgrade0_9_10pre6()
  {
		global $phpgw_info, $phpgw_setup;

    $phpgw_setup->db->query("alter table config rename phpgw_config",__LINE__,__FILE__);
     
		$phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.10pre7";
  }

  $test[] = "0.9.10pre7";
  function upgrade0_9_10pre7()
  {
		global $phpgw_info, $phpgw_setup;

    $phpgw_setup->db->query("alter table applications rename phpgw_applications",__LINE__,__FILE__);
     
		$phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.10pre8";
  }

  $test[] = "0.9.10pre8";
  function upgrade0_9_10pre8()
  {
		global $phpgw_info, $phpgw_setup;

		// Just temp data anyway
		$phpgw_setup->db->query("drop table phpgw_sessions",__LINE__,__FILE__);
	  $sql = "create table phpgw_sessions (
	    session_id         varchar(255),
 	   session_lid        varchar(255),
  	  session_ip         varchar(255),
   	 session_logintime  int,
	    session_dla        int,
 	   session_action     varchar(255),
  	  unique(session_id)
	  )";
 	 $phpgw_setup->db->query($sql);
     
		$phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.10pre9";
  }

  $test[] = '0.9.10pre9';
  function upgrade0_9_10pre9()
  {
		global $phpgw_info, $phpgw_setup;

		$phpgw_setup->db->query('alter table preferences rename phpgw_preferences',__LINE__,__FILE__);
     
		$phpgw_info['setup']['currentver']['phpgwapi'] = '0.9.10pre10';
  }

	$test[] = '0.9.10pre10';
	function upgrade0_9_10pre10()
	{
		global $phpgw_info, $phpgw_setup;

		$phpgw_setup->db->query("create table phpgw_temp as select acl_appname,acl_location,acl_account,"
                            . "acl_rights from phpgw_acl",__LINE__,__FILE__);
		$phpgw_setup->db->query("drop table phpgw_acl",__LINE__,__FILE__);

		$sql = "CREATE TABLE phpgw_acl (
			acl_appname       varchar(50),
			acl_location      varchar(255),
			acl_account       int,
			acl_rights        int
		)";
		$phpgw_setup->db->query($sql);  

		$phpgw_setup->db->query("insert into phpgw_acl select * from phpgw_temp",__LINE__,__FILE__);
		$phpgw_setup->db->query("drop table phpgw_temp",__LINE__,__FILE__);
     
		$phpgw_info['setup']['currentver']['phpgwapi'] = '0.9.10pre11';
	}

	$test[] = '0.9.10pre11';
	function upgrade0_9_10pre11()
        {
                global $phpgw_info, $phpgw_setup;

                $phpgw_setup->db->query("create table phpgw_temp as select * from notes",__LINE__,__FILE__);

		$phpgw_setup->db->query("drop sequence notes_note_id_seq",__LINE__,__FILE__);

                $phpgw_setup->db->query("drop table notes",__LINE__,__FILE__);

		$sql = "CREATE TABLE phpgw_notes (
			note_id        serial,
			note_owner     int,
			note_date      int,
			note_category  int,
			note_content   text
		)";
		$phpgw_setup->db->query($sql);

                $phpgw_setup->db->query("insert into phpgw_notes select * from phpgw_temp",__LINE__,__FILE__);

                $phpgw_setup->db->query("drop table phpgw_temp",__LINE__,__FILE__);

                $phpgw_info['setup']['currentver']['phpgwapi'] = '0.9.10pre12';
	}

  $test[] = '0.9.10pre12';
    function upgrade0_9_10pre12()
    {
        global $phpgw_info, $phpgw_setup;
        $db1 = $phpgw_setup->db;
        $db2 = $phpgw_setup->db;

        $sql = "DROP sequence phpgw_addressbook_id_seq";
        $db1->query($sql,__LINE__,__FILE__);

        $sql = "DROP TABLE phpgw_addressbook";
        $db1->query($sql,__LINE__,__FILE__);

        $sql = "DROP TABLE phpgw_addressbook_extra";
        $db1->query($sql,__LINE__,__FILE__);

        $sql = "CREATE TABLE phpgw_addressbook (

                    id 			serial,
                    lid			varchar(32),
                    tid 		char(1),
                    owner 		int,
                    fn 			varchar(64),
                    sound 		varchar(64),
                    org_name 		varchar(64),
                    org_unit 		varchar(64),
                    title 		varchar(64),
                    n_family 		varchar(64),
                    n_given 		varchar(64),
                    n_middle 		varchar(64),
                    n_prefix 		varchar(64),
                    n_suffix 		varchar(64),
                    label 		text,
                    adr_poaddr 		varchar(64),
                    adr_extaddr 	varchar(64),
                    adr_street 	 	varchar(64),
                    adr_locality 	varchar(32),
                    adr_region 		varchar(32),
                    adr_postalcode 	varchar(32),
                    adr_countryname 	varchar(32),
                    adr_work 		boolean DEFAULT 'n' NOT NULL,
                    adr_home 		boolean DEFAULT 'n' NOT NULL,
                    adr_parcel 		boolean DEFAULT 'n' NOT NULL,
                    adr_postal 		boolean DEFAULT 'n' NOT NULL,
                    tz 			varchar(8),
                    geo 		varchar(32),
                    a_tel 		varchar(40) DEFAULT '+1 (000) 000-0000' NOT NULL,
                    a_tel_work 		boolean DEFAULT 'n' NOT NULL,
                    a_tel_home 		boolean DEFAULT 'n' NOT NULL,
                    a_tel_voice 	boolean DEFAULT 'n' NOT NULL,
                    a_tel_msg 		boolean DEFAULT 'n' NOT NULL,
                    a_tel_fax 		boolean DEFAULT 'n' NOT NULL,
                    a_tel_prefer 	boolean DEFAULT 'n' NOT NULL,
                    b_tel 		varchar(40) DEFAULT '+1 (000) 000-0000' NOT NULL,
                    b_tel_work 		boolean DEFAULT 'n' NOT NULL,
                    b_tel_home 		boolean DEFAULT 'n' NOT NULL,
                    b_tel_voice 	boolean DEFAULT 'n' NOT NULL,
                    b_tel_msg 		boolean DEFAULT 'n' NOT NULL,
                    b_tel_fax 		boolean DEFAULT 'n' NOT NULL,
                    b_tel_prefer 	boolean DEFAULT 'n' NOT NULL,
                    c_tel 		varchar(40) DEFAULT '+1 (000) 000-0000' NOT NULL,
                    c_tel_work 		boolean DEFAULT 'n' NOT NULL,
                    c_tel_home 		boolean DEFAULT 'n' NOT NULL,
                    c_tel_voice 	boolean DEFAULT 'n' NOT NULL,
                    c_tel_msg 		boolean DEFAULT 'n' NOT NULL,
                    c_tel_fax 		boolean DEFAULT 'n' NOT NULL,
                    c_tel_prefer 	boolean DEFAULT 'n' NOT NULL,
                    d_emailtype 	text check(d_emailtype in('INTERNET','CompuServe','AOL','Prodigy','eWorld','AppleLink','AppleTalk','PowerShare','IBMMail','ATTMail','MCIMail','X.400','TLX')) DEFAULT 'INTERNET' NOT NULL,
                    d_email 		varchar(64),
                    d_email_work 	boolean DEFAULT 'n' NOT NULL,
                    d_email_home 	boolean DEFAULT 'n' NOT NULL,
                    UNIQUE (id)
                    )";

      $db1->query($sql,__LINE__,__FILE__);

        $sql = "CREATE TABLE phpgw_addressbook_extra (
                    contact_id 		int,
                    contact_owner 	int,
                    contact_name 	varchar(255),
                    contact_value 	varchar(255)
                )";

        $db1->query($sql,__LINE__,__FILE__);

        $db1->query("SELECT * FROM addressbook",__LINE__,__FILE__);

            $fields = $extra = array();

                while ($db1->next_record()) {
                    $fields['id']         = $db1->f("ab_id");
                    $fields['owner']      = $db1->f("ab_owner");
                    $fields['n_given']    = $db1->f("ab_firstname");
                    $fields['n_family']   = $db1->f("ab_lastname");
                    $fields['d_email']    = $db1->f("ab_email");
                    $fields['b_tel']      = $db1->f("ab_hphone");
                    $fields['a_tel']      = $db1->f("ab_wphone");
                    $fields['c_tel']      = $db1->f("ab_fax");
                    $fields['fn']         = $db1->f("ab_firstname")." ".$db1->f("ab_lastname");
                    $fields["a_tel_work"] = "y";
                    $fields["b_tel_home"] = "y";
                    $fields["c_tel_fax"]  = "y";
                    $fields['org_name']   = $db1->f("ab_company");
                    $fields['title']      = $db1->f("ab_title");
                    $fields['adr_street'] = $db1->f("ab_street");
                    $fields['adr_locality']       = $db1->f("ab_city");
                    $fields['adr_region']         = $db1->f("ab_state");
                    $fields['adr_postalcode']     = $db1->f("ab_zip");

                    $extra['pager']       = $db1->f("ab_pager");
                    $extra['mphone']      = $db1->f("ab_mphone");
                    $extra['ophone']      = $db1->f("ab_ophone");
                    $extra['bday']        = $db1->f("ab_bday");
                    $extra['notes']       = $db1->f("ab_notes");
                    $extra['address2']    = $db1->f("ab_address2");
                    $extra['url']         = $db1->f("ab_url");

        $sql="INSERT INTO phpgw_addressbook (org_name,n_given,n_family,fn,d_email,title,a_tel,a_tel_work,"
                                . "b_tel,b_tel_home,c_tel,c_tel_fax,adr_street,adr_locality,adr_region,adr_postalcode,owner)"
                                . " VALUES ('".$fields["org_name"]."','".$fields["n_given"]."','".$fields["n_family"]."','"
                                . $fields["fn"]."','".$fields["d_email"]."','".$fields["title"]."','".$fields["a_tel"]."','"
                                . $fields["a_tel_work"]."','".$fields["b_tel"]."','".$fields["b_tel_home"]."','"
                                . $fields["c_tel"]."','".$fields["c_tel_fax"]."','".$fields["adr_street"]."','"
                                . $fields["adr_locality"]."','".$fields["adr_region"]."','".$fields["adr_postalcode"]."','"
                                . $fields["owner"] ."')";

        $db2->query($sql,__LINE__,__FILE__);

                while (list($name,$value) = each($extra)) {

                        $sql = "INSERT INTO phpgw_addressbook_extra VALUES ('".$fields["id"]."','" . $$fields["owner"] . "','"
                                        . addslashes($name) . "','" . addslashes($value) . "')";

                $db2->query($sql,__LINE__,__FILE__);
                }
            }

    $phpgw_info['setup']['currentver']['phpgwapi'] = '0.9.10pre13';
    }

  reset ($test);
  while (list ($key, $value) = each ($test)){
    if ($phpgw_info["setup"]["currentver"]["phpgwapi"] == $value) {
      $ver = "upgrade".ereg_replace("\.","_",$value);
      $ver();
      echo "<table>";
      echo "  <tr bgcolor=\"e6e6e6\">\n";
      echo "    <td>Upgrade from ".$value." to ".$phpgw_info["setup"]["currentver"]["phpgwapi"]." is completed.</td>\n";
      echo "  </tr>\n";
      echo "</table>";
      if ($tableschanged == True){$tablechanges = True;}
      if (!$phpgw_info["setup"]["prebeta"]){
        $phpgw_setup->db->query("update phpgw_applications set app_version='".$phpgw_info["setup"]["currentver"]["phpgwapi"]."' where (app_name='admin' or app_name='filemanager' or app_name='addressbook' or app_name='todo' or app_name='calendar' or app_name='email' or app_name='nntp' or app_name='cron_apps' or app_name='notes')");
      }
    }
  }

?>
