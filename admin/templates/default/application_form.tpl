<!-- BEGIN form -->
{error}
<br>
<form action="{form_action}" method="POST">
 {hidden_vars}
 <div align="center">
 <table border="0" width="55%">
  <tr bgcolor="{th_bg}">
   <td colspan="2">&nbsp;</td>
  </tr>

  {rows}

  <tr>
   <td nowrap>
    <input type="submit" name="save" value="{lang_save_button}"> &nbsp;
    <input type="submit" name="cancel" value="{lang_cancel_button}">
   </td>
   <td align="right">&nbsp;
<!-- BEGIN delete_button -->
    <input type="submit" name="delete" value="{lang_delete_button}">
<!-- END delete_button -->
   </td>
  </tr>

 </table>
 </div>
</form>
<!-- END form -->

<!-- BEGIN row -->
  <tr bgcolor="{tr_color}">
   <td>{label}</td>
   <td>{value}</td>
  </tr>
<!-- END row -->
