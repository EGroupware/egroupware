<!-- BEGIN addressbook/inc/config.inc.php -->
   <tr bgcolor="<?php nextcolor();?>">
    <td colspan="2">&nbsp;</td>
   </tr>
   <tr bgcolor="<?php nextcolor();?>">
    <td colspan="2">&nbsp;<b><?php echo lang('Addressbook/Contact settings');?></b></font></td>
   </tr>
   <tr bgcolor="<?php nextcolor();?>">
    <td><?php echo lang('Contact application');?>:</td>
    <?php if (!$current_config["contact_application"]) { $current_config["contact_application"] = "addressbook"; } ?>
    <td><input name="newsettings[contact_application]" value="<?php echo $current_config["contact_application"]; ?>"></td>
   </tr>
   <tr bgcolor="<?php nextcolor();?>">
    <td><?php echo lang('Select from list instead of text entry for country');?>:</td>
    <td><input type="checkbox" name="newsettings[countrylist]" value="True"<?php echo ($current_config["countrylist"]?" checked":""); ?>></td>
   </tr>
   <?php $selected[$current_config["contact_repository"]] = " selected"; ?>
   <tr bgcolor="<?php nextcolor();?>">
    <td><?php echo lang('Select where you want to store/retrieve contacts');?>.</td>
    <td>
     <select name="newsettings[contact_repository]">
      <option value="sql"<?php echo $selected["sql"]; ?>>SQL</option>
      <option value="ldap"<?php echo $selected["ldap"]; ?>>LDAP</option>
     </select>
    </td>
   </tr>
   <tr bgcolor="<?php nextcolor();?>">
    <td><?php echo lang('LDAP host for contacts');?>:</td>
    <?php if (!$current_config["ldap_contact_host"]) { $current_config["ldap_contact_host"] = $current_config["ldap_host"]; } ?>
    <td><input name="newsettings[ldap_contact_host]" value="<?php echo $current_config["ldap_contact_host"]; ?>"></td>
   </tr>
   <tr bgcolor="<?php nextcolor();?>">
    <td><?php echo lang('LDAP context for contacts');?>:</td>
    <td><input name="newsettings[ldap_contact_context]" value="<?php echo $current_config["ldap_contact_context"]; ?>" size="40"></td>
   </tr>
  <tr bgcolor="<?php nextcolor();?>">
   <td><?php echo lang('LDAP root dn for contacts');?>:</td>
   <?php if (!$current_config["ldap_contact_dn"]) { $current_config["ldap_contact_dn"] = $current_config["ldap_root_dn"]; } ?>
   <td><input name="newsettings[ldap_contact_dn]" value="<?php echo $current_config["ldap_contact_dn"]; ?>"></td>
  </tr>
  <tr bgcolor="<?php nextcolor();?>">
   <td><?php echo lang('LDAP root pw for contacts');?>:</td>
   <?php if (!$current_config["ldap_contact_pw"]) { $current_config["ldap_contact_pw"] = $current_config["ldap_root_pw"]; } ?>
   <td><input name="newsettings[ldap_contact_pw]" type="password" value="<?php echo $current_config["ldap_contact_pw"]; ?>"></td>
  </tr>
