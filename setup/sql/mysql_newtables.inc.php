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
  
  // NOTE: Please use spaces to seperate the field names.  It makes copy and pasting easier.

  $sql = "CREATE TABLE phpgw_config (
    config_name     varchar(255) NOT NULL,
    config_value    varchar(100),
    UNIQUE config_name (config_name)
  )";
  $phpgw_setup->db->query($sql);  
 
  $sql = "CREATE TABLE phpgw_applications (
    app_name     varchar(25) NOT NULL,
    app_title    varchar(50),
    app_enabled  int,
    app_order    int,
    app_tables   varchar(255),
    app_version  varchar(20) NOT NULL default '0.0',
    UNIQUE app_name (app_name)
  )";
  $phpgw_setup->db->query($sql);  

  $sql = "CREATE TABLE phpgw_accounts (
    account_id             int(11) DEFAULT '0' NOT NULL auto_increment,
    account_lid            varchar(25) NOT NULL,
    account_pwd            varchar(32) NOT NULL,
    account_firstname      varchar(50),
    account_lastname       varchar(50),
    account_lastlogin      int(11),
    account_lastloginfrom  varchar(255),
    account_lastpwd_change int(11),
    account_status         enum('A','L'),
    account_type           char(1),
    PRIMARY KEY (account_id),
    UNIQUE account_lid (account_lid)
  )";
  $phpgw_setup->db->query($sql);  

  $sql = "CREATE TABLE phpgw_preferences (
    preference_owner       int,
    preference_value       text
  )";
  $phpgw_setup->db->query($sql);  

  $sql = "CREATE TABLE phpgw_sessions (
    session_id        varchar(255) NOT NULL,
    session_lid       varchar(255),
    session_ip        varchar(255),
    session_logintime int(11),
    session_dla       int(11),
    session_action    varchar(255),
    UNIQUE sessionid (session_id)
  )";
  $phpgw_setup->db->query($sql);  

  $sql = "CREATE TABLE phpgw_acl (
    acl_appname       varchar(50),
    acl_location      varchar(255),
    acl_account       int,
    acl_rights        int
  )";
  $phpgw_setup->db->query($sql);  

  $sql = "CREATE TABLE phpgw_app_sessions (
    sessionid	   varchar(255) NOT NULL,
    loginid	     varchar(20),
    location      varchar(255),
    app	         varchar(20),
    content	     text
  )";
  $phpgw_setup->db->query($sql);  

  $sql = "create table phpgw_access_log (
    sessionid	varchar(255),
    loginid	  varchar(30),
    ip		   varchar(30),
    li		   int,
    lo		   varchar(255)
  )";
  $phpgw_setup->db->query($sql);  

  $sql = "CREATE TABLE profiles (
    con int(11) DEFAULT '0' NOT NULL auto_increment,
    owner varchar(20),
    title varchar(255),
    phone_number varchar(255),
    comments text,
    picture_format varchar(255),
    picture blob,
    PRIMARY KEY (con)
  )";
  $phpgw_setup->db->query($sql);  

  $sql = "CREATE TABLE phpgw_addressbook (

                    id 				int(8) PRIMARY KEY DEFAULT '0' NOT NULL auto_increment,
                    lid 			varchar(32),
                    tid 			char(1),
                    owner 			int(8),
                    fn 				varchar(64),
                    sound 			varchar(64),
                    org_name 		varchar(64),
                    org_unit 		varchar(64),
                    title 			varchar(64),
                    n_family 		varchar(64),
                    n_given 		varchar(64),
                    n_middle 		varchar(64),
                    n_prefix 		varchar(64),
                    n_suffix 		varchar(64),
                    label 			text,
                    adr_poaddr 		varchar(64),
                    adr_extaddr 	varchar(64),
                    adr_street 		varchar(64),
                    adr_locality 	varchar(32),
                    adr_region 		varchar(32),
                    adr_postalcode 	varchar(32),
                    adr_countryname 	varchar(32),
                    adr_work 		enum('n','y') NOT NULL,
                    adr_home 		enum('n','y') NOT NULL,
                    adr_parcel 		enum('n','y') NOT NULL,
                    adr_postal 		enum('n','y') NOT NULL,
                    tz 				varchar(8),
                    geo 			varchar(32),
					url				varchar(128),
					bday			varchar(32),
                    a_tel 			varchar(40) DEFAULT '+1 (000) 000-0000' NOT NULL,
                    a_tel_work 		enum('n','y') NOT NULL,
                    a_tel_home 		enum('n','y') NOT NULL,
                    a_tel_voice 	enum('n','y') NOT NULL,
                    a_tel_msg 		enum('n','y') NOT NULL,
                    a_tel_fax 		enum('n','y') NOT NULL,
                    a_tel_prefer 	enum('n','y') NOT NULL,
                    b_tel 			varchar(40) DEFAULT '+1 (000) 000-0000' NOT NULL,
                    b_tel_work 		enum('n','y') NOT NULL,
                    b_tel_home 		enum('n','y') NOT NULL,
                    b_tel_voice 	enum('n','y') NOT NULL,
                    b_tel_msg 		enum('n','y') NOT NULL,
                    b_tel_fax 		enum('n','y') NOT NULL,
                    b_tel_prefer 	enum('n','y') NOT NULL,
                    c_tel 			varchar(40) DEFAULT '+1 (000) 000-0000' NOT NULL,
                    c_tel_work 		enum('n','y') NOT NULL,
                    c_tel_home 		enum('n','y') NOT NULL,
                    c_tel_voice 	enum('n','y') NOT NULL,
                    c_tel_msg 		enum('n','y') NOT NULL,
                    c_tel_fax 		enum('n','y') NOT NULL,
                    c_tel_prefer 	enum('n','y') NOT NULL,
                    d_emailtype 	enum('INTERNET','CompuServe','AOL','Prodigy','eWorld','AppleLink','AppleTalk','PowerShare','IBMMail','ATTMail','MCIMail','X.400','TLX') NOT NULL, 
                    d_email 		varchar(64),
                    d_email_work 	enum('n','y') NOT NULL,
                    d_email_home 	enum('n','y') NOT NULL,
                    UNIQUE (id)
		    )";
  $phpgw_setup->db->query($sql);

        $sql = "CREATE TABLE phpgw_addressbook_extra (
                    contact_id 		int(11),
                    contact_owner 	int(11),
                    contact_name 	varchar(255),
                    contact_value 	text
            	    )";

  $phpgw_setup->db->query($sql);

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

  $sql = "CREATE TABLE todo (
    todo_id      int(11) DEFAULT '0' NOT NULL auto_increment,
    todo_id_parent	int(11) DEFAULT '0' NOT NULL,
    todo_owner   varchar(25),
    todo_access  varchar(10),
    todo_des     text,
    todo_pri     int(11),
    todo_status  int(11),
    todo_datecreated  int(11),
    todo_startdate int(11),
    todo_enddate int(11),
    PRIMARY KEY (todo_id)
  )";
  $phpgw_setup->db->query($sql);  

  $sql = "CREATE TABLE calendar_entry (
    cal_id		int(11) DEFAULT '0' NOT NULL auto_increment,
    cal_owner 		int(11) DEFAULT '0' NOT NULL,
    cal_group		varchar(255),
    cal_datetime	int(11),
    cal_mdatetime	int(11),
    cal_edatetime 	int(11),
    cal_priority 	int(11) DEFAULT '2' NOT NULL,
    cal_type		varchar(10),
    cal_access		varchar(10),
    cal_name		varchar(80) NOT NULL,
    cal_description 	text,
    PRIMARY KEY (cal_id)
  )";
  $phpgw_setup->db->query($sql);  

  $sql = "CREATE TABLE calendar_entry_repeats (
    cal_id		int(11) DEFAULT '0' NOT NULL,
    cal_type		enum('daily','weekly','monthlyByDay','monthlyByDate','yearly') DEFAULT 'daily' NOT NULL,
    cal_use_end		int DEFAULT '0',
    cal_end		int(11),
    cal_frequency	int(11) DEFAULT '1',
    cal_days		char(7)
  )";
  $phpgw_setup->db->query($sql);  

  $sql = "CREATE TABLE calendar_entry_user (
    cal_id       int(11) DEFAULT '0' NOT NULL,
    cal_login    int(11) DEFAULT '0' NOT NULL,
    cal_status   char(1) DEFAULT 'A',
    PRIMARY KEY (cal_id, cal_login)
  )";
  $phpgw_setup->db->query($sql);  

  $sql = "CREATE TABLE newsgroups (
    con             int(11) NOT NULL auto_increment,
    name            varchar(255) NOT NULL,
    messagecount    int(11) NOT NULL,
    lastmessage     int(11) NOT NULL,
    active          char DEFAULT 'N' NOT NULL,
    lastread        int(11),
    PRIMARY KEY (con),
    UNIQUE name (name)
  )";
  $phpgw_setup->db->query($sql);  

  $sql = "CREATE TABLE news_msg (
    con	        int(11)      NOT NULL,
    msg	        int(11)      NOT NULL,
    uid	        varchar(255) DEFAULT '',
    udate       int(11)      DEFAULT 0,
    path        varchar(255) DEFAULT '',
    fromadd     varchar(255) DEFAULT '',
    toadd       varchar(255) DEFAULT '',
    ccadd       varchar(255) DEFAULT '',
    bccadd      varchar(255) DEFAULT '',
    reply_to    varchar(255) DEFAULT '',
    sender      varchar(255) DEFAULT '',
    return_path varchar(255) DEFAULT '',
    subject     varchar(255) DEFAULT '',
    message_id  varchar(255) DEFAULT '',
    reference   varchar(255) DEFAULT '',
    in_reply_to varchar(255) DEFAULT '',
    follow_up_to varchar(255) DEFAULT '',
    nntp_posting_host varchar(255) DEFAULT '',
    nntp_posting_date varchar(255) DEFAULT '',
    x_complaints_to varchar(255) DEFAULT '',
    x_trace     varchar(255) DEFAULT '',
    x_abuse_info varchar(255) DEFAULT '',
    x_mailer    varchar(255) DEFAULT '',
    organization varchar(255) DEFAULT '',
    content_type varchar(255) DEFAULT '',
    content_description	varchar(255) DEFAULT '',
    content_transfer_encoding varchar(255) DEFAULT '',
    mime_version varchar(255) DEFAULT '',
    msgsize     int(11)      DEFAULT 0,
    msglines    int(11)      DEFAULT 0,
    body        longtext     NOT NULL,
    primary key(con,msg)
  )";
  $phpgw_setup->db->query($sql);

  $sql = "CREATE TABLE lang (
    message_id      varchar(150) DEFAULT '' NOT NULL,
    app_name        varchar(100) DEFAULT 'common' NOT NULL,
    lang            varchar(5) DEFAULT '' NOT NULL,
    content         text NOT NULL,
    PRIMARY KEY (message_id,app_name,lang)
  )";
  $phpgw_setup->db->query($sql);
  
  $sql = "CREATE TABLE phpgw_categories (
            cat_id          int(9) DEFAULT '0' NOT NULL auto_increment,
            cat_parent      int(9) DEFAULT '0' NOT NULL,
            cat_owner       int(11) DEFAULT '0' NOT NULL,
            cat_appname     varchar(50) NOT NULL,
            cat_name        varchar(150) NOT NULL,
            cat_description varchar(255) NOT NULL,
            cat_data        text,
            PRIMARY KEY (cat_id)
         )";
  $phpgw_setup->db->query($sql);

  $sql = "CREATE TABLE languages (
     lang_id         varchar(2) NOT NULL,
     lang_name       varchar(50) NOT NULL,
     available       char(3) NOT NULL DEFAULT 'No', 
     PRIMARY KEY (lang_id)
  )";
  $phpgw_setup->db->query($sql);

  $sql = "CREATE TABLE phpgw_notes (
           note_id        int(20) NOT NULL auto_increment, 
           note_owner     int(11),
           note_date      int(11),
	   note_category  int(9),
           note_content   text, 
           PRIMARY KEY (note_id)
          )";
  $phpgw_setup->db->query($sql);

  $sql = "create table phpgw_hooks (
           hook_id       int not null auto_increment,
           hook_appname  varchar(255),
           hook_location varchar(255),
           hook_filename varchar(255),
           primary key hook_id (hook_id)
         );";
  $phpgw_setup->db->query($sql); 

  $phpgw_info['setup']['currentver']['phpgwapi'] = '0.9.10pre13';
  $phpgw_info['setup']['oldver']['phpgwapi'] = $phpgw_info['setup']['currentver']['phpgwapi'];
  update_version_table();
//  $phpgw_setup->update_version_table();
?>
