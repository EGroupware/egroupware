   <tr bgcolor="FFFFFF">
    <td colspan="2">&nbsp;</td>
   </tr>

   <tr bgcolor="486591">
    <td colspan="2"><font color="fefefe">&nbsp;<b>Authentication / Accounts</b></font></td>
   </tr>

   <?php $selected = array(); ?>
   <?php $selected[$GLOBALS['current_config']['auth_type']] = ' selected'; ?>
   <tr bgcolor="e6e6e6">
    <td>Select which type of authentication you are using.</td>
    <td>
     <select name="newsettings[auth_type]">
      <option value="sql"<?php echo $selected['sql']; ?>>SQL</option>
      <option value="sqlssl"<?php echo $selected['sqlssl']; ?>>SQL / SSL</option>
      <option value="ldap"<?php echo $selected['ldap']; ?>>LDAP</option>
      <option value="mail"<?php echo $selected['mail']; ?>>Mail</option>
      <option value="http"<?php echo $selected['http']; ?>>HTTP</option>
      <option value="pam"<?php echo $selected['pam']; ?>>PAM (Not Ready)</option>
     </select>
    </td>
   </tr>

   <?php $selected = array(); ?>
   <?php $selected[$GLOBALS['current_config']['account_repository']] = ' selected'; ?>
   <tr bgcolor="e6e6e6">
    <td>Select where you want to store/retrieve user accounts.</td>
    <td>
     <select name="newsettings[account_repository]">
      <option value="sql"<?php echo $selected['sql']; ?>>SQL</option>
      <option value="ldap"<?php echo $selected['ldap']; ?>>LDAP</option>
      <option value="contacts"<?php echo $selected['contacts']; ?>>Contacts - EXPERIMENTAL</option>
     </select>
    </td>
   </tr>

   <?php $selected = array(); ?>
   <?php $selected[$GLOBALS['current_config']['file_repository']] = ' selected'; ?>
   <tr bgcolor="e6e6e6">
    <td>Select where you want to store/retrieve filesystem information.</td>
    <td>
     <select name="newsettings[file_repository]">
      <option value="sql"<?php echo $selected['sql']; ?>>SQL</option>
     </select>
    </td>
   </tr>

   <tr bgcolor="e6e6e6">
    <td>Minimum account id (e.g. 500 or 100, etc.):</td>
    <td><input name="newsettings[account_min_id]" value="<?php echo $GLOBALS['current_config']['account_min_id']; ?>"></td>
   </tr>
   <tr bgcolor="e6e6e6">
    <td>Maximum account id (e.g. 65535 or 1000000):</td>
    <td><input name="newsettings[account_max_id]" value="<?php echo $GLOBALS['current_config']['account_max_id']; ?>"></td>
   </tr>

   <?php $selected = array(); ?>
   <?php $selected[$GLOBALS['current_config']['ldap_extra_attributes']] = ' selected'; ?>
   <tr bgcolor="e6e6e6">
     <td>If using LDAP, do you want to manage homedirectory and loginshell attributes?:</td>
     <td>
      <select name="newsettings[ldap_extra_attributes]">
       <option value="">No</option>
       <option value="True"<?php echo $selected['True']?>>Yes</option>
      </select>
     </td>
    </tr>

   <tr bgcolor="e6e6e6">
    <td>&nbsp;&nbsp;&nbsp;LDAP Default homedirectory prefix (e.g. /home for /home/username):</td>
    <td><input name="newsettings[ldap_account_home]" value="<?php echo $GLOBALS['current_config']['ldap_account_home']; ?>"></td>
   </tr>

   <tr bgcolor="e6e6e6">
    <td>&nbsp;&nbsp;&nbsp;LDAP Default shell (e.g. /bin/bash):</td>
    <td><input name="newsettings[ldap_account_shell]" value="<?php echo $GLOBALS['current_config']['ldap_account_shell']; ?>"></td>
   </tr>

   <?php $selected = array(); ?>
   <?php $selected[$GLOBALS['current_config']['auto_create_acct']] = ' selected'; ?>
   <tr bgcolor="e6e6e6">
     <td>Auto create account records for authenticated users:</td>
     <td>
      <select name="newsettings[auto_create_acct]">
       <option value="">No</option>
       <option value="True"<?php echo $selected['True']?>>Yes</option>
      </select>
     </td>
    </tr>

   <tr bgcolor="e6e6e6">
    <td>Add auto-created users to this group ('Default' will be attempted if this is empty.):</td>
    <td><input name="newsettings[default_group_lid]" value="<?php echo $GLOBALS['current_config']['default_group_lid']; ?>"></td>
   </tr>

   <?php $selected = array(); ?>
   <?php $selected[$GLOBALS['current_config']['acl_default']] = ' selected'; ?>
   <tr bgcolor="e6e6e6">
    <td>If no ACL records for user or any group the user is a member of: </td>
    <td>
     <select name="newsettings[acl_default]">
      <option value="deny"<?php echo $selected['deny']; ?>>Deny Access</option>
      <option value="grant"<?php echo $selected['grant']; ?>>Grant Access</option>
     </select>
    </td>
   </tr>

   <tr bgcolor="e6e6e6">
    <td>LDAP host:</td>
    <td><input name="newsettings[ldap_host]" value="<?php echo $GLOBALS['current_config']['ldap_host']; ?>"></td>
   </tr>

   <tr bgcolor="e6e6e6">
    <td>LDAP accounts context:</td>
    <td><input name="newsettings[ldap_context]" value="<?php echo $GLOBALS['current_config']['ldap_context']; ?>" size="40"></td>
   </tr>

   <tr bgcolor="e6e6e6">
    <td>LDAP groups context:</td>
    <td><input name="newsettings[ldap_group_context]" value="<?php echo $GLOBALS['current_config']['ldap_group_context']; ?>" size="40"></td>
   </tr>

   <tr bgcolor="e6e6e6">
    <td>LDAP root dn:</td>
    <td><input name="newsettings[ldap_root_dn]" value="<?php echo $GLOBALS['current_config']['ldap_root_dn']; ?>" size="40"></td>
   </tr>

   <tr bgcolor="e6e6e6">
    <td>LDAP root password:</td>
    <td><input name="newsettings[ldap_root_pw]" type="password" value="<?php echo $GLOBALS['current_config']['ldap_root_pw']; ?>"></td>
   </tr>

   <?php $selected = array(); ?>
   <?php $selected[$GLOBALS['current_config']['ldap_encryption_type']] = ' selected'; ?>
   <tr bgcolor="e6e6e6">
    <td>LDAP encryption type</td>
    <td>
     <select name="newsettings[ldap_encryption_type]">
      <option value="DES"<?php echo $selected['DES']; ?>>DES</option>
      <option value="MD5"<?php echo $selected['MD5']; ?>>MD5</option>
     </select>
    </td>
   </tr>
   <?php $selected = array(); ?>

   <tr bgcolor="e6e6e6">
    <td>Enter some random text for app_session <br>encryption (requires mcrypt)</td>
    <td><input name="newsettings[encryptkey]" value="<?php echo $GLOBALS['current_config']['encryptkey']; ?>" size="40"></td>
   </tr>
