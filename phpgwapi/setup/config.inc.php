   <tr bgcolor="486591">
    <td colspan="2"><font color="fefefe">&nbsp;<b>Directory information</b></font></td>
   </tr>
   
   </tr>
   <tr bgcolor="e6e6e6">
    <td>Enter file path for temporary files.</td>
    <td><input name="newsettings[temp_dir]" value="<?php echo $current_config["temp_dir"]; ?>" size="40"></td>
   </tr>

   <tr bgcolor="e6e6e6">
    <td>Enter file path for users and group files.</td>
    <td><input name="newsettings[files_dir]" value="<?php echo $current_config["files_dir"]; ?>" size="40"></td>
   </tr>
   
   <tr bgcolor="e6e6e6">
    <td>Enter the location of phpGroupWare's URL.<br>Example: http://www.domain.com/phpgroupware<br></td>
    <td><input name="newsettings[webserver_url]" value="<?php echo $current_config["webserver_url"]; ?>" size="40"></td>
   </tr>

   <tr bgcolor="FFFFFF">
    <td colspan="2">&nbsp;</td>
   </tr>

   <tr bgcolor="486591">
    <td colspan="2"><font color="fefefe">&nbsp;<b>Host information</b></font></td>
   </tr>

   <tr bgcolor="e6e6e6">
    <td>Enter your default FTP server.</td>
    <td><input name="newsettings[default_ftp_server]" value="<?php echo $current_config["default_ftp_server"]; ?>"></td>
   </tr>

   <tr bgcolor="e6e6e6">
    <td>Enter your HTTP proxy server.</td>
    <td><input name="newsettings[httpproxy_server]" value="<?php echo $current_config["httpproxy_server"]; ?>"></td>
   </tr>

   <tr bgcolor="e6e6e6">
    <td>Enter your HTTP proxy server port.</td>
    <td><input name="newsettings[httpproxy_port]" value="<?php echo $current_config["httpproxy_port"]; ?>"></td>
   </tr>

   <tr bgcolor="e6e6e6">
    <td>Enter the hostname of the machine this server is running on.</td>
    <td><input name="newsettings[hostname]" value="<?php echo $SERVER_NAME; ?>"></td>
   </tr>
