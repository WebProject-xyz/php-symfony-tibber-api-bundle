# PHP Package Template

[![CI](https://github.com/WebProject-xyz/php-package-template/actions/workflows/ci.yml/badge.svg)](https://github.com/WebProject-xyz/php-package-template/actions/workflows/ci.yml)
[![PHP Version](https://img.shields.io/badge/php-~8.5.0-blue.svg)](https://www.php.net/)
[![Codeception](https://img.shields.io/badge/codeception-%5E5.3-red.svg)](https://codeception.com/)
[![PHPStan](https://img.shields.io/badge/phpstan-level%208-brightgreen.svg)](https://phpstan.org/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

> A modern, opinionated GitHub template repository for building PHP 8.5+ packages and libraries with standardized quality tooling, static analysis, and automated CI/CD workflows.

---

## 📦 What's Included

- **PHP 8.5+**: Configured with strict types (`declare(strict_types=1);`) and platform version `8.5.0`.
- **Testing**: [Codeception 5](https://codeception.com/) Unit suite with [AI Reporter](https://github.com/WebProject-xyz/codeception-module-ai-reporter) integration for structured test output.
- **Static Analysis**: [PHPStan](https://phpstan.org/) at **Level 8** (`phpstan.neon`).
- **Coding Standards**: [PHP-CS-Fixer](https://cs.symfony.com/) with centralized configuration via [`webproject-xyz/php-cs-fixer-config`](https://github.com/WebProject-xyz/php-cs-fixer-config).
- **Git Hooks**: [GrumPHP](https://github.com/phpro/grumphp) pre-commit automation running style checks, static analysis, and test suites.
- **Continuous Integration**: Reusable GitHub Actions CI workflow with matrix testing and test coverage generation.
- **Release Automation**: Pre-configured [Semantic Release](https://github.com/semantic-release/semantic-release) (`.releaserc`) and [Renovate](https://docs.renovatebot.com/) (`renovate.json`).

---

## 🚀 Using This Template

### 1. Create a Repository from this Template

Use GitHub's web interface by clicking **"Use this template"** &rarr; **"Create a new repository"**, or use the [GitHub CLI](https://cli.github.com/):

```bash
gh repo create <vendor>/<package-name> --template WebProject-xyz/php-package-template --public --clone
cd <package-name>
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Initialize Git Hooks

Initialize GrumPHP to enable pre-commit quality checks:

```bash
vendor/bin/grumphp git:init
```

### 4. Customize for Your Package

After initializing the project, adjust the configuration to match your package:

1. **`composer.json`**:
   - Update `name`, `description`, `authors`, and `homepage`.
   - Update the PSR-4 autoload namespaces:
     - `WebProject\PhpPackageTemplate\` &rarr; `YourVendor\YourPackage\`
     - `WebProject\PhpPackageTemplate\Tests\` &rarr; `YourVendor\YourPackage\Tests\`
2. **Codeception Configuration**:
   - Update `namespace` in `codeception.yml`.
   - Update `suite_namespace` in `tests/Unit.suite.yml`.
   - Update namespaces in `tests/Support/UnitTester.php` and `tests/Unit/ExampleTest.php`.
3. **`README.md`**:
   - Replace this template documentation with specific documentation for your package.

---

## 🛠️ Development & Quality Assurance

All development scripts are defined in `composer.json` with `XDEBUG_MODE=off` (except code coverage) to maximize execution speed.

### Available Composer Commands

| Command | Description |
| :--- | :--- |
| `composer qa` | Executes the complete QA suite (`test:build`, `cs:fix`, `test`, `stan`). |
| `composer test` | Runs the Codeception test suite (`codecept run --report`). |
| `composer test:build` | Generates / rebuilds Codeception Actor classes (`codecept build`). |
| `composer test:coverage` | Runs tests and generates an XML coverage report (`coverage.xml`). |
| `composer stan` | Runs PHPStan static analysis at Level 8 without progress bars. |
| `composer cs:check` | Checks coding standards with PHP-CS-Fixer in dry-run mode showing diffs. |
| `composer cs:fix` | Automatically fixes code styling violations with PHP-CS-Fixer. |

---

## 🤝 Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for contribution guidelines, pull request processes, and branch naming conventions.

---

## 📜 License

Distributed under the **MIT** License. See `LICENSE` for details.

---

## ✉️ Support & Contact

- **Issues:** [GitHub Issue Tracker](https://github.com/WebProject-xyz/php-package-template/issues)
- **Website:** [webproject.xyz](https://www.webproject.xyz)
