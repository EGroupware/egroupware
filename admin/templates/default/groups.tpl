<!-- BEGIN list -->
<p>
 <table border="0" width="45%" align="center">
  <tr>
   <td align="left">{left_next_matchs}</td>
   <td align="center">{lang_groups}</td>
   <td align="right">{right_next_matchs}</td>
  </tr>
 </table>

 <table border="0" width="45%" align="center">
  <tr bgcolor="{th_bg}">
   <td>{sort_name}</td>
   <td>{header_edit}</td>
   <td>{header_delete}</td>
  </tr>

  {rows}

 </table>

 <table border="0" width="45%" align="center">
  <tr>
   <td align="left">
    <form method="POST" action="{new_action}">
     {input_add}
    </form>
   </td>
   <td align="right">
    <form method="POST" action="{search_action}">
     {input_search}
    </form>
   </td>
  </tr>
 </table>
<!-- END list -->

<!-- BEGIN row -->
 <tr bgcolor="{tr_color}">
  <td>{group_name}</td>
  <td width="5%">{edit_link}</td>
  <td width="5%">{delete_link}</td>
 </tr>
<!-- END row -->

<!-- BEGIN row_empty -->
   <tr>
    <td colspan="5" align="center">{message}</td>
   </tr>
<!-- END row_empty -->
