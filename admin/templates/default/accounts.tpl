<!-- BEGIN header -->
<p>
 <table border="0" width="65%" align="center">
  <tr bgcolor="{bg_color}">
   <td align="left">{left_next_matchs}</td>
   <td align="center">{lang_user_accounts}</td>
   <td align="right">{right_next_matchs}</td>
  </tr>
 </table>

 <center>
  <table border=0 width=65%>
   <tr bgcolor="{th_bg}">
    <td>{lang_lastname}</td>
    <td>{lang_firstname}</td>
    <td>{lang_edit}</td>
    <td>{lang_delete}</td>
    <td>{lang_view}</td>
   </tr>
<!-- END header -->

{output}

<!-- BEGIN row -->
   <tr bgcolor="{tr_color}">
    <td>{row_lastname}</td>
    <td>{row_firstname}</td>
    <td width="5%">{row_edit}</td>
    <td width="5%">{row_delete}</td>
    <td width="5%">{row_view}</td>
   </tr>
<!-- END row -->

<!-- BEGIN footer -->
  </table>
 </center>

 <form method="POST" action="{actionurl}">
  <table border="0" width="65%" align="center">
   <tr>
    <td align=left>
     <input type="submit" value="{lang_add}"></form>
    </td>
    <td align="right">
     <form action="accounts.php">
      {lang_search}&nbsp;
      <input name="query">
     </form>
    </td>
   </tr>
  </table>

<!-- END footer -->
