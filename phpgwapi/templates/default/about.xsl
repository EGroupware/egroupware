<!-- $Id$ -->

	<xsl:template match="about">
		<table cellpadding="2" cellspacing="2" width="80%" align="center">
			<xsl:variable name="phpgw_logo"><xsl:value-of select="phpgw_logo"/></xsl:variable>
			<tr>
				<td>
					<a href="http://www.phpgroupware.org" target="_blank"><img src="{$phpgw_logo}" border="0"></a>
				</td>
			</tr>
			<tr>
				<td align="center">
					<xsl:value-of select="lang_version"/><xsl:text>: </xsl:text><xsl:value-of select="phpgw_version"/>
				</td>
			</tr>
			<tr>
				<td>
					<xsl:value-of select="lang_phpgw_descr"/>
				</td>
			</tr>
			<hr noshade="noshade" width="98%" align="center" size="1"/>
			<xsl:apply-template select="about_app"/>
		</table>
	</xsl:template>

	<xsl:template match="about_app">
		<tr>
			<td colspan="2" align="center" class="th_text">
				<xsl:value-of select="app_name"/>
			</td>
		</tr>
		<tr>
			<td colspan="2" align="center">
				<xsl:value-of select="lang_version"/><xsl:text>: </xsl:text><xsl:value-of select="app_version"/>
			</td>
		</tr>
		<tr>
			<td valign="top"><xsl:value-of select="lang_based_on"/></td>
			<td><xsl:value-of select="app_source"/></td>
		</tr>
		<tr>
			<td height="5"></td>
			<td>
				<a href="{$app_source_url}" target="_blank"><xsl:value-of select="lang_app_source_url"/></a>
			</td>
		</tr>
		<tr>
			<td valign="top">
				<xsl:value-of select="lang_written_by"/>
			</td>
			<td>
				<xsl:value-of select="developers"/>
			</td>
		</tr>
		<tr>
			<td height="5"></td>
		</tr>
		<tr>
			<td colspan="2">
				<xsl:value-of select="app_descr"/>
			</td>
		</tr>
	</xsl:template>
