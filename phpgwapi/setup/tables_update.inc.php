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
	function phpgwapi_upgrade0_9_1()
	{
		global $phpgw_info, $oProc;
		
		$oProc->AlterColumn("access_log", "lo", array("type" => "varchar", "precision" => 255));
		
		$oProc->m_odb->query("update lang set lang='da' where lang='dk'");
		$oProc->m_odb->query("update lang set lang='ko' where lang='kr'");
		
		$oProc->m_odb->query("update preferences set preference_name='da' where preference_name='dk'");
		$oProc->m_odb->query("update preferences set preference_name='ko' where preference_name='kr'");
		
		  	//install weather support
		$oProc->m_odb->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('weather', 'Weather', 1, 12, NULL, '".$phpgw_info["server"]["version"]."')");
		$oProc->m_odb->query("INSERT INTO lang (message_id, app_name, lang, content) VALUES( 'weather','Weather','en','weather')");
		$phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.2";
	}
	
	function phpgwapi_v0_9_2to0_9_3update_owner($table, $field)
	{
		global $phpgw_setup, $oProc;
		
		$oProc->m_odb->query("select distinct($field) from $table");
		if ($oProc->m_odb->num_rows()) {
			while ($oProc->m_odb->next_record()) {
				$owner[count($owner)] = $phpgw_setup->db->f($field);
			}
			for($i=0;$i<count($owner);$i++) {
				$oProc->m_odb->query("select account_id from accounts where account_lid='".$owner[$i]."'");
				$oProc->m_odb->next_record();
				$oProc->m_odb->query("update $table set $field=".$oProc->m_odb->f("account_id")." where $field='".$owner[$i]."'");
			}
		}
		
		$oProc->AlterColumn($table, $field, array("type" => "int", "precision" => 4, "nullable" => false, "default" => 0));
	}
	
	$test[] = "0.9.3pre4";
	function phpgwapi_upgrade0_9_3pre4()
	{
		global $phpgw_info, $oProc;
		
		$oProc->AlterColumn("config", "config_name", array("type" => "varchar", "precision" => 255, "nullable" => false));
		
		$phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.3pre5";
	}
	
	$test[] = "0.9.3pre5";
	function phpgwapi_upgrade0_9_3pre5()
	{
		global $phpgw_info, $oProc;
		
		$oProc->CreateTable("categories", array(
				"fd" => array(
					"cat_id" => array("type" => "auto", "nullable" => false),
					"account_id" => array("type" => "int", "precision" => 4, "nullable" => false, "default" => "0"),
					"app_name" => array("type" => "varchar", "precision" => 25, "nullable" => false),
					"cat_name" => array("type" => "varchar", "precision" => 150, "nullable" => false),
					"cat_description" => array("type" => "text", "nullable" => false)
				),
				"pk" => array("cat_id"),
				"ix" => array(),
				"fk" => array(),
				"uc" => array()
			));
		
		$phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.3pre6";
	}
	
	$test[] = "0.9.3pre6";
	function phpgwapi_upgrade0_9_3pre6()
	{
		global $phpgw_info, $oProc;
		
		$oProc->m_odb->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('transy', 'Translation Management', 0, 13, NULL, '".$phpgw_info["server"]["version"]."')");
		
		$phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.3pre7";
	}
	
	$test[] = "0.9.3pre7";
	function phpgwapi_upgrade0_9_3pre7()
	{
		global $phpgw_info, $oProc;
		
		$oProc->CreateTable("languages", array(
				"fd" => array(
					"lang_id" => array("type" => "varchar", "precision" => 2, "nullable" => false),
					"lang_name" => array("type" => "varchar", "precision" => 50, "nullable" => false),
					"available" => array("type" => "char", "precision" => 3, "nullable" => false, "default" => "No")
				),
				"pk" => array("lang_id"),
				"ix" => array(),
				"fk" => array(),
				"uc" => array()
			));
		
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('AA','Afar','No')");        
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('AB','Abkhazian','No')");   
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('AF','Afrikaans','No')");   
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('AM','Amharic','No')");     
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('AR','Arabic','No')");      
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('AS','Assamese','No')");    
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('AY','Aymara','No')");      
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('AZ','Azerbaijani','No')"); 
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('BA','Bashkir','No')");     
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('BE','Byelorussian','No')");        
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('BG','Bulgarian','No')");   
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('BH','Bihari','No')");      
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('BI','Bislama','No')");     
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('BN','Bengali / Bangla','No')");    
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('BO','Tibetan','No')");     
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('BR','Breton','No')");      
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('CA','Catalan','No')");     
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('CO','Corsican','No')");    
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('CS','Czech','No')");       
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('CY','Welsh','No')");       
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('DA','Danish','Yes')");     
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('DE','German','Yes')");     
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('DZ','Bhutani','No')");     
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('EL','Greek','No')");       
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('EN','English / American','Yes')"); 
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('EO','Esperanto','No')");   
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('ES','Spanish','Yes')");    
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('ET','Estonian','No')");    
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('EU','Basque','No')");      
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('FA','Persian','No')");     
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('FI','Finnish','No')");     
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('FJ','Fiji','No')");        
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('FO','Faeroese','No')");    
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('FR','French','Yes')");     
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('FY','Frisian','No')");     
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('GA','Irish','No')");       
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('GD','Gaelic / Scots Gaelic','No')");       
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('GL','Galician','No')");    
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('GN','Guarani','No')");     
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('GU','Gujarati','No')");    
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('HA','Hausa','No')");       
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('HI','Hindi','No')");       
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('HR','Croatian','No')");    
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('HU','Hungarian','No')");   
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('HY','Armenian','No')");    
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('IA','Interlingua','No')"); 
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('IE','Interlingue','No')"); 
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('IK','Inupiak','No')");     
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('IN','Indonesian','No')");  
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('IS','Icelandic','No')");   
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('IT','Italian','Yes')");    
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('IW','Hebrew','No')");      
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('JA','Japanese','No')");    
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('JI','Yiddish','No')");     
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('JW','Javanese','No')");    
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('KA','Georgian','No')");    
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('KK','Kazakh','No')");      
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('KL','Greenlandic','No')"); 
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('KM','Cambodian','No')");   
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('KN','Kannada','No')");     
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('KO','Korean','Yes')");     
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('KS','Kashmiri','No')");    
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('KU','Kurdish','No')");     
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('KY','Kirghiz','No')");     
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('LA','Latin','No')");       
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('LN','Lingala','No')");     
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('LO','Laothian','No')");    
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('LT','Lithuanian','No')");  
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('LV','Latvian / Lettish','No')");   
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('MG','Malagasy','No')");    
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('MI','Maori','No')");       
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('MK','Macedonian','No')");  
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('ML','Malayalam','No')");   
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('MN','Mongolian','No')");   
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('MO','Moldavian','No')");   
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('MR','Marathi','No')");     
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('MS','Malay','No')");       
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('MT','Maltese','No')");     
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('MY','Burmese','No')");     
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('NA','Nauru','No')");       
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('NE','Nepali','No')");      
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('NL','Dutch','Yes')");      
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('NO','Norwegian','Yes')");  
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('OC','Occitan','No')");     
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('OM','Oromo / Afan','No')");        
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('OR','Oriya','No')");       
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('PA','Punjabi','No')");     
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('PL','Polish','No')");      
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('PS','Pashto / Pushto','No')");     
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('PT','Portuguese','Yes')"); 
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('QU','Quechua','No')");     
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('RM','Rhaeto-Romance','No')");      
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('RN','Kirundi','No')");     
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('RO','Romanian','No')");    
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('RU','Russian','No')");     
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('RW','Kinyarwanda','No')"); 
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('SA','Sanskrit','No')");    
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('SD','Sindhi','No')");      
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('SG','Sangro','No')");      
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('SH','Serbo-Croatian','No')");      
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('SI','Singhalese','No')");  
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('SK','Slovak','No')");      
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('SL','Slovenian','No')");   
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('SM','Samoan','No')");      
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('SN','Shona','No')");       
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('SO','Somali','No')");      
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('SQ','Albanian','No')");    
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('SR','Serbian','No')");     
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('SS','Siswati','No')");     
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('ST','Sesotho','No')");     
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('SU','Sudanese','No')");    
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('SV','Swedish','Yes')");     
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('SW','Swahili','No')");     
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('TA','Tamil','No')");       
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('TE','Tegulu','No')");      
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('TG','Tajik','No')");       
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('TH','Thai','No')");        
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('TI','Tigrinya','No')");    
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('TK','Turkmen','No')");     
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('TL','Tagalog','No')");     
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('TN','Setswana','No')");    
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('TO','Tonga','No')");       
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('TR','Turkish','No')");     
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('TS','Tsonga','No')");      
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('TT','Tatar','No')");       
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('TW','Twi','No')"); 
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('UK','Ukrainian','No')");   
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('UR','Urdu','No')");        
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('UZ','Uzbek','No')");       
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('VI','Vietnamese','No')");  
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('VO','Volapuk','No')");     
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('WO','Wolof','No')");       
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('XH','Xhosa','No')");       
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('YO','Yoruba','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('ZH','Chinese','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('ZU','Zulu','No')");
		
		$phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.3pre8";
	}
	
	$test[] = "0.9.4pre4";
	function phpgwapi_upgrade0_9_4pre4()
	{
		global $oldversion, $phpgw_info, $oProc;
		
		$oProc->AlterColumn("sessions", "session_lid", array("type" => "varchar", "precision" => 255));
		
		$phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.4pre5";
	}
	
	$test[] = "0.9.4";
	function phpgwapi_upgrade0_9_4()
	{
		global $phpgw_info, $oProc;
		$oProc->m_odb->query("delete from languages");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('aa','Afar','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('ab','Abkhazian','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('af','Afrikaans','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('am','Amharic','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('ar','Arabic','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('as','Assamese','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('ay','Aymara','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('az','Azerbaijani','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('ba','Bashkir','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('be','Byelorussian','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('bg','Bulgarian','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('bh','Bihari','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('bi','Bislama','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('bn','Bengali / Bangla','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('bo','Tibetan','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('br','Breton','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('ca','Catalan','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('co','Corsican','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('cs','Czech','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('cy','Welsh','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('da','Danish','Yes')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('de','German','Yes')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('dz','Bhutani','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('el','Greek','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('en','English / American','Yes')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('eo','Esperanto','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('es','Spanish','Yes')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('et','Estonian','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('eu','Basque','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('fa','Persian','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('fi','Finnish','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('fj','Fiji','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('fo','Faeroese','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('fr','French','Yes')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('fy','Frisian','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('ga','Irish','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('gd','Gaelic / Scots Gaelic','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('gl','Galician','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('gn','Guarani','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('gu','Gujarati','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('ha','Hausa','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('hi','Hindi','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('hr','Croatian','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('hu','Hungarian','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('hy','Armenian','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('ia','Interlingua','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('ie','Interlingue','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('ik','Inupiak','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('in','Indonesian','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('is','Icelandic','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('it','Italian','Yes')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('iw','Hebrew','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('ja','Japanese','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('ji','Yiddish','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('jw','Javanese','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('ka','Georgian','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('kk','Kazakh','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('kl','Greenlandic','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('km','Cambodian','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('kn','Kannada','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('ko','Korean','Yes')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('ks','Kashmiri','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('ku','Kurdish','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('ky','Kirghiz','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('la','Latin','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('ln','Lingala','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('lo','Laothian','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('lt','Lithuanian','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('lv','Latvian / Lettish','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('mg','Malagasy','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('mi','Maori','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('mk','Macedonian','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('ml','Malayalam','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('mn','Mongolian','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('mo','Moldavian','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('mr','Marathi','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('ms','Malay','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('mt','Maltese','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('my','Burmese','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('na','Nauru','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('ne','Nepali','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('nl','Dutch','Yes')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('no','Norwegian','Yes')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('oc','Occitan','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('om','Oromo / Afan','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('or','Oriya','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('pa','Punjabi','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('pl','Polish','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('ps','Pashto / Pushto','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('pt','Portuguese','Yes')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('qu','Quechua','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('rm','Rhaeto-Romance','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('rn','Kirundi','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('ro','Romanian','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('ru','Russian','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('rw','Kinyarwanda','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('sa','Sanskrit','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('sd','Sindhi','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('sg','Sangro','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('sh','Serbo-Croatian','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('si','Singhalese','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('sk','Slovak','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('sl','Slovenian','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('sm','Samoan','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('sn','Shona','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('so','Somali','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('sq','Albanian','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('sr','Serbian','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('ss','Siswati','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('st','Sesotho','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('su','Sudanese','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('sv','Swedish','Yes')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('sw','Swahili','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('ta','Tamil','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('te','Tegulu','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('tg','Tajik','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('th','Thai','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('ti','Tigrinya','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('tk','Turkmen','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('tl','Tagalog','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('tn','Setswana','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('to','Tonga','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('tr','Turkish','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('ts','Tsonga','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('tt','Tatar','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('tw','Twi','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('uk','Ukrainian','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('ur','Urdu','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('uz','Uzbek','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('vi','Vietnamese','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('vo','Volapuk','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('wo','Wolof','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('xh','Xhosa','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('yo','Yoruba','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('zh','Chinese','No')");
		@$oProc->m_odb->query("INSERT INTO languages (lang_id, lang_name, available) values ('zu','Zulu','No')");
		
		$phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.5pre1";
	}
	
	$test[] = "0.9.5pre1";
	function phpgwapi_upgrade0_9_5pre1()
	{
		global $phpgw_info, $oProc;
		
		$oProc->DropTable("sessions");
		$oProc->CreateTable("phpgw_sessions", array(
				"fd" => array(
					"session_id" => array("type" => "varchar", "precision" => 255, "nullable" => false),
					"session_lid" => array("type" => "varchar", "precision" => 255),
					"session_pwd" => array("type" => "varchar", "precision" => 255),
					"session_ip" => array("type" => "varchar", "precision" => 255),
					"session_logintime" => array("type" => "int", "precision" => 4),
					"session_dla" => array("type" => "int", "precision" => 4)
				),
				"pk" => array(),
				"ix" => array(),
				"fk" => array(),
				"uc" => array("session_id")
			));
		
		$oProc->CreateTable("phpgw_acl", array(
				"fd" => array(
					"acl_appname" => array("type" => "varchar", "precision" => 50),
					"acl_location" => array("type" => "varchar", "precision" => 255),
					"acl_account" => array("type" => "int", "precision" => 4),
					"acl_account_type" => array("type" => "char", "precision" => 1),
					"acl_rights" => array("type" => "int", "precision" => 4)
				),
				"pk" => array(),
				"ix" => array(),
				"fk" => array(),
				"uc" => array()
			));
		
		$oProc->DropTable("app_sessions");
		$oProc->CreateTable("phpgw_app_sessions", array(
				"fd" => array(
					"sessionid" => array("type" => "varchar", "precision" => 255, "nullable" => false),
					"loginid" => array("type" => "varchar", "precision" => 20),
					"app" => array("type" => "varchar", "precision" => 20),
					"content" => array("type" => "text")
				),
				"pk" => array(),
				"ix" => array(),
				"fk" => array(),
				"uc" => array()
			));
		
		$oProc->DropTable("access_log");
		$oProc->CreateTable("phpgw_access_log", array(
				"fd" => array(
					"sessionid" => array("type" => "varchar", "precision" => 255),
					"loginid" => array("type" => "varchar", "precision" => 30),
					"ip" => array("type" => "varchar", "precision" => 30),
					"li" => array("type" => "int", "precision" => 4),
					"lo" => array("type" => "varchar", "precision" => 255)
				),
				"pk" => array(),
				"ix" => array(),
				"fk" => array(),
				"uc" => array()
			));
		
		$phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.5pre2";
	}
	
	$test[] = "0.9.8pre1";
	function phpgwapi_upgrade0_9_8pre1()
	{
		global $phpgw_info, $oProc;
		
		$oProc->m_odb->query("select * from preferences order by preference_owner");
		$t = array();
		while ($oProc->m_odb->next_record()) {
			$t[$oProc->m_odb->f("preference_owner")][$phpgw_setup->db->f("preference_appname")][$phpgw_setup->db->f("preference_var")] = $phpgw_setup->db->f("preference_value");
		}
		
		$oProc->DropTable("preferences");
		$oProc->CreateTable("preferences", array(
				"fd" => array(
					"preference_owner" => array("type" => "int", "precision" => 4, "nullable" => false),
					"preference_value" => array("type" => "text")
				),
				"pk" => array(),
				"ix" => array(),
				"fk" => array(),
				"uc" => array()
			));
		
		while ($tt = each($t)) {
			$oProc->m_odb->query("insert into preferences values ('$tt[0]','" . serialize($tt[1]) . "')");
		}
		
		$phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.8pre2";
	}
	
	$test[] = "0.9.8pre3";
	function phpgwapi_upgrade0_9_8pre3()
	{
		global $oldversion, $phpgw_info, $phpgw_setup, $oProc;
		
		$oProc->DropTable("phpgw_sessions");
		$oProc->CreateTable("phpgw_sessions", array(
				"fd" => array(
					"session_id" => array("type" => "varchar", "precision" => 255, "nullable" => false),
					"session_lid" => array("type" => "varchar", "precision" => 255),
					"session_ip" => array("type" => "varchar", "precision" => 255),
					"session_logintime" => array("type" => "int", "precision" => 4),
					"session_dla" => array("type" => "int", "precision" => 4),
					"session_info" => array("type" => "text")
				),
				"pk" => array(),
				"ix" => array(),
				"fk" => array(),
				"uc" => array("session_id")
			));
		
		$phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.8pre4";
	}
	
	$test[] = "0.9.8pre4";
	function phpgwapi_upgrade0_9_8pre4()
	{
		global $oldversion, $phpgw_info, $phpgw_setup, $oProc;
		
		$oProc->CreateTable("phpgw_hooks", array(
				"fd" => array(
					"hook_id" => array("type" => "auto", "nullable" => false),
					"hook_appname" => array("type" => "varchar", "precision" => 255),
					"hook_location" => array("type" => "varchar", "precision" => 255),
					"hook_filename" => array("type" => "varchar", "precision" => 255)
				),
				"pk" => array("hook_id"),
				"ix" => array(),
				"fk" => array(),
				"uc" => array()
			));
		
		$phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.8pre5";
	}
?>
