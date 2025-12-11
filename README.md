# PHP Mock Server

A comprehensive PHP-based mock server for testing and development. Supports REST API operations, multiple file upload methods, and various authentication mechanisms.

## Features

### HTTP Methods
- GET - Retrieve resources
- POST - Create new resources
- PUT - Update or create resources
- PATCH - Partial update of resources
- DELETE - Delete resources

### File Upload Support
1. **Direct Raw/Binary Upload**: Upload files directly in the request body
   - Example: `PUT /image.jpg` with `Content-Type: image/jpeg`

2. **Multipart Form-Data Upload**: Traditional file upload via forms
   - Supports single and multiple file uploads
   
3. **Base64 Encoded (JSON)**: Upload files as base64-encoded content in JSON
   - Example: `POST /upload` with JSON containing filename and base64 content

4. **Resumable (TUS Protocol)**: Support for resumable uploads using TUS protocol
   - Supports creation, chunked upload, and status checking

### Authentication Methods
- **None**: No authentication required (default)
- **Basic Auth**: Username and password authentication
- **API Keys**: API key-based authentication via `X-API-Key` header
- **JWT (JSON Web Tokens)**: Bearer token authentication
- **OAuth 2.0**: OAuth 2.0 client credentials flow
- **mTLS**: Mutual TLS certificate authentication
- **OpenID Connect**: OpenID Connect token validation

### Data Storage
- All data stored as JSON files for simplicity
- Automatic file-based persistence
- No database required

## Installation

1. Clone the repository:
```bash
git clone https://github.com/srkrambo/mock-server.git
cd mock-server
```

2. Make sure PHP is installed (PHP 7.4+ recommended)

3. Start the built-in PHP server:
```bash
php -S localhost:8080
```

## Configuration

Edit `config.php` to customize:
- Storage paths
- Maximum upload size
- Authentication settings
- CORS settings
- TUS protocol settings

## Usage Examples

### 1. Dummy Login

```bash
# Login with username and password
curl -X POST http://localhost:8080/login \
  -H "Content-Type: application/json" \
  -d '{"username": "admin", "password": "admin123"}'

# Response:
# {
#   "success": true,
#   "message": "Login successful",
#   "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
#   "user": {
#     "username": "admin",
#     "role": "user"
#   }
# }
```

### 2. Basic REST Operations

```bash
# Create a resource (POST)
curl -X POST http://localhost:8080/users/1 \
  -H "Content-Type: application/json" \
  -d '{"name": "John Doe", "email": "john@example.com"}'

# Retrieve a resource (GET)
curl http://localhost:8080/users/1

# Update a resource (PUT)
curl -X PUT http://localhost:8080/users/1 \
  -H "Content-Type: application/json" \
  -d '{"name": "John Smith", "email": "john.smith@example.com"}'

# Partial update (PATCH)
curl -X PATCH http://localhost:8080/users/1 \
  -H "Content-Type: application/json" \
  -d '{"email": "newemail@example.com"}'

# Delete a resource (DELETE)
curl -X DELETE http://localhost:8080/users/1
```

### 3. File Upload Methods

#### Raw/Binary Upload
```bash
# Upload image directly
curl -X PUT http://localhost:8080/upload/image.jpg \
  -H "Content-Type: image/jpeg" \
  --data-binary @/path/to/image.jpg
```

#### Multipart Form-Data Upload
```bash
# Upload via form-data
curl -X POST http://localhost:8080/upload \
  -F "file=@/path/to/document.pdf"

# Multiple files
curl -X POST http://localhost:8080/upload \
  -F "file1=@/path/to/file1.jpg" \
  -F "file2=@/path/to/file2.png"
```

#### Base64 Encoded Upload
```bash
# Upload base64 encoded file
curl -X POST http://localhost:8080/upload \
  -H "Content-Type: application/json" \
  -d '{
    "type": "base64",
    "filename": "document.pdf",
    "content": "JVBERi0xLjQKJeLjz9MKMy..."
  }'
```

#### TUS Resumable Upload
```bash
# Create upload
curl -X POST http://localhost:8080/upload \
  -H "Tus-Resumable: 1.0.0" \
  -H "Upload-Length: 1000000"

# Upload chunk
curl -X PATCH http://localhost:8080/upload/tus_xxxxx \
  -H "Tus-Resumable: 1.0.0" \
  -H "Upload-Offset: 0" \
  -H "Content-Type: application/offset+octet-stream" \
  --data-binary @chunk1.bin

# Check status
curl -X HEAD http://localhost:8080/upload/tus_xxxxx \
  -H "Tus-Resumable: 1.0.0"
```

### 4. Authentication Examples

To enable authentication, edit `config.php` and set:
```php
'auth' => [
    'enabled' => true,
    'default_method' => 'basic', // or 'api_key', 'jwt', 'oauth2', 'mtls', 'openid'
]
```

#### Basic Auth
```bash
curl -X GET http://localhost:8080/users/1 \
  -u admin:admin123
```

#### API Key
```bash
curl -X GET http://localhost:8080/users/1 \
  -H "X-API-Key: test-api-key-123"
```

#### JWT Bearer Token
```bash
# Get token from login
TOKEN=$(curl -X POST http://localhost:8080/login \
  -H "Content-Type: application/json" \
  -d '{"username": "admin", "password": "admin123"}' | jq -r '.token')

# Use token
curl -X GET http://localhost:8080/users/1 \
  -H "Authorization: Bearer $TOKEN"
```

#### OAuth 2.0
```bash
# Get access token
curl -X POST http://localhost:8080/oauth/token \
  -H "Content-Type: application/json" \
  -d '{
    "grant_type": "client_credentials",
    "client_id": "mock-client-id",
    "client_secret": "mock-client-secret"
  }'

# Use access token
curl -X GET http://localhost:8080/users/1 \
  -H "Authorization: Bearer <access_token>"
```

### 5. List Resources and Files

```bash
# List all stored resources
curl http://localhost:8080/resources

# List all uploaded files
curl http://localhost:8080/files
```

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/login` | Dummy login endpoint, returns JWT token |
| POST | `/oauth/token` | OAuth 2.0 token endpoint |
| POST | `/upload` | Upload files (multipart or raw) |
| POST/PATCH/HEAD | `/upload/{id}` | TUS resumable upload operations |
| GET | `/files` | List all uploaded files |
| GET | `/resources` | List all stored resources |
| GET/POST/PUT/PATCH/DELETE | `/{resource}` | CRUD operations on resources |

## Directory Structure

```
mock-server/
├── config.php              # Configuration file
├── index.php               # Main entry point
├── .htaccess               # Apache rewrite rules
├── src/
│   ├── Auth/
│   │   └── AuthHandler.php # Authentication handler
│   ├── Handlers/
│   │   ├── DataHandler.php # Data storage handler
│   │   └── FileUploadHandler.php # File upload handler
│   ├── Utils/
│   │   ├── Request.php     # Request wrapper
│   │   └── Response.php    # Response wrapper
│   └── Router.php          # Main router
└── storage/
    ├── data/               # JSON data storage
    ├── uploads/            # Uploaded files
    └── sessions/           # Session data
```

## Requirements

- PHP 7.4 or higher
- Apache with mod_rewrite (or PHP built-in server)
- Write permissions for storage directories

## Testing

The server is ready to use immediately after starting. All endpoints are available without authentication by default. Configure authentication in `config.php` as needed.

## License

MIT License

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.
