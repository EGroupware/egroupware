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

		$phpgw_setup->oProc->AlterColumn("access_log", "lo", array("type" => "varchar", "precision" => 255));

		$phpgw_setup->oProc->query("update lang set lang='da' where lang='dk'");
		$phpgw_setup->oProc->query("update lang set lang='ko' where lang='kr'");

		$phpgw_setup->oProc->AddColumn('addressbook', 'ab_company_id', array('type' => 'int', 'precision' => 4));
		$phpgw_setup->oProc->AddColumn('addressbook', 'ab_title', array('type' => 'varchar', 'precision' => 60));
		$phpgw_setup->oProc->AddColumn('addressbook', 'ab_address2', array('type' => 'varchar', 'precision' => 60));

		$phpgw_setup->oProc->query("update preferences set preference_name='da' where preference_name='dk'");
		$phpgw_setup->oProc->query("update preferences set preference_name='ko' where preference_name='kr'");

		//install weather support
		$phpgw_setup->oProc->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('weather', 'Weather', 1, 12, NULL, '".$phpgw_info["server"]["version"]."')");
		$phpgw_setup->oProc->query("INSERT INTO lang (message_id, app_name, lang, content) VALUES( 'weather','Weather','en','weather')");

		$setup_info['phpgwapi']['currentver'] = '0.9.2';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	function phpgwapi_v0_9_2to0_9_3update_owner($table, $field)
	{
		global $phpgw_setup;

		$phpgw_setup->oProc->query("select distinct($field) from $table");
		if ($phpgw_setup->oProc->num_rows()) {
			while ($phpgw_setup->oProc->next_record())
			{
				$owner[count($owner)] = $phpgw_setup->oProc->f($field);
			}
			for($i=0;$i<count($owner);$i++)
			{
				$phpgw_setup->oProc->query("select account_id from accounts where account_lid='".$owner[$i]."'");
				$phpgw_setup->oProc->next_record();
				$phpgw_setup->oProc->query("update $table set $field=".$phpgw_setup->oProc->f("account_id")." where $field='".$owner[$i]."'");
			}
		}

		$phpgw_setup->oProc->AlterColumn($table, $field, array("type" => "int", "precision" => 4, "nullable" => false, "default" => 0));
	}

	$test[] = "0.9.2";
	function phpgwapi_upgrade0_9_2()
	{
		global $setup_info;

		$setup_info['phpgwapi']['currentver'] = '0.9.3pre1';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}
	$test[] = "0.9.3pre1";
	function phpgwapi_upgrade0_9_3pre1()
	{
		global $setup_info;

		$setup_info['phpgwapi']['currentver'] = '0.9.3pre2';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = "0.9.3pre2";
	function phpgwapi_upgrade0_9_3pre2()
	{
		global $setup_info;

		$setup_info['phpgwapi']['currentver'] = '0.9.3pre3';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = "0.9.3pre3";
	function phpgwapi_upgrade0_9_3pre3()
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

		$phpgw_setup->oProc->AlterColumn("config", "config_name", array("type" => "varchar", "precision" => 255, "nullable" => false));

		$setup_info['phpgwapi']['currentver'] = '0.9.3pre5';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = "0.9.3pre5";
	function phpgwapi_upgrade0_9_3pre5()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->CreateTable(
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

		$phpgw_setup->oProc->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('transy', 'Translation Management', 0, 13, NULL, '".$setup_info['phpgwapi']['version']."')");

		$phpgw_setup->oProc->AddColumn('addressbook', 'ab_url', array('type' => 'varchar', 'precision' => 255));

		$setup_info['phpgwapi']['currentver'] = '0.9.3pre7';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = "0.9.3pre7";
	function phpgwapi_upgrade0_9_3pre7()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->CreateTable('languages', array(
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

		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('AA','Afar','No')");        
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('AB','Abkhazian','No')");   
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('AF','Afrikaans','No')");   
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('AM','Amharic','No')");     
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('AR','Arabic','No')");      
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('AS','Assamese','No')");    
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('AY','Aymara','No')");      
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('AZ','Azerbaijani','No')"); 
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('BA','Bashkir','No')");     
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('BE','Byelorussian','No')");        
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('BG','Bulgarian','No')");   
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('BH','Bihari','No')");      
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('BI','Bislama','No')");     
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('BN','Bengali / Bangla','No')");    
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('BO','Tibetan','No')");     
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('BR','Breton','No')");      
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('CA','Catalan','No')");     
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('CO','Corsican','No')");    
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('CS','Czech','No')");       
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('CY','Welsh','No')");       
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('DA','Danish','Yes')");     
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('DE','German','Yes')");     
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('DZ','Bhutani','No')");     
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('EL','Greek','No')");       
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('EN','English / American','Yes')"); 
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('EO','Esperanto','No')");   
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ES','Spanish','Yes')");    
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ET','Estonian','No')");    
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('EU','Basque','No')");      
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('FA','Persian','No')");     
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('FI','Finnish','No')");     
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('FJ','Fiji','No')");        
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('FO','Faeroese','No')");    
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('FR','French','Yes')");     
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('FY','Frisian','No')");     
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('GA','Irish','No')");       
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('GD','Gaelic / Scots Gaelic','No')");       
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('GL','Galician','No')");    
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('GN','Guarani','No')");     
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('GU','Gujarati','No')");    
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('HA','Hausa','No')");       
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('HI','Hindi','No')");       
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('HR','Croatian','No')");    
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('HU','Hungarian','No')");   
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('HY','Armenian','No')");    
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('IA','Interlingua','No')"); 
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('IE','Interlingue','No')"); 
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('IK','Inupiak','No')");     
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('IN','Indonesian','No')");  
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('IS','Icelandic','No')");   
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('IT','Italian','Yes')");    
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('IW','Hebrew','No')");      
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('JA','Japanese','No')");    
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('JI','Yiddish','No')");     
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('JW','Javanese','No')");    
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('KA','Georgian','No')");    
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('KK','Kazakh','No')");      
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('KL','Greenlandic','No')"); 
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('KM','Cambodian','No')");   
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('KN','Kannada','No')");     
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('KO','Korean','Yes')");     
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('KS','Kashmiri','No')");    
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('KU','Kurdish','No')");     
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('KY','Kirghiz','No')");     
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('LA','Latin','No')");       
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('LN','Lingala','No')");     
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('LO','Laothian','No')");    
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('LT','Lithuanian','No')");  
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('LV','Latvian / Lettish','No')");   
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('MG','Malagasy','No')");    
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('MI','Maori','No')");       
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('MK','Macedonian','No')");  
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ML','Malayalam','No')");   
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('MN','Mongolian','No')");   
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('MO','Moldavian','No')");   
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('MR','Marathi','No')");     
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('MS','Malay','No')");       
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('MT','Maltese','No')");     
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('MY','Burmese','No')");     
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('NA','Nauru','No')");       
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('NE','Nepali','No')");      
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('NL','Dutch','Yes')");      
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('NO','Norwegian','Yes')");  
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('OC','Occitan','No')");     
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('OM','Oromo / Afan','No')");        
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('OR','Oriya','No')");       
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('PA','Punjabi','No')");     
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('PL','Polish','No')");      
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('PS','Pashto / Pushto','No')");     
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('PT','Portuguese','Yes')"); 
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('QU','Quechua','No')");     
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('RM','Rhaeto-Romance','No')");      
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('RN','Kirundi','No')");     
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('RO','Romanian','No')");    
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('RU','Russian','No')");     
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('RW','Kinyarwanda','No')"); 
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SA','Sanskrit','No')");    
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SD','Sindhi','No')");      
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SG','Sangro','No')");      
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SH','Serbo-Croatian','No')");      
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SI','Singhalese','No')");  
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SK','Slovak','No')");      
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SL','Slovenian','No')");   
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SM','Samoan','No')");      
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SN','Shona','No')");       
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SO','Somali','No')");      
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SQ','Albanian','No')");    
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SR','Serbian','No')");     
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SS','Siswati','No')");     
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ST','Sesotho','No')");     
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SU','Sudanese','No')");    
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SV','Swedish','Yes')");     
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SW','Swahili','No')");     
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('TA','Tamil','No')");       
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('TE','Tegulu','No')");      
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('TG','Tajik','No')");       
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('TH','Thai','No')");        
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('TI','Tigrinya','No')");    
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('TK','Turkmen','No')");     
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('TL','Tagalog','No')");     
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('TN','Setswana','No')");    
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('TO','Tonga','No')");       
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('TR','Turkish','No')");     
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('TS','Tsonga','No')");      
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('TT','Tatar','No')");       
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('TW','Twi','No')"); 
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('UK','Ukrainian','No')");   
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('UR','Urdu','No')");        
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('UZ','Uzbek','No')");       
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('VI','Vietnamese','No')");  
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('VO','Volapuk','No')");     
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('WO','Wolof','No')");       
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('XH','Xhosa','No')");       
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('YO','Yoruba','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ZH','Chinese','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ZU','Zulu','No')");

		$setup_info['phpgwapi']['currentver'] = '0.9.3pre8';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = "0.9.3pre8";
	function phpgwapi_upgrade0_9_3pre8()
	{
		global $setup_info, $phpgw_setup;
		$setup_info['phpgwapi']['currentver'] = '0.9.3pre9';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}
	$test[] = "0.9.3pre9";
	function phpgwapi_upgrade0_9_3pre9()
	{
		global $setup_info, $phpgw_setup;
		$setup_info['phpgwapi']['currentver'] = '0.9.3pre10';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}
	$test[] = "0.9.3pre10";
	function phpgwapi_upgrade0_9_3pre10()
	{
		global $setup_info, $phpgw_setup;
		$setup_info['phpgwapi']['currentver'] = '0.9.3';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}
	$test[] = "0.9.3";
	function phpgwapi_upgrade0_9_3()
	{
		global $setup_info, $phpgw_setup;
		$setup_info['phpgwapi']['currentver'] = '0.9.4pre1';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}
	$test[] = "0.9.4pre1";
	function phpgwapi_upgrade0_9_4pre1()
	{
		global $setup_info, $phpgw_setup;
		$setup_info['phpgwapi']['currentver'] = '0.9.4pre2';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}
	$test[] = "0.9.4pre2";
	function phpgwapi_upgrade0_9_4pre2()
	{
		global $setup_info, $phpgw_setup;
		$setup_info['phpgwapi']['currentver'] = '0.9.4pre3';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}
	$test[] = "0.9.4pre3";
	function phpgwapi_upgrade0_9_4pre3()
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

		$phpgw_setup->oProc->AlterColumn("sessions", "session_lid", array("type" => "varchar", "precision" => 255));

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

		$phpgw_setup->oProc->query("delete from languages");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('aa','Afar','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ab','Abkhazian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('af','Afrikaans','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('am','Amharic','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ar','Arabic','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('as','Assamese','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ay','Aymara','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('az','Azerbaijani','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ba','Bashkir','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('be','Byelorussian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('bg','Bulgarian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('bh','Bihari','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('bi','Bislama','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('bn','Bengali / Bangla','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('bo','Tibetan','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('br','Breton','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ca','Catalan','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('co','Corsican','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('cs','Czech','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('cy','Welsh','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('da','Danish','Yes')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('de','German','Yes')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('dz','Bhutani','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('el','Greek','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('en','English / American','Yes')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('eo','Esperanto','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('es','Spanish','Yes')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('et','Estonian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('eu','Basque','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('fa','Persian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('fi','Finnish','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('fj','Fiji','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('fo','Faeroese','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('fr','French','Yes')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('fy','Frisian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ga','Irish','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('gd','Gaelic / Scots Gaelic','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('gl','Galician','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('gn','Guarani','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('gu','Gujarati','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ha','Hausa','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('hi','Hindi','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('hr','Croatian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('hu','Hungarian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('hy','Armenian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ia','Interlingua','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ie','Interlingue','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ik','Inupiak','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('in','Indonesian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('is','Icelandic','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('it','Italian','Yes')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('iw','Hebrew','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ja','Japanese','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ji','Yiddish','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('jw','Javanese','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ka','Georgian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('kk','Kazakh','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('kl','Greenlandic','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('km','Cambodian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('kn','Kannada','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ko','Korean','Yes')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ks','Kashmiri','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ku','Kurdish','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ky','Kirghiz','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('la','Latin','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ln','Lingala','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('lo','Laothian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('lt','Lithuanian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('lv','Latvian / Lettish','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('mg','Malagasy','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('mi','Maori','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('mk','Macedonian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ml','Malayalam','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('mn','Mongolian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('mo','Moldavian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('mr','Marathi','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ms','Malay','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('mt','Maltese','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('my','Burmese','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('na','Nauru','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ne','Nepali','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('nl','Dutch','Yes')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('no','Norwegian','Yes')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('oc','Occitan','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('om','Oromo / Afan','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('or','Oriya','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('pa','Punjabi','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('pl','Polish','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ps','Pashto / Pushto','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('pt','Portuguese','Yes')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('qu','Quechua','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('rm','Rhaeto-Romance','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('rn','Kirundi','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ro','Romanian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ru','Russian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('rw','Kinyarwanda','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('sa','Sanskrit','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('sd','Sindhi','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('sg','Sangro','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('sh','Serbo-Croatian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('si','Singhalese','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('sk','Slovak','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('sl','Slovenian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('sm','Samoan','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('sn','Shona','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('so','Somali','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('sq','Albanian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('sr','Serbian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ss','Siswati','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('st','Sesotho','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('su','Sudanese','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('sv','Swedish','Yes')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('sw','Swahili','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ta','Tamil','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('te','Tegulu','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('tg','Tajik','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('th','Thai','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ti','Tigrinya','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('tk','Turkmen','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('tl','Tagalog','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('tn','Setswana','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('to','Tonga','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('tr','Turkish','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ts','Tsonga','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('tt','Tatar','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('tw','Twi','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('uk','Ukrainian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ur','Urdu','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('uz','Uzbek','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('vi','Vietnamese','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('vo','Volapuk','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('wo','Wolof','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('xh','Xhosa','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('yo','Yoruba','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('zh','Chinese','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('zu','Zulu','No')");

		$setup_info['phpgwapi']['currentver'] = '0.9.5pre1';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = "0.9.5pre1";
	function phpgwapi_upgrade0_9_5pre1()
	{
		global $phpgw_info, $phpgw_setup;

		$phpgw_setup->oProc->DropTable("sessions");
		$phpgw_setup->oProc->CreateTable("phpgw_sessions", array(
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

		$phpgw_setup->oProc->CreateTable("phpgw_acl", array(
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

		$phpgw_setup->oProc->DropTable("app_sessions");
		$phpgw_setup->oProc->CreateTable("phpgw_app_sessions", array(
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

		$phpgw_setup->oProc->DropTable("access_log");
		$phpgw_setup->oProc->CreateTable("phpgw_access_log", array(
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
		$setup_info['phpgwapi']['currentver'] = '0.9.7pre2';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}
	$test[] = "0.9.7pre2";
	function phpgwapi_upgrade0_9_7pre2()
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

		$phpgw_setup->oProc->query("SELECT * FROM preferences ORDER BY preference_owner");
		$t = array();
		while ($phpgw_setup->oProc->next_record())
		{
			$t[$phpgw_setup->oProc->f("preference_owner")][$phpgw_setup->oProc->f("preference_appname")][$phpgw_setup->oProc->f("preference_var")] = $phpgw_setup->oProc->f("preference_value");
		}

		$phpgw_setup->oProc->DropTable("preferences");
		$phpgw_setup->oProc->CreateTable("preferences", array(
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
			$phpgw_setup->oProc->query("insert into preferences values ('$tt[0]','" . serialize($tt[1]) . "')");
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

		$phpgw_setup->oProc->DropTable("phpgw_sessions");
		$phpgw_setup->oProc->CreateTable(
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

		$phpgw_setup->oProc->CreateTable(
			'phpgw_hooks', array(
				'fd' => array(
					'hook_id' =>       array('type' => 'auto', 'nullable' => false),
					'hook_appname' =>  array('type' => 'varchar', 'precision' => 255),
					'hook_location' => array('type' => 'varchar', 'precision' => 255),
					'hook_filename' => array('type' => 'varchar', 'precision' => 255)
				),
				'pk' => array("hook_id"),
				'ix' => array(),
				'fk' => array(),
				'uc' => array()
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

		// Since no applications are using it yet.  I am gonna drop it and create a new one.
		// This is becuase I never finished the classes
		$phpgw_setup->oProc->DropTable('categories');

		$phpgw_setup->oProc->CreateTable(
			'phpgw_categories', array(
				'fd' => array(
					'cat_id' =>      array('type' => 'auto', 'nullable' => false),
					'cat_parent' =>  array('type' => 'int', 'precision' => 4, 'default' => 0, 'nullable' => false),
					'cat_owner' =>   array('type' => 'int', 'precision' => 4, 'default' => 0, 'nullable' => false),
					'cat_appname' => array('type' => 'varchar', 'precision'  => 50, 'nullable' => false),
					'cat_name' =>    array('type' => 'varchar', 'precision'  => 150, 'nullable' => false),
					'cat_description' => array('type' => 'varchar', 'precision'  => 255, 'nullable' => false),
					'cat_data' =>    array('type' => 'text')
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

		$db2 = $phpgw_setup->db;
		//convert user settings
		$phpgw_setup->oProc->query("select account_id, account_permissions from accounts",__LINE__,__FILE__);
		if($phpgw_setup->oProc->num_rows())
		{
			while($phpgw_setup->oProc->next_record())
			{
				$apps_perms = explode(":",$phpgw_setup->oProc->f("account_permissions"));
				for($i=1;$i<count($apps_perms)-1;$i++)
				{
					if ($apps_perms[$i] != "")
					{
						$sql = "insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights)";
						$sql .= " values('".$apps_perms[$i]."', 'run', ".$phpgw_setup->oProc->f("account_id").", 'u', 1)";
						$db2->query($sql ,__LINE__,__FILE__);
					}
				}
			}
		}
		$phpgw_setup->oProc->query("update accounts set account_permissions = ''",__LINE__,__FILE__);
		//convert group settings
		$phpgw_setup->oProc->query("select group_id, group_apps from groups",__LINE__,__FILE__);
		if($phpgw_setup->oProc->num_rows())
		{
			while($phpgw_setup->oProc->next_record())
			{
				$apps_perms = explode(":",$phpgw_setup->oProc->f("group_apps"));
				for($i=1;$i<count($apps_perms)-1;$i++)
				{
					if ($apps_perms[$i] != "")
					{
						$sql = "insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights)";
						$sql .= " values('".$apps_perms[$i]."', 'run', ".$phpgw_setup->oProc->f("group_id").", 'g', 1)";
						$db2->query($sql ,__LINE__,__FILE__);
					}
				}
			}
		}
		$phpgw_setup->oProc->query("update groups set group_apps = ''",__LINE__,__FILE__);

		$setup_info['phpgwapi']['currentver'] = '0.9.10pre1';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = "0.9.10pre1";
	function phpgwapi_upgrade0_9_10pre1()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->AddColumn('phpgw_categories','cat_access',array('type' => 'varchar', 'precision' => 25));

		$setup_info['phpgwapi']['currentver'] = '0.9.10pre2';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = "0.9.10pre2";
	function phpgwapi_upgrade0_9_10pre2()
	{
		global $setup_info, $phpgw_setup;

		$db2 = $phpgw_setup->oProc;

		$phpgw_setup->oProc->query("SELECT account_groups,account_id FROM accounts",__LINE__,__FILE__);
		if($phpgw_setup->oProc->num_rows())
		{
			while($phpgw_setup->oProc->next_record())
			{
				$gl = explode(",",$phpgw_setup->oProc->f("account_groups"));
				for ($i=1; $i<(count($gl)-1); $i++)
				{
					$ga = explode(":",$gl[$i]);
					$sql = "INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights)";
					$sql .= " VALUES('phpgw_group', '".$ga[0]."', ".$phpgw_setup->oProc->f("account_id").", 'u', 1)";
					$db2->query($sql ,__LINE__,__FILE__);
				}
			}
		}
		$phpgw_setup->oProc->query("UPDATE accounts SET account_groups= ''",__LINE__,__FILE__);

		$setup_info['phpgwapi']['currentver'] = '0.9.10pre3';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = "0.9.10pre3";
	function phpgwapi_upgrade0_9_10pre3()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->RenameTable('accounts','phpgw_accounts');

		$newtbldef = array(
			'fd' => array(
				'account_id' => array('type' => 'auto', 'nullable' => false),
				'account_lid' => array('type' => 'varchar', 'precision' => 25, 'nullable' => false),
				'account_pwd' => array('type' => 'varchar', 'precision' => 32, 'nullable' => false),
				'account_firstname' => array('type' => 'varchar', 'precision' => 50),
				'account_lastname' => array('type' => 'varchar', 'precision' => 50),
				'account_groups' => array('type' => 'varchar', 'precision' => 30),
				'account_lastlogin' => array('type' => 'int', 'precision' => 4),
				'account_lastloginfrom' => array('type' => 'varchar', 'precision' => 255),
				'account_lastpwd_change' => array('type' => 'int', 'precision' => 4),
				'account_status' => array('type' => 'char', 'precision' => 1, 'nullable' => false, 'default' => 'A')
			),
			'pk' => array('account_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array('account_lid')
		);
		$phpgw_setup->oProc->DropColumn('phpgw_accounts',$newtbldef,'account_permissions');

		$newtbldef = array(
			'fd' => array(
				'account_id' => array('type' => 'auto', 'nullable' => false),
				'account_lid' => array('type' => 'varchar', 'precision' => 25, 'nullable' => false),
				'account_pwd' => array('type' => 'varchar', 'precision' => 32, 'nullable' => false),
				'account_firstname' => array('type' => 'varchar', 'precision' => 50),
				'account_lastname' => array('type' => 'varchar', 'precision' => 50),
				'account_lastlogin' => array('type' => 'int', 'precision' => 4),
				'account_lastloginfrom' => array('type' => 'varchar', 'precision' => 255),
				'account_lastpwd_change' => array('type' => 'int', 'precision' => 4),
				'account_status' => array('type' => 'char', 'precision' => 1, 'nullable' => false, 'default' => 'A')
			),
			'pk' => array('account_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array('account_lid')
		);
		$phpgw_setup->oProc->DropColumn('phpgw_accounts',$newtbldef,'account_groups');

		$phpgw_setup->oProc->AddColumn('phpgw_accounts','account_type', array('type' => 'char', 'precision' => 1));
		$phpgw_setup->oProc->query("update phpgw_accounts set account_type='u'",__LINE__,__FILE__);

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

		$db2 = $phpgw_setup->oProc;
		$db3 = $phpgw_setup->oProc;

		$phpgw_setup->oProc->query("SELECT MAX(group_id) FROM groups",__LINE__,__FILE__);
		$phpgw_setup->oProc->next_record();
		$max_group_id = $phpgw_setup->oProc->f(0);

		// This is for use by former CORE apps to use in this version number's upgrade locally
		$phpgw_setup->oProc->CreateTable(
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

		$phpgw_setup->oProc->query("SELECT group_id, group_name FROM groups",__LINE__,__FILE__);
		while($phpgw_setup->oProc->next_record())
		{
			$old_group_id = $phpgw_setup->oProc->f("group_id");
			$old_group_name = $phpgw_setup->oProc->f("group_name");
			$group_name = $phpgw_setup->oProc->f("group_name");
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

		$phpgw_setup->oProc->DropTable('groups');

		/* Moved back from addressbook */
		$phpgw_setup->oProc->query('SELECT oldid,newid FROM phpgw_temp_groupmap',__LINE__,__FILE__);
		if($phpgw_setup->oProc->num_rows())
		{
			while($phpgw_setup->oProc->next_record())
			{
				$old_group_id = $phpgw_setup->oProc->f(0);
				$new_group_id = $phpgw_setup->oProc->f(1);
				$db2->query("SELECT ab_access,ab_id FROM addressbook WHERE ab_access LIKE '%,".$old_group_id.",%'",__LINE__,__FILE__);
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
		}

		$setup_info["phpgwapi"]["currentver"] = "0.9.10pre5";
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}
 
	$test[] = "0.9.10pre5";
	function phpgwapi_upgrade0_9_10pre5()
	{
		global $setup_info, $phpgw_setup;

		// This is only temp data, so we can kill it.
		$phpgw_setup->oProc->DropTable('phpgw_app_sessions');
		$phpgw_setup->oProc->CreateTable(
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

		$phpgw_setup->oProc->RenameTable('config','phpgw_config');

		$setup_info['phpgwapi']['currentver'] = '0.9.10pre7';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = "0.9.10pre7";
	function phpgwapi_upgrade0_9_10pre7()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->RenameTable('applications','phpgw_applications');

		$setup_info['phpgwapi']['currentver'] = '0.9.10pre8';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = "0.9.10pre8";
	function phpgwapi_upgrade0_9_10pre8()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->RenameColumn('phpgw_sessions', 'session_info', 'session_action');
		$phpgw_setup->oProc->AlterColumn('phpgw_sessions', 'session_action', array('type' => 'varchar', 'precision' => '255'));

		$setup_info['phpgwapi']['currentver'] = '0.9.10pre9';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.10pre9';
	function phpgwapi_upgrade0_9_10pre9()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->RenameTable('preferences','phpgw_preferences');

		$setup_info['phpgwapi']['currentver'] = '0.9.10pre10';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.10pre10';
	function phpgwapi_upgrade0_9_10pre10()
	{
		global $setup_info, $phpgw_setup;

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

		$phpgw_setup->oProc->DropColumn('phpgw_acl',$newtbldef,'acl_account_type');

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
		global $setup_info, $phpgw_setup;

		$db1 = $phpgw_setup->db;

		$phpgw_setup->oProc->CreateTable(
			'phpgw_addressbook', array(
				'fd' => array(
					'id'           => array('type' => 'auto', 'nullable' => False),
					'lid'          => array('type' => 'varchar', 'precision' => 32),
					'tid'          => array('type' => 'char', 'precision' => 1),
					'owner'        => array('type' => 'int', 'precision' => 4),
					'fn'           => array('type' => 'varchar', 'precision' => 64),
					'sound'        => array('type' => 'varchar', 'precision' => 64),
					'org_name'     => array('type' => 'varchar', 'precision' => 64),
					'org_unit'     => array('type' => 'varchar', 'precision' => 64),
					'title'        => array('type' => 'varchar', 'precision' => 64),
					'n_family'     => array('type' => 'varchar', 'precision' => 64),
					'n_given'      => array('type' => 'varchar', 'precision' => 64),
					'n_middle'     => array('type' => 'varchar', 'precision' => 64),
					'n_prefix'     => array('type' => 'varchar', 'precision' => 64),
					'n_suffix'     => array('type' => 'varchar', 'precision' => 64),
					'label'        => array('type' => 'text'),
					'adr_poaddr'   => array('type' => 'varchar', 'precision' => 64),
					'adr_extaddr'  => array('type' => 'varchar', 'precision' => 64),
					'adr_street'   => array('type' => 'varchar', 'precision' => 64),
					'adr_locality' => array('type' => 'varchar', 'precision' => 32),
					'adr_region'   => array('type' => 'varchar', 'precision' => 32),
					'adr_postalcode'  => array('type' => 'varchar', 'precision' => 32),
					'adr_countryname' => array('type' => 'varchar', 'precision' => 32),
					'adr_work'     => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'adr_home'     => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'adr_parcel'   => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'adr_postal'   => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'tz'           => array('type' => 'varchar', 'precision' => 8),
					'geo'          => array('type' => 'varchar', 'precision' => 32),
					'a_tel'        => array('type' => 'varchar', 'precision' => 40, 'default' => '+1 (000) 000-0000', 'nullable' => False),
					'a_tel_work'   => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'a_tel_home'   => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'a_tel_voice'  => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'a_tel_msg'    => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'a_tel_fax'    => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'a_tel_prefer' => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'b_tel'        => array('type' => 'varchar', 'precision' => 40, 'default' => '+1 (000) 000-0000', 'nullable' => False),
					'b_tel_work'   => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'b_tel_home'   => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'b_tel_voice'  => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'b_tel_msg'    => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'b_tel_fax'    => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'b_tel_prefer' => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'c_tel'        => array('type' => 'varchar', 'precision' => 40, 'default' => '+1 (000) 000-0000', 'nullable' => False),
					'c_tel_work'   => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'c_tel_home'   => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'c_tel_voice'  => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'c_tel_msg'    => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'c_tel_fax'    => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'c_tel_prefer' => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'd_emailtype'  => array('type' => 'varchar', 'precision' => 32),
					'd_email'      => array('type' => 'varchar', 'precision' => 64),
					'd_email_work' => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'd_email_home' => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False)
				),
				'pk' => array('id'),
				'fk' => array(),
				'ix' => array(),
				'uc' => array('id')
			)
		);

		$phpgw_setup->oProc->CreateTable(
			'phpgw_addressbook_extra', array(
				'fd' => array(
					'contact_id'    => array('type' => 'int',     'precision' => 4),
					'contact_owner' => array('type' => 'int',     'precision' => 4),
					'contact_name'  => array('type' => 'varchar', 'precision' => 255),
					'contact_value' => array('type' => 'varchar', 'precision' => 255)
				),
				'pk' => array(),
				'fk' => array(),
				'ix' => array(),
				'uc' => array()
			)
		);

		$phpgw_setup->oProc->query("SELECT * FROM addressbook");
		echo '<br>numrows: ' . $phpgw_setup->oProc->num_rows;

		while ($phpgw_setup->oProc->next_record())
		{
			$fields = $extra = array();

			$fields['id']         = $phpgw_setup->oProc->f('ab_id');
			$fields['owner']      = addslashes($phpgw_setup->oProc->f('ab_owner'));
			$fields['n_given']    = addslashes($phpgw_setup->oProc->f('ab_firstname'));
			$fields['n_family']   = addslashes($phpgw_setup->oProc->f('ab_lastname'));
			$fields['d_email']    = addslashes($phpgw_setup->oProc->f('ab_email'));
			$fields['b_tel']      = addslashes($phpgw_setup->oProc->f('ab_hphone'));
			$fields['a_tel']      = addslashes($phpgw_setup->oProc->f('ab_wphone'));
			$fields['c_tel']      = addslashes($phpgw_setup->oProc->f('ab_fax'));
			$fields['fn']         = addslashes($phpgw_setup->oProc->f('ab_firstname').' '.$phpgw_setup->oProc->f('ab_lastname'));
			$fields['a_tel_work'] = 'y';
			$fields['b_tel_home'] = 'y';
			$fields['c_tel_fax']  = 'y';
			$fields['org_name']   = addslashes($phpgw_setup->oProc->f('ab_company'));
			$fields['title']      = addslashes($phpgw_setup->oProc->f('ab_title'));
			$fields['adr_street'] = addslashes($phpgw_setup->oProc->f('ab_street'));
			$fields['adr_locality']   = addslashes($phpgw_setup->oProc->f('ab_city'));
			$fields['adr_region']     = addslashes($phpgw_setup->oProc->f('ab_state'));
			$fields['adr_postalcode'] = addslashes($phpgw_setup->oProc->f('ab_zip'));

			$extra['pager']       = $phpgw_setup->oProc->f('ab_pager');
			$extra['mphone']      = $phpgw_setup->oProc->f('ab_mphone');
			$extra['ophone']      = $phpgw_setup->oProc->f('ab_ophone');
			$extra['bday']        = $phpgw_setup->oProc->f('ab_bday');
			$extra['notes']       = $phpgw_setup->oProc->f('ab_notes');
			$extra['address2']    = $phpgw_setup->oProc->f('ab_address2');
			$extra['url']         = $phpgw_setup->oProc->f('ab_url');

			$sql = "INSERT INTO phpgw_addressbook (org_name,n_given,n_family,fn,d_email,title,a_tel,a_tel_work,"
				. "b_tel,b_tel_home,c_tel,c_tel_fax,adr_street,adr_locality,adr_region,adr_postalcode,owner)"
				. " VALUES ('".$fields['org_name']."','".$fields['n_given']."','".$fields['n_family']."','"
				. $fields['fn']."','".$fields['d_email']."','".$fields['title']."','".$fields['a_tel']."','"
				. $fields['a_tel_work']."','".$fields['b_tel']."','".$fields['b_tel_home']."','"
				. $fields['c_tel']."','".$fields['c_tel_fax']."','".$fields['adr_street']."','"
				. $fields['adr_locality']."','".$fields['adr_region']."','".$fields['adr_postalcode']."','"
				. $fields['owner'] ."')";

			$db1->query($sql);

			while (list($name,$value) = each($extra))
			{
				$sql = "INSERT INTO phpgw_addressbook_extra VALUES ('".$fields['id']."','" . $fields['owner'] . "','"
					. addslashes($name) . "','" . addslashes($value) . "')";
				$db1->query($sql);
			}
		}
		$setup_info['phpgwapi']['currentver'] = '0.9.10pre13';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
		// Note we are still leaving the old addressbook table alone here... for third party apps if they need it
	}

	$test[] = '0.9.10pre13';
	function phpgwapi_upgrade0_9_10pre13()
	{
		global $setup_info, $phpgw_setup;

		$db1 = $phpgw_setup->db;

		$phpgw_setup->oProc->AddColumn('phpgw_addressbook', 'url',  array('type' => 'varchar', 'precision' => 128));
		$phpgw_setup->oProc->AddColumn('phpgw_addressbook', 'bday', array('type' => 'varchar', 'precision' => 32));
		$phpgw_setup->oProc->AddColumn('phpgw_addressbook', 'note', array('type' => 'text'));
		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook_extra', 'contact_value', array('type' => 'text'));

		$sql = "SELECT * FROM phpgw_addressbook_extra WHERE contact_name='url'";
		$phpgw_setup->oProc->query($sql,__LINE__,__FILE__);

		while($phpgw_setup->oProc->next_record())
		{
			$cid   = $phpgw_setup->oProc->f('contact_id');
			$cvalu = $phpgw_setup->oProc->f('contact_value');
			if ($cid && $cvalu)
			{
				$update = "UPDATE phpgw_addressbook SET url='" . $cvalu . "' WHERE id=" . $cid;
				$db1->query($update);
				$delete = "DELETE FROM phpgw_addressbook_extra WHERE contact_id=" . $cid . " AND contact_name='url'";
				$db1->query($delete);
			}
		}

		$sql = "SELECT * FROM phpgw_addressbook_extra WHERE contact_name='bday'";
		$phpgw_setup->oProc->query($sql,__LINE__,__FILE__);

		while($phpgw_setup->oProc->next_record())
		{
			$cid   = $phpgw_setup->oProc->f('contact_id');
			$cvalu = $phpgw_setup->oProc->f('contact_value');
			if ($cid && $cvalu)
			{
				$update = "UPDATE phpgw_addressbook set bday='" . $cvalu . "' WHERE id=" . $cid;
				$db1->query($update);
				$delete = "DELETE FROM phpgw_addressbook_extra WHERE contact_id=" . $cid . " AND contact_name='bday'";
				$db1->query($delete);
			}
		}

		$sql = "SELECT * FROM phpgw_addressbook_extra WHERE contact_name='notes'";
		$phpgw_setup->oProc->query($sql,__LINE__,__FILE__);

		while($phpgw_setup->oProc->next_record())
		{
			$cid   = $phpgw_setup->oProc->f('contact_id');
			$cvalu = $phpgw_setup->oProc->f('contact_value');
			if ($cvalu)
			{
				$update = "UPDATE phpgw_addressbook set note='" . $cvalu . "' WHERE id=" . $cid;
				$db1->query($update);
				$delete = "DELETE FROM phpgw_addressbook_extra WHERE contact_id=" . $cid . " AND contact_name='notes'";
				$db1->query($delete);
			}
		}
		$setup_info['phpgwapi']['currentver'] = '0.9.10pre14';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.10pre14';
	function phpgwapi_upgrade0_9_10pre14()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->AddColumn('phpgw_sessions','session_flags', array('type' => 'char', 'precision' => 2));

		$setup_info['phpgwapi']['currentver'] = '0.9.10pre15';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.10pre15';
	function phpgwapi_upgrade0_9_10pre15()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'adr_work',      array('type' => 'char', 'precision' => 1, 'default' => 'n', 'nullable' => False));
		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'adr_home',      array('type' => 'char', 'precision' => 1, 'default' => 'n', 'nullable' => False));
		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'adr_parcel',    array('type' => 'char', 'precision' => 1, 'default' => 'n', 'nullable' => False));
		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'adr_postal',    array('type' => 'char', 'precision' => 1, 'default' => 'n', 'nullable' => False));
 		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'a_tel_work',    array('type' => 'char', 'precision' => 1, 'default' => 'n', 'nullable' => False));
 		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'a_tel_home',    array('type' => 'char', 'precision' => 1, 'default' => 'n', 'nullable' => False));
 		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'a_tel_voice',   array('type' => 'char', 'precision' => 1, 'default' => 'n', 'nullable' => False));
 		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'a_tel_msg',     array('type' => 'char', 'precision' => 1, 'default' => 'n', 'nullable' => False));
 		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'a_tel_fax',     array('type' => 'char', 'precision' => 1, 'default' => 'n', 'nullable' => False));
 		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'a_tel_prefer',  array('type' => 'char', 'precision' => 1, 'default' => 'n', 'nullable' => False));
 		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'b_tel_work',    array('type' => 'char', 'precision' => 1, 'default' => 'n', 'nullable' => False));
 		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'b_tel_home',    array('type' => 'char', 'precision' => 1, 'default' => 'n', 'nullable' => False));
 		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'b_tel_voice',   array('type' => 'char', 'precision' => 1, 'default' => 'n', 'nullable' => False));
 		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'b_tel_msg',     array('type' => 'char', 'precision' => 1, 'default' => 'n', 'nullable' => False));
 		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'b_tel_fax',     array('type' => 'char', 'precision' => 1, 'default' => 'n', 'nullable' => False));
 		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'b_tel_prefer',  array('type' => 'char', 'precision' => 1, 'default' => 'n', 'nullable' => False));
 		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'c_tel_work',    array('type' => 'char', 'precision' => 1, 'default' => 'n', 'nullable' => False));
 		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'c_tel_home',    array('type' => 'char', 'precision' => 1, 'default' => 'n', 'nullable' => False));
 		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'c_tel_voice',   array('type' => 'char', 'precision' => 1, 'default' => 'n', 'nullable' => False));
 		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'c_tel_msg',     array('type' => 'char', 'precision' => 1, 'default' => 'n', 'nullable' => False));
 		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'c_tel_fax',     array('type' => 'char', 'precision' => 1, 'default' => 'n', 'nullable' => False));
 		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'c_tel_prefer',  array('type' => 'char', 'precision' => 1, 'default' => 'n', 'nullable' => False));
		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'd_email_work',  array('type' => 'char', 'precision' => 1, 'default' => 'n', 'nullable' => False));
		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'd_email_home',  array('type' => 'char', 'precision' => 1, 'default' => 'n', 'nullable' => False));

		$setup_info['addressbook']['currentver'] = '0.9.10pre16';
		return $setup_info['addressbook']['currentver'];
		//return True;
	}

	$test[] = '0.9.10pre16';
	function phpgwapi_upgrade0_9_10pre16()
	{
		global $setup_info, $phpgw_setup;

		$db1 = $phpgw_setup->db;

		$phpgw_setup->oProc->RenameTable('phpgw_addressbook', 'phpgw_addressbook_old');
		$phpgw_setup->oProc->CreateTable(
			'phpgw_addressbook', array(
				'fd' => array(
					'id' =>                  array('type' => 'auto', 'nullable' => False),
					'lid' =>                 array('type' => 'varchar', 'precision' => 32),
					'tid' =>                 array('type' => 'char', 'precision' => 1),
					'owner' =>               array('type' => 'int', 'precision' => 4),
					'fn' =>                  array('type' => 'varchar', 'precision' => 64),
					'n_family' =>            array('type' => 'varchar', 'precision' => 64),
					'n_given' =>             array('type' => 'varchar', 'precision' => 64),
					'n_middle' =>            array('type' => 'varchar', 'precision' => 64),
					'n_prefix' =>            array('type' => 'varchar', 'precision' => 64),
					'n_suffix' =>            array('type' => 'varchar', 'precision' => 64),
					'sound' =>               array('type' => 'varchar', 'precision' => 64),
					'bday' =>                array('type' => 'varchar', 'precision' => 32),
					'note' =>                array('type' => 'text'),
					'tz' =>                  array('type' => 'varchar', 'precision' => 8),
					'geo' =>                 array('type' => 'varchar', 'precision' => 32),
					'url' =>                 array('type' => 'varchar', 'precision' => 128),
					'org_name' =>            array('type' => 'varchar', 'precision' => 64),
					'org_unit' =>            array('type' => 'varchar', 'precision' => 64),
					'title' =>               array('type' => 'varchar', 'precision' => 64),
					'adr_one_street' =>      array('type' => 'varchar', 'precision' => 64),
					'adr_one_locality' =>    array('type' => 'varchar', 'precision' => 32),
					'adr_one_region' =>      array('type' => 'varchar', 'precision' => 32),
					'adr_one_postalcode' =>  array('type' => 'varchar', 'precision' => 32),
					'adr_one_countryname' => array('type' => 'varchar', 'precision' => 32),
					'adr_one_type' =>        array('type' => 'varchar', 'precision' => 64),
					'label' =>               array('type' => 'text'),
					'adr_two_street' =>      array('type' => 'varchar', 'precision' => 64),
					'adr_two_locality' =>    array('type' => 'varchar', 'precision' => 32),
					'adr_two_region' =>      array('type' => 'varchar', 'precision' => 32),
					'adr_two_postalcode' =>  array('type' => 'varchar', 'precision' => 32),
					'adr_two_type' =>        array('type' => 'varchar', 'precision' => 64),
					'tel_work' =>            array('type' => 'varchar', 'precision' => 40, 'default' => '+1 (000) 000-0000', 'nullable' => False),
					'tel_home' =>            array('type' => 'varchar', 'precision' => 40, 'default' => '+1 (000) 000-0000', 'nullable' => False),
					'tel_voice' =>           array('type' => 'varchar', 'precision' => 40, 'default' => '+1 (000) 000-0000', 'nullable' => False),
					'tel_fax' =>             array('type' => 'varchar', 'precision' => 40, 'default' => '+1 (000) 000-0000', 'nullable' => False),
					'tel_msg' =>             array('type' => 'varchar', 'precision' => 40, 'default' => '+1 (000) 000-0000', 'nullable' => False),
					'tel_cell' =>            array('type' => 'varchar', 'precision' => 40, 'default' => '+1 (000) 000-0000', 'nullable' => False),
					'tel_pager' =>           array('type' => 'varchar', 'precision' => 40, 'default' => '+1 (000) 000-0000', 'nullable' => False),
					'tel_bbs' =>             array('type' => 'varchar', 'precision' => 40, 'default' => '+1 (000) 000-0000', 'nullable' => False),
					'tel_modem' =>           array('type' => 'varchar', 'precision' => 40, 'default' => '+1 (000) 000-0000', 'nullable' => False),
					'tel_car' =>             array('type' => 'varchar', 'precision' => 40, 'default' => '+1 (000) 000-0000', 'nullable' => False),
					'tel_isdn' =>            array('type' => 'varchar', 'precision' => 40, 'default' => '+1 (000) 000-0000', 'nullable' => False),
					'tel_video' =>           array('type' => 'varchar', 'precision' => 40, 'default' => '+1 (000) 000-0000', 'nullable' => False),
					'tel_prefer' =>          array('type' => 'varchar', 'precision' => 32),
					'email' =>               array('type' => 'varchar', 'precision' => 64),
					'email_type' =>          array('type' => 'varchar', 'precision' => 32, 'default' => 'INTERNET'),
					'email_home' =>          array('type' => 'varchar', 'precision' => 64),
					'email_home_type' =>     array('type' => 'varchar', 'precision' => 32, 'default' => 'INTERNET')
				),
				'pk' => array('id'),
				'fk' => array(),
				'ix' => array(),
				'uc' => array()
			)
		);

		$phpgw_setup->oProc->query("SELECT * FROM phpgw_addressbook_old");
		while ($phpgw_setup->oProc->next_record())
		{
			$fields['id']                  = $phpgw_setup->oProc->f("id");
			$fields['owner']               = $phpgw_setup->oProc->f("owner");
			$fields['n_given']             = $phpgw_setup->oProc->f("firstname");
			$fields['n_family']            = $phpgw_setup->oProc->f("lastname");
			$fields['email']               = $phpgw_setup->oProc->f("d_email");
			$fields['email_type']          = $phpgw_setup->oProc->f("d_emailtype");
			$fields['tel_home']            = $phpgw_setup->oProc->f("b_tel");
			$fields['tel_work']            = $phpgw_setup->oProc->f("a_tel");
			$fields['tel_fax']             = $phpgw_setup->oProc->f("c_tel");
			$fields['fn']                  = $phpgw_setup->oProc->f("fn");
			$fields['org_name']            = $phpgw_setup->oProc->f("org_name");
			$fields['title']               = $phpgw_setup->oProc->f("title");
			$fields['adr_one_street']      = $phpgw_setup->oProc->f("adr_street");
			$fields['adr_one_locality']    = $phpgw_setup->oProc->f("adr_locality");
			$fields['adr_one_region']      = $phpgw_setup->oProc->f("adr_region");
			$fields['adr_one_postalcode']  = $phpgw_setup->oProc->f("adr_postalcode");
			$fields['adr_one_countryname'] = $phpgw_setup->oProc->f("adr_countryname");
			$fields['bday']                = $phpgw_setup->oProc->f("bday");
			$fields['note']                = $phpgw_setup->oProc->f("note");
			$fields['url']                 = $phpgw_setup->oProc->f("url");

			$sql="INSERT INTO phpgw_addressbook (org_name,n_given,n_family,fn,email,email_type,title,tel_work,"
				. "tel_home,tel_fax,adr_one_street,adr_one_locality,adr_one_region,adr_one_postalcode,adr_one_countryname,"
				. "owner,bday,url,note)"
				. " VALUES ('".$fields["org_name"]."','".$fields["n_given"]."','".$fields["n_family"]."','"
				. $fields["fn"]."','".$fields["email"]."','".$fields["email_type"]."','".$fields["title"]."','".$fields["tel_work"]."','"
				. $fields["tel_home"]."','".$fields["tel_fax"] ."','".$fields["adr_one_street"]."','"
				. $fields["adr_one_locality"]."','".$fields["adr_one_region"]."','".$fields["adr_one_postalcode"]."','"
				. $fields["adr_one_countryname"]."','".$fields["owner"] ."','".$fields["bday"]."','".$fields["url"]."','".$fields["note"]."')";

			$db1->query($sql,__LINE__,__FILE__);
		}
 
		$phpgw_setup->oProc->query("DROP TABLE phpgw_addressbook_old");
 
		$phpgw_setup->oProc->query("UPDATE phpgw_addressbook SET tel_home=''   WHERE tel_home='n'   OR tel_home='y'");
		$phpgw_setup->oProc->query("UPDATE phpgw_addressbook SET tel_work=''   WHERE tel_work='n'   OR tel_work='y'");
		$phpgw_setup->oProc->query("UPDATE phpgw_addressbook SET tel_cell=''   WHERE tel_cell='n'   OR tel_cell='y'");
		$phpgw_setup->oProc->query("UPDATE phpgw_addressbook SET tel_voice=''  WHERE tel_voice='n'  OR tel_voice='y'");
		$phpgw_setup->oProc->query("UPDATE phpgw_addressbook SET tel_fax=''    WHERE tel_fax='n'    OR tel_fax='y'");
		$phpgw_setup->oProc->query("UPDATE phpgw_addressbook SET tel_car=''    WHERE tel_car='n'    OR tel_car='y'");
		$phpgw_setup->oProc->query("UPDATE phpgw_addressbook SET tel_pager=''  WHERE tel_pager='n'  OR tel_pager='y'");
		$phpgw_setup->oProc->query("UPDATE phpgw_addressbook SET tel_msg=''    WHERE tel_msg='n'    OR tel_msg='y'");
		$phpgw_setup->oProc->query("UPDATE phpgw_addressbook SET tel_bbs=''    WHERE tel_bbs='n'    OR tel_bbs='y'");
		$phpgw_setup->oProc->query("UPDATE phpgw_addressbook SET tel_modem=''  WHERE tel_modem='n'  OR tel_modem='y'");
		$phpgw_setup->oProc->query("UPDATE phpgw_addressbook SET tel_prefer='' WHERE tel_prefer='n' OR tel_prefer='y'");
		$phpgw_setup->oProc->query("UPDATE phpgw_addressbook SET tel_video=''  WHERE tel_video='n'  OR tel_video='y'");
		$phpgw_setup->oProc->query("UPDATE phpgw_addressbook SET tel_isdn=''   WHERE tel_isdn='n'   OR tel_isdn='y'");

		$sql = "SELECT * FROM phpgw_addressbook_extra WHERE contact_name='mphone'";
		$phpgw_setup->oProc->query($sql,__LINE__,__FILE__);

		while($phpgw_setup->oProc->next_record())
		{
			$cid   = $phpgw_setup->oProc->f('contact_id');
			$cvalu = $phpgw_setup->oProc->f('contact_value');
			if ($cvalu)
			{
				$update = "UPDATE phpgw_addressbook SET tel_cell='" . $cvalu . "' WHERE id=" . $cid;
				$db1->query($update);
				$delete = "DELETE FROM phpgw_addressbook_extra WHERE contact_id=" . $cid . " AND contact_name='mphone'";
				$db1->query($delete);
			}
		}
		$setup_info['phpgwapi']['currentver'] = '0.9.10pre17';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.10pre17';
	function phpgwapi_upgrade0_9_10pre17()
	{
		global $phpgw_info, $phpgw_setup;

		$phpgw_setup->oProc->AddColumn('phpgw_addressbook','pubkey', array('type' => 'text'));
		$phpgw_setup->oProc->AddColumn('phpgw_addressbook','adr_two_countryname', array('type' => 'varchar', 'precision' => 32));

		$setup_info['phpgwapi']['currentver'] = '0.9.10pre18';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.10pre18';
	function phpgwapi_upgrade0_9_10pre18()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->CreateTable(
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

		$phpgw_setup->oProc->DropTable('phpgw_nextid');

		$phpgw_setup->oProc->CreateTable(
			'phpgw_nextid', array(
				'fd' => array(
					'appname' => array('type' => 'varchar', 'precision' => 25, 'nullable' => False),
					'id' => array('type' => 'int', 'precision' => 4)
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
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->AddColumn('phpgw_addressbook', 'access', array('type' => 'char', 'precision' => 7));

		$setup_info['phpgwapi']['currentver'] = '0.9.10pre21';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.10pre21';
	function phpgwapi_upgrade0_9_10pre21()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->AddColumn('phpgw_addressbook', 'cat_id', array('type' => 'varchar', 'precision' => 32));

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
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->query("UPDATE phpgw_addressbook SET tid='n' WHERE tid is null");

		$setup_info['phpgwapi']['currentver'] = '0.9.10pre24';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.10pre24';
	function phpgwapi_upgrade0_9_10pre24()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->AlterColumn('phpgw_categories','cat_access', array('type' => 'char', 'precision' => 7));

		$setup_info['phpgwapi']['currentver'] = '0.9.10pre25';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.10pre25';
	function phpgwapi_upgrade0_9_10pre25()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->AddColumn('phpgw_app_sessions','session_dla', array('type' => 'int', 'precision' => 4));

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

		$phpgw_setup->oProc->AlterColumn('phpgw_app_sessions', 'content', array('type' => 'longtext'));

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
		global $phpgw_info, $phpgw_setup;

		$phpgw_setup->oProc->AddColumn('phpgw_categories','cat_main',array('type' => 'int', 'precision' => 4, 'default' => 0, 'nullable' => False));
		$phpgw_setup->oProc->AddColumn('phpgw_categories','cat_level',array('type' => 'int', 'precision' => 4, 'default' => 0, 'nullable' => False));

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

		$phpgw_setup->oProc->AddColumn('phpgw_config','config_app', array('type' => 'varchar', 'precision' => 50));
		$phpgw_setup->oProc->query("UPDATE phpgw_config SET config_app='phpgwapi'",__LINE__,__FILE__);

		$setup_info['phpgwapi']['currentver'] = '0.9.11.005';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.11.005';
	function phpgwapi_upgrade0_9_11_005()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->AddColumn('phpgw_accounts','account_expires', array('type' => 'int', 'precision' => 4));
		$phpgw_setup->oProc->query("UPDATE phpgw_accounts SET account_expires='-1'",__LINE__,__FILE__);

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

		$phpgw_setup->oProc->DropTable('profiles');
		
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

	$test[] = '0.9.11.011';
	function phpgwapi_upgrade0_9_11_011()
	{
		global $setup_info;

		$setup_info['phpgwapi']['currentver'] = '0.9.13.001';
		return $setup_info['phpgwapi']['currentver'];
	}

	$test[] = '0.9.13.001';
	function phpgwapi_upgrade0_9_13_001()
	{
		global $setup_info,$phpgw_setup;

		$phpgw_setup->oProc->AlterColumn('phpgw_categories','cat_access', array('type' => 'varchar', 'precision' => 7));
		$setup_info['phpgwapi']['currentver'] = '0.9.13.002';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.13.002';
	function phpgwapi_upgrade0_9_13_002()
	{
		global $setup_info,$phpgw_setup;
		$phpgw_setup->oProc->AddColumn('phpgw_accounts','account_file_space', array ('type' => 'varchar', 'precision' => 25));
		$setup_info['phpgwapi']['currentver'] = '0.9.13.003';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.13.003';
	function phpgwapi_upgrade0_9_13_003()
	{
		global $setup_info,$phpgw_setup;

		$phpgw_setup->oProc->AlterColumn('phpgw_access_log','sessionid',array('type' => 'char', 'precision' => 32));

		$setup_info['phpgwapi']['currentver'] = '0.9.13.004';
		return $setup_info['phpgwapi']['currentver'];
	}

	$test[] = '0.9.13.004';
	function phpgwapi_upgrade0_9_13_004()
	{
		global $setup_info, $phpgw_setup, $phpgw_info, $phpgw;

		$phpgw_setup->oProc->AddColumn('phpgw_access_log','account_id',array('type' => 'int', 'precision' => 4, 'default' => 0, 'nullable' => False));

		class phpgw
		{
			var $common;
			var $accounts;
			var $applications;
			var $db;
		}
		$phpgw = new phpgw;
		$phpgw->common = CreateObject('phpgwapi.common');

		$phpgw_setup->oProc->query("SELECT config_name,config_value FROM phpgw_config WHERE config_name LIKE 'ldap%' OR config_name='account_repository'",__LINE__,__FILE__);
		while ($phpgw_setup->oProc->next_record())
		{
			$config[$phpgw_setup->oProc->f('config_name')] = $phpgw_setup->oProc->f('config_value');
		}
		$phpgw_info['server']['ldap_host']          = $config['ldap_host'];
		$phpgw_info['server']['ldap_context']       = $config['ldap_context'];
		$phpgw_info['server']['ldap_group_context'] = $config['ldap_group_context'];
		$phpgw_info['server']['ldap_root_dn']       = $config['ldap_root_dn'];
		$phpgw_info['server']['ldap_root_pw']       = $config['ldap_root_pw'];
		$phpgw_info['server']['account_repository'] = $config['account_repository'];

		$accounts = CreateObject('phpgwapi.accounts');
		$accounts->db = $phpgw_setup->db;

		$phpgw_setup->oProc->query("select * from phpgw_access_log");
		while ($phpgw_setup->oProc->next_record())
		{
			$lid         = explode('@',$phpgw_setup->oProc->f('loginid'));
			$account_lid = $lid[0];
			$account_id = $accounts->name2id($account_lid);

			$phpgw_setup->db->query("update phpgw_access_log set account_id='" . $account_id
				. "' where sessionid='" . $phpgw_setup->oProc->f('sessionid') . "'");
		}

		$setup_info['phpgwapi']['currentver'] = '0.9.13.005';
		return $setup_info['phpgwapi']['currentver'];
	}

	$test[] = '0.9.13.005';
	function phpgwapi_upgrade0_9_13_005()
	{
		global $setup_info, $phpgw_setup;

		$newtbldef = array(
			'fd' => array(
				'account_id' => array('type' => 'auto', 'nullable' => false),
				'account_lid' => array('type' => 'varchar', 'precision' => 25, 'nullable' => false),
				'account_pwd' => array('type' => 'varchar', 'precision' => 32, 'nullable' => false),
				'account_firstname' => array('type' => 'varchar', 'precision' => 50),
				'account_lastname' => array('type' => 'varchar', 'precision' => 50),
				'account_permissions' => array('type' => 'text'),
				'account_groups' => array('type' => 'varchar', 'precision' => 30),
				'account_lastlogin' => array('type' => 'int', 'precision' => 4),
				'account_lastloginfrom' => array('type' => 'varchar', 'precision' => 255),
				'account_lastpwd_change' => array('type' => 'int', 'precision' => 4),
				'account_status' => array('type' => 'char', 'precision' => 1, 'nullable' => false, 'default' => 'A'),
				'account_expires' => array('type' => 'int', 'precision' => 4),
				'account_type' => array('type' => 'char', 'precision' => 1, 'nullable' => true)
			),
			'pk' => array('account_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array('account_lid')
		);

		$phpgw_setup->oProc->DropColumn('phpgw_accounts',$newtbldef,'account_file_space');

		$setup_info['phpgwapi']['currentver'] = '0.9.13.006';
		return $setup_info['phpgwapi']['currentver'];
	}
// begin j3rry
	$test[] = '0.9.13.006';  // version number?
	function phpgwapi_upgrade0_9_13_006()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->CreateTable(
			'phpgw_log', array(
				'fd' => array(
					'log_id' 	=> array('type' => 'auto', 		'precision' => 4,	'nullable' => False),
					'log_date' 	=> array('type' => 'timestamp',	'nullable' => False),
					'log_user' 	=> array('type' => 'int',  		'precision' => 4,	'nullable' => False),
					'log_app' 	=> array('type' => 'varchar', 	'precision' => 50,	'nullable' => False),
					'log_severity' 	=> array('type' => 'char', 	'precision' => 1,	'nullable' => False)
			    	),
				'pk' => array('log_id'),
				'fk' => array(),
				'ix' => array(),
				'uc' => array()
			)
		);

		$phpgw_setup->oProc->CreateTable(
			'phpgw_log_msg', array(
				'fd' => array(
					'log_msg_log_id' 	=> array('type' => 'auto', 		'precision' => 4,	'nullable' => False),
					'log_msg_seq_no'	=> array('type' => 'int',  		'precision' => 4,	'nullable' => False),
					'log_msg_date'		=> array('type' => 'timestamp',	'nullable' => False),
					'log_msg_tx_fid'	=> array('type' => 'varchar',	'precision' => 4,	'nullable' => True),
					'log_msg_tx_id'		=> array('type' => 'varchar',	'precision' => 4,	'nullable' => True),
					'log_msg_severity'	=> array('type' => 'char', 		'precision' => 1,	'nullable' => False),
					'log_msg_code' 		=> array('type' => 'varchar', 	'precision' => 30,	'nullable' => False),
					'log_msg_msg' 		=> array('type' => 'text', 							'nullable' => False),
					'log_msg_parms'		=> array('type' => 'text', 							'nullable' => False)
			    	),
				'pk' => array('log_msg_log_id', 'log_msg_seq_no'),
				'fk' => array(),
				'ix' => array(),
				'uc' => array()
			)
		);

		$setup_info['phpgwapi']['currentver'] = '0.9.13.007';
		return $setup_info['phpgwapi']['currentver'];
	}


	$test[] = '0.9.13.007';  
	function phpgwapi_upgrade0_9_13_007()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->AlterColumn('phpgw_log_msg','log_msg_log_id',array('type' => 'int', 'precision' => 4, 'nullable'=> False));

		$setup_info['phpgwapi']['currentver'] = '0.9.13.008';
		return $setup_info['phpgwapi']['currentver'];
	}

	$test[] = '0.9.13.008';  
	function phpgwapi_upgrade0_9_13_008()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->AddColumn('phpgw_log_msg','log_msg_file',array('type' => 'varchar', 'precision' => 255, 'nullable'=> False));
		$phpgw_setup->oProc->AddColumn('phpgw_log_msg','log_msg_line',array('type' => 'int', 'precision' => 4, 'nullable'=> False));

		$setup_info['phpgwapi']['currentver'] = '0.9.13.009';
		return $setup_info['phpgwapi']['currentver'];
	}

?>
