<!-- $Id$ -->

	<xsl:template name="phpgw_top">
		<xsl:variable name="url_current_users"><xsl:value-of select="url_current_users"/></xsl:variable>
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
				<td>
					<a href="{$url_current_users}"><xsl:value-of select="current_users"/></a>
				</td>
			</tr>
		</table>
	</xsl:template>

	<xsl:template match="app_list">
		<xsl:variable name="app_link"><xsl:value-of select="app_link"/></xsl:variable>
		<xsl:variable name="app_icon"><xsl:value-of select="app_icon"/></xsl:variable>
		<xsl:variable name="app_label"><xsl:value-of select="app_label"/></xsl:variable>
		<td>
			<a href="{$app_link}" target="_top"><img src="{$app_icon}" alt="{$app_label}" border="0"></a>
		</td>
	</xsl:template>
