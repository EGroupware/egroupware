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
  <tr class="th">
   <td>{sort_name}</td>
   <td>{header_edit}</td>
   <td>{header_delete}</td>
   {header_submit}
  </tr>

  {rows}

 </table>

 <table border="0" width="45%" align="center">
  <tr>
   <td align="left">
    <form method="POST" action="{new_action}">
     <input type="submit" value="{lang_add}">
    </form>
   </td>
   {back_button}
   <td align="right">{lang_search}&nbsp;
    <form method="POST" action="{search_action}">
     <input name="query">
    </form>
   </td>
  </tr>
 </table>
<!-- END list -->
<!-- BEGIN row -->
 <tr class="{tr_color}">
  <td>{group_name}</td>
  <td width="5%">{edit_link}</td>
  <td width="5%">{delete_link}</td>
  {submit_link_column}
 </tr>
<!-- END row -->
<!-- BEGIN row_empty -->
   <tr>
    <td colspan="5" align="center">{message}</td>
   </tr>
<!-- END row_empty -->
<!-- BEGIN submit_column -->
<td{submit_extra}>{submit_link}</td>
<!-- END submit_column -->
<!-- BEGIN back_button_form -->
   <td align="center">
    <form method="POST" action="{back_action}">
     <input type="submit" value="{lang_back}">
    </form>
   </td>
<!-- END back_button_form -->
