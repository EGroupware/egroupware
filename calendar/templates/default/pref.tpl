<!-- BEGIN pref -->
<p><b>{title}:</b><hr><p>
<form action="{action_url}" method="POST">
 <table border="0" align="center" width="90%">
  {row}
 </table>
 <center><input type="submit" name="submit" value="{submit_lang}"></center>
</form>
<!-- END pref -->

<!-- BEGIN pref_colspan -->
  <tr bgcolor="{bg_color}">
   <td colspan="2">{text}</td>
  </tr>
<!-- END pref_colspan -->

<!-- BEGIN pref_list -->
  <tr bgcolor="{bg_color}">
   <td align="right">{field}</td>
   <td align="center">{data}</td>
  </tr>
<!-- END pref_list -->
