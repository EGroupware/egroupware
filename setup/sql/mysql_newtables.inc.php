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

  $sql = "CREATE TABLE config (
    config_name     varchar(255) NOT NULL,
    config_value    varchar(100),
    UNIQUE config_name (config_name)
  )";
  $phpgw_setup->db->query($sql);  
 
  $sql = "CREATE TABLE applications (
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

  $sql = "create table groups (
    group_id	int NOT NULL auto_increment,
    group_name	varchar(255),
    group_apps    varchar(255),
    primary key(group_id)
  )";
  $phpgw_setup->db->query($sql);  

  $sql = "CREATE TABLE preferences (
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
    session_info      text,
    UNIQUE sessionid (session_id)
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

  $sql = "CREATE TABLE phpgw_app_sessions (
    sessionid	varchar(255) NOT NULL,
    loginid	varchar(20),
    app	varchar(20),
    content	text
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

  $sql = "CREATE TABLE addressbook (
    ab_id       int(11) NOT NULL auto_increment,
    ab_owner    varchar(25),
    ab_access   varchar(10),
    ab_firstname varchar(255),
    ab_lastname varchar(255),
    ab_email    varchar(255),
    ab_hphone   varchar(255),
    ab_wphone   varchar(255),
    ab_fax      varchar(255),
    ab_pager    varchar(255),
    ab_mphone   varchar(255),
    ab_ophone   varchar(255),
    ab_street   varchar(255),
    ab_city     varchar(255),
    ab_state    varchar(255),
    ab_zip      varchar(255),
    ab_bday     varchar(255),
    ab_notes    text,
    ab_company  varchar(255),
    ab_company_id int(10) unsigned,
    ab_title    varchar(60),
    ab_address2 varchar(60),
    ab_url      varchar(255),
    PRIMARY KEY (ab_id)
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

  $sql = "CREATE TABLE notes (
           note_id        int(20) NOT NULL auto_increment, 
           note_owner     int(11),
           note_date      int(11),
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

  $phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.9";
  $phpgw_info["setup"]["oldver"]["phpgwapi"] = $phpgw_info["setup"]["currentver"]["phpgwapi"];
  update_version_table();
//  $phpgw_setup->update_version_table();
?>
