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
    config_name     varchar(255) NOT NULL UNIQUE,
    config_value    varchar(100) NOT NULL
  )";
  $phpgw_setup->db->query($sql);

  $sql = "create table phpgw_applications (
    app_name     varchar(25) NOT NULL,
    app_title    varchar(50),
    app_enabled  int,
    app_order    int,
    app_tables   varchar(255),
    app_version  varchar(20) NOT NULL default '0.0',
    unique(app_name)
  )";
  $phpgw_setup->db->query($sql);


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

  $sql = "create table phpgw_preferences ( 
    preference_owner       int,
    preference_value       text
  )";
  $phpgw_setup->db->query($sql);

  $sql = "create table phpgw_sessions (
    session_id         varchar(255),
    session_lid        varchar(255),
    session_ip         varchar(255),
    session_logintime  int,
    session_dla        int,
    session_action     varchar(255),
    session_flags      char(2),
    unique(session_id)
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
   sessionid    varchar(255),
   loginid      varchar(30),
   ip           varchar(30),
   li           int,
   lo           varchar(255)
  )";
  $phpgw_setup->db->query($sql);

	// Note: This table will be removed durring 0.9.11
  $sql = "CREATE TABLE profiles (
   con		serial,
   owner 	varchar(20),
   title 	varchar(255),
   phone_number varchar(255),
   comments 	text,
   picture_format varchar(255),
   picture 	text
  )";
  $phpgw_setup->db->query($sql);

  $sql = "CREATE TABLE phpgw_addressbook(
     id    serial,
     lid   varchar(32),
     tid   varchar(1),
     owner int,
     access   char(7),
	 cat_id   varchar(32),
     fn       varchar(64),
     n_family varchar(64),
     n_given  varchar(64),
     n_middle varchar(64),
     n_prefix varchar(64),
     n_suffix varchar(64),
     sound    varchar(64),
     bday     varchar(32),
     note     text,
     tz       varchar(8),
     geo      varchar(32),
     url      varchar(128),
     pubkey   text,
     org_name varchar(64),
     org_unit varchar(64),
     title    varchar(64),
     adr_one_street      varchar(64),
     adr_one_locality    varchar(64),
     adr_one_region      varchar(64),
     adr_one_postalcode  varchar(64),
     adr_one_countryname varchar(64),
     adr_one_type        varchar(32),
     label text,
     adr_two_street      varchar(64),
     adr_two_locality    varchar(64),
     adr_two_region      varchar(64),
     adr_two_postalcode  varchar(64),
     adr_two_countryname varchar(64),
     adr_two_type        varchar(32),
     tel_work   varchar(40) DEFAULT '+1 (000) 000-0000' NOT NULL,
     tel_home   varchar(40) DEFAULT '+1 (000) 000-0000' NOT NULL,
     tel_voice  varchar(40) DEFAULT '+1 (000) 000-0000' NOT NULL,
     tel_fax    varchar(40) DEFAULT '+1 (000) 000-0000' NOT NULL,
     tel_msg    varchar(40) DEFAULT '+1 (000) 000-0000' NOT NULL,
     tel_cell   varchar(40) DEFAULT '+1 (000) 000-0000' NOT NULL,
     tel_pager  varchar(40) DEFAULT '+1 (000) 000-0000' NOT NULL,
     tel_bbs    varchar(40) DEFAULT '+1 (000) 000-0000' NOT NULL,
     tel_modem  varchar(40) DEFAULT '+1 (000) 000-0000' NOT NULL,
     tel_car    varchar(40) DEFAULT '+1 (000) 000-0000' NOT NULL,
     tel_isdn   varchar(40) DEFAULT '+1 (000) 000-0000' NOT NULL,
     tel_video  varchar(40) DEFAULT '+1 (000) 000-0000' NOT NULL,
     tel_prefer varchar(32),
     email varchar(64),
     email_type varchar(32) DEFAULT 'INTERNET',
     email_home varchar(64),
     email_home_type varchar(32) DEFAULT 'INTERNET',
	 primary key (id)
   )";

  $phpgw_setup->db->query($sql);

       $sql = "CREATE TABLE phpgw_addressbook_extra (
                    contact_id          int,
                    contact_owner       int,
                    contact_name        varchar(255),
                    contact_value       text
                )";

  $phpgw_setup->db->query($sql);

  $sql = "create table phpgw_todo (
    todo_id	     serial,
    todo_id_parent int,
    todo_owner	varchar(25),
    todo_access	varchar(10),
    todo_des	text,
    todo_pri	int,
    todo_status	int,
    todo_datecreated	int,
    todo_startdate	int,
    todo_enddate int
  )";
  $phpgw_setup->db->query($sql);

  $sql = "CREATE TABLE calendar_entry (
    cal_id		serial,
    cal_owner		int DEFAULT 0 NOT NULL,
    cal_group		varchar(255),
    cal_datetime	int4,
    cal_mdatetime	int4,
    cal_edatetime	int4,
    cal_priority	int DEFAULT 2,
    cal_type		varchar(10),
    cal_access		varchar(10),
    cal_name		varchar(80) NOT NULL,
    cal_description	text
  )";
  $phpgw_setup->db->query($sql);

  $sql = "CREATE TABLE calendar_entry_user (
    cal_id		int DEFAULT 0 NOT NULL,
    cal_login		int DEFAULT 0 NOT NULL,
    cal_status		char(1) DEFAULT 'A'
  )";
  $phpgw_setup->db->query($sql);

  $sql = "create table calendar_entry_repeats ( 
    cal_id		int DEFAULT 0 NOT NULL,
    cal_type		varchar(20),
    cal_use_end		int default 0,
    cal_end		int4,
    cal_frequency	int default 1,
    cal_days		char(7)
  )";
  $phpgw_setup->db->query($sql);

  $sql = "CREATE TABLE newsgroups (
    con		serial,
    name		varchar(255) NOT NULL,
    messagecount	int,
    lastmessage	int,
    active	char DEFAULT 'N' NOT NULL,
    lastread	int
  )";
  $phpgw_setup->db->query($sql);

  $sql = "CREATE TABLE news_msg (
                con	           serial,
                msg	           int      NOT NULL,
                uid	           varchar(255) DEFAULT '',
                udate             int      DEFAULT 0,
                path              varchar(255) DEFAULT '',
                fromadd           varchar(255) DEFAULT '',
                toadd             varchar(255) DEFAULT '',
                ccadd             varchar(255) DEFAULT '',
                bccadd            varchar(255) DEFAULT '',
                reply_to          varchar(255) DEFAULT '',
                sender            varchar(255) DEFAULT '',
                return_path       varchar(255) DEFAULT '',
                subject           varchar(255) DEFAULT '',
                message_id        varchar(255) DEFAULT '',
                reference         varchar(255) DEFAULT '',
                in_reply_to       varchar(255) DEFAULT '',
                follow_up_to      varchar(255) DEFAULT '',
                nntp_posting_host varchar(255) DEFAULT '',
                nntp_posting_date varchar(255) DEFAULT '',
                x_complaints_to   varchar(255) DEFAULT '',
                x_trace           varchar(255) DEFAULT '',
                x_abuse_info      varchar(255) DEFAULT '',
                x_mailer          varchar(255) DEFAULT '',
                organization      varchar(255) DEFAULT '',
                content_type      varchar(255) DEFAULT '',
                content_description varchar(255) DEFAULT '',
                content_transfer_encoding varchar(255) DEFAULT '',
                mime_version      varchar(255) DEFAULT '',
                msgsize           int      DEFAULT 0,
                msglines          int      DEFAULT 0,
                body              text     NOT NULL
      )";
  $phpgw_setup->db->query($sql);


  $sql = "CREATE TABLE lang (
    message_id     varchar(150) DEFAULT '' NOT NULL,
    app_name       varchar(100) DEFAULT 'common' NOT NULL,
    lang           varchar(5) DEFAULT '' NOT NULL,
    content        text NOT NULL,
    unique(message_id,app_name,lang)
  )";
  $phpgw_setup->db->query($sql);


  $sql = "CREATE TABLE phpgw_categories (
            cat_id          serial,
            cat_parent      int,
            cat_owner       int,
	    cat_access	    char(7),
            cat_appname     varchar(50) NOT NULL,
            cat_name        varchar(150) NOT NULL,
            cat_description varchar(255) NOT NULL,
            cat_data        text
         )";
  $phpgw_setup->db->query($sql);

  
  $sql = "CREATE TABLE languages (
     lang_id         varchar(2) NOT NULL,
     lang_name       varchar(50) NOT NULL,
     available       varchar(3) NOT NULL DEFAULT 'No'
  )";
  $phpgw_setup->db->query($sql);

  $sql = "CREATE TABLE phpgw_notes (
           note_id        serial, 
           note_owner     int,
           note_date      int,
           note_category  int,
           note_content   text
          )";
  $phpgw_setup->db->query($sql);

  $sql = "create table phpgw_hooks (
           hook_id       serial,
           hook_appname  varchar(255),
           hook_location varchar(255),
           hook_filename varchar(255)
          )";
  $phpgw_setup->db->query($sql);  

  $sql = "create table phpgw_nextid (
           appname varchar(25),
           id      int
          )";
  $phpgw_setup->db->query($sql);

  $phpgw_info['setup']['currentver']['phpgwapi'] = '0.9.10pre25';
  $phpgw_info['setup']['oldver']['phpgwapi'] = $phpgw_info['setup']['currentver']['phpgwapi'];
  update_version_table();
?>
