BEGIN WORK;

CREATE TABLE phpgw_vfs (
  file_id int(11) DEFAULT '0' NOT NULL serial,
  owner_id int(11) DEFAULT '0' NOT NULL,
  createdby_id int(11),
  modifiedby_id int(11),
  created date DEFAULT '0000-00-00' NOT NULL,
  modified date,
  size int(14),
  mime_type varchar(150),
  deleteable char(1) DEFAULT 'Y',
  comment text,
  app varchar(25),
  directory text,
  name text NOT NULL,
  PRIMARY KEY (file_id)
);

INSERT INTO phpgw_vfs VALUES (1,0,0,NULL,'2000-01-01',NULL,NULL,'Directory','Y',NULL,'phpwebhosting','/','');
INSERT INTO phpgw_vfs VALUES (2,0,0,NULL,'2000-01-01',NULL,NULL,'Directory','Y',NULL,'phpwebhosting','/','home');

COMMIT;
