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
		echo "Moving FAQ.txt to $(INSTDIR)"; \
		mv FAQ.txt $(INSTDIR)/; \
	fi
	-@if [ -e FAQ.html ]; \
		then \
		if [ ! -d $(INSTDIR)/html/FAQ ]; \
			then \
			mkdir -p $(INSTDIR)/html/FAQ; \
		fi; \
		echo "Tidying HTML files and moving them to $(INSTDIR)/html"; \
		echo "You may get an ignored error here, it's OK";\
		tidy -i -clean <FAQ.html >$(INSTDIR)/html/FAQ/FAQ.html 2> /dev/null; \
		rm FAQ.html; \
	fi
	@if [ -e FAQ.ps ]; \
		then \
		echo "Moving FAQ.ps to $(INSTDIR)/ps"; \
		if [ ! -d $(INSTDIR)/ps ]; \
			then \
			mkdir $(INSTDIR)/ps; \
		fi; \
		mv FAQ.ps $(INSTDIR)/ps; \
	fi

clean: 
	@if [ -e FAQ.txt ];\
		then \
		rm FAQ.txt ;\
	fi
	-rm txt
	@if [ -e FAQ.html ]; \
		then \
		rm FAQ.html; \
	fi
	-rm html
	@if [ -e FAQ.ps ]; \
		then \
		rm FAQ.ps; \
	fi
	-rm ps

