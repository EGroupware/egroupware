<!-- BEGIN list -->
<br>
<div align="center">
 <table border="0" width="45%">
  <tr>
{left}
    <td align="center">{lang_showing}</td>
{right}
  </tr>
 </table>
 
 <table border="0" width="45%">
  <tr bgcolor="{th_bg}">
   <td> {sort_title} </td>
   <td>{lang_edit}</td>
   <td>{lang_delete}</td>
   <td>{lang_enabled}</td>
  </tr>

  {rows}

 </table>
 
 {addbutton}
<!-- END list -->

<!-- BEGIN add -->
 <table border="0" width="45%">
  <tr>
   <td align="left" nobreak>
    <form method="POST" action="{new_action}">
     <input type="submit" value="{lang_add}"> 
    </form>
   </td>
   <td>
    {lang_note}
   </td>
  </tr>
 </table>
</div>
<!-- END add -->

<!-- BEGIN row -->
  <tr bgcolor="{tr_color}">
   <td>{name}</td>
   <td class="narrow_column">{edit}</td>
   <td class="narrow_column">{delete}</td>
   <td class="narrow_column" align="center">{status}</td>
  </tr>
<!-- END row -->
