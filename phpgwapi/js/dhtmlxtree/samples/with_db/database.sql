Create database sampledb;
use sampledb;
CREATE TABLE Tree (
  item_id INT UNSIGNED not null AUTO_INCREMENT,
  item_nm VARCHAR (200) DEFAULT '0',
  item_order INT  UNSIGNED DEFAULT '0',
  item_desc TEXT ,
  item_parent_id INT UNSIGNED DEFAULT '0',
  PRIMARY KEY ( item_id )
  )