BEGIN WORK;

create table applications (
  app_name varchar(25) NOT NULL,
  app_title varchar(50),
  app_enabled int,
  unique(app_name)
);

insert into applications (app_name, app_title, app_enabled) values ('admin', 'Administration', 1);
insert into applications (app_name, app_title, app_enabled) values ('tts', 'Trouble Ticket System', 1);
insert into applications (app_name, app_title, app_enabled) values ('inv', 'Inventory', 1);
insert into applications (app_name, app_title, app_enabled) values ('chat', 'Chat', 1);
insert into applications (app_name, app_title, app_enabled) values ('headlines', 'Headlines', 1);
insert into applications (app_name, app_title, app_enabled) values ('filemanager', 'File manager', 1);
insert into applications (app_name, app_title, app_enabled) values ('ftp', 'FTP', 1);
insert into applications (app_name, app_title, app_enabled) values ('addressbook', 'Address Book', 1);
insert into applications (app_name, app_title, app_enabled) values ('todo', 'ToDo List', 1);
insert into applications (app_name, app_title, app_enabled) values ('calendar', 'Calendar', 1);
insert into applications (app_name, app_title, app_enabled) values ('email', 'Email', 1);
insert into applications (app_name, app_title, app_enabled) values ('nntp', 'NNTP', 1);
insert into applications (app_name, app_title, app_enabled) values ('bookmarks', 'Bookmarks', 0);
insert into applications (app_name, app_title, app_enabled) values ('cron_apps', 'cron_apps', 0);
insert into applications (app_name, app_title, app_enabled) values ('napster', 'Napster', 0);

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

create table groups (
  group_id	serial,
  group_name	varchar(50),
  group_apps    varchar(255)
);

insert into groups (group_name) values ('Default');

insert into accounts (loginid,passwd,firstname,lastname,permissions,groups,
status) values ('demo','81dc9bdb52d04dc20036dbd8313ed055','Demo','Account',
':admin:email:todo:addressbook:calendar:hr:',',1,','A');

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

create table bookmarks (
  con 		serial,
  owner		varchar(255),
  category	int NOT NULL,
  url		varchar(255),
  title		varchar(255),
  des		text,
  access	char(7),
  lastupdate	int,
  lastview	int,
  totalviews	int
);

create table bookmarks_cats (
  con 		serial,
  owner		varchar(255),
  parent	int,
  parent_name	varchar(255),
  type		char(4),
  name		varchar(255)
);

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

create table todo (
  con 		serial,
  owner		varchar(25),
  access	varchar(10),
  des		text,
  pri		int,
  status	int,
  datecreated	int,
  datedue	int
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

create table preferences ( 
  owner 	varchar(20),
  name		varchar(50),
  value		varchar(50)
);

create table access_log (
   sessionid    varchar(30),
   loginid      varchar(30),
   ip           varchar(30),
   li           int,
   lo           int
);

create table ticket (
  t_id		serial,
  t_category	varchar(40) not null,
  t_subject varchar(80),
  t_detail	text,
  t_priority	smallint,
  t_user	varchar(10) not null,
  t_assignedto	varchar(10) not null,
  t_timestamp_opened	int,
  t_timestamp_closed	int,
  t_department	varchar(25)
);

create table category (
  c_id		 serial,
  c_department	 varchar(25) not null,
  c_name	 varchar(40) not null
);

insert into preferences values ('demo','maxmatchs','10');
insert into preferences values ('demo','email_sig','');
insert into preferences values ('demo','mainscreen_showbirthdays','True');
insert into preferences values ('demo','mainscreen_showevents','True');
insert into preferences values ('demo','timeformat','12');
insert into preferences values ('demo','dateformat','m/d/Y');
insert into preferences values ('demo','theme','default');
insert into preferences values ('demo','tz_offset','0');

CREATE TABLE news_site (
  con		serial,
  display	varchar(255),
  base_url	varchar(255),
  newsfile	varchar(255),
  lastread	int,
  newstype	varchar(15),
  cachetime	int,
  listings	int
);
insert into news_site (display,base_url,newsfile,lastread,newstype,cachetime,listings) values ('Slashdot','http://slashdot.org','/slashdot.rdf',0,'rdf',60,20);
insert into news_site (display,base_url,newsfile,lastread,newstype,cachetime,listings) values ('Freshmeat','http://freshmeat.net','/backend/fm.rdf',0,'fm',60,20);
insert into news_site (display,base_url,newsfile,lastread,newstype,cachetime,listings) values ('Linux&nbsp;Today','http://linuxtoday.com','/backend/linuxtoday.xml',0,'lt',60,20);
insert into news_site (display,base_url,newsfile,lastread,newstype,cachetime,listings) values ('Linux&nbsp;Game&nbsp;Tome','http://happypenguin.org','/html/news.rdf',0,'rdf',60,20);
insert into news_site (display,base_url,newsfile,lastread,newstype,cachetime,listings) values ('Segfault','http://segfault.org','/stories.xml',0,'sf',60,20);
insert into news_site (display,base_url,newsfile,lastread,newstype,cachetime,listings) values ('KDE&nbsp;News','http://www.kde.org','/news/kdenews.rdf',0,'rdf',60,20);
insert into news_site (display,base_url,newsfile,lastread,newstype,cachetime,listings) values ('Gnome&nbsp;News','http://news.gnome.org','/gnome-news/rdf',0,'rdf',60,20);
insert into news_site (display,base_url,newsfile,lastread,newstype,cachetime,listings) values ('Gimp&nbsp;News','http://www.xach.com','/gimp/news/channel.rdf',0,'rdf-chan',60,20);
insert into news_site (display,base_url,newsfile,lastread,newstype,cachetime,listings) values ('Mozilla','http://www.mozilla.org','/news.rdf',0,'rdf-chan',60,20);
insert into news_site (display,base_url,newsfile,lastread,newstype,cachetime,listings) values ('MozillaZine','http://www.mozillazine.org','/contents.rdf',0,'rdf',60,20);

CREATE TABLE news_headlines (
  site		int,
  title		varchar(255),
  link		varchar(255)
);

CREATE TABLE users_headlines (
  owner		varchar(25) not null,
  site		int
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

COMMIT;
