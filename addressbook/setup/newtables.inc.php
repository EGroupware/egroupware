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


  $sql = "CREATE TABLE addressbook (
   id int(8) DEFAULT '0' NOT NULL,
   lid varchar(32),
   tid char(1),
   owner int(8),
   FN varchar(64),
   SOUND varchar(64),
   ORG_Name varchar(64),
   ORG_Unit varchar(64),
   TITLE varchar(64),
   N_Family varchar(64),
   N_Given varchar(64),
   N_Middle varchar(64),
   N_Prefix varchar(64),
   N_Suffix varchar(64),
   LABEL text,
   ADR_POAddr varchar(64),
   ADR_ExtAddr varchar(64),
   ADR_Street varchar(64),
   ADR_Locality varchar(32),
   ADR_Region varchar(32),
   ADR_PostalCode varchar(32),
   ADR_CountryName varchar(32),
   ADR_Work enum('y','n') DEFAULT 'n' NOT NULL,
   ADR_Home enum('y','n') DEFAULT 'n' NOT NULL,
   ADR_Parcel enum('y','n') DEFAULT 'n' NOT NULL,
   ADR_Postal enum('y','n') DEFAULT 'n' NOT NULL,
   TZ varchar(8),
   GEO varchar(32),
   A_TEL varchar(40) DEFAULT '+1 (000) 000-0000' NOT NULL,
   A_TEL_Work enum('y','n') DEFAULT 'n' NOT NULL,
   A_TEL_Home enum('y','n') DEFAULT 'n' NOT NULL,
   A_TEL_Voice enum('y','n') DEFAULT 'n' NOT NULL,
   A_TEL_Msg enum('y','n') DEFAULT 'n' NOT NULL,
   A_TEL_Fax enum('y','n') DEFAULT 'n' NOT NULL,
   A_TEL_Prefer enum('y','n') DEFAULT 'n' NOT NULL,
   B_TEL varchar(40) DEFAULT '+1 (000) 000-0000' NOT NULL,
   B_TEL_Work enum('y','n') DEFAULT 'n' NOT NULL,
   B_TEL_Home enum('y','n') DEFAULT 'n' NOT NULL,
   B_TEL_Voice enum('y','n') DEFAULT 'n' NOT NULL,
   B_TEL_Msg enum('y','n') DEFAULT 'n' NOT NULL,
   B_TEL_Fax enum('y','n') DEFAULT 'n' NOT NULL,
   B_TEL_Prefer enum('y','n') DEFAULT 'n' NOT NULL,
   C_TEL varchar(40) DEFAULT '+1 (000) 000-0000' NOT NULL,
   C_TEL_Work enum('y','n') DEFAULT 'n' NOT NULL,
   C_TEL_Home enum('y','n') DEFAULT 'n' NOT NULL,
   C_TEL_Voice enum('y','n') DEFAULT 'n' NOT NULL,
   C_TEL_Msg enum('y','n') DEFAULT 'n' NOT NULL,
   C_TEL_Fax enum('y','n') DEFAULT 'n' NOT NULL,
   C_TEL_Prefer enum('y','n') DEFAULT 'n' NOT NULL,
   D_EMAILTYPE enum('INTERNET','CompuServe','AOL','Prodigy','eWorld','AppleLink','AppleTalk','PowerShare','IBMMail','ATTMail','MCIMail','X.400','TLX') DEFAULT 'INTERNET' NOT NULL,
   D_EMAIL varchar(64),
   D_EMAIL_Work enum('y','n') DEFAULT 'n' NOT NULL,
   D_EMAIL_Home enum('y','n') DEFAULT 'n' NOT NULL,
   PRIMARY KEY (id),
   UNIQUE id (id),
  )";

  $db->query($sql);

  $sql = "CREATE TABLE addressbook_extra (
   contact_id int(11),
   contact_owner int(11),
   contact_name varchar(255),
   contact_value varchar(255)
  )";
  
  $db->query($sql);  

  $currentver = "0.9.8pre5";
  $oldversion = $currentver;
  update_app_version("addressbook");
?>
