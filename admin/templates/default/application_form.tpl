<!-- BEGIN form -->
<p><b>{lang_header}</b>
<hr><p>
{error}

<form action="{form_action}" method="POST">
 {hidden_vars}
 <div align="center">
 <table border="0" width="55%">
  <tr class="th">
   <td colspan="2">&nbsp;</td>
  </tr>

  {rows}

  <tr>
   <td colspan="2" align="center">
    <input type="submit" name="submit" value="{lang_submit_button}">
   </td>
  </tr>

 </table>
 </div>
</form>
<!-- END form -->

<!-- BEGIN row -->
  <tr class="{tr_color}">
   <td>{label}</td>
   <td>{value}</td>
  </tr>
<!-- END row -->
