<!-- BEGIN addressbook_footer -->
 </table>
 <form method="POST" action="{actionurl}">
 <input type="hidden" name="start" value="{start}">
 <input type="hidden" name="filter" value="{filter}">
 <table width="75%" border="0" cellspacing="0" cellpadding="4">
   <tr> 
     <td width="4%"> 
       <div align="left"> 
         <input type="submit" name="Add" value="{lang_add}">
       </div>
     </td>
     <td width="8%">
       <div align="left">
        <input type="submit" name="AddVcard" value="{lang_addvcard}">
       </div>
     </td>
     <td width="64%">&nbsp;</td>
     <td width="24%">&nbsp;</td>
   </tr>
   </form>
   <tr>
     <td width="8%">
       <div align="right">
        <form action="{import_url}" method="post">
	<input type="submit" name="Import" value="{lang_import}">
	</form>
       </div>
     </td>
     <td width="64%">&nbsp;</td>
     <td width="32">&nbsp;</td>
   </tr>
 </table>
 </center>
<!-- END addressbook_footer -->
