<!-- BEGIN list -->
<p><b>{lang_installed}</b><hr><p>

<p>
<div align="center">
 <table border="0" width="45%">
  <tr class="bg_color">
{left}
    <td align="center">{lang_showing}</td>
{right}
  </tr>
 </table>
 
 <table border="0" width="45%">
  <tr class="th">
   <td> {sort_title} </td>
   <td>{lang_edit}</td>
   <td>{lang_delete}</td>
   <td>{lang_enabled}</td>
  </tr>

  {rows}

 </table>

 <table border="0" width="45%">
  <tr>
   <td align="left">
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
<!-- END list -->

<!-- BEGIN row -->
  <tr class="{tr_color}">
   <td>{name}</td>
   <td width="5%">{edit}</td>
   <td width="5%">{delete}</td>
   <td width="5%">{status}</td>
  </tr>
<!-- END row -->
