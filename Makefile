default: ecs phpstan tester

phpstan:
	vendor/bin/phpstan

ecs:
	vendor/bin/phpcs --standard=PSR12  ./src

tester:
	vendor/bin/tester tests
