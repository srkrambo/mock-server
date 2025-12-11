# Quick Start Guide

Get up and running with the PHP Mock Server in under 5 minutes!

## Prerequisites

- PHP 7.4 or higher
- Command line access
- (Optional) `curl` and `jq` for testing

## Installation

### 1. Clone the Repository
```bash
git clone https://github.com/srkrambo/mock-server.git
cd mock-server
```

### 2. Start the Server

**Option A: PHP Built-in Server (Quickest)**
```bash
php -S localhost:8080 router.php
```
Access at: `http://localhost:8080/`

**Option B: MAMP/Apache Server**
1. Copy `mock-server` folder to MAMP's `htdocs` directory
2. Start MAMP
3. Access at: `http://localhost:8788/mock-server/` (adjust port as needed)

See [MAMP_SETUP.md](MAMP_SETUP.md) for detailed MAMP setup instructions.

That's it! The server is now running!

## Basic Usage

> **Note**: Examples below use `http://localhost:8080/` (PHP built-in server). 
> If using MAMP, replace with `http://localhost:8788/mock-server/` (adjust port as needed).

### Create a Resource
```bash
curl -X POST http://localhost:8080/users/1 \
  -H "Content-Type: application/json" \
  -d '{"name": "Alice", "email": "alice@example.com"}'
```

### Retrieve a Resource
```bash
curl http://localhost:8080/users/1
```

### Update a Resource
```bash
curl -X PUT http://localhost:8080/users/1 \
  -H "Content-Type: application/json" \
  -d '{"name": "Alice Smith", "email": "alice.smith@example.com"}'
```

### Delete a Resource
```bash
curl -X DELETE http://localhost:8080/users/1
```

## File Uploads

### Multipart Upload
```bash
curl -X POST http://localhost:8080/upload \
  -F "file=@/path/to/your/file.pdf"
```

### Raw Binary Upload
```bash
curl -X PUT http://localhost:8080/upload/document.pdf \
  -H "Content-Type: application/pdf" \
  --data-binary @/path/to/document.pdf
```

### Base64 Upload
```bash
BASE64_CONTENT=$(base64 < /path/to/file.txt)
curl -X POST http://localhost:8080/upload \
  -H "Content-Type: application/json" \
  -d "{\"type\": \"base64\", \"filename\": \"file.txt\", \"content\": \"$BASE64_CONTENT\"}"
```

## Authentication

### Login (Get JWT Token)
```bash
curl -X POST http://localhost:8080/login \
  -H "Content-Type: application/json" \
  -d '{"username": "admin", "password": "admin123"}'
```

Response includes a JWT token you can use for authenticated requests.

### Using JWT Token
```bash
TOKEN="your-jwt-token-here"
curl http://localhost:8080/secure/resource \
  -H "Authorization: Bearer $TOKEN"
```

Note: By default, authentication is **disabled**. Enable it in `config.php`:
```php
'auth' => [
    'enabled' => true,
    'default_method' => 'jwt',
]
```

## Production Mode & API Keys

### Generating API Keys (Requires Google OAuth)

**Note:** API key generation requires Google authentication. Follow these steps:

1. **Configure Google OAuth** (see [PRODUCTION.md](PRODUCTION.md) for detailed setup)
   ```bash
   export GOOGLE_CLIENT_ID="your-client-id.apps.googleusercontent.com"
   export GOOGLE_CLIENT_SECRET="your-client-secret"
   ```

2. **Authenticate with Google**
   - Visit: `http://localhost:8080/auth/google`
   - Complete Google authentication
   - Save the returned JWT token

3. **Generate API Key**
   ```bash
   curl -X POST http://localhost:8080/api/generate-key \
     -H "Content-Type: application/json" \
     -H "Authorization: Bearer <your-jwt-token>" \
     -d '{"metadata": {"description": "My API key"}}'
   ```

4. **Use API Key**
   ```bash
   curl http://localhost:8080/users/1 \
     -H "X-API-Key: mk_your_api_key_here"
   ```

## Running Example Tests

The repository includes test scripts in the `examples/` directory:

### Test REST API Operations
```bash
./examples/test-api.sh
```

### Test File Uploads
```bash
./examples/test-uploads.sh
```

### Test Authentication
```bash
./examples/test-auth.sh
```

### Test Google OAuth Authentication
```bash
./examples/test-google-auth.sh
```

### Test Production Mode
```bash
./examples/test-production.sh
```

## Common Endpoints

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/login` | POST | Get JWT token |
| `/oauth/token` | POST | Get OAuth access token |
| `/auth/google` | GET | Start Google OAuth flow |
| `/auth/google/callback` | GET | Google OAuth callback |
| `/api/generate-key` | POST | Generate API key (requires Google auth) |
| `/api/keys` | GET | List API keys |
| `/upload` | POST | Upload files |
| `/upload/{filename}` | PUT | Raw binary upload |
| `/files` | GET | List uploaded files |
| `/resources` | GET | List stored resources |
| `/{resource}` | GET/POST/PUT/PATCH/DELETE | CRUD operations |

## Configuration

Edit `config.php` to customize:
- Storage paths
- Max upload size
- Authentication settings
- CORS configuration
- TUS protocol settings

Example:
```php
'server' => [
    'max_upload_size' => 100 * 1024 * 1024, // 100MB
],
'auth' => [
    'enabled' => true,
    'default_method' => 'api_key',
],
```

## Troubleshooting

### Server won't start (PHP built-in)
- Check if port 8080 is already in use
- Try a different port: `php -S localhost:9000 router.php`

### MAMP Issues
- Ensure Apache is running in MAMP
- Verify the folder is in the correct location (htdocs)
- Check that mod_rewrite is enabled
- See [MAMP_SETUP.md](MAMP_SETUP.md) for detailed troubleshooting

### PUT/PATCH requests return 405
- **PHP built-in server**: Make sure you're using `router.php`: `php -S localhost:8080 router.php`
- **MAMP/Apache**: Ensure `.htaccess` file exists and mod_rewrite is enabled

### File uploads fail
- Check `storage/uploads/` directory exists and is writable
- Verify file size is within limits (default 50MB)
- Ensure Content-Type header is set

### Authentication errors
- Check if auth is enabled in `config.php`
- Verify credentials match those in config
- For JWT, ensure token hasn't expired (default 1 hour)

## Next Steps

- Read the full [README.md](README.md) for detailed documentation
- Check [HEADER_VALIDATION.md](HEADER_VALIDATION.md) for header requirements
- Review [SECURITY.md](SECURITY.md) for security considerations
- Customize `config.php` for your needs

## Getting Help

- Check existing issues on GitHub
- Review the documentation files
- Look at the example scripts in `examples/`

## Development Tips

### Using with Your Frontend App
```javascript
// React/Vue/Angular example
const API_URL = 'http://localhost:8080';

fetch(`${API_URL}/users/1`, {
  method: 'GET',
  headers: {
    'Content-Type': 'application/json',
  }
})
.then(response => response.json())
.then(data => console.log(data));
```

### Using with Testing Tools
- **Postman**: Import the endpoints and start testing
- **Insomnia**: Create a workspace with the API endpoints
- **curl**: Use the provided example scripts as templates

### Storage Location
- Data: `storage/data/` - JSON files
- Uploads: `storage/uploads/` - Uploaded files
- Sessions: `storage/sessions/` - Session data (future use)

## Pro Tips

1. **Use jq for pretty JSON**
   ```bash
   curl http://localhost:8080/users/1 | jq '.'
   ```

2. **Test with different auth methods**
   Edit `config.php` to switch between auth types

3. **Monitor storage usage**
   ```bash
   du -sh storage/
   ```

4. **Clear all data**
   ```bash
   rm -rf storage/data/* storage/uploads/*
   ```

5. **Run in background**
   ```bash
   php -S localhost:8080 router.php > server.log 2>&1 &
   ```

Happy mocking! 🎭
