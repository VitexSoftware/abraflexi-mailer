# AbraFlexi Mailer - Copilot Instructions

This project is an **AbraFlexi document mailer** that sends invoices and other documents via email with templating support and MultiFlexi integration.

## 🚀 Project Context

- **Technology**: PHP 8.4+, AbraFlexi API integration
- **Purpose**: Automated document sending from AbraFlexi accounting system  
- **Architecture**: Command-line tools with MultiFlexi platform integration
- **Key Components**: Email templating, document processing, report generation

## 📋 Code Standards & Requirements

### Language & Framework
- **PHP Version**: PHP 8.4 or later (strict requirement)
- **Coding Standard**: PSR-12 (mandatory for all PHP code)
- **Language**: All code comments, error messages, and documentation in English
- **Type Safety**: Always use type hints for parameters and return types

### Documentation Standards
```php
/**
 * Send unsent documents from AbraFlexi.
 *
 * @param FakturaVydana $invoice The invoice document to send
 * @param array $recipients List of email recipients
 * @return bool Success status of the send operation
 * @throws MailerException When email sending fails
 */
public function sendDocument(FakturaVydana $invoice, array $recipients): bool
```

### Testing Requirements
- **Framework**: PHPUnit for all tests
- **Coverage**: Create/update PHPUnit tests for every new/modified class
- **Validation**: Run `php -l` after every PHP file edit (mandatory)

## 🔧 Development Workflow

### File Structure & Paths
```bash
# Always run scripts from src/ directory during development
cd src/
php SendUnsent.php

# Relative paths (../vendor/autoload.php, ../.env) are intentional
# They get resolved during Debian packaging via sed commands
```

### Code Quality Checklist
1. ✅ Use meaningful variable names that describe purpose
2. ✅ Define constants instead of magic numbers/strings  
3. ✅ Handle exceptions with meaningful error messages
4. ✅ Ensure security - no sensitive information exposure
5. ✅ Optimize for performance where applicable
6. ✅ Maintain compatibility with latest PHP/libraries
7. ✅ Follow maintainable coding practices

## 🌐 Internationalization
- **Library**: i18n for internationalization
- **Usage**: Always use `_()` functions for translatable strings
```php
$this->addStatusMessage(_('Document sent successfully'), 'success');
$this->addStatusMessage(_('Failed to send document'), 'error');
```

## 📄 Schema Compliance

### MultiFlexi App Configuration
- **Location**: `multiflexi/*.app.json`
- **Schema**: https://raw.githubusercontent.com/VitexSoftware/php-vitexsoftware-multiflexi-core/refs/heads/main/multiflexi.app.schema.json
- **Validation**: Run `multiflexi-cli application validate-json --json multiflexi/[filename].app.json`

### Report Generation
- **Schema**: https://raw.githubusercontent.com/VitexSoftware/php-vitexsoftware-multiflexi-core/refs/heads/main/multiflexi.report.schema.json
- **Required Fields**: `status`, `timestamp`
- **Optional Fields**: `message`, `artifacts`, `metrics`

**Example Report Structure**:
```php
$report = [
    'status' => 'success|warning|error',
    'timestamp' => date('c'), // ISO8601 format
    'message' => 'Human readable message',
    'artifacts' => [
        'unsent_invoices' => $data
    ],
    'metrics' => [
        'total_processed' => $count,
        'success_rate' => $percentage
    ]
];
```

## 🏗️ Key Classes & Components

### Main Classes
- `SendUnsent.php` - Bulk sending of unsent documents
- `SendUnsentWithAttachments.php` - Sending with file attachments
- `ShowUnsent.php` - Generate reports of unsent documents
- `BulkMail.php` - Mass mailing to addressbook contacts
- `AbraFlexi\Mailer\Templater` - Email template processing
- `AbraFlexi\Mailer\DocumentMailer` - Document email composer

### External Dependencies  
- **AbraFlexi API**: Communication with accounting system
- **EasePHP Framework**: Logging, HTML generation, utilities
- **MultiFlexi Platform**: Application execution environment

## ⚡ Development Commands

```bash
# Code validation (mandatory after edits)
php -l filename.php

# Run tests
vendor/bin/phpunit tests/

# Validate MultiFlexi configs
multiflexi-cli application validate-json --json multiflexi/app.json

# Build packages
make clean && make deb
```

## 🎯 When Working on This Project

1. **New Features**: Always add corresponding tests
2. **Bug Fixes**: Ensure fix doesn't break existing functionality  
3. **Schema Changes**: Validate against MultiFlexi schemas
4. **Documentation**: Update README.md with new capabilities
5. **Deployment**: Test relative path resolution works correctly
