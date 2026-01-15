set dotenv-load := false

# default recipe to display help information
default:
  @just --list

# Lint files
@lint:
	./vendor/bin/pint

# Check code quality
@quality:
	./vendor/bin/phpstan analyse --memory-limit=2G

# Run unit and integration tests
@test:
	echo "Running unit and integration tests"; \
	vendor/bin/pest --parallel

# Run tests and create code-coverage report with Herd (PCOV)
@coverage:
	echo "Running unit and integration tests with coverage"
	herd coverage vendor/bin/pest --coverage --min=80 --compact
	just type-coverage

# Run type coverage
@type-coverage:
	vendor/bin/pest --type-coverage --min=80 --compact
