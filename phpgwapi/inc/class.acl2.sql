#
# Table structure for table 'phpgw_acl2'
#
#


CREATE TABLE `phpgw_acl2` (
  `acl_host` int(11) NOT NULL default '0',
  `acl_appid` int(11) NOT NULL default '0',
  `acl_account` int(11) NOT NULL default '0',
  `acl_location` varchar(255) NOT NULL default '',
  `acl_rights` int(11) default NULL,
  `acl_type` tinyint(4) NOT NULL default '0',
  `acl_data` text
);

#
# Sample data for table 'phpgw_acl2'
#

INSERT INTO `phpgw_acl2` VALUES("0", "0", "1", "5", "1", "0", NULL);
INSERT INTO `phpgw_acl2` VALUES("0", "0", "5", "6", "1", "0", NULL);
INSERT INTO `phpgw_acl2` VALUES("0", "1", "1", ".run", "1", "0", NULL);
INSERT INTO `phpgw_acl2` VALUES("0", "1", "1", ".one.three.four", "1", "0", NULL);
INSERT INTO `phpgw_acl2` VALUES("0", "1", "6", ".one.two", "3", "0", NULL);
INSERT INTO `phpgw_acl2` VALUES("0", "1", "1", ".one.two.three", "2", "0", "");
INSERT INTO `phpgw_acl2` VALUES("0", "1", "1", ".one.two", "3", "1", "");
