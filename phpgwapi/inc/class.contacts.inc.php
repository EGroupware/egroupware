<?php
  if (!$phpgw_info['server']['contacts_application']) { $phpgw_info['server']['contacts_application'] = 'addressbook'; }
  include(PHPGW_INCLUDE_ROOT.'/'.$phpgw_info['server']['contacts_application'].'/inc/class.contacts.inc.php');
?>
