<!-- $Id$ -->

	<xsl:template name="app_data">
		<xsl:choose>
			<xsl:when test="list">
				<xsl:apply-templates select="list"/>
			</xsl:when>
			<xsl:when test="cat_list">
				<xsl:call-template name="cats"/>
			</xsl:when>
			<xsl:when test="cat_edit">
				<xsl:call-template name="cats"/>
			</xsl:when>
			<xsl:when test="group_list">
				<xsl:call-template name="groups"/>
			</xsl:when>
			<xsl:when test="group_edit">
				<xsl:call-template name="groups"/>
			</xsl:when>
			<xsl:when test="account_list">
				<xsl:call-template name="users"/>
			</xsl:when>
			<xsl:when test="account_edit">
				<xsl:call-template name="users"/>
			</xsl:when>
			<xsl:when test="delete">
				<xsl:call-template name="app_delete"/>
			</xsl:when>
			<xsl:otherwise>
				<xsl:apply-templates select="list"/>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>

<!-- BEGIN mainscreen -->

	<xsl:template match="list">
		<table width="75%" border="0" cellspacing="0" cellpadding="0">
			<xsl:choose>
				<xsl:when test="app_row_icon">
					<xsl:apply-templates select="app_row_icon"/>
				</xsl:when>
				<xsl:otherwise>
					<xsl:apply-templates select="app_row_noicon"/>
				</xsl:otherwise>
			</xsl:choose>
		</table>
	</xsl:template>

	<xsl:template match="app_row_icon">
		<xsl:variable name="app_icon" select="app_icon"/>
		<xsl:variable name="app_title" select="app_title"/>
		<xsl:variable name="app_name" select="app_name"/>
		<tr class="th">
			<td width="5%" valign="middle"><img src="{$app_icon}" alt="{$app_title}" name="{$app_title}"/></td>
			<td width="95%" valign="middle" class="th_text"><xsl:value-of select="app_title"/></td>
		</tr>
		<xsl:apply-templates select="link_row"/>
		<tr>
			<td colspan="2">&nbsp;</td>
		</tr>
	</xsl:template>

	<xsl:template match="app_row_noicon">
		<tr class="th">
			<td height="20" colspan="2" width="100%" valign="bottom" class="th_text">&nbsp;<xsl:value-of select="app_title"/></td>
		</tr>
		<xsl:apply-templates select="link_row"/>
		<tr>
			<td colspan="2">&nbsp;</td>
		</tr>
	</xsl:template>

	<xsl:template match="link_row">
		<xsl:variable name="pref_link" select="pref_link"/>
		<tr>
			<xsl:attribute name="class">
				<xsl:choose>
					<xsl:when test="@class">
						<xsl:value-of select="@class"/>
					</xsl:when>
					<xsl:when test="position() mod 2 = 0">
						<xsl:text>row_off</xsl:text>
					</xsl:when>
					<xsl:otherwise>
						<xsl:text>row_on</xsl:text>
					</xsl:otherwise>
				</xsl:choose>
			</xsl:attribute>
			<td colspan="2">&nbsp;&#8226;&nbsp;<a href="{$pref_link}"><xsl:value-of select="pref_text"/></a></td>
		</tr>
	</xsl:template>
