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
    config_name     varchar(255) NOT NULL UNIQUE,
    config_value    varchar(100) NOT NULL
  )";
  $db->query($sql);

  $sql = "create table applications (
    app_name     varchar(25) NOT NULL,
    app_title    varchar(50),
    app_enabled  int,
    app_order    int,
    app_tables   varchar(255),
    app_version  varchar(20) NOT NULL default '0.0',
    unique(app_name)
  )";
  $db->query($sql);


  $sql = "create table accounts (
    account_id             serial,
    account_lid            varchar(25) NOT NULL,
    account_pwd            char(32) NOT NULL,
    account_firstname      varchar(50),
    account_lastname       varchar(50),
    account_permissions    text,
    account_groups         varchar(30),
    account_lastlogin	     int,
    account_lastloginfrom  varchar(255),
    account_lastpwd_change int,
    account_status         char(1),
    unique(account_lid)
  )";
  $db->query($sql);

  $sql = "create table groups (
    group_id     serial,
    group_name   varchar(50),
    group_apps   varchar(255)
  )";
  $db->query($sql);
  
  $sql = "create table sessions (
    session_id         varchar(255),
    session_lid        varchar(20),
    session_pwd        varchar(255),
    session_ip         varchar(255),
    session_logintime	 int,
    session_dla        int,
    unique(session_id)
  )";
  $db->query($sql);

  $sql = "CREATE TABLE app_sessions (
   sessionid	varchar(255) NOT NULL,
   loginid	varchar(20),
   app	        varchar(20),
   content	text
  )";
  $db->query($sql);

  $sql = "create table preferences ( 
    preference_owner       varchar(20),
    preference_name        varchar(50),
    preference_value       varchar(50),
    preference_appname     varchar(50)
  )";
  $db->query($sql);

  $sql = "create table access_log (
   sessionid    varchar(255),
   loginid      varchar(30),
   ip           varchar(30),
   li           int,
   lo           varchar(255)
  )";
  $db->query($sql);

  $sql = "CREATE TABLE profiles (
   con		serial,
   owner 	varchar(20),
   title 	varchar(255),
   phone_number varchar(255),
   comments 	text,
   picture_format varchar(255),
   picture 	text
  )";
  $db->query($sql);

  $sql = "create table addressbook (
    ab_id       serial,
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
    ab_notes    varchar(255),
    ab_company  varchar(255),
    ab_company_id int,
    ab_title    varchar(60),   
    ab_address2 varchar(60),
    ab_url      varchar(255)
  )";
  $db->query($sql);

  $sql = "create table todo (
    todo_id	     serial,
    todo_id_parent int,
    todo_owner	varchar(25),
    todo_access	varchar(10),
    todo_des	text,
    todo_pri	int,
    todo_status	int,
    todo_datecreated	int,
    todo_datedue	int
  )";
  $db->query($sql);

  $sql = "CREATE TABLE webcal_entry (
    cal_id	serial,
    cal_group_id	int NULL,
    cal_create_by	varchar(25) NOT NULL,
    cal_date	int NOT NULL,
    cal_time	int NULL,
    cal_mod_date	int,
    cal_mod_time	int,
    cal_duration	int NOT NULL,
    cal_priority	int DEFAULT 2,
    cal_type	varchar(10),
    cal_access	varchar(10),
    cal_name	varchar(80) NOT NULL,
    cal_description varchar(255)
  )";
  $db->query($sql);

  $sql = "CREATE TABLE webcal_entry_user (
    cal_id	     int,
    cal_login	varchar(25) NOT NULL,
    cal_status	char(1) DEFAULT 'A'
  )";
  $db->query($sql);

  $sql = "create table webcal_entry_repeats ( 
    cal_id	     int,
    cal_type	varchar(20),
    cal_end	int,
    cal_frequency	int default 1,
    cal_days	char(7)
  )";
  $db->query($sql);

  $sql = "create table webcal_entry_groups (
    cal_id	int,
    groups	varchar(255)
  )";
  $db->query($sql);

  $sql = "CREATE TABLE newsgroups (
    con		serial,
    name		varchar(255) NOT NULL,
    messagecount	int,
    lastmessage	int,
    active	char DEFAULT 'N' NOT NULL,
    lastread	int
  )";
  $db->query($sql);

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
  $db->query($sql);


  $sql = "CREATE TABLE lang (
    message_id     varchar(150) DEFAULT '' NOT NULL,
    app_name       varchar(100) DEFAULT 'common' NOT NULL,
    lang           varchar(5) DEFAULT '' NOT NULL,
    content        text NOT NULL,
    unique(message_id,app_name,lang)
  )";
  $db->query($sql);

  $sql = "CREATE TABLE categories (
    cat_id          serial,
    account_id      int DEFAULT '0' NOT NULL,
    app_name        varchar(25) NOT NULL,
    cat_name        varchar(150) NOT NULL,
    cat_description text NOT NULL
  )";
  $db->query($sql);
  
  $sql = "CREATE TABLE languages (
     lang_id         varchar(2) NOT NULL,
     lang_name       varchar(50) NOT NULL,
     available       varchar(3) NOT NULL DEFAULT 'No'
  )";
  $db->query($sql);

  $sql = "CREATE TABLE notes (
     accountid      int(11),
     date           int(11),
     note           text, 
     noteid         int(20) auto_increment, 
     PRIMARY KEY (noteid)
  )";
  $db->query($sql);

  $currentver = "0.9.3";
  update_version_table();

?>
