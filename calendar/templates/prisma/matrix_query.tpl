<!-- $Id$ -->
<!-- BEGIN matrix_query -->
<script language="javascript">
<!--
function removeSelectedOptions(id)
{
    openerSelectBox = document.getElementById(id);

    if (openerSelectBox == null) 
	window.close();

    for (i=0; i < openerSelectBox.length; i++) 
    {
	if (openerSelectBox.options[i].selected) 
	{
	    alert (i);
	    openerSelectBox.removeChild(openerSelectBox[i]);
	    openerSelectBox.options[i--] = null	    
        }
    }
}
function bobo()
{
    openerSelectBox = document.getElementById('uicalendar_matrix_users');

    if (openerSelectBox == null) 
	window.close();

    for (i=0; i < openerSelectBox.length; i++) 
    {
    	    alert (i);
    }
}

-->																				
</script>
<center>
<form action="{action_url}" method="post" name="matrixform">
<table border="0" width="98%">
 <tr bgcolor="{th_bg}">
  <td colspan="2" align="center"><b>{title}</b></td>
 </tr>
 {rows}
 <tr>
  <td colspan="2" align="center">
  <img src="" onClick="javascript:removeSelectedOptions('uicalendar_matrix_users')" width="100" height="100">  
    <input type="submit" value="{submit_button}">
  </td>
 </tr>
</table>
</form>
</center>
<!-- END matrix_query -->
<!-- BEGIN list -->
 <tr bgcolor="{tr_color}">
  <td valign="top" width="35%" align="right"><b>&nbsp;{field}&nbsp;:&nbsp;</b></td>
  <td valign="top" width="65%" align="left">{data}</td>
 </tr>
<!-- END list -->
