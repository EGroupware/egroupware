BEGIN WORK;

create table applications (
  app_name varchar(25) NOT NULL,
  app_title varchar(50),
  app_enabled int,
  unique(app_name)
);

insert into applications (app_name, app_title, app_enabled) values ('admin', 'Administration', 1);
insert into applications (app_name, app_title, app_enabled) values ('filemanager', 'File manager', 1);
insert into applications (app_name, app_title, app_enabled) values ('addressbook', 'Address Book', 1);
insert into applications (app_name, app_title, app_enabled) values ('todo', 'ToDo List', 1);
insert into applications (app_name, app_title, app_enabled) values ('calendar', 'Calendar', 1);
insert into applications (app_name, app_title, app_enabled) values ('email', 'Email', 1);
insert into applications (app_name, app_title, app_enabled) values ('nntp', 'NNTP', 1);
insert into applications (app_name, app_title, app_enabled) values ('cron_apps', 'cron_apps', 0);

create table accounts (
  con 	 	serial,
  loginid 	varchar(25) NOT NULL,
  passwd  	char(32) NOT NULL,
  firstname 	varchar(50),
  lastname  	varchar(50),
  permissions	text,
  groups	varchar(30),
  lastlogin	int,
  lastloginfrom	varchar(255),
  lastpasswd_change int,
  status	char(1),
  unique(loginid)
);

insert into accounts (loginid,passwd,firstname,lastname,permissions,groups,
status) values ('demo','81dc9bdb52d04dc20036dbd8313ed055','Demo','Account',
':admin:email:todo:addressbook:calendar:hr:',',1,','A');

create table groups (
  group_id	serial,
  group_name	varchar(50),
  group_apps    varchar(255)
);

insert into groups (group_name) values ('Default');

create table sessions (
  sessionid	varchar(255),
  loginid	varchar(20),
  passwd	varchar(255),
  ip		varchar(255),
  logintime	int,
  dla		int,
  unique(sessionid)
);

CREATE TABLE app_sessions (
   sessionid	varchar(255) NOT NULL,
   loginid	varchar(20),
   app	varchar(20),
   content	text
);

create table preferences ( 
  owner 	varchar(20),
  name		varchar(50),
  value		varchar(50)
);

insert into preferences values ('demo','maxmatchs','10');
insert into preferences values ('demo','email_sig','');
insert into preferences values ('demo','mainscreen_showbirthdays','True');
insert into preferences values ('demo','mainscreen_showevents','True');
insert into preferences values ('demo','timeformat','12');
insert into preferences values ('demo','dateformat','m/d/Y');
insert into preferences values ('demo','theme','default');
insert into preferences values ('demo','tz_offset','0');

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
  con 		serial,
  owner		varchar(25),
  access	varchar(10),
  firstname	varchar(255),
  lastname	varchar(255),
  email		varchar(255),
  hphone	varchar(255),
  wphone	varchar(255),
  fax		varchar(255),
  pager		varchar(255),
  mphone	varchar(255),
  ophone	varchar(255),
  street	varchar(255),
  city		varchar(255),
  state		varchar(255),
  zip		varchar(255),
  bday		varchar(255),
  notes		TEXT,
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
  cal_id	int NOT NULL,
  cal_login	varchar(25) NOT NULL,
  cal_status	char(1) DEFAULT 'A'
);

create table webcal_entry_repeats ( 
  cal_id	int,
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
  messagecount	int NOT NULL,
  lastmessage	int NOT NULL,
  active	char DEFAULT 'N' NOT NULL,
  lastread	int
);

CREATE TABLE users_newsgroups (
  owner		int NOT NULL,
  newsgroup	int NOT NULL
);

CREATE TABLE lang (
  message_id varchar(150) DEFAULT '' NOT NULL,
  app_name varchar(100) DEFAULT 'common' NOT NULL,
  lang varchar(5) DEFAULT '' NOT NULL,
  content text NOT NULL,
  unique(message_id,app_name,lang)
);

COMMIT;
