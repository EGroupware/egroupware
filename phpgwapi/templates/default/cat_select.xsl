<!-- $Id$ -->

	<xsl:template name="cat_select">
		<option value=""><xsl:value-of select="lang_none"/></option>
			<xsl:apply-templates select="cat_list"/>
	</xsl:template>

	<xsl:template match="cat_list">
	<xsl:variable name="id"><xsl:value-of select="id"/></xsl:variable>
		<xsl:choose>
			<xsl:when test="selected">
				<option value="{$id}" selected="selected"><xsl:value-of select="name"/></option>
			</xsl:when>
			<xsl:otherwise>
				<option value="{$id}"><xsl:value-of select="name"/></option>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>
