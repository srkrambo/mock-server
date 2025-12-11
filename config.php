<?php
/**
 * Mock Server Configuration
 */

return [
    // Storage paths
    'storage' => [
        'data' => __DIR__ . '/storage/data',
        'uploads' => __DIR__ . '/storage/uploads',
        'sessions' => __DIR__ . '/storage/sessions',
    ],
    
    // Server settings
    'server' => [
        'base_url' => 'http://localhost:8080',
        'max_upload_size' => 50 * 1024 * 1024, // 50MB
        'allowed_file_types' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'txt', 'doc', 'docx', 'zip'],
    ],
    
    // Authentication settings
    'auth' => [
        'enabled' => true,
        'default_method' => 'none', // none, basic, api_key, jwt, oauth2, mtls, openid
        
        // Basic Auth
        'basic' => [
            'users' => [
                'admin' => 'admin123',
                'user' => 'password',
            ],
        ],
        
        // API Keys
        'api_keys' => [
            'valid_keys' => [
                'test-api-key-123',
                'demo-key-456',
                'dev-key-789',
            ],
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
