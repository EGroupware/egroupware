<!-- $Id$ -->

	<xsl:template name="app_header">
		<table border="0" cellpadding="2" cellspacing="4" width="98%" align="center">
			<tr>
				<td><b><xsl:value-of select="appname"/></b></td>
			</tr>
			<xsl:apply-templates select="function_msg"/>
		</table>
		<hr noshade="noshade" width="98%" align="center" size="1"/>
	</xsl:template>

	<xsl:template match="function_msg">
		<tr>
			<td><b><xsl:value-of select="."/></b></td>
		</tr>
	</xsl:template>
