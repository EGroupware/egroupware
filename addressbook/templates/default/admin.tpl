<!-- BEGIN admin.tpl -->
<p><b>{lang_admin}:</b><hr><p>
   <form method="POST" action="{action_url}">
   <table border="0" align="center" cellspacing="1" cellpadding="1">
    <tr bgcolor="#EEEEEE">
     <td colspan="3">
     {lang_countrylist}
     <input type="checkbox" name="usecountrylist"{countrylist}></td>
    </tr>
    <tr>
     <td colspan="5" align="center">
      <input type="submit" name="submit" value="{lang_submit}">
     </td>
    </tr>
   </table>
   </form>
