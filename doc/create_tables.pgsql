BEGIN WORK;

create table applications (
  app_name varchar(25) NOT NULL,
  app_title varchar(50),
  app_enabled int,
  app_order    int,
  app_tables   varchar(255),
  unique(app_name)
);


insert into applications (app_name, app_title, app_enabled, app_order, app_tables) values ('admin', 'Administration', 1, 1, NULL);
insert into applications (app_name, app_title, app_enabled, app_order, app_tables) values ('tts', 'Trouble Ticket System', 0, 2, NULL);
insert into applications (app_name, app_title, app_enabled, app_order, app_tables) values ('inv', 'Inventory', 0, 3, NULL);
insert into applications (app_name, app_title, app_enabled, app_order, app_tables) values ('chat', 'Chat', 0, 4, NULL);
insert into applications (app_name, app_title, app_enabled, app_order, app_tables) values ('headlines', 'Headlines', 0, 5, 'news_sites,news_headlines,users_headlines');
insert into applications (app_name, app_title, app_enabled, app_order, app_tables) values ('filemanager', 'File manager', 1, 6, NULL);
insert into applications (app_name, app_title, app_enabled, app_order, app_tables) values ('addressbook', 'Address Book', 1, 7, 'addressbook');
insert into applications (app_name, app_title, app_enabled, app_order, app_tables) values ('todo', 'ToDo List', 1, 8, 'todo');
insert into applications (app_name, app_title, app_enabled, app_order, app_tables) values ('calendar', 'Calendar', 1, 9, 'webcal_entry,webcal_entry_users,webcal_entry_groups,webcal_repeats');
insert into applications (app_name, app_title, app_enabled, app_order, app_tables) values ('email', 'Email', 1, 10,NULL);
insert into applications (app_name, app_title, app_enabled, app_order, app_tables) values ('nntp', 'NNTP', 1, 11, 'newsgroups,users_newsgroups');
insert into applications (app_name, app_title, app_enabled, app_order, app_tables) values ('cron_apps', 'cron_apps', 0, 0, NULL);


create table accounts (
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
);

insert into accounts (account_ld,account_pwd,account_firstname,account_lastname,account_permissions,account_groups,
status) values ('demo','81dc9bdb52d04dc20036dbd8313ed055','Demo','Account',':admin:email:todo:addressbook:calendar:',',1,','A');

create table groups (
  group_id	serial,
  group_name	varchar(50),
  group_apps    varchar(255)
);

insert into groups (group_name) values ('Default');

create table sessions (
  session_id        varchar(255),
  session_lid       varchar(20),
  session_pwd       varchar(255),
  session_ip        varchar(255),
  session_logintime	int,
  session_dla       int,
  unique(session_id)
);

CREATE TABLE app_sessions (
   sessionid	varchar(255) NOT NULL,
   loginid	varchar(20),
   app	        varchar(20),
   content	text
);

create table preferences ( 
  preference_owner       varchar(20),
  preference_name        varchar(50),
  preference_value       varchar(50)
);

insert into preferences values ('demo','maxmatchs','10','');
insert into preferences values ('demo','mainscreen_showbirthdays','True','');
insert into preferences values ('demo','mainscreen_showevents','True','');
insert into preferences values ('demo','timeformat','12','');
insert into preferences values ('demo','dateformat','m/d/Y','');
insert into preferences values ('demo','theme','default','');
insert into preferences values ('demo','tz_offset','0','');

create table access_log (
   sessionid    varchar(30),
   loginid      varchar(30),
   ip           varchar(30),
   li           int,
   lo           int
);

CREATE TABLE profiles (
   con		serial,
   owner 	varchar(20),
   title 	varchar(255),
   phone_number varchar(255),
   comments 	text,
   picture_format varchar(255),
   picture 	text
);

create table addressbook (
  ab_id 		serial,
  ab_owner	varchar(25),
  ab_access	varchar(10),
  ab_firstname	varchar(255),
  ab_lastname	varchar(255),
  ab_email	varchar(255),
  ab_hphone	varchar(255),
  ab_wphone	varchar(255),
  ab_fax		varchar(255),
  ab_pager	varchar(255),
  ab_mphone	varchar(255),
  ab_ophone	varchar(255),
  ab_street	varchar(255),
  ab_city		varchar(255),
  ab_state	varchar(255),
  ab_zip		varchar(255),
  ab_bday		varchar(255),
  ab_notes	TEXT,
  company	varchar(255)
);

create table todo (
  todo_id	     serial,
  todo_owner	varchar(25),
  todo_access	varchar(10),
  todo_des	text,
  todo_pri	int,
  todo_status	int,
  todo_datecreated	int,
  todo_datedue	int
);

CREATE TABLE webcal_entry (
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
  cal_description TEXT
);

CREATE TABLE webcal_entry_user (
  cal_id	     int,
  cal_login	varchar(25) NOT NULL,
  cal_status	char(1) DEFAULT 'A'
);

create table webcal_entry_repeats ( 
  cal_id	     int,
  cal_type	varchar(20),
  cal_end	int,
  cal_frequency	int default 1,
  cal_days	char(7)
);

create table webcal_entry_groups (
  cal_id	int,
  groups	varchar(255)
);

CREATE TABLE newsgroups (
  con		serial,
  name		varchar(255) NOT NULL,
  messagecount	int,
  lastmessage	int,
  active	char DEFAULT 'N' NOT NULL,
  lastread	int
);

CREATE TABLE users_newsgroups (
  owner		int,
  newsgroup	int
);

CREATE TABLE lang (
  message_id varchar(150) DEFAULT '' NOT NULL,
  app_name varchar(100) DEFAULT 'common' NOT NULL,
  lang varchar(5) DEFAULT '' NOT NULL,
  content text NOT NULL,
  unique(message_id,app_name,lang)
);

COMMIT;
