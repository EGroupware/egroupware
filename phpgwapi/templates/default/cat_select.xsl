<!-- $Id$ -->

	<xsl:template name="cat_select">
	<xsl:variable name="lang_cat_statustext"><xsl:value-of select="lang_cat_statustext"/></xsl:variable>
	<xsl:variable name="select_name"><xsl:value-of select="select_name"/></xsl:variable>
		<select name="{$select_name}" class="forms" onMouseover="window.status='{$lang_cat_statustext}'; return true;" onMouseout="window.status='';return true;">
			<option value=""><xsl:value-of select="lang_no_cat"/></option>
				<xsl:apply-templates select="cat_list"/>
		</select>
	</xsl:template>

	<xsl:template match="cat_list">
	<xsl:variable name="cat_id"><xsl:value-of select="cat_id"/></xsl:variable>
		<xsl:choose>
			<xsl:when test="selected != ''">
				<option value="{$cat_id}" selected="selected"><xsl:value-of disable-output-escaping="yes" select="name"/></option>
			</xsl:when>
			<xsl:otherwise>
				<option value="{$cat_id}"><xsl:value-of disable-output-escaping="yes" select="name"/></option>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>
