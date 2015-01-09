<!-- BEGIN form -->
<form method="POST" action="{form_action}">
 <table border="0" align="center" >
 <input type="hidden" name="select_lang" value="{select_lang}">
 <input type="hidden" name="section" value="{section}">
  {rows}

 </table>
</form>
<!-- END form -->

<!-- BEGIN row -->
  <tr >
   <td>{label}</td>
   <td>{value}</td>
  </tr>
<!-- END row -->

<!-- BEGIN row_2 -->
  <tr >
   <td colspan="2">{value}</td>
  </tr>
<!-- END row_2 -->
