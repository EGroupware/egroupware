<!-- BEGIN header -->
<form method="POST" action="{action_url}">
{hidden_vars}
<table border="0" align="center">
   <tr class="th">
	   <td colspan="2"><font color="{th_text}">&nbsp;<b>{title}</b></font></td>
   </tr>
   <tr>
    <td colspan="2">&nbsp;<i><font color="red">{error}</i></font></td>
   </tr>
<!-- END header -->
<!-- BEGIN body -->
   <tr class="th">
    <td colspan="2">&nbsp;<b>{lang_Windows_Popup_Configuration}</b></td>
   </tr>
   <tr class="row_on">
    <td>&nbsp;{lang_Netbios_command}:</td>
    <td><input name="newsettings[winpopup_netbios_command]" value="{value_winpopup_netbios_command}" size="80"></td>
   </tr>
	 <tr class="row_n">
    <td>&nbsp;</td>
    <td>
		<strong>Example:</strong> /bin/echo '[MESSAGE]' | /usr/bin/smbclient -M computer-[4] -I [IP] -U '[SENDER]'<br /><br />
		<strong><u>placeholders:</u></strong><br />
		[MESSAGE] is the notification message itself<br />
		[1] - [4] are the IP-Octets of the windows machine to notify<br />
		[IP] is the IP-Adress of the windows machine to notify<br />
		[SENDER] is the sender of the netbios message configured above<br />
		<strong>Note:</strong> the webserver-user needs execute rights for this command<br />
		Don't forget to enclose placeholders containig whitespaces with apostrophes
	</td>
   </tr>
<!-- END body -->
<!-- BEGIN footer -->
  <tr valign="bottom" style="height: 30px;">
    <td colspan="2" align="center">
      <input type="submit" name="submit" value="{lang_submit}">
      <input type="submit" name="cancel" value="{lang_cancel}">
    </td>
  </tr>
</table>
</form>
<!-- END footer -->
