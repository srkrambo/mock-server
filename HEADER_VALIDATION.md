# Header Validation

The PHP Mock Server implements comprehensive header validation to ensure API requests are well-formed and secure.

## Required Headers by Endpoint

### POST Requests (Data Creation)
- **Content-Type**: Required
  - `application/json` - For JSON data
  - `application/x-www-form-urlencoded` - For form data
  - `multipart/form-data` - For file uploads

### PUT Requests (Data Update/Upload)
- **Content-Type**: Required
  - `application/json` - For JSON data updates
  - `application/octet-stream` - For binary file uploads
  - `image/*`, `video/*`, `audio/*` - For media file uploads
- **Content-Length**: Validated when present (enforces max upload size)

### PATCH Requests (Partial Update)
- **Content-Type**: Required
  - `application/json` - Only JSON is supported for PATCH

### DELETE Requests
- No special headers required

### GET Requests
- No special headers required

## Authentication Headers

### Basic Authentication
- **Authorization**: Required when auth method is "basic"
  - Format: `Basic <base64-encoded-credentials>`
  - Credentials format: `username:password`

### API Key Authentication
- **X-API-Key**: Required when auth method is "api_key"
  - Format: `X-API-Key: your-api-key-here`

### JWT Authentication
- **Authorization**: Required when auth method is "jwt"
  - Format: `Bearer <jwt-token>`

### OAuth 2.0 Authentication
- **Authorization**: Required when auth method is "oauth2"
  - Format: `Bearer <access-token>`

### mTLS Authentication
- **SSL_CLIENT_CERT**: Server environment variable (automatic)

### OpenID Connect Authentication
- **Authorization**: Required when auth method is "openid"
  - Format: `Bearer <id-token>`

## File Upload Headers

### Multipart Form-Data Upload
- **Content-Type**: `multipart/form-data; boundary=...` (automatically set by client)

### Raw/Binary Upload
- **Content-Type**: Required (e.g., `image/jpeg`, `application/octet-stream`)
- **Content-Length**: Recommended (validated against max size)

### Base64 Encoded Upload (JSON)
- **Content-Type**: `application/json`

### TUS Resumable Upload

#### POST (Create Upload)
- **Tus-Resumable**: Required - Must be `1.0.0`
- **Upload-Length**: Required - Total file size in bytes
- **Upload-Metadata**: Optional - Base64-encoded metadata

#### PATCH (Upload Chunk)
- **Tus-Resumable**: Required - Must be `1.0.0`
- **Upload-Offset**: Required - Current byte offset
- **Content-Type**: Required - Must be `application/offset+octet-stream`
- **Content-Length**: Required - Chunk size

#### HEAD (Check Status)
- **Tus-Resumable**: Required - Must be `1.0.0`

#### OPTIONS (Get Server Capabilities)
- **Tus-Resumable**: Required - Must be `1.0.0`

## Special Endpoints

### /login
- **Content-Type**: Required - Must be `application/json`
- Body must contain: `username` and `password`

### /oauth/token
- **Content-Type**: Required - Must be `application/json`
- Body must contain: `grant_type`, `client_id`, `client_secret`

## Error Responses for Missing/Invalid Headers

### 400 Bad Request
Returned when required headers are missing or have invalid values:
```json
{
  "error": "Bad Request",
  "message": "Content-Type header is required for POST requests"
}
```

### 401 Unauthorized
Returned when authentication headers are missing or invalid:
```json
{
  "error": "Authentication failed",
  "message": "Authorization header missing"
}
```

### 415 Unsupported Media Type
Returned when Content-Type is not supported for the operation:
```json
{
  "error": "Unsupported Media Type",
  "message": "Content-Type must be application/json for data storage"
}
```

## Content-Length Validation

The server validates the `Content-Length` header for file uploads:
- Maximum upload size: 50MB (configurable in `config.php`)
- Returns 400 Bad Request if file size exceeds limit
- Returns 400 Bad Request if Content-Length is invalid or zero

Example error response:
```json
{
  "error": "Bad Request",
  "message": "File size (60000000 bytes) exceeds maximum allowed size (52428800 bytes)"
}
```

## CORS Headers

The server automatically adds CORS headers to responses (if enabled in config):
- `Access-Control-Allow-Origin`
- `Access-Control-Allow-Methods`
- `Access-Control-Allow-Headers`
- `Access-Control-Max-Age`

## Header Validation Utility

The `HeaderValidator` utility class provides reusable validation methods:

```php
use MockServer\Utils\HeaderValidator;

// Validate Content-Type
$validation = HeaderValidator::validateContentType($contentType, ['application/json']);

// Validate required headers
$validation = HeaderValidator::validateRequiredHeaders($headers, ['Authorization', 'Content-Type']);

// Validate Authorization header
$validation = HeaderValidator::validateAuthorizationHeader($authHeader, 'Bearer');

// Validate TUS headers
$validation = HeaderValidator::validateTusHeaders($headers, 'POST');

// Validate Content-Length
$validation = HeaderValidator::validateContentLength($contentLength, $maxSize);
```

All validation methods return an array with:
- `valid` (bool): Whether validation passed
- `error` (string): Error message if validation failed
- Additional context-specific fields

## Best Practices

1. Always set the appropriate `Content-Type` header for your request
2. Include `Content-Length` for file uploads
3. Use proper authentication headers based on the configured auth method
4. For TUS uploads, ensure all required TUS headers are present and properly formatted
5. Check the error message in 4xx responses for specific header issues
6. Use `Accept: application/json` header to indicate you expect JSON responses
