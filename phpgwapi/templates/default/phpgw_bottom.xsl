<!-- $Id$ -->

	<xsl:template name="phpgw_bottom">
	<xsl:variable name="phpgw_url"><xsl:value-of select="phpgw_url"/></xsl:variable>
		<table border="0" cellspacing="0" cellpadding="0" width="100%" class="navbar">
			<tr>
				<td valign="middle" align="center">
					<xsl:value-of select="lang_powered_by"/>
					<a href="{phpgw_url}" target="_blank"><xsl:text>phpGroupWare</xsl:text></a>
					<xsl:value-of select="lang_version"/><xsl:text> </xsl:text>
					<xsl:value-of select="phpgw_version"/>
				</td>
			</tr>
		</table>
	</xsl:template>
