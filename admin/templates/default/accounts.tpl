<!-- BEGIN list -->
<p>
 <div align="center">
  <table border="0" width="70%">
   <tr>
    <td align="left">{left_next_matchs}</td>
    <td align="center">{lang_user_accounts}</td>
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
    <td>{lang_edit}</td>
    <td>{lang_delete}</td>
    <td>{lang_view}</td>
   </tr>

   {rows}

  </table>
 </div>

 <form method="POST" action="{actionurl}">
  <div align="center">
   <table border="0" width="70%">
    <tr>
     <td align="left">
      {input_add}
      </form>
     </td>
     <td align="center">
      <form method="POST" action="{accounts_url}">
       {input_search}
      </form>
     </td>
     <td align="right">
      <form method="POST" action="{doneurl}">
       <input type="submit" name="done" value="{lang_done}">
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
    <td width="5%">{row_edit}</td>
    <td width="5%">{row_delete}</td>
    <td width="5%">{row_view}</td>
   </tr>
<!-- END row -->

<!-- BEGIN row_empty -->
   <tr>
    <td colspan="5" align="center">{message}</td>
   </tr>
<!-- END row_empty -->
