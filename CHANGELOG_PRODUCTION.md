# Production Features Changelog

## Version: Production Ready Features

### Release Date: December 11, 2025

### Overview
This release adds production-ready features to enable the mock server to be used in production environments with enhanced security, rate limiting, and configurable upload limits. All features are implemented without external dependencies (no MySQL or similar databases).

---

## New Features

### 1. Environment-Based Configuration
- **Production Mode**: Enable via `MOCK_SERVER_ENV=production` environment variable
- **Local Mode**: Default mode for development (no changes to existing behavior)
- Automatic detection and different behavior based on environment

### 2. Configurable Upload Size Limits
- **Production Default**: 1 KB (configurable in `config.php`)
- **Local Default**: 50 MB (configurable in `config.php`)
- Applied to all upload methods:
  - Raw/binary uploads (PUT)
  - Multipart form-data uploads
  - Base64 encoded uploads (JSON)
  - TUS resumable uploads

### 3. Rate Limiting (File-Based)
- **IP-Based Rate Limiting**: Limits requests per IP address
  - Default: 100 requests per 60 seconds per IP
  - Configurable in `config.php`
  
- **Global Rate Limiting**: Limits total requests to server
  - Default: 1000 requests per 60 seconds
  - Configurable in `config.php`
  
- **Endpoint-Specific Rate Limiting**: Custom limits per endpoint
  - Example: `/upload` - 10 requests per 60 seconds
  - Example: `/login` - 5 requests per 300 seconds
  - Configurable in `config.php`

- **Rate Limit Response**: Returns 429 status with headers:
  - `X-RateLimit-Limit`: Maximum requests allowed
  - `X-RateLimit-Remaining`: Requests remaining in window
  - `X-RateLimit-Reset`: Unix timestamp when limit resets

### 4. API Key Management (Production Mode)
- **File-Based Storage**: No external database required
- **API Key Generation**: `POST /api/generate-key`
  - Generates unique API keys with `mk_` prefix
  - Supports metadata (description, owner, etc.)
  - Returns full key only once during generation
  
- **API Key Listing**: `GET /api/keys`
  - Protected endpoint (requires API key in production)
  - Returns masked keys for security (e.g., `mk_1234...abcd`)
  - Shows usage statistics (last used, usage count)
  
- **API Key Validation**: Automatic in production mode
  - Required for all requests (except key generation and login)
  - Provided via `X-API-Key` header
  - Updates usage statistics on each use

### 5. Security Enhancements
- **File Locking**: Atomic writes using `LOCK_EX` flag
- **IP Spoofing Protection**: Uses `REMOTE_ADDR` only in production mode
- **API Key Masking**: Keys are masked in listing responses
- **Protected Endpoints**: Key listing requires authentication in production
- **Error Handling**: Proper error handling for file operations

---

## New Files

### Source Code
- `src/Utils/RateLimiter.php` - File-based rate limiting implementation
- `src/Auth/ApiKeyManager.php` - API key generation and management

### Documentation
- `PRODUCTION.md` - Comprehensive production deployment guide
- `CHANGELOG_PRODUCTION.md` - This file
- `examples/test-production.sh` - Test script for production features

### Storage Directories
- `storage/rate_limits/` - Rate limit data (auto-managed)
- `storage/api_keys/` - API keys database (auto-managed)

---

## Modified Files

### Configuration
- `config.php` - Added production mode settings, rate limiting config, upload limits

### Core Components
- `src/Router.php` - Integrated rate limiting, production checks, API key endpoints
- `src/Handlers/FileUploadHandler.php` - Added upload size validation
- `README.md` - Added production mode documentation section
- `.gitignore` - Excluded auto-generated storage files

---

## API Endpoints

### New Endpoints
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/generate-key` | Generate a new API key |
| GET | `/api/keys` | List all API keys (masked, requires auth in production) |

### Modified Behavior
All existing endpoints now:
- Check rate limits before processing
- Require API key in production mode
- Enforce upload size limits based on environment

---

## Configuration Options

### Environment Mode
```php
'environment' => [
    'mode' => 'production', // or 'local'
],
```

### Upload Limits
```php
'server' => [
    'max_upload_size' => 50 * 1024 * 1024, // 50MB for local
    'production_max_upload_size' => 1 * 1024, // 1KB for production
],
```

### Rate Limiting
```php
'rate_limit' => [
    'enabled' => true,
    'ip_based' => [
        'enabled' => true,
        'max_requests' => 100,
        'window' => 60,
    ],
    'global' => [
        'enabled' => true,
        'max_requests' => 1000,
        'window' => 60,
    ],
    'endpoint_specific' => [
        '/upload' => ['max_requests' => 10, 'window' => 60],
        '/login' => ['max_requests' => 5, 'window' => 300],
    ],
],
```

### API Key Authentication
```php
'auth' => [
    'production_enforce_api_key' => true,
],
```

---

## Usage Examples

### Generate API Key
```bash
curl -X POST http://localhost:8080/api/generate-key \
  -H "Content-Type: application/json" \
  -d '{"metadata": {"description": "My App Key"}}'
```

### Use API Key
```bash
curl -X GET http://localhost:8080/users/1 \
  -H "X-API-Key: mk_your_api_key_here"
```

### Upload File in Production
```bash
curl -X PUT http://localhost:8080/upload/document.txt \
  -H "Content-Type: text/plain" \
  -H "X-API-Key: mk_your_api_key_here" \
  --data-binary @document.txt
```

---

## Backward Compatibility

### ✅ Fully Backward Compatible
- Local mode works exactly as before
- No breaking changes to existing APIs
- Optional API keys in local mode
- Rate limiting can be disabled if needed

### Migration Path
1. Test in local mode (default)
2. Review and adjust rate limits
3. Set production upload size limit
4. Generate API keys for production use
5. Set `MOCK_SERVER_ENV=production`
6. Update client applications to include API keys

---

## Testing

### Test Suite
Run the production test suite:
```bash
bash examples/test-production.sh
```

### Manual Testing
1. Start in production mode: `MOCK_SERVER_ENV=production php -S localhost:8080 router.php`
2. Generate API key: `curl -X POST http://localhost:8080/api/generate-key ...`
3. Test with API key: `curl -H "X-API-Key: mk_..." ...`
4. Test upload limits with files of different sizes
5. Test rate limiting by sending multiple rapid requests

---

## Performance Considerations

- **File-Based Storage**: Suitable for small to medium deployments
- **Rate Limit Cleanup**: Automatically cleans up expired files
- **Concurrent Access**: Uses file locking to prevent race conditions
- **Storage Requirements**: Minimal (~1KB per rate limit entry, ~1KB per API key)

---

## Security Considerations

1. **API Keys**: Store securely, never commit to version control
2. **HTTPS**: Use HTTPS in production (configure with reverse proxy)
3. **File Permissions**: Ensure storage directories have proper permissions (755)
4. **IP Spoofing**: Production mode uses REMOTE_ADDR to prevent header spoofing
5. **Key Masking**: Full keys are never exposed after generation
6. **Rate Limiting**: Protects against abuse and DoS attacks

---

## Known Limitations

1. File-based storage may not scale to very high traffic (consider Redis for large deployments)
2. No API key revocation UI (must be done manually or via API)
3. Rate limit windows are fixed (not sliding windows)
4. No built-in API key rotation mechanism

---

## Future Enhancements

- [ ] API key revocation endpoint
- [ ] API key expiration support
- [ ] Sliding window rate limiting
- [ ] Optional Redis backend for high-traffic scenarios
- [ ] API key usage analytics and reporting
- [ ] Automatic rate limit adjustment based on load
- [ ] Integration with external authentication providers (Google Sign-In)

---

## Support

For issues, questions, or contributions:
- See [PRODUCTION.md](PRODUCTION.md) for detailed documentation
- See [README.md](README.md) for general usage
- Run `examples/test-production.sh` for testing

---

## Credits

Implemented to support production deployments with:
- No external dependencies (no MySQL, Redis, etc.)
- File-based storage for simplicity
- Configurable limits and behavior
- Full backward compatibility
