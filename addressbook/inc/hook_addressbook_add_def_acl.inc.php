<?php
  global $acl,$account_id;
  // Add default acl to allow the new user to access their
  // addressbook
  // This file is not really needed or used at this time
  $acl->add('addressbook','u_'.$account_id,1);
?>
