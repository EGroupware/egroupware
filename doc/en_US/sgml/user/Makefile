# Makefile for phpGroupware phpGroupWare-User-Manual
# Written by Brandon Neill
# Copyright 2002
INSTDIR ?= ../..

all:  html ps txt

ps: phpGroupWare-User-Manual.sgml
	sgmltools -b ps phpGroupWare-User-Manual.sgml
	@touch ps

txt: phpGroupWare-User-Manual.sgml
	sgmltools -b txt phpGroupWare-User-Manual.sgml
	@touch txt

html:  phpGroupWare-User-Manual.sgml
	sgmltools -b html phpGroupWare-User-Manual.sgml
	@touch html

install:
	@if [ -e phpGroupWare-User-Manual.txt ]; \
		then \
		echo "Moving phpGroupWare-User-Manual.txt to $(INSTDIR)"; \
		mv phpGroupWare-User-Manual.txt $(INSTDIR)/; \
	fi
	-@if [ -e phpGroupWare-User-Manual ]; \
		then \
		if [ ! -d $(INSTDIR)/html/user ]; \
			then \
			mkdir -p $(INSTDIR)/html/user; \
			else \
			rm $(INSTDIR)/html/user/*.html; \
		fi; \
		echo "Tidying HTML files and moving them to $(INSTDIR)/html/user"; \
		echo "You may get an ignored error here, it's OK";\
		for file in `ls -1 phpGroupWare-User-Manual`; \
			do \
			tidy -i -clean < phpGroupWare-User-Manual/$$file >$(INSTDIR)/html/user/$$file 2> /dev/null; \
			done; \
		rm  -r phpGroupWare-User-Manual; \
	fi
	@if [ -e phpGroupWare-User-Manual.ps ]; \
		then \
		echo "Moving phpGroupWare-User-Manual.ps to $(INSTDIR)/ps"; \
		if [ ! -d $(INSTDIR)/ps ]; \
			then \
			mkdir $(INSTDIR)/ps; \
		fi; \
		mv phpGroupWare-User-Manual.ps $(INSTDIR)/ps; \
	fi

clean: 
	@if [ -e phpGroupWare-User-Manual.txt ];\
		then \
		rm phpGroupWare-User-Manual.txt ;\
	fi
	-rm txt
	@if [ -e phpGroupWare-User-Manual ]; \
		then \
		rm -r phpGroupWare-User-Manual; \
	fi
	-rm html
	@if [ -e phpGroupWare-User-Manual.ps ]; \
		then \
		rm phpGroupWare-User-Manual.ps; \
	fi
	-rm ps

