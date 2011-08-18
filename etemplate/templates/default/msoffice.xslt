<?xml version="1.0" encoding="ISO-8859-1"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" 
	xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" 
	xmlns:str="http://exslt.org/strings"
	extension-element-prefixes="str"
>
	<xsl:output method="xml" omit-xml-declaration="yes"/>
	<xsl:template name="rbga-to-hex">
		<xsl:param name="rgba-val"/>
		<xsl:param name="count" select="1"/>
		<xsl:variable name="val" select="substring-before($rgba-val,',')"/>
		<xsl:variable name="tail" select="substring-after($rgba-val,concat($val,','))"/>

		<xsl:choose>
			<xsl:when test="$count &lt; 3">
				<xsl:call-template name="to-hex">
					<xsl:with-param name="val" select="$val"/>
				</xsl:call-template>
				<xsl:call-template name="rbga-to-hex">
					<xsl:with-param name="count" select="$count + 1"/>
					<xsl:with-param name="rgba-val" select="$tail"/>
				</xsl:call-template>
			</xsl:when>
			<xsl:otherwise>
				<xsl:call-template name="to-hex">
					<xsl:with-param name="val" select="$rgba-val"/>
				</xsl:call-template>				
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>

	<xsl:template name="to-hex">
		<xsl:param name="val"/>
		<xsl:param name="max" select="255"/>
		<xsl:param name="min" select="0"/>
		<xsl:param name="hex-key" select="'0123456789ABCDEF'"/>

		<!-- REMOVE NON-NUMERIC CHARACTERS -->
		<xsl:variable name="val"
			select="translate($val,'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ,.-_=+!@#$%^*():; ','')"/>

		<!-- insure that the rgb value is within 0-255 -->
		<xsl:variable name="num">
			<xsl:choose>

				<xsl:when test="$val &gt; $max">
					<xsl:value-of select="$max"/>
				</xsl:when>

				<xsl:when test="$val &lt; $min">
					<xsl:value-of select="$min"/>
				</xsl:when>

				<!-- insure that we have whole numbers -->
				<xsl:otherwise>
					<xsl:value-of select="round($val)"/>
				</xsl:otherwise>

			</xsl:choose>
		</xsl:variable>

		<!-- Return Hex Val -->
		<!-- substring(string, position, length) -->
		<xsl:value-of select="concat( substring($hex-key,(ceiling(($num - ceiling($num mod 16)) div 16)+1),1),
		    substring($hex-key,($num mod 16)+1,1)
		)"/>
	</xsl:template>
	<xsl:template match="node()|@*">
		<xsl:copy>
			<xsl:apply-templates select="node()|@*"/>
		</xsl:copy>
	</xsl:template>

<!--	Try to do replacements completely in XSLT
	
-->
<!-- w:p doesn't work right now
	<xsl:template match="w:p[descendant::ul|descendant::ol]">
		<xsl:for-each select="node()|@*">
			<xsl:choose>
			<xsl:when test="descendant::ul|descendant::ol" >
				<xsl:variable name="current" select="." />
				<xsl:variable name="break" select="descendant::*[ul|ol|table]" />
Breakers
	<xsl:copy-of select="$break" />
	</xsl:template>
-->

	<xsl:template match="w:r[descendant::strong|descendant::em|descendant::u|descendant::span]">
		<xsl:for-each select="node()|@*[not(w:rPr)]">
			<xsl:choose>
			<xsl:when test="descendant::strong|descendant::em|descendant::u|descendant::span" >
				<xsl:for-each select="node()|@*">
					<xsl:choose>
					<xsl:when test="descendant-or-self::strong|descendant-or-self::em|descendant-or-self::u|descendant-or-self::span" >
						<w:r>
							<w:rPr>
								<xsl:apply-templates select=".|child::*" />
							</w:rPr>
							<w:t xml:space="preserve"><xsl:value-of select="." /></w:t>
						</w:r>
					</xsl:when>
					<xsl:otherwise>
						<w:r><w:t xml:space="preserve"><xsl:copy-of select="." /></w:t></w:r>
					</xsl:otherwise>
					</xsl:choose>
				</xsl:for-each>
			</xsl:when>
			<xsl:otherwise>
				<w:r>
				<xsl:copy-of select="." />
				</w:r>
			</xsl:otherwise>
			</xsl:choose>
		</xsl:for-each>
	</xsl:template>

	<!-- Fix any bad breaks -->
	<xsl:template match="w:t[child::w:br]">
		<w:t>
		<xsl:copy-of select="text()"/>
		</w:t>
		<w:br/>
	</xsl:template>
	<xsl:template match="i|em">
		<w:i />
	</xsl:template>
	<xsl:template match="b|strong">
		<w:b />
	</xsl:template>
	<xsl:template match="u">
		<w:u w:val="single" />
	</xsl:template>

	<!-- Color & font -->
	<xsl:template match="span">
<!--
		<xsl:choose>
			<xsl:when test="contains(@style,';')">
			</xsl:when>
			<xsl:otherwise>
				<xsl:variable name="style" select="@style" />
			</xsl:otherwise>
		</xsl:choose>
-->
		<xsl:variable name="style" select="str:tokenize(@style,';')" />
		<xsl:for-each select="$style">
		<xsl:if test="starts-with(.,'color:')">
			<xsl:variable name="hex">
				<xsl:choose>
					<xsl:when test="contains(., 'rgb(')">
						<xsl:call-template name="rbga-to-hex">
							<xsl:with-param name="rgba-val" select="substring-after(.,':')"/>
						</xsl:call-template>
					</xsl:when>
					<xsl:otherwise>
						<xsl:value-of select="substring-after(.,'#')" />
					</xsl:otherwise>
				</xsl:choose>
			</xsl:variable>
				<w:color w:val="{$hex}" />
		</xsl:if>
		<xsl:if test="starts-with(.,'background-color:')">
			<xsl:variable name="hex">
				<xsl:choose>
					<xsl:when test="contains(., 'rgb(')">
						<xsl:call-template name="rbga-to-hex">
							<xsl:with-param name="rgba-val" select="substring-after(.,':')"/>
						</xsl:call-template>
					</xsl:when>
					<xsl:otherwise>
						<xsl:value-of select="substring-after(.,'#')" />
					</xsl:otherwise>
				</xsl:choose>
			</xsl:variable>
			<w:shd w:fill="{$hex}"/>
		</xsl:if>
		<xsl:if test="starts-with(.,'font-size')">
			<xsl:variable name="font-size" select="substring-after(text(),'font-size:')" />
			<!-- Approximate conversion that seems to work -->
			<xsl:variable name="size" select="ceiling(number(translate($font-size,translate($font-size,'0123456789',''),''))*1.5)"/>
				<w:sz w:val="{$size}"/>
		</xsl:if>
		<xsl:if test="starts-with(., 'font-family:')">
			<xsl:variable name="font-name" select="translate(substring-before(substring-after(.,'font-family:'),','),&quot;&#39;&quot;,'')" />
			<w:rFonts w:ascii="{$font-name}" w:hAnsi="{$font-name}"/>
		</xsl:if>
		</xsl:for-each>
	</xsl:template>

	<!-- 
	Unordered (bullet) list
	
	Numbers determined by examining a docx file from OpenOffice.org
	 -->
	<xsl:template match="ul[child::li]|ol[child::li]">
		<xsl:for-each select="./li">
			<w:p>
			<w:pPr>
				<w:numPr>
					<w:ilvl w:val="0"/>
					<xsl:choose>
						<xsl:when test="name(..)='ol'">
							<w:numId w:val="2"/>
						</xsl:when>
						<xsl:otherwise>
							<w:numId w:val="1"/>
							<w:numFmt w:val="bullet"/>
							<w:lvlJc w:val="left"/>
							<w:lvlText w:val="&#xB7;"/>
						</xsl:otherwise>
					</xsl:choose>
				</w:numPr>
				<w:tabs>
					<w:tab w:leader="none" w:pos="707" w:val="left"/>
				</w:tabs>
				<w:ind w:hanging="283" w:left="707" w:right="0"/>
				<w:spacing w:after="0" w:before="0"/>
			</w:pPr>
			<w:r>
			<xsl:choose>
				<xsl:when test="name(..)='ol'">
<!--	This line gives numbers when opened in OO.o, but when the file is opened in MSWord, the numbers are doubled.
				<w:t><xsl:number value="position()" format="1" />.	</w:t>
-->
				</xsl:when>
				<xsl:otherwise>
				<w:rPr>
					<w:rFonts w:ascii="Symbol" w:cs="Symbol" w:hAnsi="Symbol" w:hint="default"/>
				</w:rPr>
				<w:t>&#xB7;	</w:t>
				</xsl:otherwise>
			</xsl:choose>
			</w:r>
			<w:r>
				<w:t><xsl:value-of select="normalize-space(text())" /></w:t>
			</w:r>
			</w:p>
		</xsl:for-each>
	</xsl:template>

	<!-- HTML Table -->
	<xsl:template match="table">
		<w:tbl>
			<w:tblPr>
				<w:tblW w:type="dxa" w:w="9972"/>
				<w:jc w:value="left"/>
			</w:tblPr>
			<w:tblGrid>
				<xsl:for-each select="./tr[1]/td">
				<w:gridCol />
				</xsl:for-each>
			</w:tblGrid>
		<xsl:for-each select="./tr">
			<w:tr>
			<xsl:for-each select="./td">
				<w:tc><w:p><w:r><w:t><xsl:apply-templates select="child::node()" /></w:t></w:r></w:p></w:tc>
			</xsl:for-each>
			</w:tr>
		</xsl:for-each>
		</w:tbl>
	</xsl:template>
</xsl:stylesheet>
