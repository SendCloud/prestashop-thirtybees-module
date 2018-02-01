.PHONY: ps-module tb-module

IGNORE_PATTERNS=-x *.git* -x *config*.xml -x *.idea* -x *.editor* -x *.DS_Store* -x *.vscode*

ps-module: ./sendcloud/sendcloud.php
	rm -f sendcloud.zip
	zip -r sendcloud.zip ./sendcloud $(IGNORE_PATTERNS)

tb-module: ./sendcloud/sendcloud.php
	rm -f sendcloud.zip
	zip -r sendcloud.zip ./sendcloud ./.tbstore ./.tbstore.yml $(IGNORE_PATTERNS)
