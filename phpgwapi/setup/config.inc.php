   <tr bgcolor="486591">
    <td colspan="2"><font color="fefefe">&nbsp;<b>Path information</b></font></td>
   </tr>
   
   </tr>
   <tr bgcolor="e6e6e6">
    <td>Enter the full path for temporary files.<br>Examples: /tmp, C:\TEMP</td>
    <td><input name="newsettings[temp_dir]" value="<?php echo $current_config['temp_dir']; ?>" size="40"></td>
   </tr>

   <tr bgcolor="e6e6e6">
    <td>Enter the full path for users and group files.<br>Examples: /files, E:\FILES</td>
    <td><input name="newsettings[files_dir]" value="<?php echo $current_config['files_dir']; ?>" size="40"></td>
   </tr>
   
   <tr bgcolor="e6e6e6">
    <td>Enter the location of phpGroupWare's URL.<br>Example: http://www.domain.com/phpgroupware &nbsp; or &nbsp; /phpgroupware<br><b>No trailing slash</b></td>
    <td><input name="newsettings[webserver_url]" value="<?php echo $current_config['webserver_url']; ?>" size="40"></td>
   </tr>

   <tr bgcolor="FFFFFF">
    <td colspan="2">&nbsp;</td>
   </tr>

   <tr bgcolor="486591">
    <td colspan="2"><font color="fefefe">&nbsp;<b>Host information</b></font></td>
   </tr>

   <tr bgcolor="e6e6e6">
	<?php if ($current_config['hostname']) { $thishostname = $current_config['hostname']; }
		else { $thishostname = $SERVER_NAME; }
	?>
    <td>Enter the hostname of the machine on which this server is running.</td>
    <td><input name="newsettings[hostname]" value="<?php echo $thishostname; ?>"></td>
   </tr>

   <tr bgcolor="e6e6e6">
    <td>Enter your default FTP server.</td>
    <td><input name="newsettings[default_ftp_server]" value="<?php echo $current_config['default_ftp_server']; ?>"></td>
   </tr>

   <?php $selected = array(); ?>
   <?php $selected[@$current_config['ftp_use_mime']] = " selected"; ?>
   <tr bgcolor="e6e6e6">
    <td>Attempt to use correct mimetype for FTP instead of default 'application/octet-stream'.</td>
    <td>
     <select name="newsettings[ftp_use_mime]">
      <option value="">No</option>
      <option value="True"<?php echo $selected['True']; ?>>Yes</option>
     </select>
    </td>
   </tr>

   <tr bgcolor="e6e6e6">
    <td>Enter your HTTP proxy server.</td>
    <td><input name="newsettings[httpproxy_server]" value="<?php echo $current_config['httpproxy_server']; ?>"></td>
   </tr>

   <tr bgcolor="e6e6e6">
    <td>Enter your HTTP proxy server port.</td>
    <td><input name="newsettings[httpproxy_port]" value="<?php echo $current_config['httpproxy_port']; ?>"></td>
   </tr>
