	<xsl:template name="app_data">
		<xsl:apply-templates select="list"/>
	</xsl:template>

	<xsl:template match="list">
		<table border="0" width="100%" cellspacing="0" cellpadding="0">
			<tr>
				<td align="left"><xsl:value-of disable-output-escaping="yes" select="tabs"></td>
			</tr>
		</table>
		<table width="75%" border="0" cellspacing="2" cellpadding="2">
			<xsl:apply-templates select="app_link"/>
		</table>
	</xsl:template>

	<xsl:template match="app_link">
		<xsl:variable name="pref_link"><xsl:value-of select="pref_link"/></xsl:variable>
		<xsl:variable name="app_name"><xsl:value-of select="app_name"/></xsl:variable>
		<tr class="th_bright">
			<td height="25" width="95%" valign="bottom"><b>[<xsl:value-of select="app_title">]</b> <a name="{$app_name}"></a></td>
		</tr>
		<tr>
			<td>&nbsp;&#8226;&nbsp;<a href="{$pref_link}"><xsl:value-of select="pref_text"></a></td>
		</tr>
		<tr>
			<td>&nbsp;</td>
		</tr>
	</xsl:template>
