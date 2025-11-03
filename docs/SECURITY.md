# Security

Notion Sync for WordPress is built with security as a top priority. This document outlines our security practices, features, and guidelines.

---

## Table of Contents

- [Security Audit Status](#security-audit-status)
- [Security Features](#security-features)
- [Best Practices for Users](#best-practices-for-users)
- [Reporting Security Issues](#reporting-security-issues)
- [Security Architecture](#security-architecture)
- [Compliance](#compliance)

---

## Security Audit Status

✅ **Latest Audit:** November 2, 2025 - **PASSED**

The plugin has undergone comprehensive security auditing in preparation for WordPress.org submission:

- ✅ **12/12 AJAX handlers** properly implement nonce verification
- ✅ **12/12 AJAX handlers** properly check user capabilities
- ✅ **3/3 REST API controllers** implement permission callbacks
- ✅ **100%** of database queries use prepared statements
- ✅ **100%** of admin output properly escaped
- ✅ **Sensitive data** (API tokens) encrypted at rest

**View Full Audit:** [SECURITY-AUDIT-2025-11-02.md](security/SECURITY-AUDIT-2025-11-02.md)

---

## Security Features

### 1. Encrypted Credential Storage

Notion API tokens are encrypted at rest using AES-256-CBC encryption:

```php
// Tokens are encrypted before storage
$encrypted_token = Encryption::encrypt( $token );
update_option( 'notion_wp_token', $encrypted_token );

// And decrypted only when needed
$token = Encryption::decrypt( $encrypted_token );
```

**Key Features:**
- AES-256-CBC encryption algorithm
- Unique initialization vector (IV) per encryption
- Keys derived from WordPress `SECURE_AUTH_KEY` salt
- Tokens never logged or displayed in plain text

### 2. HTTPS Enforcement

The plugin enforces HTTPS for all API token configuration:

- Admin settings page requires SSL connection
- Override available for development: `define( 'NOTION_WP_ALLOW_INSECURE', true );`
- Prevents tokens from being transmitted over unencrypted connections

### 3. Nonce Verification

All AJAX endpoints verify WordPress nonces before processing:

```php
check_ajax_referer( 'notion_sync_ajax', 'nonce' );
```

This prevents Cross-Site Request Forgery (CSRF) attacks.

### 4. Capability Checks

All administrative operations require `manage_options` capability:

```php
if ( ! current_user_can( 'manage_options' ) ) {
    wp_send_json_error(
        array( 'message' => 'Insufficient permissions' ),
        403
    );
}
```

Only WordPress administrators can:
- Configure Notion API tokens
- Trigger sync operations
- Manage database imports

### 5. Input Validation & Sanitization

All user inputs are validated and sanitized:

```php
// String sanitization
$page_id = sanitize_text_field( wp_unslash( $_POST['page_id'] ) );

// Array sanitization
$page_ids = array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['page_ids'] ) );

// Format validation
if ( ! preg_match( '/^[a-zA-Z0-9\-]+$/', $page_id ) ) {
    return new WP_Error( 'invalid_format', 'Invalid page ID format' );
}
```

### 6. SQL Injection Prevention

All database queries use prepared statements:

```php
$wpdb->get_col(
    $wpdb->prepare(
        "SELECT DISTINCT post_id
        FROM {$wpdb->postmeta}
        WHERE meta_key = %s",
        'notion_page_id'
    )
);
```

### 7. Output Escaping

All output is properly escaped to prevent XSS attacks:

- `esc_html__()` for translatable text
- `esc_url()` for URLs and links
- `esc_attr()` for HTML attributes
- `wp_kses()` for HTML with allowed tags

### 8. Rate Limit Handling

The Notion API client implements exponential backoff for rate limit handling, preventing abuse of external API services.

---

## Best Practices for Users

### For Site Administrators

1. **Use Strong WordPress Admin Credentials**
   - The plugin requires administrator access
   - Enable two-factor authentication for admin accounts
   - Use unique, complex passwords

2. **Protect Your Notion Integration Token**
   - Never share your integration token
   - Rotate tokens if you suspect compromise
   - Use Notion's integration settings to limit permissions

3. **Keep WordPress Updated**
   - Run the latest WordPress version
   - Keep all plugins and themes up to date
   - Enable automatic security updates

4. **Use HTTPS**
   - Always use SSL/TLS certificates
   - The plugin enforces HTTPS for token configuration
   - Consider HTTP Strict Transport Security (HSTS)

5. **Review Synced Content**
   - Check content before publishing
   - Review media attachments
   - Verify internal links resolve correctly

6. **Limit Access**
   - Only give `manage_options` capability to trusted users
   - Use WordPress roles to limit who can sync content
   - Regularly audit user accounts with admin access

### For Developers

1. **Environment Security**
   - Use different Notion integration tokens for dev/staging/production
   - Never commit tokens to version control
   - Use environment variables for sensitive configuration

2. **Code Review**
   - Review all modifications to security-sensitive code
   - Test nonce verification and capability checks
   - Validate input sanitization

3. **Testing**
   - Run security-focused tests before deployment
   - Test with non-admin user accounts
   - Verify AJAX endpoints reject unauthorized requests

---

## Reporting Security Issues

We take security seriously. If you discover a security vulnerability:

### Do NOT

- Open a public GitHub issue
- Disclose the vulnerability publicly before a fix is available
- Exploit the vulnerability beyond confirming it exists

### DO

1. **Email us privately:** hello 📧 thevgergroup.com
2. **Include:**
   - Description of the vulnerability
   - Steps to reproduce
   - Potential impact
   - Suggested fix (if you have one)
3. **Give us time to respond and fix the issue**
4. **Coordinate disclosure timeline with us**

### Response Timeline

- **24 hours:** Initial response acknowledging receipt
- **7 days:** Assessment and severity classification
- **30 days:** Fix developed, tested, and released
- **Public disclosure:** Coordinated after fix is released

### Recognition

We maintain a security hall of fame for responsible disclosure. Contributors who report valid security issues will be credited (with permission) in:
- Security advisories
- Release notes
- This documentation

---

## Security Architecture

### Data Flow Security

```
User Browser → HTTPS → WordPress (Nonce + Capability Check)
                              ↓
                       AJAX/REST Handler
                              ↓
                       Input Validation
                              ↓
                       Business Logic
                              ↓
                       Output Escaping → User Browser

WordPress → Encrypted Token → Notion API → Synced Content
```

### Authentication & Authorization

1. **WordPress Admin Authentication**
   - Standard WordPress login required
   - Multi-factor authentication supported (via plugins)

2. **Notion API Authentication**
   - Internal Integration Token (Bearer auth)
   - Encrypted storage in WordPress database
   - Transmitted over HTTPS only

3. **Permission Model**
   - Admin operations: `manage_options` capability
   - Content viewing: Standard WordPress post permissions
   - API endpoints: Permission callbacks on all routes

### Data Storage Security

| Data Type | Storage Method | Protection |
|-----------|---------------|------------|
| Notion API Token | WordPress options table | AES-256-CBC encrypted |
| Synced Content | WordPress posts/postmeta | Standard WordPress ACLs |
| Media Files | WordPress uploads directory | Standard file permissions |
| Sync Logs | Custom database table | Administrator access only |
| Database Records | Custom table with wpdb | Prepared statements only |

---

## Compliance

### WordPress.org Security Requirements

✅ All WordPress.org security requirements met:

- Nonce verification for state-changing operations
- Capability checks for privileged operations
- Input validation and sanitization
- Output escaping for all user-facing content
- SQL injection prevention via prepared statements
- No hardcoded credentials
- HTTPS enforcement for sensitive operations
- Secure credential storage
- No unauthorized external connections
- No use of dangerous PHP functions

### OWASP Top 10 (2021)

Protection against common vulnerabilities:

| Vulnerability | Protection |
|--------------|------------|
| A01: Broken Access Control | Capability checks on all endpoints |
| A02: Cryptographic Failures | AES-256 encryption, HTTPS enforcement |
| A03: Injection | Prepared statements, input validation |
| A04: Insecure Design | Security-first architecture |
| A05: Security Misconfiguration | Secure defaults, HTTPS required |
| A06: Vulnerable Components | Regular updates, dependency scanning |
| A07: Authentication Failures | WordPress core authentication |
| A08: Software & Data Integrity | Code signing, checksum verification |
| A09: Security Logging Failures | Comprehensive error logging |
| A10: Server-Side Request Forgery | Validated API URLs only |

---

## Security Testing

### Automated Testing

Our CI/CD pipeline includes:

- **PHPCS WordPress Security Ruleset** - Static analysis for security issues
- **PHPStan** - Type safety and potential bug detection
- **261+ PHPUnit tests** - Including security-focused test cases
- **ESLint** - JavaScript security patterns

### Manual Security Review

Regular security reviews include:

- AJAX handler audits (nonce verification, capability checks)
- REST API permission callback verification
- Database query prepared statement verification
- Output escaping verification in templates
- Credential storage and transmission review

---

## Security Updates

### Update Policy

- **Critical vulnerabilities:** Patched within 24-48 hours
- **High severity:** Patched within 7 days
- **Medium severity:** Patched in next minor release
- **Low severity:** Patched in next major release

### Notification Channels

Security updates are announced via:

1. WordPress.org plugin update system
2. GitHub Security Advisories
3. Plugin changelog
4. Email notifications (if configured)

---

## Additional Resources

- [WordPress Plugin Security Best Practices](https://developer.wordpress.org/plugins/security/)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [WordPress Security White Paper](https://wordpress.org/about/security/)
- [Notion API Security](https://developers.notion.com/docs/authorization)

---

## Questions?

For security-related questions:

- **General questions:** Open a [GitHub Discussion](https://github.com/thevgergroup/notion-wp/discussions)
- **Security concerns:** Email hello 📧 thevgergroup.com
- **Bug reports:** [GitHub Issues](https://github.com/thevgergroup/notion-wp/issues)

---

**Last Updated:** November 3, 2025
**Next Security Audit:** Before v2.0.0 release or after major feature additions
