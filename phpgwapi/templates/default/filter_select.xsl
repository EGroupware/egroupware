<!-- $Id$ -->

	<xsl:template name="filter_select">
		<xsl:variable name="select_url"><xsl:value-of select="select_url"/></xsl:variable>
		<xsl:variable name="lang_submit"><xsl:value-of select="lang_submit"/></xsl:variable>
		<form method="post" action="{$select_url}">
			<select name="filter" onChange="this.form.submit()" onMouseout="window.status='';return true;">
				<xsl:attribute name="onMouseover">
					<xsl:text>window.status='</xsl:text>
						<xsl:value-of select="lang_filter_statustext"/>
					<xsl:text>'; return true;</xsl:text>
				</xsl:attribute>
				<xsl:apply-templates select="filter_list"/>
			</select>
			<noscript>
				<xsl:text> </xsl:text>
				<input type="submit" class="forms" name="submit" value="{$lang_submit}"/> 
			</noscript>
		</form>
	</xsl:template>

	<xsl:template match="filter_list">
	<xsl:variable name="key"><xsl:value-of select="key"/></xsl:variable>
		<xsl:choose>
			<xsl:when test="selected">
				<option value="{$key}" selected="selected"><xsl:value-of select="lang"/></option>
			</xsl:when>
			<xsl:otherwise>
				<option value="{$key}"><xsl:value-of select="lang"/></option>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>
