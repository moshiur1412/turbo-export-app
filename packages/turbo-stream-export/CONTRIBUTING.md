# Contributing

Thank you for considering contributing to TurboStream Export Engine!

## Development Setup

1. Fork the repository
2. Clone your fork:
   ```bash
   git clone https://github.com/moshiur1412/export-engine.git
   cd export-engine
   ```

3. Install dependencies:
   ```bash
   composer install
   ```

4. Run tests:
   ```bash
   composer test
   ```

## Coding Standards

This project uses:
- **PSR-12** coding standard
- **Pest** for testing
- **PHPStan** for static analysis (when available)

Run linting:
```bash
./vendor/bin/pint
```

## Testing

Write tests for all new features and bug fixes:

```bash
# Run all tests
composer test

# Run with coverage
composer test:coverage

# Run specific test file
./vendor/bin/pest tests/Unit/ExportServiceTest.php
```

## Pull Request Process

1. Create a feature branch from `main`:
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. Write your code and tests

3. Ensure tests pass:
   ```bash
   composer test
   ```

4. Commit your changes with clear messages:
   ```bash
   git commit -m "Add feature: your feature description"
   ```

5. Push to your fork:
   ```bash
   git push origin feature/your-feature-name
   ```

6. Open a Pull Request on GitHub

## Reporting Issues

Please report issues on [GitHub Issues](https://github.com/turbostream/export-engine/issues) with:
- Clear description of the problem
- Steps to reproduce
- Expected vs actual behavior
- Laravel and PHP version
- Relevant error logs

## Security

If you discover security vulnerabilities, please email support@turbostream.dev instead of using the public issue tracker.

## Code of Conduct

Please be respectful and constructive in all interactions. We follow the [Laravel Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).
