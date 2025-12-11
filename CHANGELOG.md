# Changelog

All notable changes to the PHP Mock Server project will be documented in this file.

## [1.0.0] - 2025-12-11

### Added - Initial Release

#### Core Features
- **HTTP Methods Support**
  - GET - Retrieve resources
  - POST - Create new resources
  - PUT - Update or create resources
  - PATCH - Partial update of resources
  - DELETE - Delete resources

#### File Upload Methods
- **Direct Raw/Binary Upload** - Upload files directly in request body with PUT
- **Multipart Form-Data Upload** - Traditional file upload via forms
- **Base64 Encoded (JSON)** - Upload files as base64-encoded content in JSON
- **Resumable (TUS Protocol)** - Support for TUS 1.0.0 resumable uploads
  - POST - Create upload session
  - PATCH - Upload chunks
  - HEAD - Check upload status
  - OPTIONS - Get server capabilities

#### Authentication Methods
- **None** - No authentication (default mode)
- **Basic Auth** - Username and password authentication
- **API Keys** - API key-based authentication via X-API-Key header
- **JWT (JSON Web Tokens)** - Bearer token authentication with token generation
- **OAuth 2.0** - Client credentials flow with token endpoint
- **mTLS** - Mutual TLS certificate authentication
- **OpenID Connect** - OpenID Connect token validation

#### Endpoints
- `/login` - Dummy login endpoint that returns JWT token
- `/oauth/token` - OAuth 2.0 token endpoint
- `/upload` - File upload endpoint (all methods)
- `/upload/{filename}` - Raw binary upload via PUT
- `/upload/{id}` - TUS resumable upload operations
- `/files` - List all uploaded files
- `/resources` - List all stored resources
- `/{resource}` - Generic resource CRUD operations

#### Storage
- File-based JSON storage for data
- Separate directory for uploaded files
- No database required
- Automatic directory creation

#### Header Validation
- Content-Type validation for all request types
- Content-Length validation for file uploads
- Authorization header format validation
- TUS protocol header validation (Tus-Resumable, Upload-Length, Upload-Offset)
- Required headers checking with meaningful error messages
- Proper HTTP status codes (400, 401, 415, 422)

#### Configuration
- Centralized configuration in `config.php`
- Configurable storage paths
- Configurable authentication settings
- Configurable CORS settings
- Configurable max upload size
- Configurable TUS protocol settings

#### Documentation
- **README.md** - Complete project documentation with examples
- **QUICKSTART.md** - Quick start guide for new users
- **HEADER_VALIDATION.md** - Detailed header validation documentation
- **SECURITY.md** - Security considerations and warnings
- **CHANGELOG.md** - This file

#### Test Scripts
- `examples/test-api.sh` - Test REST API operations
- `examples/test-uploads.sh` - Test all file upload methods
- `examples/test-auth.sh` - Test authentication methods
- `examples/comprehensive-test.sh` - Complete test suite with 19 tests

#### Utilities
- `src/Utils/Request.php` - Request wrapper with header and body parsing
- `src/Utils/Response.php` - Response wrapper with JSON, CORS, and status codes
- `src/Utils/HeaderValidator.php` - Header validation utility class

#### Handlers
- `src/Auth/AuthHandler.php` - Authentication handler for all auth methods
- `src/Handlers/DataHandler.php` - File-based JSON data storage handler
- `src/Handlers/FileUploadHandler.php` - File upload handler for all 4 methods

#### Router
- `src/Router.php` - Main request router with CRUD operations
- `router.php` - PHP built-in server router for proper HTTP method handling
- `.htaccess` - Apache rewrite rules for clean URLs

#### CORS Support
- Configurable CORS origins
- Automatic CORS headers on all responses
- OPTIONS preflight request handling

### Security
- Input validation on all endpoints
- Filename sanitization using basename()
- JWT token expiration checking
- Content-Length validation against max size
- Basic Auth credentials format validation
- opendir() return value checking
- Proper CORS wildcard origin handling

### Developer Experience
- Comprehensive example scripts
- Detailed error messages
- Clean JSON responses
- Easy configuration
- No dependencies (except PHP)
- Works with PHP built-in server

## Future Considerations

### Potential Future Enhancements (Not Planned for Current Version)
- Rate limiting
- Request logging
- Response mocking based on rules
- GraphQL support
- WebSocket support
- Request/response recording and replay
- Admin UI for managing resources
- Database backend option
- User management system
- API versioning support

---

## Version Format

This project follows [Semantic Versioning](https://semver.org/):
- MAJOR version for incompatible API changes
- MINOR version for new functionality in a backwards compatible manner
- PATCH version for backwards compatible bug fixes

## Contributing

When contributing, please:
1. Update this CHANGELOG.md with your changes
2. Follow the existing format
3. Group changes by type (Added, Changed, Fixed, Removed, Security)
4. Include the date of release

## Links

- [Repository](https://github.com/srkrambo/mock-server)
- [Issues](https://github.com/srkrambo/mock-server/issues)
- [Pull Requests](https://github.com/srkrambo/mock-server/pulls)
