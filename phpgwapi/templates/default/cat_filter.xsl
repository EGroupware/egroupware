<!-- $Id$ -->

	<xsl:template name="cat_select">
		<xsl:variable name="select_action"><xsl:value-of select="select_action"/></xsl:variable>
		<xsl:variable name="lang_submit"><xsl:value-of select="lang_submit"/></xsl:variable>
		<form method="post" action="{$select_action}">
			<select name="cat_id" class="forms" onChange="this.form.submit();" onMouseover="window.status='Select the category. To show all entries select NO CATEGORY.';return true;" onMouseout="window.status='';return true;">
				<option value=""><xsl:value-of select="lang_no_cat"/></option>
					<xsl:apply-templates select="cat_list"/>
			</select>
			<noscript>
				<xsl:text> </xsl:text>
				<input type="submit" class="forms" name="submit" value="{$lang_submit}"/> 
			</noscript>
		</form>
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
