<!-- $Id$ -->

	<xsl:template name="phpgw_top">
		<table width="100%" cellspacing="0" cellpadding="0">
			<tr class="navbar">
				<xsl:apply-templates select="app_list"/>
			</tr>
			<tr>
				<td>
					<xsl:value-of select="user_info"/>
				</td>
			</tr>
			<tr>
				<td><xsl:value-of select="current_users"/></td>
			</tr>
		</table>
	</xsl:template>

	<xsl:template match="app_list">
		<xsl:variable name="app_link"><xsl:value-of select="app_link"/></xsl:variable>
		<xsl:variable name="app_icon"><xsl:value-of select="app_icon"/></xsl:variable>
		<td>
			<a href="{$app_link}" target="_top"><img src="{$app_icon}" border="0"></a>
		</td>
	</xsl:template>
