PHP ?= php

.PHONY: test lint check integration-mocks

test:
	$(PHP) tests/check-policy.php
	$(PHP) tests/check-plan-limits.php
	$(PHP) tests/check-plan-catalog.php
	$(PHP) tests/check-plan-catalog-ui.php
	$(PHP) tests/check-webhook-target.php
	$(PHP) tests/check-bedrock-server-side.php
	$(PHP) tests/check-ai-workspace.php
	$(PHP) tests/check-activate-readiness.php
	$(PHP) tests/check-site-branding-settings.php
	$(PHP) tests/check-project-qa-regressions.php
	$(PHP) tests/check-session-timeout.php
	$(PHP) tests/check-file-storage-media-ui.php

lint:
	@find . -type f -name '*.php' -not -path './vendor/*' -not -path './node_modules/*' -print0 | xargs -0 -n1 $(PHP) -l

check: test lint

integration-mocks:
	$(PHP) tests/run-local-integration-mocks.php
