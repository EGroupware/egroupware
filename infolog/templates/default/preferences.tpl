<!-- BEGIN pref_line -->
  <tr bgcolor="{bg_nm_color}">
   <td align="right">{field}</td>
   <td align="center">{data}</td>
  </tr>
<!-- END pref_line -->

<!-- BEGIN info_prefs -->
<p><b>{title}:</b><hr><p>
<form action="{action_url}" method="POST">
 <table border="0" align="center" width="90%">
  <tr bgcolor="{bg_h_color}">
   <td colspan="2">{text}</td>
  </tr>
  {pref_lines}
 </table>
 <center>{save_button}</center>
</form>
<!-- END info_prefs -->
