# Makefile for phpGroupWare documentation
# Written by Brandon Neill
# Copyright 2002

.PHONY: admin user FAQ

all: admin user FAQ

admin:
	$(MAKE) -C admin

user:
	$(MAKE) -C user

FAQ:
	$(MAKE) -C FAQ

ps:
	$(MAKE) -C admin ps
	$(MAKE) -C user ps
	$(MAKE) -C FAQ ps

html:
	$(MAKE) -C admin html
	$(MAKE) -C user html
	$(MAKE) -C FAQ html

txt:
	$(MAKE) -C admin txt
	$(MAKE) -C user txt
	$(MAKE) -C FAQ txt

clean:
	$(MAKE) -C admin clean
	$(MAKE) -C user clean
	$(MAKE) -C FAQ clean

install:
	$(MAKE) -C admin install
	$(MAKE) -C user install
	$(MAKE) -C FAQ install

