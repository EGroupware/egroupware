# Makefile for phpGroupware phpGroupWare-Admin-Manual
# Written by Brandon Neill
# Copyright 2002

INSTDIR ?= ../..

all:  html ps txt

ps: phpGroupWare-Admin-Manual.sgml
	sgmltools -b ps phpGroupWare-Admin-Manual.sgml
	@touch ps

txt: phpGroupWare-Admin-Manual.sgml
	sgmltools -b txt phpGroupWare-Admin-Manual.sgml
	@touch txt

html:  phpGroupWare-Admin-Manual.sgml
	sgmltools -b html phpGroupWare-Admin-Manual.sgml
	@touch html

install:
	@if [ -e phpGroupWare-Admin-Manual.txt ]; \
		then \
		echo "Moving phpGroupWare-Admin-Manual.txt to $(INSTDIR)"; \
		mv phpGroupWare-Admin-Manual.txt $(INSTDIR)/; \
	fi
	-@if [ -e phpGroupWare-Admin-Manual ]; \
		then \
		if [ ! -d $(INSTDIR)/html/admin ]; \
			then \
			mkdir -p $(INSTDIR)/html/admin; \
			else \
			rm $(INSTDIR)/html/user/*.html; \
		fi; \
		echo "Tidying HTML files and moving them to $(INSTDIR)/html/admin"; \
		echo "You may get an ignored error here, it's OK";\
		for file in `ls -1 phpGroupWare-Admin-Manual`; \
			do \
			tidy -i -clean < phpGroupWare-Admin-Manual/$$file >$(INSTDIR)/html/admin/$$file 2> /dev/null; \
			done; \
		rm -r phpGroupWare-Admin-Manual; \
	fi
	@if [ -e phpGroupWare-Admin-Manual.ps ]; \
		then \
		echo "Moving phpGroupWare-Admin-Manual.ps to $(INSTDIR)/ps"; \
		if [ ! -d $(INSTDIR)/ps ]; \
			then \
			mkdir $(INSTDIR)/ps; \
		fi; \
		mv phpGroupWare-Admin-Manual.ps $(INSTDIR)/ps; \
	fi

clean: 
	@if [ -e phpGroupWare-Admin-Manual.txt ];\
		then \
		rm phpGroupWare-Admin-Manual.txt ;\
	fi
	-rm txt
	@if [ -e phpGroupWare-Admin-Manual ]; \
		then \
		rm -r phpGroupWare-Admin-Manual; \
	fi
	-rm html
	@if [ -e phpGroupWare-Admin-Manual.ps ]; \
		then \
		rm phpGroupWare-Admin-Manual.ps; \
	fi
	-rm ps

