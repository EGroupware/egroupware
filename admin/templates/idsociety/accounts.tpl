<!-- BEGIN list -->
<p>
 <table border="0" width="70%" align="center">
  <tr bgcolor="{bg_color}">
   <td align="left">{left_next_matchs}</td>
   <td align="center">{lang_user_accounts}</td>
   <td align="right">{right_next_matchs}</td>
  </tr>
 </table>

 <center>
  <table border="0" width="70%">
   <tr bgcolor="{th_bg}">
    <td>{lang_loginid}</td>
    <td>{lang_lastname}</td>
    <td>{lang_firstname}</td>
    <td>{lang_edit}</td>
    <td>{lang_delete}</td>
    <td>{lang_view}</td>
   </tr>

   {rows}

  </table>
 </center>

 <form method="POST" action="{actionurl}">
  <table border="0" width="70%" align="center">
   <tr>
    <td align="left">
     <input type="submit" value="{lang_add}"></form>
    </td>
    <td align="right">
     <form method="POST" action="{accounts_url}">
      {lang_search}&nbsp;
      <input name="query">
     </form>
    </td>
   </tr>
  </table>

<!-- END list -->
