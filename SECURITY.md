# Security Considerations

## Overview
This is a **mock server** intended for development and testing purposes. It should **NOT** be used in production environments without significant security hardening.

## Implemented Security Features

### 1. Input Validation
- **Header Validation**: All endpoints validate required headers (Content-Type, Authorization, etc.)
- **Content-Type Enforcement**: Strict Content-Type checking prevents unexpected data formats
- **Content-Length Validation**: File uploads are validated against maximum size limits
- **Base64 Decoding**: Validates base64 content before processing
- **Filename Sanitization**: Uses `basename()` to prevent directory traversal attacks
- **JWT Validation**: Checks token expiration and structure

### 2. Authentication
Multiple authentication methods are supported:
- Basic Auth with credential validation
- API Key authentication
- JWT with expiration checking
- OAuth 2.0 client credentials flow
- mTLS support
- OpenID Connect

**Note**: By default, authentication is disabled. Enable in `config.php` for testing auth flows.

### 3. CORS Configuration
- Configurable CORS origins
- Proper handling of wildcard origins
- Prevents credentials exposure with wildcard origins

### 4. Error Handling
- Validates `opendir()` returns before use
- Checks credential format before parsing
- Validates array counts before list assignment
- Returns appropriate HTTP status codes for errors

### 5. File Upload Protection
- Maximum file size enforcement (50MB default, configurable)
- File type restrictions (configurable)
- TUS protocol validation
- Separate storage directory for uploads

## Known Limitations & Security Warnings

### ⚠️ For Development/Testing Only
This mock server is designed for:
- Local development environments
- Testing and prototyping
- API mocking and simulation
- Learning and experimentation

### ⚠️ Do NOT Use in Production
This server lacks:
- Rate limiting
- Request throttling
- DDoS protection
- SQL injection protection (not applicable - no database)
- XSS protection for rendered content
- CSRF protection
- Secure session management
- Input sanitization for all edge cases
- Logging and monitoring
- Security headers (CSP, HSTS, etc.)

### ⚠️ Authentication Weaknesses
- **Hardcoded Credentials**: Users and API keys are stored in config file
- **Weak JWT Secret**: Default secret key should be changed
- **No Password Hashing**: Passwords stored in plain text in config
- **No Account Lockout**: No protection against brute force attacks
- **No Token Revocation**: JWT tokens cannot be invalidated before expiration
- **No Certificate Validation**: mTLS only checks for certificate presence

### ⚠️ File System Security
- **No Quota Management**: Users can fill disk space
- **No Malware Scanning**: Uploaded files are not scanned
- **No Virus Detection**: Files accepted without validation
- **Direct File System Access**: Data stored directly on disk
- **No Encryption**: Uploaded files and data are stored unencrypted

### ⚠️ Data Privacy
- **No Data Encryption**: All data stored in plain text
- **No Access Control**: All resources accessible to authenticated users
- **No Audit Logging**: No record of who accessed what
- **No Data Retention Policies**: Data persists indefinitely

### ⚠️ Network Security
- **HTTP Only**: No HTTPS/TLS by default (use reverse proxy for HTTPS)
- **No IP Whitelisting**: All IPs can access the server
- **No Firewall Rules**: Network-level security must be implemented separately

## Security Best Practices for Using This Mock Server

### 1. Network Isolation
```bash
# Only bind to localhost
php -S localhost:8080 router.php

# Use in isolated network segments
# Configure firewall rules to restrict access
```

### 2. Change Default Credentials
Edit `config.php`:
```php
'basic' => [
    'users' => [
        'your-username' => 'strong-password-here',
    ],
],
'jwt' => [
    'secret' => 'change-this-to-a-strong-random-secret',
],
```

### 3. Enable Authentication
```php
'auth' => [
    'enabled' => true,
    'default_method' => 'jwt', // or 'basic', 'api_key', etc.
]
```

### 4. Restrict File Uploads
```php
'server' => [
    'max_upload_size' => 5 * 1024 * 1024, // 5MB
    'allowed_file_types' => ['txt', 'pdf', 'jpg', 'png'],
]
```

### 5. Use HTTPS in Production-like Environments
Use a reverse proxy (nginx, Apache) to add TLS:
```nginx
server {
    listen 443 ssl;
    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;
    
    location / {
        proxy_pass http://localhost:8080;
    }
}
```

### 6. Regular Cleanup
```bash
# Periodically clean up storage directories
rm -rf storage/uploads/*
rm -rf storage/data/*
```

### 7. Monitor Disk Usage
```bash
# Check disk usage regularly
du -sh storage/
```

## Vulnerability Disclosure

If you discover a security vulnerability in this mock server, please:
1. **Do not** create a public issue
2. Contact the repository maintainer directly
3. Provide detailed information about the vulnerability
4. Allow time for a fix before public disclosure

## Security Updates

This mock server receives:
- ✅ Bug fixes for critical security issues
- ✅ Updates for reported vulnerabilities
- ❌ No regular security audits
- ❌ No CVE tracking
- ❌ No security patches for old versions

## Compliance

This mock server:
- ❌ Is NOT GDPR compliant
- ❌ Is NOT HIPAA compliant
- ❌ Is NOT PCI DSS compliant
- ❌ Is NOT SOC 2 compliant

**Do not use this server to process real sensitive data, personal information, or protected health information.**

## Recommendations for Production Use

If you need a production-ready API server, consider:
- Laravel (PHP) - Full-featured PHP framework
- Express.js (Node.js) - Mature Node.js framework
- FastAPI (Python) - Modern Python framework
- Spring Boot (Java) - Enterprise Java framework
- Django (Python) - Batteries-included Python framework

All of these provide:
- Built-in security features
- Authentication/authorization systems
- Input validation and sanitization
- CSRF protection
- Security headers
- Regular security updates

## License

This software is provided "AS IS" without warranty of any kind. Use at your own risk.

## Summary

✅ **Safe for**: Local development, testing, learning, prototyping
❌ **NOT safe for**: Production, sensitive data, public internet, enterprise use

**Remember**: This is a MOCK server. The "M" stands for "Mock", not "Mission-critical"! 😊
