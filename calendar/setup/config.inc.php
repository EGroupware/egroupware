<!-- BEGIN calendar/setup/config.inc.php -->
   <tr bgcolor="FFFFFF">
    <td colspan="2">&nbsp;</td>
   </tr>

   <tr bgcolor="486591">
    <td colspan="2"><font color="fefefe">&nbsp;<b>Calendar settings</b></font></td>
   </tr>
   <tr bgcolor="e6e6e6">
    <td>Do you wish to autoload calendar holidays files dynamically?</td>
    <td><input type="checkbox" name="newsettings[auto_load_holidays]" value="True"<?php echo ($current_config['auto_load_holidays']?" checked":""); ?>></td>
   </tr>
   <tr bgcolor="e6e6e6">
    <td>Location to autoload from:</td>
    <td>
     <select name="newsettings[holidays_url_path]">
      <option value="localhost"<?php echo ($current_config['holidays_url_path']=='localhost'?' selected':''); ?>>localhost</option>
      <option value="http://www.phpgroupware.org/cal"<?php echo ($current_config['holidays_url_path']=='http://www.phpgroupware.org/cal'?' selected':''); ?>>www.phpgroupware.org</option>
     </select>
    </td>
   </tr>

