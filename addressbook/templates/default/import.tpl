
<!-- BEGIN import -->
<CENTER>
  <TABLE WIDTH=90%>
    <TR BGCOLOR="{navbar_bg}">
      <TD><B><FONT SIZE=+2 COLOR="{navbar_text}"><CENTER>{import_text}</CENTER></FONT></B>
      </TD>
    </TR>
    <TR>
      <TD>
        <TABLE WIDTH=85%>
	  <TR>
	    <TD><FORM ENCTYPE="multipart/form-data" action="{action_url}" method="post">
            <OL>
            <LI>In Outlook, select your Contacts folder, select <b>Import 
              and Export...</b> from the <b>File</b> 
              menu and export your contacts into a comma separated text file.<P></LI>
            <LI>Enter the path to the exported file here:
              <INPUT NAME="tsvfile" SIZE=48 TYPE="file" VALUE="{tsvfilename}"><P></LI>
            <LI>Select the type of conversion (Import types will perform an actual import.  Debug will display output in browser or via a download.):<BR>
            <SELECT NAME="conv_type">
            <OPTION VALUE="none">&lt;none&gt;</OPTION>
	    {conv}
            </SELECT><P></LI>
            <LI><INPUT NAME="download" TYPE="checkbox" VALUE="{debug}" CHECKED>Debug output in browser (Uncheck to download output.)</LI>
            <LI>Use this basedn (LDAP)<BR><INPUT NAME="basedn" TYPE="text" VALUE="{basedn}" SIZE="48"></LI>
            <LI>Use this context for storing Contacts (LDAP)<BR><INPUT NAME="context" TYPE="text" VALUE="{context}" SIZE="48"></LI>
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
