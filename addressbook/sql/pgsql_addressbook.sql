
#DROP TABLE IF EXISTS phpgw_addressbook;
#DROP TABLE IF EXISTS phpgw_addressbook_extra;

#
# Table structure for table 'phpgw_addressbook'
#

CREATE TABLE phpgw_addressbook (
   id    serial,
   lid   varchar(32),
   tid   char(1),
   owner int,

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
   adr_one_locality    varchar(32),
   adr_one_region      varchar(32),
   adr_one_postalcode  varchar(32),
   adr_one_countryname varchar(32),
   adr_one_type        varchar(64),
   label text,

   adr_two_street      varchar(64),
   adr_two_locality    varchar(32),
   adr_two_region      varchar(32),
   adr_two_postalcode  varchar(32),
   adr_two_countryname varchar(32),
   adr_two_type        varchar(64),

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
   email_type varchar(32) DEFAULT 'INTERNET' NOT NULL,
   email_home varchar(64),
   email_home_type varchar(32) DEFAULT 'INTERNET' NOT NULL,
   PRIMARY KEY (id)
);

#
# Table structure for table 'phpgw_addressbook_extra'
#

CREATE TABLE phpgw_addressbook_extra (
    contact_id 		int,
    contact_owner 	int,
    contact_name 	varchar(255),
    contact_value 	text
);

