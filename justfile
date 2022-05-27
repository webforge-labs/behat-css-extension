set positional-arguments

set dotenv-load := true

php := "docker-compose exec php"

phpunit *args='':
    {{php}} vendor/bin/phpunit "$@"

phpstan *args='':
    {{php}} vendor/bin/phpstan "$@"

composer *args='':
    {{php}} composer "$@"

unit filter:
    just phpunit --filter {{filter}}

release:
    release-it
