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
		global $phpgw_info, $setup_info, $phpgw_setup;

		$oProc = $phpgw_setup->oProc;
		$oProc->AlterColumn("access_log", "lo", array("type" => "varchar", "precision" => 255));

		$oProc->query("update lang set lang='da' where lang='dk'");
		$oProc->query("update lang set lang='ko' where lang='kr'");

		$oProc->query("update preferences set preference_name='da' where preference_name='dk'");
		$oProc->query("update preferences set preference_name='ko' where preference_name='kr'");

	  	//install weather support
		$oProc->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('weather', 'Weather', 1, 12, NULL, '".$phpgw_info["server"]["version"]."')");
		$oProc->query("INSERT INTO lang (message_id, app_name, lang, content) VALUES( 'weather','Weather','en','weather')");

		$setup_info['phpgwapi']['currentver'] = '0.9.2';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	function phpgwapi_v0_9_2to0_9_3update_owner($table, $field)
	{
		global $phpgw_setup;

		$oProc = $phpgw_setup->oProc;
		$oProc->query("select distinct($field) from $table");
		if ($oProc->num_rows()) {
			while ($oProc->next_record())
			{
				$owner[count($owner)] = $oProc->f($field);
			}
			for($i=0;$i<count($owner);$i++)
			{
				$oProc->query("select account_id from accounts where account_lid='".$owner[$i]."'");
				$oProc->next_record();
				$oProc->query("update $table set $field=".$oProc->f("account_id")." where $field='".$owner[$i]."'");
			}
		}

		$oProc->AlterColumn($table, $field, array("type" => "int", "precision" => 4, "nullable" => false, "default" => 0));
	}

	$test[] = "0.9.2";
	function phpgwapi_upgrade0_9_2()
	{
		global $setup_info;

		$setup_info['phpgwapi']['currentver'] = '0.9.3pre4';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = "0.9.3pre4";
	function phpgwapi_upgrade0_9_3pre4()
	{
		global $setup_info, $phpgw_setup;

		$oProc = $phpgw_setup->oProc;
		$oProc->AlterColumn("config", "config_name", array("type" => "varchar", "precision" => 255, "nullable" => false));

		$setup_info['phpgwapi']['currentver'] = '0.9.3pre5';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = "0.9.3pre5";
	function phpgwapi_upgrade0_9_3pre5()
	{
		global $setup_info, $phpgw_setup;

		$oProc = $phpgw_setup->oProc;
		$oProc->CreateTable(
			'categories', array(
				'fd' => array(
					'cat_id' => array('type' => 'auto', 'nullable' => false),
					'account_id' => array('type' => 'int', 'precision' => 4, 'nullable' => false, 'default' => 0),
					'app_name' => array('type' => 'varchar', 'precision' => 25, 'nullable' => false),
					'cat_name' => array('type' => 'varchar', 'precision' => 150, 'nullable' => false),
					'cat_description' => array('type' => 'text', 'nullable' => false)
				),
				'pk' => array('cat_id'),
				'ix' => array(),
				'fk' => array(),
				'uc' => array()
			)
		);

		$setup_info['phpgwapi']['currentver'] = '0.9.3pre6';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = "0.9.3pre6";
	function phpgwapi_upgrade0_9_3pre6()
	{
		global $setup_info, $phpgw_setup;

		$oProc = $phpgw_setup->oProc;
		$oProc->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('transy', 'Translation Management', 0, 13, NULL, '".$setup_info['phpgwapi']['version']."')");

		$setup_info['phpgwapi']['currentver'] = '0.9.3pre7';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = "0.9.3pre7";
	function phpgwapi_upgrade0_9_3pre7()
	{
		global $setup_info, $phpgw_setup;

		$oProc = $phpgw_setup->oProc;
		$oProc->CreateTable('languages', array(
				'fd' => array(
					'lang_id' =>   array('type' => 'varchar', 'precision' => 2, 'nullable' => false),
					'lang_name' => array('type' => 'varchar', 'precision' => 50, 'nullable' => false),
					'available' => array('type' => 'char', 'precision' => 3, 'nullable' => false, 'default' => 'No')
				),
				'pk' => array('lang_id'),
				'ix' => array(),
				'fk' => array(),
				'uc' => array()
			)
		);

		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('AA','Afar','No')");        
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('AB','Abkhazian','No')");   
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('AF','Afrikaans','No')");   
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('AM','Amharic','No')");     
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('AR','Arabic','No')");      
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('AS','Assamese','No')");    
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('AY','Aymara','No')");      
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('AZ','Azerbaijani','No')"); 
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('BA','Bashkir','No')");     
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('BE','Byelorussian','No')");        
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('BG','Bulgarian','No')");   
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('BH','Bihari','No')");      
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('BI','Bislama','No')");     
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('BN','Bengali / Bangla','No')");    
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('BO','Tibetan','No')");     
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('BR','Breton','No')");      
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('CA','Catalan','No')");     
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('CO','Corsican','No')");    
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('CS','Czech','No')");       
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('CY','Welsh','No')");       
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('DA','Danish','Yes')");     
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('DE','German','Yes')");     
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('DZ','Bhutani','No')");     
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('EL','Greek','No')");       
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('EN','English / American','Yes')"); 
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('EO','Esperanto','No')");   
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ES','Spanish','Yes')");    
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ET','Estonian','No')");    
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('EU','Basque','No')");      
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('FA','Persian','No')");     
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('FI','Finnish','No')");     
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('FJ','Fiji','No')");        
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('FO','Faeroese','No')");    
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('FR','French','Yes')");     
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('FY','Frisian','No')");     
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('GA','Irish','No')");       
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('GD','Gaelic / Scots Gaelic','No')");       
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('GL','Galician','No')");    
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('GN','Guarani','No')");     
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('GU','Gujarati','No')");    
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('HA','Hausa','No')");       
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('HI','Hindi','No')");       
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('HR','Croatian','No')");    
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('HU','Hungarian','No')");   
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('HY','Armenian','No')");    
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('IA','Interlingua','No')"); 
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('IE','Interlingue','No')"); 
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('IK','Inupiak','No')");     
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('IN','Indonesian','No')");  
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('IS','Icelandic','No')");   
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('IT','Italian','Yes')");    
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('IW','Hebrew','No')");      
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('JA','Japanese','No')");    
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('JI','Yiddish','No')");     
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('JW','Javanese','No')");    
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('KA','Georgian','No')");    
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('KK','Kazakh','No')");      
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('KL','Greenlandic','No')"); 
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('KM','Cambodian','No')");   
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('KN','Kannada','No')");     
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('KO','Korean','Yes')");     
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('KS','Kashmiri','No')");    
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('KU','Kurdish','No')");     
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('KY','Kirghiz','No')");     
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('LA','Latin','No')");       
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('LN','Lingala','No')");     
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('LO','Laothian','No')");    
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('LT','Lithuanian','No')");  
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('LV','Latvian / Lettish','No')");   
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('MG','Malagasy','No')");    
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('MI','Maori','No')");       
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('MK','Macedonian','No')");  
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ML','Malayalam','No')");   
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('MN','Mongolian','No')");   
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('MO','Moldavian','No')");   
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('MR','Marathi','No')");     
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('MS','Malay','No')");       
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('MT','Maltese','No')");     
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('MY','Burmese','No')");     
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('NA','Nauru','No')");       
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('NE','Nepali','No')");      
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('NL','Dutch','Yes')");      
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('NO','Norwegian','Yes')");  
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('OC','Occitan','No')");     
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('OM','Oromo / Afan','No')");        
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('OR','Oriya','No')");       
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('PA','Punjabi','No')");     
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('PL','Polish','No')");      
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('PS','Pashto / Pushto','No')");     
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('PT','Portuguese','Yes')"); 
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('QU','Quechua','No')");     
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('RM','Rhaeto-Romance','No')");      
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('RN','Kirundi','No')");     
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('RO','Romanian','No')");    
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('RU','Russian','No')");     
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('RW','Kinyarwanda','No')"); 
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SA','Sanskrit','No')");    
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SD','Sindhi','No')");      
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SG','Sangro','No')");      
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SH','Serbo-Croatian','No')");      
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SI','Singhalese','No')");  
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SK','Slovak','No')");      
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SL','Slovenian','No')");   
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SM','Samoan','No')");      
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SN','Shona','No')");       
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SO','Somali','No')");      
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SQ','Albanian','No')");    
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SR','Serbian','No')");     
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SS','Siswati','No')");     
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ST','Sesotho','No')");     
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SU','Sudanese','No')");    
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SV','Swedish','Yes')");     
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SW','Swahili','No')");     
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('TA','Tamil','No')");       
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('TE','Tegulu','No')");      
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('TG','Tajik','No')");       
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('TH','Thai','No')");        
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('TI','Tigrinya','No')");    
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('TK','Turkmen','No')");     
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('TL','Tagalog','No')");     
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('TN','Setswana','No')");    
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('TO','Tonga','No')");       
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('TR','Turkish','No')");     
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('TS','Tsonga','No')");      
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('TT','Tatar','No')");       
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('TW','Twi','No')"); 
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('UK','Ukrainian','No')");   
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('UR','Urdu','No')");        
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('UZ','Uzbek','No')");       
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('VI','Vietnamese','No')");  
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('VO','Volapuk','No')");     
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('WO','Wolof','No')");       
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('XH','Xhosa','No')");       
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('YO','Yoruba','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ZH','Chinese','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ZU','Zulu','No')");

		$setup_info['phpgwapi']['currentver'] = '0.9.3pre8';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = "0.9.3pre8";
	function phpgwapi_upgrade0_9_3pre8()
	{
		global $setup_info, $phpgw_setup;
		$setup_info['phpgwapi']['currentver'] = '0.9.4pre4';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = "0.9.4pre4";
	function phpgwapi_upgrade0_9_4pre4()
	{
		global $setup_info, $phpgw_setup;

		$oProc = $phpgw_setup->oProc;
		$oProc->AlterColumn("sessions", "session_lid", array("type" => "varchar", "precision" => 255));

		$setup_info['phpgwapi']['currentver'] = '0.9.4pre5';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = "0.9.4pre5";
	function phpgwapi_upgrade0_9_4pre5()
	{
		global $setup_info, $phpgw_setup;
		$setup_info['phpgwapi']['currentver'] = '0.9.4';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = "0.9.4";
	function phpgwapi_upgrade0_9_4()
	{
		global $setup_info, $phpgw_setup;

		$oProc = $phpgw_setup->oProc;
		$oProc->query("delete from languages");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('aa','Afar','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ab','Abkhazian','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('af','Afrikaans','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('am','Amharic','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ar','Arabic','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('as','Assamese','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ay','Aymara','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('az','Azerbaijani','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ba','Bashkir','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('be','Byelorussian','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('bg','Bulgarian','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('bh','Bihari','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('bi','Bislama','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('bn','Bengali / Bangla','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('bo','Tibetan','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('br','Breton','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ca','Catalan','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('co','Corsican','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('cs','Czech','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('cy','Welsh','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('da','Danish','Yes')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('de','German','Yes')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('dz','Bhutani','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('el','Greek','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('en','English / American','Yes')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('eo','Esperanto','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('es','Spanish','Yes')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('et','Estonian','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('eu','Basque','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('fa','Persian','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('fi','Finnish','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('fj','Fiji','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('fo','Faeroese','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('fr','French','Yes')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('fy','Frisian','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ga','Irish','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('gd','Gaelic / Scots Gaelic','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('gl','Galician','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('gn','Guarani','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('gu','Gujarati','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ha','Hausa','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('hi','Hindi','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('hr','Croatian','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('hu','Hungarian','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('hy','Armenian','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ia','Interlingua','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ie','Interlingue','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ik','Inupiak','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('in','Indonesian','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('is','Icelandic','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('it','Italian','Yes')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('iw','Hebrew','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ja','Japanese','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ji','Yiddish','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('jw','Javanese','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ka','Georgian','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('kk','Kazakh','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('kl','Greenlandic','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('km','Cambodian','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('kn','Kannada','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ko','Korean','Yes')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ks','Kashmiri','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ku','Kurdish','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ky','Kirghiz','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('la','Latin','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ln','Lingala','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('lo','Laothian','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('lt','Lithuanian','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('lv','Latvian / Lettish','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('mg','Malagasy','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('mi','Maori','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('mk','Macedonian','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ml','Malayalam','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('mn','Mongolian','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('mo','Moldavian','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('mr','Marathi','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ms','Malay','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('mt','Maltese','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('my','Burmese','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('na','Nauru','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ne','Nepali','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('nl','Dutch','Yes')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('no','Norwegian','Yes')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('oc','Occitan','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('om','Oromo / Afan','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('or','Oriya','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('pa','Punjabi','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('pl','Polish','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ps','Pashto / Pushto','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('pt','Portuguese','Yes')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('qu','Quechua','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('rm','Rhaeto-Romance','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('rn','Kirundi','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ro','Romanian','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ru','Russian','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('rw','Kinyarwanda','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('sa','Sanskrit','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('sd','Sindhi','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('sg','Sangro','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('sh','Serbo-Croatian','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('si','Singhalese','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('sk','Slovak','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('sl','Slovenian','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('sm','Samoan','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('sn','Shona','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('so','Somali','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('sq','Albanian','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('sr','Serbian','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ss','Siswati','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('st','Sesotho','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('su','Sudanese','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('sv','Swedish','Yes')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('sw','Swahili','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ta','Tamil','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('te','Tegulu','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('tg','Tajik','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('th','Thai','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ti','Tigrinya','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('tk','Turkmen','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('tl','Tagalog','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('tn','Setswana','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('to','Tonga','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('tr','Turkish','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ts','Tsonga','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('tt','Tatar','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('tw','Twi','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('uk','Ukrainian','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ur','Urdu','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('uz','Uzbek','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('vi','Vietnamese','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('vo','Volapuk','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('wo','Wolof','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('xh','Xhosa','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('yo','Yoruba','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('zh','Chinese','No')");
		@$oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('zu','Zulu','No')");

		$setup_info['phpgwapi']['currentver'] = '0.9.5pre1';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = "0.9.5pre1";
	function phpgwapi_upgrade0_9_5pre1()
	{
		global $phpgw_info, $phpgw_setup;

		$oProc = $phpgw_setup->oProc;
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
		
		$setup_info['phpgwapi']['currentver'] = '0.9.5pre2';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = "0.9.5pre2";
	function phpgwapi_upgrade0_9_5pre2()
	{
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.5';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = "0.9.5";
	function phpgwapi_upgrade0_9_5()
	{
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.6';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = "0.9.6";
	function phpgwapi_upgrade0_9_6()
	{
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.7pre1';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = "0.9.7pre1";
	function phpgwapi_upgrade0_9_7pre1()
	{
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.7pre3';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = "0.9.7pre3";
	function phpgwapi_upgrade0_9_7pre3()
	{
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.7';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = "0.9.7";
	function phpgwapi_upgrade0_9_7()
	{
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.8pre1';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = "0.9.8pre1";
	function phpgwapi_upgrade0_9_8pre1()
	{
		global $setup_info, $phpgw_setup;

		$oProc = $phpgw_setup->oProc;
		$oProc->query("select * from preferences order by preference_owner");
		$t = array();
		while ($oProc->next_record())
		{
			$t[$oProc->f("preference_owner")][$oProc->f("preference_appname")][$oProc->f("preference_var")] = $oProc->f("preference_value");
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

		while ($tt = each($t))
		{
			$oProc->query("insert into preferences values ('$tt[0]','" . serialize($tt[1]) . "')");
		}

		$setup_info['phpgwapi']['currentver'] = '0.9.8pre2';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = "0.9.8pre2";
	function phpgwapi_upgrade0_9_8pre2()
	{
		global $setup_info, $phpgw_setup;
		$setup_info['phpgwapi']['currentver'] = '0.9.8pre3';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = "0.9.8pre3";
	function phpgwapi_upgrade0_9_8pre3()
	{
		global $setup_info, $phpgw_setup;

		$oProc = $phpgw_setup->oProc;
		$oProc->DropTable("phpgw_sessions");
		$oProc->CreateTable(
			"phpgw_sessions", array(
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
			)
		);

		$setup_info['phpgwapi']['currentver'] = '0.9.8pre4';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = "0.9.8pre4";
	function phpgwapi_upgrade0_9_8pre4()
	{
		global $setup_info, $phpgw_setup;

		$oProc = $phpgw_setup->oProc;
		$oProc->CreateTable(
			"phpgw_hooks", array(
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
			)
		);

		$setup_info['phpgwapi']['currentver'] = '0.9.8pre5';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = "0.9.8pre5";
	function phpgwapi_upgrade0_9_8pre5()
	{
		global $setup_info, $phpgw_setup;

		$oProc = $phpgw_setup->oProc;
		// Since no applications are using it yet.  I am gonna drop it and create a new one.
		// This is becuase I never finished the classes
		$oProc->DropTable('categories');

		$oProc->CreateTable(
			'phpgw_categories', array(
			"fd" => array(
				'cat_id' => array('type' => 'auto', 'default' => '0', 'nullable' => false),
				'cat_parent' => array('type' => 'int', 'precision' => 4, 'default' => '0', 'nullable' => false),
				'cat_owner' => array('type' => 'int', 'precision' => 4, 'default' => '0', 'nullable' => false),
				'cat_appname' => array('type' => 'varchar', 'precision'  => 50, 'nullable' => false),
				'cat_name' => array('type' => 'varchar', 'precision'  => 150, 'nullable' => false),
				'cat_description' => array('type' => 'varchar', 'precision'  => 255, 'nullable' => false),
				'cat_data' => array('type' => 'text')
			),
				'pk' => array('cat_id'),
				'ix' => array(),
				'fk' => array(),
				'uc' => array()
			)
		);

		$setup_info['phpgwapi']['currentver'] = '0.9.9pre1';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = "0.9.9pre1";
	function phpgwapi_upgrade0_9_9pre1()
	{
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.9';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = "0.9.9";
	function phpgwapi_upgrade0_9_9()
	{
		global $setup_info, $phpgw_setup;

		$oProc = $phpgw_setup->oProc;
		$db2 = $oProc;
		//convert user settings
		$oProc->query("select account_id, account_permissions from accounts",__LINE__,__FILE__);
		if($oProc->num_rows())
		{
			while($oProc->next_record())
			{
				$apps_perms = explode(":",$oProc->f("account_permissions"));
				for($i=1;$i<count($apps_perms)-1;$i++)
				{
					if ($apps_perms[$i] != "")
					{
						$sql = "insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights)";
						$sql .= " values('".$apps_perms[$i]."', 'run', ".$oProc->f("account_id").", 'u', 1)";
						$db2->query($sql ,__LINE__,__FILE__);
					}
				}
			}
		}
		$oProc->query("update accounts set account_permissions = ''",__LINE__,__FILE__);
		//convert group settings
		$oProc->query("select group_id, group_apps from groups",__LINE__,__FILE__);
		if($oProc->num_rows())
		{
			while($oProc->next_record())
			{
				$apps_perms = explode(":",$oProc->f("group_apps"));
				for($i=1;$i<count($apps_perms)-1;$i++)
				{
					if ($apps_perms[$i] != "")
					{
						$sql = "insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights)";
						$sql .= " values('".$apps_perms[$i]."', 'run', ".$oProc->f("group_id").", 'g', 1)";
						$db2->query($sql ,__LINE__,__FILE__);
					}
				}
			}
		}
		$oProc->query("update groups set group_apps = ''",__LINE__,__FILE__);

		$setup_info['phpgwapi']['currentver'] = '0.9.10pre1';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = "0.9.10pre1";                                                                                                                                                    
	function phpgwapi_upgrade0_9_10pre1()
	{
		global $setup_info, $phpgw_setup;

		$oProc = $phpgw_setup->oProc;
		$oProc->query("alter table phpgw_categories add column cat_access varchar(25) after cat_owner");

		$setup_info['phpgwapi']['currentver'] = '0.9.10pre2';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = "0.9.10pre2";
	function phpgwapi_upgrade0_9_10pre2()
	{
		global $setup_info, $phpgw_setup;

		$oProc = $phpgw_setup->oProc;
		$db2 = $oProc;

		$oProc->query("SELECT account_groups,account_id FROM accounts",__LINE__,__FILE__);
		if($oProc->num_rows())
		{
			while($oProc->next_record())
			{
				$gl = explode(",",$oProc->f("account_groups"));
				for ($i=1; $i<(count($gl)-1); $i++)
				{
					$ga = explode(":",$gl[$i]);
					$sql = "INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights)";
					$sql .= " VALUES('phpgw_group', '".$ga[0]."', ".$oProc->f("account_id").", 'u', 1)";
					$db2->query($sql ,__LINE__,__FILE__);
				}
			}
		}
		$oProc->query("UPDATE accounts SET account_groups= ''",__LINE__,__FILE__);

		$setup_info['phpgwapi']['currentver'] = '0.9.10pre3';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = "0.9.10pre3";
	function phpgwapi_upgrade0_9_10pre3()
	{
		global $setup_info, $phpgw_setup;

		$oProc = $phpgw_setup->oProc;
		$oProc->query("alter table accounts rename phpgw_accounts",__LINE__,__FILE__);
		$oProc->query("alter table phpgw_accounts drop column account_permissions",__LINE__,__FILE__);
		$oProc->query("alter table phpgw_accounts drop column account_groups",__LINE__,__FILE__);
		$oProc->query("alter table phpgw_accounts add column account_type char(1)",__LINE__,__FILE__);
		$oProc->query("update phpgw_accounts set account_type='u'",__LINE__,__FILE__);

		$setup_info['phpgwapi']['currentver'] = '0.9.10pre4';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	// TODO see next function
/*
		$tables = Array('addressbook','calendar_entry','f_forums','todo');
		$fields["addressbook"] = Array('ab_access','ab_id');
		$fields["calendar_entry"] = Array('cal_group','cal_id');
		$fields["f_forums"] = Array('groups','id');
		$fields["phpgw_categories"] = Array('cat_access','cat_id');
		$fields["todo"] = Array('todo_access','todo_id');
*/
	function change_groups($table,$field,$old_id,$new_id,$db2,$db3)
	{
		$sql = $field[0];
		for($i=1;$i<count($field);$i++)
		{
			$sql .= ", ".$field[$i];
		}
		$db2->query("SELECT $sql FROM $table WHERE $field[0] like '%,".$old_id.",%'",__LINE__,__FILE__);
		if($db2->num_rows())
		{
			while($db2->next_record())
			{
				$access = $db2->f($field[0]);
				$id = $db2->f($field[1]);
				$access = str_replace(','.$old_id.',' , ','.$new_id.',' , $access);
				$db3->query("UPDATE $table SET ".$field[0]."='".$access."' WHERE ".$field[1]."=".$id,__LINE__,__FILE__);
			}
		}
	}

	$test[] = "0.9.10pre4";
	function phpgwapi_upgrade0_9_10pre4()
	{
		global $setup_info, $phpgw_setup;

		$oProc = $phpgw_setup->oProc;
		$db2 = $oProc;
		$db3 = $oProc;

		$oProc->query("SELECT MAX(group_id) FROM groups",__LINE__,__FILE__);
		$oProc->next_record();
		$max_group_id = $oProc->f(0);

		// This is for use by former CORE apps to use in this version number's upgrade locally
		$oProc->CreateTable(
			'phpgw_temp_groupmap', array(
				'fd' => array(
					'oldid'  => array('type' => 'int', 'precision' => 4, 'nullable' => False),
					'oldlid' => array('type' => 'varchar', 'precision' => 255, 'nullable' => True),
					'newid'  => array('type' => 'int', 'precision' => 4, 'nullable' => True),
					'newlid' => array('type' => 'varchar', 'precision' => 255, 'nullable' => True)
				),
				'pk' => array('oldid'),
				'fk' => array(),
				'ix' => array(),
				'uc' => array()
			)
		);

		$oProc->query("SELECT group_id, group_name FROM groups",__LINE__,__FILE__);
		while($oProc->next_record())
		{
			$old_group_id = $oProc->f("group_id");
			$old_group_name = $oProc->f("group_name");
			$group_name = $oProc->f("group_name");
			while(1)
			{
				$new_group_id = mt_rand ($max_group_id, 60000);
				$db2->query("SELECT account_id FROM phpgw_accounts WHERE account_id=$new_group_id",__LINE__,__FILE__);
				if(!$db2->num_rows()) { break; }
			}
			$db2->query("SELECT account_lid FROM phpgw_accounts WHERE account_lid='$group_name'",__LINE__,__FILE__);
			if($db2->num_rows())
			{
				$group_name .= "_group";
			}
			$db2->query("INSERT INTO phpgw_accounts(account_id, account_lid, account_pwd, "
				."account_firstname, account_lastname, account_lastlogin, "
				."account_lastloginfrom, account_lastpwd_change, "
				."account_status, account_type) "
				."VALUES ($new_group_id,'$group_name','x','','',$old_group_id,NULL,NULL,'A','g')");

			// insert oldid/newid into temp table (for use by other apps in this version upgrade
			$db2->query("INSERT INTO phpgw_temp_groupmap (oldid,oldlid,newid,newlid) VALUES ($old_group_id,'$old_group_name',$new_group_id,'$group_name')",__LINE__,__FILE__);

			$db2->query("UPDATE phpgw_acl SET acl_location='$new_group_id' "
				."WHERE acl_appname='phpgw_group' AND acl_account_type='u' "
				."AND acl_location='$old_group_id'");
			$db2->query("UPDATE phpgw_acl SET acl_account=$new_group_id "
				."WHERE acl_location='run' AND acl_account_type='g' "
				."AND acl_account=$old_group_id");

			$db2->query("SELECT cat_access,cat_id FROM phpgw_categories WHERE cat_access LIKE '%,".$old_group_id.",%'",__LINE__,__FILE__);
			if($db2->num_rows())
			{
				while($db2->next_record())
				{
					$access = $db2->f('cat_access');
					$id     = $db2->f('cat_id');
					$access = str_replace(','.$old_group_id.',' , ','.$new_group_id.',' , $access);
					$db3->query("UPDATE phpgw_categories SET cat_access='".$access."' WHERE cat_id=".$id,__LINE__,__FILE__);
				}
			}
		}

		$oProc->DropTable('groups');
		$setup_info["phpgwapi"]["currentver"] = "0.9.10pre5";
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}
 
	$test[] = "0.9.10pre5";
	function phpgwapi_upgrade0_9_10pre5()
	{
		global $setup_info, $phpgw_setup;

		$oProc = $phpgw_setup->oProc;
		// This is only temp data, so we can kill it.
		$oProc->DropTable('phpgw_app_sessions');
		$oProc->CreateTable(
			'phpgw_app_sessions', array(
				'fd' => array(
					'sessionid' => array('type' => 'varchar', 'precision' => 255, 'nullable' => False),
					'loginid' => array('type' => 'varchar', 'precision' => 20),
					'location' => array('type' => 'varchar', 'precision' => 255),
					'app' => array('type' => 'varchar', 'precision' => 20),
					'content' => array('type' => 'text')
				),
				'pk' => array(),
				'fk' => array(),
				'ix' => array(),
				'uc' => array()
			)
		);

		$setup_info['phpgwapi']['currentver'] = '0.9.10pre6';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = "0.9.10pre6";
	function phpgwapi_upgrade0_9_10pre6()
	{
		global $setup_info, $phpgw_setup;

		$oProc = $phpgw_setup->oProc;
		$oProc->RenameTable('config','phpgw_config');

		$setup_info['phpgwapi']['currentver'] = '0.9.10pre7';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = "0.9.10pre7";
	function phpgwapi_upgrade0_9_10pre7()
	{
		global $setup_info, $phpgw_setup;

		$oProc = $phpgw_setup->oProc;
		$oProc->RenameTable('applications','phpgw_applications');

		$setup_info['phpgwapi']['currentver'] = '0.9.10pre8';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = "0.9.10pre8";
	function phpgwapi_upgrade0_9_10pre8()
	{
		global $setup_info, $phpgw_setup;

		$oProc = $phpgw_setup->oProc;
		$oProc->RenameColumn('phpgw_sessions', 'session_info', 'session_action');
		$oProc->AlterColumn('phpgw_sessions', 'session_action', array('type' => 'varchar', 'precision' => '255'));

		$setup_info['phpgwapi']['currentver'] = '0.9.10pre9';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.10pre9';
	function phpgwapi_upgrade0_9_10pre9()
	{
		global $setup_info, $phpgw_setup;

		$oProc = $phpgw_setup->oProc;
		$oProc->RenameTable('preferences','phpgw_preferences');

		$setup_info['phpgwapi']['currentver'] = '0.9.10pre10';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.10pre10';
	function phpgwapi_upgrade0_9_10pre10()
	{
		global $setup_info, $phpgw_setup;

		$oProc = $phpgw_setup->oProc;
		$newtbldef = array(
			"fd" => array(
				'acl_appname' => array('type' => 'varchar', 'precision' => 50),
				'acl_location' => array('type' => 'varchar', 'precision' => 255),
				'acl_account' => array('type' => 'int', 'precision' => 4),
				'acl_rights' => array('type' => 'int', 'precision' => 4)
			),
			'pk' => array(),
			'ix' => array(),
			'fk' => array(),
			'uc' => array()
		);

		$oProc->DropColumn('phpgw_acl',$newtbldef,'acl_account_type');

		$setup_info['phpgwapi']['currentver'] = '0.9.10pre11';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.10pre11';
	function phpgwapi_upgrade0_9_10pre11()
	{
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.10pre12';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

    $test[] = '0.9.10pre12';
    function phpgwapi_upgrade0_9_10pre12()
    {
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.10pre13';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

    $test[] = '0.9.10pre13';
    function phpgwapi_upgrade0_9_10pre13()
    {
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.10pre14';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.10pre14';
	function phpgwapi_upgrade0_9_10pre14()
	{
		global $setup_info, $phpgw_setup;

		$oProc = $phpgw_setup->oProc;
		$oProc->AddColumn('phpgw_sessions','session_flags', array('type' => 'char', 'precision' => 2));

		$setup_info['phpgwapi']['currentver'] = '0.9.10pre15';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.10pre15';
	function phpgwapi_upgrade0_9_10pre15()
	{
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.10pre16';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

    $test[] = '0.9.10pre16';
    function phpgwapi_upgrade0_9_10pre16()
    {
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.10pre17';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

    $test[] = '0.9.10pre17';
    function phpgwapi_upgrade0_9_10pre17()
    {
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.10pre18';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.10pre18';
	function phpgwapi_upgrade0_9_10pre18()
	{
		global $setup_info, $phpgw_setup;

		$oProc = $phpgw_setup->oProc;
		$oProc->CreateTable(
			'phpgw_nextid', array(
				'fd' => array(
					'appname' => array('type' => 'varchar', 'precision' => 25),
					'id' => array('type' => 'int', 'precision' => 4)
				),
				'pk' => array(),
				'fk' => array(),
				'ix' => array(),
				'uc' => array('appname')
			)
		);

		$setup_info['phpgwapi']['currentver'] = '0.9.10pre19';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.10pre19';
	function phpgwapi_upgrade0_9_10pre19()
	{
		global $setup_info, $phpgw_setup;

		$oProc = $phpgw_setup->oProc;
		$oProc->DropTable('phpgw_nextid');

		$oProc->CreateTable(
			'phpgw_nextid', array(
				'fd' => array(
					'appname' => array('type' => 'varchar', 'precision' => 25, 'nullable' => False),
					'id' => array('type' => 'int', 'precision' => 4),
				),
				'pk' => array(),
				'fk' => array(),
				'ix' => array(),
				'uc' => array('appname')
			)
		);

		$setup_info['phpgwapi']['currentver'] = '0.9.10pre20';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.10pre20';
	function phpgwapi_upgrade0_9_10pre20()
	{
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.10pre21';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.10pre21';
	function phpgwapi_upgrade0_9_10pre21()
	{
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.10pre22';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.10pre22';
	function phpgwapi_upgrade0_9_10pre22()
	{
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.10pre23';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.10pre23';
	function phpgwapi_upgrade0_9_10pre23()
	{
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.10pre24';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.10pre24';
	function phpgwapi_upgrade0_9_10pre24()
	{
		global $setup_info, $phpgw_setup;

		$oProc = $phpgw_setup->oProc;
		$oProc->AlterColumn('phpgw_categories','cat_access', array('type' => 'char', 'precision' => 7));

		$setup_info['phpgwapi']['currentver'] = '0.9.10pre25';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.10pre25';
	function phpgwapi_upgrade0_9_10pre25()
	{
		global $setup_info, $phpgw_setup;

		$oProc = $phpgw_setup->oProc;
		$oProc->AddColumn('phpgw_app_sessions','session_dla', array('type' => 'int', 'precision' => 4));

		$setup_info['phpgwapi']['currentver'] = '0.9.10pre26';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.10pre26';
	function phpgwapi_upgrade0_9_10pre26()
	{
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.10pre27';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.10pre27';
	function phpgwapi_upgrade0_9_10pre27()
	{
		global $setup_info, $phpgw_setup;

		$oProc = $phpgw_setup->oProc;
		$oProc->AlterColumn('phpgw_app_sessions', 'content', array('type' => 'longtext'));

		$setup_info['phpgwapi']['currentver'] = '0.9.10pre28';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.10pre28';
	function phpgwapi_upgrade0_9_10pre28()
	{
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.10';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.10';
	function phpgwapi_upgrade0_9_10()
	{
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.11.001';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.11.001';
	function phpgwapi_upgrade0_9_11_001()
	{
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.11.002';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.11.002';
	function phpgwapi_upgrade0_9_11_002()
	{
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.11.003';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.11.002';
	function upgrade0_9_11_002()
	{
		global $phpgw_info, $phpgw_setup;

		$oProc = $phpgw_setup->oProc;
		$oProc->AddColumn('phpgw_categories','cat_main',array('type' => 'int', 'precision' => 4, 'default' => 0, 'nullable' => False));
		$oProc->AddColumn('phpgw_categories','cat_level',array('type' => 'int', 'precision' => 4, 'default' => 0, 'nullable' => False));

		$setup_info['phpgwapi']['currentver'] = '0.9.11.003';
		return $setup_info['phpgwapi']['currentver'];
	}

	$test[] = '0.9.11.003';
	function phpgwapi_upgrade0_9_11_003()
	{
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.11.004';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.11.004';
	function phpgwapi_upgrade0_9_11_004()
	{
		global $setup_info, $phpgw_setup;

		$oProc = $phpgw_setup->oProc;
		$oProc->AddColumn('phpgw_config','config_app', array('type' => 'varchar', 'precision' => 50));
		$oProc->query("UPDATE phpgw_config SET config_app='phpgwapi'",__LINE__,__FILE__);

		$setup_info['phpgwapi']['currentver'] = '0.9.11.005';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.11.005';
	function phpgwapi_upgrade0_9_11_005()
	{
		global $setup_info, $phpgw_setup;

		$oProc = $phpgw_setup->oProc;
		$oProc->AddColumn('phpgw_accounts','account_expires', array('type' => 'int', 'precision' => 4));
		$oProc->query("UPDATE phpgw_accounts SET account_expires='-1'",__LINE__,__FILE__);

		$setup_info['phpgwapi']['currentver'] = '0.9.11.006';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.11.006';
	function phpgwapi_upgrade0_9_11_006()
	{
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.11.007';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.11.007';
	function phpgwapi_upgrade0_9_11_007()
	{
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.11.008';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.11.008';
	function phpgwapi_upgrade0_9_11_008()
	{
		global $setup_info, $phpgw_setup;

		$oProc = $phpgw_setup->oProc;
		$oProc->DropTable('profiles');
		
		$setup_info['phpgwapi']['currentver'] = '0.9.11.009';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.11.009';
	function phpgwapi_upgrade0_9_11_009()
	{
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.11.010';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.11.010';
	function phpgwapi_upgrade0_9_11_010()
	{
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.13.001';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.13.001';
	function phpgwapi_upgrade0_9_13_001()
	{
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.13.002';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}
?>
