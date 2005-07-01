<!-- BEGIN list -->
<b>{lang_header}</b>
<hr><p>

 <div align="center">
 <table border="0" width="70%">
  <tr>
   <td align="left">{left_next_matchs}</td>
   <td width="95%" align="center">&nbsp;</td>
   <td align="right">{right_next_matchs}</td>
  </tr>
 </table>
 </div>

 <div align="center">
  <table border="0" width="70%">
   <tr bgcolor="{th_bg}">
    <td>{lang_loginid}</td>
    <td>{lang_lastname}</td>
    <td>{lang_firstname}</td>
    <td>{lang_access}</td>
   </tr>

   {rows}

  </table>
 </div>

 <form method="POST" action="{actionurl}">
  <div align="center">
  <table border="0" width="70%">
   <tr>
    <td align="right">
     <form method="POST" action="{accounts_url}">
      <input name="query" value="{lang_search}">
     </form>
    </td>
   </tr>
  </table>
  </div>

<!-- END list -->

<!-- BEGIN row -->
   <tr bgcolor="{tr_color}">
    <td>{row_loginid}</td>
    <td>{row_lastname}</td>
    <td>{row_firstname}</td>
    <td class="narrow_column">{row_access}</td>
   </tr>
<!-- END row -->

<!-- BEGIN row_empty -->
   <tr>
    <td colspan="5" align="center">{message}</td>
   </tr>
<!-- END row_empty -->
