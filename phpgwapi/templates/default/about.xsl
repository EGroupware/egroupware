<!-- $Id$ -->

	<xsl:template name="about">
		<xsl:apply-templates select="about_data"/>
	</xsl:template>

	<xsl:template match="about_data">
		<table cellpadding="2" cellspacing="2" align="center" class="about">
			<xsl:variable name="phpgw_logo" select="phpgw_logo"/>
			<xsl:variable name="lang_url_statustext" select="lang_url_statustext"/>
			<tr>
				<td colspan="2">
					<a href="http://www.phpgroupware.org" target="_blank" onMouseover="window.status='{$lang_url_statustext}'; return true;" onMouseout="window.status=''; return true;">
						<img src="{$phpgw_logo}/logo.png" border="0" alt="{$lang_url_statustext}"/>
					</a>
				</td>
			</tr>
			<tr>
				<td colspan="2">
					<a href="http://www.phpgroupware.org" target="_blank" onMouseover="window.status='{$lang_url_statustext}'; return true;" onMouseout="window.status=''; return true;">
						<xsl:text>phpGroupWare </xsl:text>
					</a>
					<xsl:value-of select="phpgw_descr"/>
				</td>
			</tr>
			<tr>
				<td width="20%">
					<xsl:value-of select="lang_version"/>
				</td>
				<td>
					<xsl:value-of select="phpgw_version"/>
				</td>
			</tr>
			<tr>
				<td height="15"> </td>
			</tr>
			<xsl:apply-templates select="about_app"/>
		</table>
	</xsl:template>

	<xsl:template match="about_app">
		<xsl:variable name="icon" select="icon"/>
		<tr>
			<td colspan="2" valign="middle" class="th_text">
				<xsl:if test="icon != ''">
					<img src="{$icon}"/><xsl:text> </xsl:text>
				</xsl:if>
				<xsl:value-of select="title"/>
			</td>
		</tr>
		<tr>
			<td colspan="2">
				<xsl:value-of disable-output-escaping="yes" select="description"/>
			</td>
		</tr>
		<xsl:if test="note != ''">
			<tr>
				<td colspan="2">
					<i><xsl:value-of select="note"/></i>
				</td>
			</tr>
		</xsl:if>
		<xsl:if test="author != ''">
		<tr>
			<td valign="top">
				<xsl:value-of select="lang_author"/>
			</td>
			<td>
				<xsl:apply-templates select="author"/>
			</td>
		</tr>
		</xsl:if>
		<xsl:if test="maintainer != ''">
			<tr>
				<td valign="top">
					<xsl:value-of select="lang_maintainer"/>
				</td>
				<td>
					<xsl:apply-templates select="maintainer"/>
				</td>
			</tr>
		</xsl:if>
		<tr>
			<td>
				<xsl:value-of select="lang_version"/>
			</td>
			<td>
				<xsl:value-of select="version"/>
			</td>
		</tr>
		<xsl:if test="license != ''">
			<tr>
				<td>
					<xsl:value-of select="lang_license"/>
				</td>
				<td>
					<xsl:value-of select="license"/>
				</td>
			</tr>
		</xsl:if>
		<xsl:if test="based_on != ''">
			<tr>
				<td valign="top"><xsl:value-of select="lang_based_on"/></td>
				<td>
					<xsl:apply-templates select="based_on"/>
				</td>
			</tr>
		</xsl:if>
	</xsl:template>

	<xsl:template match="maintainer">
	<xsl:variable name="email"><xsl:value-of select="email"/></xsl:variable>
		<table>
			<tr>
				<td><xsl:value-of select="name"/><xsl:text> [</xsl:text><a href="mailto:{$email}"><xsl:value-of select="email"/></a><xsl:text>]</xsl:text></td>
			</tr>
		</table>
	</xsl:template>

	<xsl:template match="author">
	<xsl:variable name="email"><xsl:value-of select="email"/></xsl:variable>
		<table>
			<tr>
				<td><xsl:value-of select="name"/><xsl:text> [</xsl:text><a href="mailto:{$email}"><xsl:value-of select="email"/></a><xsl:text>]</xsl:text></td>
			</tr>
		</table>
	</xsl:template>

	<xsl:template match="based_on">
	<xsl:variable name="email"><xsl:value-of select="email"/></xsl:variable>
	<xsl:variable name="url"><xsl:value-of select="url"/></xsl:variable>
		<table>
			<tr>
				<td><xsl:value-of select="info"/></td>
			</tr>
			<tr>
				<td><a href="mailto:{$email}"><xsl:value-of select="email"/></a></td>
			</tr>
			<tr>
				<td><a href="{$url}" target="_blank"><xsl:value-of select="url"/></a></td>
			</tr>
		</table>
	</xsl:template>
