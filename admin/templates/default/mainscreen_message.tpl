<!-- BEGIN form -->
<p><b>{header_lang}</b><hr>

{error_message}<p>

<form method="POST" action="{form_action}">
 <table border="0" align="center" width="60%">

  {rows}

 </table>
</form>
<!-- END form -->
 
<!-- BEGIN row -->
  <tr bgcolor="{tr_color}">
   <td>{label}</td>
   <td align="center">{value}</td>
  </tr>
<!-- END row -->

<!-- BEGIN row_2 -->
  <tr bgcolor="{tr_color}">
   <td colspan="2" align="center">{value}</td>
  </tr>
<!-- END row_2 -->
