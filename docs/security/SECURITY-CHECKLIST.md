# Security Review Checklist for Notion-WordPress Sync

**Use this checklist for all future code reviews and pull requests.**

---

## Pre-Commit Security Checklist

### Input Validation & Sanitization

- [ ] All `$_POST` data sanitized with `sanitize_text_field()`, `sanitize_textarea_field()`, etc.
- [ ] All `$_GET` data sanitized appropriately
- [ ] All `$_REQUEST` data sanitized (or avoid using `$_REQUEST`)
- [ ] File uploads validated for type, size, and content
- [ ] URLs validated with `esc_url_raw()` or `sanitize_url()`
- [ ] Email addresses validated with `sanitize_email()` and `is_email()`
- [ ] Integer values validated with `absint()` or `intval()`
- [ ] Array keys sanitized with `sanitize_key()`
- [ ] External API responses validated and sanitized
- [ ] Data type validation performed before processing

### Output Escaping

- [ ] All HTML output escaped with `esc_html()`
- [ ] All HTML attributes escaped with `esc_attr()`
- [ ] All URLs escaped with `esc_url()`
- [ ] All JavaScript strings escaped with `esc_js()`
- [ ] Limited HTML escaped with `wp_kses()` or `wp_kses_post()`
- [ ] Translation strings properly escaped: `esc_html__()`, `esc_attr__()`, etc.
- [ ] No raw `echo` of variables without escaping
- [ ] Admin notices properly escaped
- [ ] JSON output uses `wp_json_encode()`
- [ ] SQL output (if any) uses `esc_sql()` with `$wpdb->prepare()`

### Authentication & Authorization

- [ ] All admin pages check `current_user_can('manage_options')` or appropriate capability
- [ ] AJAX handlers check user capabilities
- [ ] REST API endpoints check permissions
- [ ] No capability bypass vulnerabilities
- [ ] User roles and capabilities correctly implemented
- [ ] Multisite compatibility checked (if applicable)
- [ ] Super admin checks where appropriate
- [ ] Session handling secure (if custom sessions used)

### CSRF Protection

- [ ] All forms include `wp_nonce_field()`
- [ ] All form handlers verify nonces with `wp_verify_nonce()` or `check_admin_referer()`
- [ ] AJAX requests include nonces
- [ ] AJAX handlers verify nonces with `check_ajax_referer()`
- [ ] REST API endpoints use nonce or proper authentication
- [ ] Unique nonce action names for different forms
- [ ] No state-changing GET requests
- [ ] Referrer checks where appropriate

### SQL Injection Prevention

- [ ] All custom queries use `$wpdb->prepare()`
- [ ] No string concatenation in SQL queries
- [ ] Prepared statements used for all user input in SQL
- [ ] WordPress Query APIs preferred over custom SQL (`WP_Query`, `get_posts()`, etc.)
- [ ] Database table names properly escaped with `$wpdb->prefix`
- [ ] Column names validated against whitelist (if dynamic)
- [ ] ORDER BY and LIMIT clauses validated (no direct user input)

### XSS Prevention

- [ ] All dynamic content escaped before rendering
- [ ] External API data treated as untrusted
- [ ] No `innerHTML` or jQuery `.html()` with unsanitized data
- [ ] User-configurable HTML/JavaScript properly sanitized
- [ ] Rich text editors use `wp_kses_post()` for output
- [ ] SVG uploads disabled or strictly validated
- [ ] Content Security Policy headers considered
- [ ] No `eval()` or `Function()` constructor in JavaScript

### Sensitive Data Protection

- [ ] API tokens/credentials encrypted before storage
- [ ] Passwords hashed (never stored plaintext)
- [ ] Encryption uses strong algorithms (AES-256)
- [ ] Encryption keys stored securely (not in code)
- [ ] Sensitive data not logged
- [ ] Sensitive data not in URLs or GET parameters
- [ ] Sensitive data cleared from memory after use
- [ ] No hardcoded credentials in source code
- [ ] `.env` files not committed to git
- [ ] Database backups encrypted (documented for users)

### HTTPS & Transport Security

- [ ] HTTPS enforced for admin areas handling sensitive data
- [ ] `FORCE_SSL_ADMIN` documented for users
- [ ] External API calls use HTTPS
- [ ] Mixed content warnings avoided
- [ ] SSL certificate validation not disabled
- [ ] TLS 1.2+ required for external connections
- [ ] Security headers set (`X-Content-Type-Options`, `X-Frame-Options`, etc.)

### Rate Limiting & Brute Force Protection

- [ ] Authentication attempts rate limited
- [ ] API calls rate limited
- [ ] Account lockout after failed attempts
- [ ] Progressive delays on repeated failures
- [ ] IP-based rate limiting (with care for shared IPs)
- [ ] User-based rate limiting
- [ ] Transients or database used for rate limit tracking
- [ ] Rate limits documented

### Error Handling & Logging

- [ ] Error messages don't expose sensitive information
- [ ] Generic errors shown to users
- [ ] Detailed errors logged for admins (if `WP_DEBUG_LOG` enabled)
- [ ] Stack traces not exposed to users
- [ ] Database errors not exposed
- [ ] File paths not exposed in errors
- [ ] Security events logged
- [ ] Failed authentication attempts logged
- [ ] Successful high-privilege actions logged

### File Operations

- [ ] File uploads validated for type (MIME type check, not extension)
- [ ] File size limits enforced
- [ ] Uploaded files stored outside web root (or protected)
- [ ] File execution prevented (`.htaccess` rules)
- [ ] File paths validated (no `../` traversal)
- [ ] Temporary files cleaned up
- [ ] File permissions set correctly (644 for files, 755 for directories)
- [ ] No executable uploads allowed
- [ ] Zip file extraction validated (zip bomb protection)

### API Security

- [ ] API authentication required
- [ ] API rate limiting implemented
- [ ] API responses sanitized
- [ ] API errors don't expose sensitive data
- [ ] CORS headers properly configured
- [ ] API versioning in place
- [ ] API input validated
- [ ] API output escaped
- [ ] API tokens rotatable
- [ ] API documentation includes security notes

### JavaScript Security

- [ ] No `eval()` or `Function()` constructor
- [ ] No inline event handlers (`onclick`, etc.)
- [ ] `wp_localize_script()` used for passing data to JS
- [ ] Nonces included in AJAX requests
- [ ] User input validated in JavaScript (client-side) AND server-side
- [ ] Third-party libraries up to date
- [ ] npm dependencies audited (`npm audit`)
- [ ] No sensitive data in JavaScript
- [ ] localStorage/sessionStorage used securely

### WordPress Specific

- [ ] Proper use of WordPress APIs (no reinventing the wheel)
- [ ] Hooks and filters properly secured
- [ ] Actions check user capabilities
- [ ] Filters validate/sanitize data
- [ ] Transients have appropriate expiration
- [ ] Options table not polluted (proper cleanup on uninstall)
- [ ] Database migrations safe (no data loss)
- [ ] Multisite compatible (if applicable)
- [ ] WordPress VIP coding standards followed
- [ ] Escaping late (at output, not storage)

---

## Code Review Questions

### For Every New File

1. What user input does this file accept?
2. How is that input validated and sanitized?
3. What data does this file output to the user?
4. How is that output escaped?
5. What capabilities are required to access this code?
6. What state-changing operations occur?
7. Are those operations protected by nonces?
8. What sensitive data is handled?
9. How is that sensitive data protected?
10. What could an attacker do with this code?

### For Forms

1. Does the form have a nonce field?
2. Is the nonce verified in the handler?
3. Are capabilities checked before processing?
4. Is all form data sanitized?
5. Is the form action using `admin-post.php` or similar?
6. Are error messages generic (not exposing internals)?
7. Is HTTPS enforced (if handling sensitive data)?
8. Is there rate limiting on submissions?

### For AJAX Handlers

1. Is the AJAX handler registered properly?
2. Are capabilities checked?
3. Is the nonce verified with `check_ajax_referer()`?
4. Is all input sanitized?
5. Is all output escaped?
6. Is JSON properly encoded with `wp_json_encode()`?
7. Is there rate limiting?
8. Are errors handled securely?

### For Database Queries

1. Is `$wpdb->prepare()` used?
2. Are there any string concatenations in SQL?
3. Could WordPress Query APIs be used instead?
4. Are table names properly prefixed?
5. Are column names validated?
6. Is ORDER BY using user input? (validate against whitelist)
7. Is LIMIT using user input? (validate as integer)

### For API Integrations

1. Is the API called over HTTPS?
2. Are credentials stored encrypted?
3. Is rate limiting in place?
4. Are API responses validated and sanitized?
5. Are API errors handled securely?
6. Is there timeout protection?
7. Are API tokens rotatable?
8. Is there logging for API calls?

---

## Security Testing Checklist

### Manual Testing

- [ ] Test with invalid/malicious input in all forms
- [ ] Test without nonces (should fail)
- [ ] Test without proper capabilities (should fail)
- [ ] Test XSS payloads in text fields: `<script>alert('XSS')</script>`
- [ ] Test SQL injection payloads: `' OR '1'='1`
- [ ] Test path traversal: `../../wp-config.php`
- [ ] Test CSRF by removing nonces
- [ ] Test as different user roles
- [ ] Test on HTTP (should redirect/warn if sensitive)
- [ ] Test rate limiting (attempt multiple times rapidly)

### Automated Testing

- [ ] Run PHPCS with WordPress coding standards
- [ ] Run PHPStan at level 5+
- [ ] Run ESLint on JavaScript
- [ ] Run `npm audit` for JavaScript dependencies
- [ ] Run `composer audit` for PHP dependencies (if available)
- [ ] Run security scanner (e.g., WPScan, Sucuri)
- [ ] Check for hardcoded credentials: `git grep -i "password\|secret\|api_key"`

### Browser Testing

- [ ] Check browser console for errors
- [ ] Check Network tab for failed requests
- [ ] Check for mixed content warnings
- [ ] Verify HTTPS lock icon shows
- [ ] Test with browser XSS auditor enabled
- [ ] Test with strict CSP headers (if implemented)

---

## Common Vulnerabilities Reference

### WordPress Plugin/Theme Specific

1. **Unauthenticated AJAX Calls**
   - Handler: `wp_ajax_nopriv_{action}`
   - Fix: Always check capabilities in AJAX handlers

2. **Privilege Escalation**
   - Checking `is_admin()` instead of capabilities
   - Fix: Use `current_user_can('capability')`

3. **Arbitrary File Upload**
   - Not validating file MIME types
   - Fix: Use `wp_check_filetype()` and validate MIME type

4. **SQL Injection in ORDER BY**
   - `ORDER BY {$_GET['orderby']}`
   - Fix: Whitelist allowed column names

5. **XSS in Admin Area**
   - Assuming admin area is safe
   - Fix: Escape all output, even in admin

6. **CSRF in Settings**
   - Not using nonces
   - Fix: Add `wp_nonce_field()` and verify

7. **Path Traversal in File Inclusion**
   - `include($_GET['file']);`
   - Fix: Validate against whitelist, use absolute paths

8. **Open Redirect**
   - `wp_redirect($_GET['redirect']);`
   - Fix: Validate URL, use `wp_safe_redirect()`

9. **Plaintext Password Storage**
   - `update_option('password', $password);`
   - Fix: Use `wp_hash_password()`, encrypt if API token

10. **Information Disclosure via Comments**
    - Exposing sensitive info in HTML comments
    - Fix: Remove debugging comments from production

---

## Security Resources

### WordPress Security

- WordPress Plugin Security Handbook: https://developer.wordpress.org/plugins/security/
- WordPress Coding Standards: https://developer.wordpress.org/coding-standards/
- WordPress VIP Code Review: https://docs.wpvip.com/technical-references/code-review/
- WordPress Nonce Documentation: https://developer.wordpress.org/apis/security/nonces/

### General Security

- OWASP Top 10: https://owasp.org/www-project-top-ten/
- OWASP Cheat Sheet Series: https://cheatsheetseries.owasp.org/
- CWE Top 25: https://cwe.mitre.org/top25/
- CVSS Calculator: https://www.first.org/cvss/calculator/

### Tools

- WPScan: https://wpscan.com/
- Sucuri SiteCheck: https://sitecheck.sucuri.net/
- PHPCS: https://github.com/squizlabs/PHP_CodeSniffer
- PHPStan: https://phpstan.org/
- ESLint: https://eslint.org/

---

## Security Incident Response

### If a Vulnerability is Discovered

1. **DO NOT** publicly disclose until patch is ready
2. Document the vulnerability privately
3. Assess severity (CVSS score)
4. Develop and test a patch
5. Prepare security advisory
6. Release patch
7. Notify users
8. Publicly disclose after users have time to update

### Responsible Disclosure

If someone reports a security issue:

1. Acknowledge receipt within 24 hours
2. Assess severity and validity
3. Provide timeline for fix
4. Keep reporter updated
5. Credit reporter in security advisory (if they agree)
6. Send patch for verification before release

---

**Last Updated:** 2025-10-19
**Version:** 1.0
**Applies To:** All phases of Notion-WordPress Sync development
