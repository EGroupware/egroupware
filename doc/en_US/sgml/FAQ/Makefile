# Makefile for phpGroupware FAQ
# Written by Brandon Neill
# Copyright 2002

INSTDIR ?= ../..

all:  html ps txt

ps: FAQ.sgml
	sgmltools -b ps FAQ.sgml
	@touch ps

txt: FAQ.sgml
	sgmltools -b txt FAQ.sgml
	@touch txt

html:  FAQ.sgml
	sgmltools -b onehtml FAQ.sgml
	@touch html

install:
	@if [ -e FAQ.txt ]; \
		then \
		echo "Copying FAQ.txt to $(INSTDIR)"; \
		cp FAQ.txt $(INSTDIR)/; \
	fi
	-@if [ -e FAQ.html ]; \
		then \
		if [ ! -d $(INSTDIR)/html/FAQ ]; \
			then \
			mkdir -p $(INSTDIR)/html/FAQ; \
		fi; \
		echo "Tidying HTML files and coping them to $(INSTDIR)/html"; \
		echo "You may get an ignored error here, it's OK";\
		tidy -i -clean <FAQ.html >$(INSTDIR)/html/FAQ/FAQ.html 2> /dev/null; \
	fi
	@if [ -e FAQ.ps ]; \
		then \
		echo "Copying FAQ.ps to $(INSTDIR)/ps"; \
		if [ ! -d $(INSTDIR)/ps ]; \
			then \
			mkdir $(INSTDIR)/ps; \
		fi; \
		cp FAQ.ps $(INSTDIR)/ps; \
	fi

clean: 
	@if [ -e FAQ.txt ];\
		then \
		rm FAQ.txt ;\
		rm txt; \
	fi
	@if [ -e FAQ.html ]; \
		then \
		rm FAQ.html; \
		rm html; \
	fi
	@if [ -e FAQ.ps ]; \
		then \
		rm FAQ.ps; \
		rm ps; \
	fi

