# MAMP Server Setup Guide

This guide explains how to run the PHP Mock Server on MAMP (or any Apache server) without using PHP's built-in server.

## Prerequisites

- MAMP or MAMP PRO installed (or any Apache + PHP setup)
- PHP 7.4 or higher
- Apache with mod_rewrite enabled

## Installation Steps

### 1. Clone or Copy the Repository

Clone or copy the `mock-server` directory to your MAMP's `htdocs` folder:

```bash
# For MAMP (default location)
cd /Applications/MAMP/htdocs

# For MAMP PRO or custom location
cd /path/to/your/htdocs

# Clone the repository
git clone https://github.com/srkrambo/mock-server.git
```

The directory structure should be:
```
htdocs/
└── mock-server/
    ├── config.php
    ├── index.php
    ├── .htaccess
    ├── src/
    ├── storage/
    └── ...
```

### 2. Configure MAMP Server

1. **Open MAMP/MAMP PRO**
2. **Set the Apache port** (default: 8888, but you can use 8788 or any port)
3. **Start the servers** (Apache & MySQL if needed)

### 3. Verify .htaccess Configuration

The `.htaccess` file should already be configured correctly:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

This configuration ensures that all requests are routed through `index.php` except for existing files and directories.

### 4. Set Directory Permissions

Ensure the `storage` directories are writable:

```bash
cd /Applications/MAMP/htdocs/mock-server
chmod -R 755 storage/
```

### 5. Access the Server

If MAMP is running on port 8788, access your mock server at:

```
http://localhost:8788/mock-server/
```

## Configuration

### Base Path Auto-Detection

The application automatically detects that it's running in a subdirectory. No manual configuration is needed!

However, if you need to manually set the base path, edit `config.php`:

```php
'server' => [
    'base_path' => '/mock-server', // Set explicitly if auto-detection doesn't work
    // ...
],
```

Or set an environment variable:

```bash
export MOCK_SERVER_BASE_PATH="/mock-server"
```

### Base URL

Update the base URL in `config.php` to match your MAMP configuration:

```php
'server' => [
    'base_url' => 'http://localhost:8788/mock-server',
    // ...
],
```

## Testing the Setup

### 1. Test Basic Connectivity

Open your browser and navigate to:
```
http://localhost:8788/mock-server/
```

Or use curl:
```bash
curl http://localhost:8788/mock-server/
```

### 2. Create a Resource

```bash
curl -X POST http://localhost:8788/mock-server/users/1 \
  -H "Content-Type: application/json" \
  -d '{"name": "John Doe", "email": "john@example.com"}'
```

### 3. Retrieve the Resource

```bash
curl http://localhost:8788/mock-server/users/1
```

### 4. Test File Upload

```bash
curl -X POST http://localhost:8788/mock-server/upload \
  -F "file=@/path/to/your/file.pdf"
```

## Common Issues and Solutions

### Issue 1: 404 Not Found

**Symptoms**: All requests return 404
**Solution**: 
- Ensure `.htaccess` file exists in the mock-server directory
- Verify mod_rewrite is enabled in Apache
- Check MAMP's Apache configuration

### Issue 2: mod_rewrite Not Enabled

**For MAMP**:
1. Open MAMP PRO (or edit httpd.conf manually)
2. Go to PHP & Web Server settings
3. Ensure "LoadModule rewrite_module" is enabled

**Manual Configuration**:
Edit `/Applications/MAMP/conf/apache/httpd.conf` and uncomment:
```apache
LoadModule rewrite_module modules/mod_rewrite.so
```

Also ensure AllowOverride is set to All:
```apache
<Directory "/Applications/MAMP/htdocs">
    Options Indexes FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

### Issue 3: Permission Denied on Storage

**Symptoms**: Cannot upload files or save data
**Solution**:
```bash
chmod -R 755 storage/
chown -R _www:_www storage/  # For macOS
```

### Issue 4: Wrong Base Path

**Symptoms**: Routes don't work correctly, 404 errors
**Solution**: Manually set the base path in `config.php`:
```php
'server' => [
    'base_path' => '/mock-server',
],
```

## Differences from PHP Built-in Server

| Feature | PHP Built-in Server | MAMP/Apache |
|---------|---------------------|-------------|
| Command | `php -S localhost:8080 router.php` | Start MAMP |
| URL | `http://localhost:8080/` | `http://localhost:8788/mock-server/` |
| router.php | Required | Not needed (uses .htaccess) |
| Configuration | None needed | May need Apache config |
| Performance | Development only | Production-ready |
| Multiple Sites | One at a time | Multiple sites/directories |

## Apache Configuration (Alternative to .htaccess)

If you prefer to configure Apache directly instead of using `.htaccess`, add this to your Apache vhost configuration:

```apache
<VirtualHost *:8788>
    ServerName localhost
    DocumentRoot "/Applications/MAMP/htdocs"
    
    <Directory "/Applications/MAMP/htdocs/mock-server">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
        
        # Rewrite rules (if not using .htaccess)
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^ index.php [QSA,L]
    </Directory>
</VirtualHost>
```

## Virtual Host Setup (Optional)

For a cleaner URL without the subdirectory, set up a virtual host:

### 1. Edit hosts file

```bash
sudo nano /etc/hosts
```

Add:
```
127.0.0.1 mock-server.local
```

### 2. Create Virtual Host in MAMP PRO

Or manually edit `/Applications/MAMP/conf/apache/extra/httpd-vhosts.conf`:

```apache
<VirtualHost *:8788>
    ServerName mock-server.local
    DocumentRoot "/Applications/MAMP/htdocs/mock-server"
    
    <Directory "/Applications/MAMP/htdocs/mock-server">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### 3. Restart Apache

Now access at: `http://mock-server.local:8788/`

## Production Deployment

For production deployment on Apache:

1. Use a proper virtual host configuration
2. Enable production mode in `config.php`:
   ```php
   'environment' => [
       'mode' => 'production',
   ],
   ```
3. Configure SSL/HTTPS
4. Set up proper file permissions
5. Enable security headers
6. Configure rate limiting

See [PRODUCTION.md](PRODUCTION.md) for more details.

## Troubleshooting Tips

### Enable Apache Error Logging

Check MAMP's error logs:
```
/Applications/MAMP/logs/apache_error.log
```

### Enable PHP Error Display

In development, ensure `config.php` has error reporting enabled (it already does by default in `index.php`).

### Test Apache Configuration

```bash
/Applications/MAMP/Library/bin/apachectl configtest
```

### Verify PHP Version

```bash
/Applications/MAMP/bin/php/php7.4.33/bin/php -v
```

## Switching Between Servers

You can use both PHP built-in server and MAMP without any code changes:

**PHP Built-in Server** (for quick testing):
```bash
php -S localhost:8080 router.php
```
Access at: `http://localhost:8080/`

**MAMP** (for development with other projects):
- Start MAMP
- Access at: `http://localhost:8788/mock-server/`

The application automatically detects the environment and adjusts accordingly!

## Next Steps

- Read the full [README.md](README.md) for API documentation
- Check [QUICKSTART.md](QUICKSTART.md) for usage examples
- Review [PRODUCTION.md](PRODUCTION.md) for production setup

## Getting Help

If you encounter issues:
1. Check the Apache error logs
2. Verify mod_rewrite is enabled
3. Ensure directory permissions are correct
4. Review this guide's troubleshooting section
5. Check existing GitHub issues
