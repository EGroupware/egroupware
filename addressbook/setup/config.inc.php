   <tr bgcolor="FFFFFF">
    <td colspan="2">&nbsp;</td>
   </tr>

   <tr bgcolor="486591">
    <td colspan="2"><font color="fefefe">&nbsp;<b>Addressbook/Contact settings (ldap not yet functional)</b></font></td>
   </tr>
   <tr bgcolor="e6e6e6">
    <td>Contact application:</td>
    <? if (!$current_config["contact_application"]) { $current_config["contact_application"] = "addressbook"; } ?>
    <td><input name="newsettings[contact_application]" value="<?php echo $current_config["contact_application"]; ?>"></td>
   </tr>
   <?php $selected[$current_config["contact_repository"]] = " selected"; ?>
   <tr bgcolor="e6e6e6">
    <td>Select where you want to store/retrieve contacts.</td>
    <td>
     <select name="newsettings[contact_repository]">
      <option value="sql"<?php echo $selected["sql"]; ?>>SQL</option>
      <option value="ldap"<?php echo $selected["ldap"]; ?>>LDAP</option>
     </select>
    </td>
   </tr>
   <tr bgcolor="e6e6e6">
    <td>LDAP host for contacts:</td>
    <? if (!$current_config["ldap_contact_host"]) { $current_config["ldap_contact_host"] = $current_config["ldap_host"]; } ?>
    <td><input name="newsettings[ldap_contact_host]" value="<?php echo $current_config["ldap_contact_host"]; ?>"></td>
   </tr>
   <tr bgcolor="e6e6e6">
    <td>LDAP context for contacts:</td>
    <td><input name="newsettings[ldap_contact_context]" value="<?php echo $current_config["ldap_contact_context"]; ?>" size="40"></td>
   </tr>
  <tr bgcolor="e6e6e6">
   <td>LDAP root dn for contacts:</td>
   <? if (!$current_config["ldap_contact_dn"]) { $current_config["ldap_contact_dn"] = $current_config["ldap_root_dn"]; } ?>
   <td><input name="newsettings[ldap_contact_dn]" value="<?php echo $current_config["ldap_contact_dn"]; ?>"></td>
  </tr>
  <tr bgcolor="e6e6e6">
   <td>LDAP root pw for contacts:</td>
   <? if (!$current_config["ldap_contact_pw"]) { $current_config["ldap_contact_pw"] = $current_config["ldap_root_pw"]; } ?>
   <td><input name="newsettings[ldap_contact_pw]" value="<?php echo $current_config["ldap_contact_pw"]; ?>"></td>
  </tr>
