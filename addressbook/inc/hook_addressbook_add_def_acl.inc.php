<?php
  global $acl,$account_id;
  // Add default acl to allow the new user to access their
  // addressbook
  $acl->add('addressbook','u_'.$account_id,1);
?>
