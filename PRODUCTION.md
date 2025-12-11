# Production Mode Guide

This guide explains how to configure and use the mock server in production mode with enhanced security features including rate limiting, upload size limits, and API key authentication.

## Features

### 1. Environment-Based Configuration

The mock server supports two modes:
- **Local Mode** (default): For development with relaxed limits
- **Production Mode**: Enforces strict limits and API key authentication

### 2. Upload Size Limits

- **Local Mode**: 50 MB maximum upload size (configurable)
- **Production Mode**: 1 KB maximum upload size (configurable)

### 3. Rate Limiting

Three types of rate limiting are supported:
- **IP-based**: Limits requests per IP address
- **Global**: Limits total requests to the server
- **Endpoint-specific**: Custom limits for specific endpoints

### 4. API Key Authentication

In production mode, API keys are required for all requests (except key generation and login endpoints).

## Configuration

### Setting Environment Mode

Set the `MOCK_SERVER_ENV` environment variable:

```bash
# For production
export MOCK_SERVER_ENV=production

# For local development (default)
export MOCK_SERVER_ENV=local
```

Or edit `config.php`:

```php
'environment' => [
    'mode' => 'production', // or 'local'
],
```

### Configuring Upload Limits

Edit `config.php`:

```php
'server' => [
    'max_upload_size' => 50 * 1024 * 1024, // 50MB for local
    'production_max_upload_size' => 1 * 1024, // 1KB for production
],
```

### Configuring Rate Limits

Edit `config.php`:

```php
'rate_limit' => [
    'enabled' => true,
    'ip_based' => [
        'enabled' => true,
        'max_requests' => 100, // Max requests per IP
        'window' => 60, // Time window in seconds
    ],
    'global' => [
        'enabled' => true,
        'max_requests' => 1000, // Max total requests
        'window' => 60,
    ],
    'endpoint_specific' => [
        '/upload' => [
            'max_requests' => 10,
            'window' => 60,
        ],
        '/login' => [
            'max_requests' => 5,
            'window' => 300, // 5 minutes
        ],
    ],
],
```

### Configuring API Key Authentication

Edit `config.php`:

```php
'auth' => [
    'enabled' => true,
    'production_enforce_api_key' => true, // Enforce in production
],
```

## Usage

### Generating API Keys

Generate a new API key:

```bash
curl -X POST http://localhost:8080/api/generate-key \
  -H "Content-Type: application/json" \
  -d '{"metadata": {"description": "Production API key", "owner": "admin"}}'
```

Response:
```json
{
  "success": true,
  "message": "API key generated successfully",
  "api_key": "mk_636285e2cb20685fc6aad811c384f7bedd217fb9ed69a68cb133a9041fb66565",
  "created_at": 1765434215,
  "usage_instructions": "Include this API key in the X-API-Key header for all requests"
}
```

### Using API Keys

Include the API key in the `X-API-Key` header:

```bash
# Make a request with API key
curl -X GET http://localhost:8080/users/1 \
  -H "X-API-Key: mk_636285e2cb20685fc6aad811c384f7bedd217fb9ed69a68cb133a9041fb66565"

# Upload a file with API key
curl -X PUT http://localhost:8080/upload/document.pdf \
  -H "X-API-Key: mk_636285e2cb20685fc6aad811c384f7bedd217fb9ed69a68cb133a9041fb66565" \
  -H "Content-Type: application/pdf" \
  --data-binary @document.pdf
```

### Listing API Keys

View all generated API keys:

```bash
curl -X GET http://localhost:8080/api/keys
```

Response:
```json
{
  "success": true,
  "keys": [
    {
      "key": "mk_636285e2cb20685fc6aad811c384f7bedd217fb9ed69a68cb133a9041fb66565",
      "created_at": 1765434215,
      "active": true,
      "last_used": 1765434300,
      "usage_count": 42,
      "metadata": {
        "description": "Production API key",
        "owner": "admin"
      }
    }
  ],
  "total": 1
}
```

## Rate Limit Response

When rate limits are exceeded, you'll receive a 429 response:

```json
{
  "error": "Too Many Requests",
  "message": "Rate limit exceeded. Please try again later.",
  "retry_after": 45
}
```

Response headers include:
- `X-RateLimit-Limit`: Maximum number of requests allowed
- `X-RateLimit-Remaining`: Number of requests remaining
- `X-RateLimit-Reset`: Unix timestamp when the limit resets

## Upload Size Limit Response

When upload size exceeds the limit, you'll receive a 413 response:

```json
{
  "error": "Payload Too Large",
  "message": "Upload size exceeds maximum allowed size of 1024 bytes"
}
```

## Best Practices

### 1. API Key Management

- Generate separate API keys for different applications/users
- Store API keys securely (environment variables, secrets management)
- Never commit API keys to version control
- Regularly rotate API keys
- Monitor API key usage through the listing endpoint

### 2. Rate Limiting

- Set appropriate limits based on your usage patterns
- Monitor rate limit errors to adjust limits if needed
- Use endpoint-specific limits for sensitive operations
- Consider lower limits for authentication endpoints

### 3. Upload Limits

- Set production upload limits based on your use case
- Use appropriate limits to prevent abuse
- Consider different limits for different environments
- Monitor upload patterns to adjust limits

### 4. Security

- Always run in production mode for public-facing deployments
- Use HTTPS in production (configure with reverse proxy)
- Regularly review API key usage
- Monitor rate limit violations for potential attacks
- Set up logging and alerting for security events

## File Storage

All data is stored locally without external dependencies:

- API keys: `storage/api_keys/keys.json`
- Rate limit data: `storage/rate_limits/*.json`
- Uploaded files: `storage/uploads/`
- Resource data: `storage/data/`

Ensure proper file permissions and backup these directories.

## Troubleshooting

### API Key Not Working

1. Verify production mode is enabled
2. Check the API key in `storage/api_keys/keys.json`
3. Ensure the `X-API-Key` header is included
4. Verify the key hasn't been revoked

### Rate Limiting Issues

1. Check `storage/rate_limits/` for rate limit data
2. Wait for the time window to expire
3. Adjust limits in `config.php` if needed
4. Clear old rate limit files if necessary

### Upload Failures

1. Check the file size against configured limits
2. Verify proper Content-Type header
3. Check production mode settings
4. Ensure storage directory has write permissions

## Migration from Local to Production

1. Set environment to production mode
2. Generate production API keys
3. Update client applications to include API keys
4. Test with small uploads (under 1KB)
5. Adjust rate limits as needed
6. Monitor for errors and adjust configuration

## Example Production Setup

```bash
# 1. Set environment
export MOCK_SERVER_ENV=production

# 2. Start server
php -S 0.0.0.0:8080 router.php

# 3. Generate API key
curl -X POST http://localhost:8080/api/generate-key \
  -H "Content-Type: application/json" \
  -d '{"metadata": {"owner": "production-app"}}'

# 4. Use API key in requests
API_KEY="mk_xxxxx"
curl -X POST http://localhost:8080/users \
  -H "X-API-Key: $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"name": "John Doe"}'
```

## Additional Notes

- In local mode, API keys are optional
- Rate limiting applies to both local and production modes
- Upload limits are enforced in both modes (different defaults)
- All file operations use file-based storage (no database required)
- Rate limit files are automatically cleaned up after expiration
