# Makefile for crunching the tinymce_src.js into tinymce.js including the copyright header

all: crunch

crunch:
	ttkcrunch -v ./tiny_mce_src.js -a .crunch -o .;exit 0
	mv tiny_mce.js tiny_mce.js.old
	head -n 8 tiny_mce_src.js > tiny_mce.js
	cat tiny_mce_src.crunch.js >> tiny_mce.js
	rm tiny_mce_src.crunch.js

clean:
	rm tinymce.js.old
