<!-- $Id$ -->
<!-- BEGIN matrix_query -->
<script language="javascript">
<!--
function removeSelectedOptions(id)
{
    for(i=0; i<document.matrixform.participants.options.length;i++)
    {	
	if(document.matrixform.participants.options[i].selected==true) 
	{
	    document.matrixform.participants.options[i]=null;
	    i--;
	}
    }
}
function selectAll(id)
{

    for(i=0; i<document.matrixform.participants.options.length;i++)
    {	
	document.matrixform.participants.options[i].selected = true ;
	
    }
/*
    openerSelectBox = document.getElementById(id);

    if (openerSelectBox == null) 
	window.close();

    
    for (i=0; i < openerSelectBox.length; i++) 
	openerSelectBox.options[i].selected = 1 ;
*/
    document.matrixform.submit();
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
 <tr bgcolor="#EEEEEE">
    <td align="right">{delete_participants}&nbsp;:</td>
    <td align="left"><a href="#" onClick="javascript:removeSelectedOptions('uicalendar_matrix_users')"><img  src="/calendar/templates/prisma/images/delete.png" border="0"></a></td>
 </tr>
 
 <tr>
  <td colspan="2" align="center">
    <input type="button" value="{submit_button}" onClick="javascript:selectAll('uicalendar_matrix_users')">
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
