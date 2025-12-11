<?php
/**
 * Mock Server Configuration
 */

return [
    // Environment settings
    'environment' => [
        // Set to 'production' to enable production features (rate limiting, API key enforcement)
        'mode' => getenv('MOCK_SERVER_ENV') ?: 'local', // 'local' or 'production'
    ],
    
    // Storage paths
    'storage' => [
        'data' => __DIR__ . '/storage/data',
        'uploads' => __DIR__ . '/storage/uploads',
        'sessions' => __DIR__ . '/storage/sessions',
        'rate_limits' => __DIR__ . '/storage/rate_limits',
        'api_keys' => __DIR__ . '/storage/api_keys',
    ],
    
    // Server settings
    'server' => [
        'base_url' => 'http://localhost:8080',
        'max_upload_size' => 50 * 1024 * 1024, // 50MB for local development
        'production_max_upload_size' => 1 * 1024, // 1KB for production (configurable)
        'allowed_file_types' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'txt', 'doc', 'docx', 'zip'],
    ],
    
    // Rate limiting settings
    'rate_limit' => [
        'enabled' => true, // Enable rate limiting
        'ip_based' => [
            'enabled' => true,
            'max_requests' => 100, // Maximum requests per IP
            'window' => 60, // Time window in seconds (1 minute)
        ],
        'global' => [
            'enabled' => true,
            'max_requests' => 1000, // Maximum total requests
            'window' => 60, // Time window in seconds (1 minute)
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
    
    // Authentication settings
    'auth' => [
        'enabled' => true,
        'default_method' => 'none', // none, basic, api_key, jwt, oauth2, mtls, openid
        // In production mode, API key authentication is enforced regardless of default_method
        'production_enforce_api_key' => true, // Enforce API key in production mode
        
        // Basic Auth
        'basic' => [
            'users' => [
                'admin' => 'admin123',
                'user' => 'password',
            ],
        ],
        
        // API Keys - for local development
        'api_keys' => [
            'valid_keys' => [
                'test-api-key-123',
                'demo-key-456',
                'dev-key-789',
            ],
        ],
        
        // Production API Keys - stored in file system for production
        'production_api_keys' => [
            'storage_enabled' => true, // Store API keys in file system
            'require_authentication' => true, // Require Google OAuth authentication for API key generation
            'auth_method' => 'google', // Use Google OAuth for authentication
        ],
        
        // JWT
        'jwt' => [
            'secret' => 'your-secret-key-change-in-production',
            'algorithm' => 'HS256',
            'expiration' => 3600, // 1 hour
        ],
        
        // OAuth 2.0
        'oauth2' => [
            'client_id' => 'mock-client-id',
            'client_secret' => 'mock-client-secret',
            'token_endpoint' => '/oauth/token',
            'authorize_endpoint' => '/oauth/authorize',
        ],
        
        // Google OAuth 2.0
        'google' => [
            'client_id' => getenv('GOOGLE_CLIENT_ID') ?: '',
            'client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: '',
            'redirect_uri' => getenv('GOOGLE_REDIRECT_URI') ?: 'http://localhost:8080/auth/google/callback',
            'auth_uri' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_uri' => 'https://oauth2.googleapis.com/token',
            'user_info_uri' => 'https://www.googleapis.com/oauth2/v2/userinfo',
            'scopes' => ['openid', 'email', 'profile'],
        ],
        
        // OpenID Connect
        'openid' => [
            'issuer' => 'http://localhost:8080',
            'jwks_uri' => '/openid/jwks',
        ],
    ],
    
    // TUS (resumable upload) settings
    'tus' => [
        'enabled' => true,
        'max_size' => 100 * 1024 * 1024, // 100MB
        'chunk_size' => 1024 * 1024, // 1MB
    ],
    
    // CORS settings
    'cors' => [
        'enabled' => true,
        'origins' => ['*'],
        'methods' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],
        'headers' => ['Content-Type', 'Authorization', 'X-API-Key', 'Upload-Offset', 'Upload-Length', 'Tus-Resumable'],
    ],
];
