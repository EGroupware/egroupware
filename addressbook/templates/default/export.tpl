
<!-- BEGIN import -->
<CENTER>
  <TABLE WIDTH=90%>
    <TR BGCOLOR="{navbar_bg}">
      <TD><B><FONT SIZE=+2 COLOR="{navbar_text}"><CENTER>{export_text}</CENTER></FONT></B>
      </TD>
    </TR>
    <TR>
      <TD>
        <TABLE WIDTH=85%>
	  <TR>
	    <TD><FORM ENCTYPE="multipart/form-data" action="{action_url}" method="post">
            <OL>
            <LI>Select the type of conversion (Debug will display output in browser.):<BR>
            <SELECT NAME="conv_type">
            <OPTION VALUE="none">&lt;none&gt;</OPTION>
	    {conv}
            </SELECT><P></LI>
			<LI>{filename}:
			  <INPUT NAME="tsvfilename" VALUE="conversion.txt"></LI>
            <LI><INPUT NAME="download" TYPE="checkbox" checked>Download export file (Uncheck to debug output in browser)</LI>
            <LI><INPUT NAME="convert" TYPE="submit" VALUE="{download}"></LI>
            </OL>
            </FORM></TD>
          </TR>
        </TABLE>
      </TD>
    </TR>
   <tr>
     <td width="8%">
       <div align="left">
        <form action="{cancel_url}" method="post">
	<input type="submit" name="Cancel" value="{lang_cancel}">
	</form>
       </div>
     </td>
     <td width="64%">&nbsp;</td>
     <td width="32">&nbsp;</td>
   </tr>
  </TABLE>
</CENTER>
<!-- END import -->
