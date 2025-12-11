<?php

namespace MockServer;

use MockServer\Auth\AuthHandler;
use MockServer\Auth\ApiKeyManager;
use MockServer\Handlers\DataHandler;
use MockServer\Handlers\FileUploadHandler;
use MockServer\Utils\Request;
use MockServer\Utils\Response;
use MockServer\Utils\HeaderValidator;
use MockServer\Utils\RateLimiter;

class Router
{
    private $config;
    private $authHandler;
    private $dataHandler;
    private $fileHandler;
    private $request;
    private $response;
    private $rateLimiter;
    private $apiKeyManager;
    
    public function __construct($config)
    {
        $this->config = $config;
        $this->authHandler = new AuthHandler($config);
        $this->dataHandler = new DataHandler($config);
        $this->fileHandler = new FileUploadHandler($config);
        $this->request = new Request($config);
        $this->response = new Response();
        $this->rateLimiter = new RateLimiter($config);
        $this->apiKeyManager = new ApiKeyManager($config);
    }
    
    public function handleRequest()
    {
        $method = $this->request->getMethod();
        $uri = $this->request->getUri();
        $headers = $this->request->getHeaders();
        
        // Apply CORS
        $this->response->cors($this->config);
        
        // Handle OPTIONS preflight
        if ($method === 'OPTIONS') {
            $this->response->setStatusCode(200)->send();
            return;
        }
        
        // Check rate limits (before authentication)
        if ($this->config['rate_limit']['enabled']) {
            $rateLimitResult = $this->checkRateLimits($uri);
            if (!$rateLimitResult['allowed']) {
                $this->response
                    ->setHeader('X-RateLimit-Limit', $rateLimitResult['limit'] ?? 0)
                    ->setHeader('X-RateLimit-Remaining', $rateLimitResult['remaining'] ?? 0)
                    ->setHeader('X-RateLimit-Reset', $rateLimitResult['reset_at'] ?? 0)
                    ->json([
                        'error' => 'Too Many Requests',
                        'message' => 'Rate limit exceeded. Please try again later.',
                        'retry_after' => $rateLimitResult['reset_at'] - time(),
                    ], 429)
                    ->send();
                return;
            }
        }
        
        // Special routes that don't require authentication
        if ($uri === '/login' && $method === 'POST') {
            return $this->handleLogin();
        }
        
        if ($uri === '/oauth/token' && $method === 'POST') {
            return $this->handleOAuthToken();
        }
        
        // Web UI routes for Google OAuth
        if ($uri === '/auth/login' && $method === 'GET') {
            return $this->serveStaticFile('public/auth.html');
        }
        
        if ($uri === '/auth/success' && $method === 'GET') {
            return $this->serveStaticFile('public/auth-success.html');
        }
        
        if ($uri === '/auth/generate-key' && $method === 'GET') {
            return $this->serveStaticFile('public/generate-key.html');
        }
        
        // Google OAuth routes
        if ($uri === '/auth/google' && $method === 'GET') {
            return $this->handleGoogleAuthStart();
        }
        
        if ($uri === '/auth/google/callback' && $method === 'GET') {
            return $this->handleGoogleAuthCallback();
        }
        
        if ($uri === '/auth/google/logout' && $method === 'POST') {
            return $this->handleGoogleLogout();
        }
        
        // API key generation endpoint - requires Google authentication
        if ($uri === '/api/generate-key' && $method === 'POST') {
            return $this->handleGenerateApiKey();
        }
        
        // API key listing endpoint
        if ($uri === '/api/keys' && $method === 'GET') {
            return $this->handleListApiKeys();
        }
        
        if (strpos($uri, '/upload') === 0) {
            return $this->handleFileUpload();
        }
        
        if ($uri === '/files' && $method === 'GET') {
            return $this->handleListFiles();
        }
        
        if ($uri === '/resources' && $method === 'GET') {
            return $this->handleListResources();
        }
        
        // Check production mode API key requirement
        if ($this->isProductionMode() && $this->config['auth']['production_enforce_api_key']) {
            $apiKeyResult = $this->checkApiKey($headers);
            if (!$apiKeyResult['valid']) {
                $this->response
                    ->json([
                        'error' => 'Unauthorized',
                        'message' => 'API key is required in production mode. Please provide a valid API key via X-API-Key header.',
                    ], 401)
                    ->send();
                return;
            }
        } else {
            // Authenticate request for non-production or when API key is not enforced
            $authResult = $this->authHandler->authenticate($headers, $method);
            
            if (!$authResult['success']) {
                $this->response
                    ->json([
                        'error' => 'Authentication failed',
                        'message' => $authResult['error'],
                    ], 401)
                    ->send();
                return;
            }
        }
        
        // Route to appropriate handler
        switch ($method) {
            case 'GET':
                $this->handleGet($uri);
                break;
            case 'POST':
                $this->handlePost($uri);
                break;
            case 'PUT':
                $this->handlePut($uri);
                break;
            case 'PATCH':
                $this->handlePatch($uri);
                break;
            case 'DELETE':
                $this->handleDelete($uri);
                break;
            default:
                $this->response
                    ->json(['error' => 'Method not supported'], 405)
                    ->send();
        }
    }
    
    private function handleGet($uri)
    {
        $result = $this->dataHandler->retrieve($uri);
        
        if ($result['success']) {
            $this->response
                ->json(['data' => $result['data']])
                ->send();
        } else {
            $this->response
                ->json([
                    'error' => 'Not found',
                    'message' => $result['error'],
                ], 404)
                ->send();
        }
    }
    
    private function handlePost($uri)
    {
        $body = $this->request->getBody();
        $headers = $this->request->getHeaders();
        $contentType = $this->request->getHeader('Content-Type') ?? '';
        
        // Validate Content-Type header is present for POST requests
        if (empty($contentType)) {
            $this->response
                ->json([
                    'error' => 'Bad Request',
                    'message' => 'Content-Type header is required for POST requests',
                ], 400)
                ->send();
            return;
        }
        
        // Check upload size for file uploads
        $maxUploadSize = $this->getMaxUploadSize();
        
        // Check if this is a file upload
        if (strpos($contentType, 'multipart/form-data') !== false) {
            // Validate upload size
            $contentLength = $this->request->getHeader('Content-Length');
            if ($contentLength && (int)$contentLength > $maxUploadSize) {
                $this->response
                    ->json([
                        'error' => 'Payload Too Large',
                        'message' => sprintf('Upload size exceeds maximum allowed size of %d bytes', $maxUploadSize),
                    ], 413)
                    ->send();
                return;
            }
            
            $result = $this->fileHandler->handleMultipartUpload($maxUploadSize);
            $this->response
                ->json($result, $result['success'] ? 201 : 400)
                ->send();
            return;
        }
        
        // Check if this is base64 encoded file
        if (is_array($body) && isset($body['type']) && $body['type'] === 'base64') {
            $result = $this->fileHandler->handleBase64Upload($body, $maxUploadSize);
            $this->response
                ->json($result, $result['success'] ? 201 : 400)
                ->send();
            return;
        }
        
        // Validate JSON content type for regular data
        if (strpos($contentType, 'application/json') === false && 
            strpos($contentType, 'application/x-www-form-urlencoded') === false) {
            $this->response
                ->json([
                    'error' => 'Unsupported Media Type',
                    'message' => 'Content-Type must be application/json or application/x-www-form-urlencoded for data storage',
                ], 415)
                ->send();
            return;
        }
        
        // Store regular data
        $result = $this->dataHandler->store($uri, $body);
        
        if ($result['success']) {
            $this->response
                ->json([
                    'message' => 'Resource created',
                    'resource' => $result['resource'],
                ], 201)
                ->send();
        } else {
            $this->response
                ->json(['error' => 'Failed to create resource'], 500)
                ->send();
        }
    }
    
    private function handlePut($uri)
    {
        $body = $this->request->getBody();
        $headers = $this->request->getHeaders();
        $contentType = $this->request->getHeader('Content-Type') ?? '';
        
        // Validate Content-Type header is present
        if (empty($contentType)) {
            $this->response
                ->json([
                    'error' => 'Bad Request',
                    'message' => 'Content-Type header is required for PUT requests',
                ], 400)
                ->send();
            return;
        }
        
        // Validate Content-Length for binary uploads
        if (strpos($contentType, 'application/octet-stream') !== false ||
            strpos($contentType, 'image/') !== false ||
            strpos($contentType, 'video/') !== false ||
            strpos($contentType, 'audio/') !== false) {
            
            $contentLength = HeaderValidator::getHeader($headers, 'Content-Length');
            if ($contentLength) {
                $validation = HeaderValidator::validateContentLength(
                    $contentLength, 
                    $this->config['server']['max_upload_size']
                );
                
                if (!$validation['valid']) {
                    $this->response
                        ->json([
                            'error' => 'Bad Request',
                            'message' => $validation['error'],
                        ], 400)
                        ->send();
                    return;
                }
            }
            
            // Extract filename from URI
            $filename = basename($uri);
            $result = $this->fileHandler->handleRawUpload($filename, $maxUploadSize);
            
            $this->response
                ->json($result, $result['success'] ? 200 : 400)
                ->send();
            return;
        }
        
        // Validate JSON content type for regular data
        if (strpos($contentType, 'application/json') === false) {
            $this->response
                ->json([
                    'error' => 'Unsupported Media Type',
                    'message' => 'Content-Type must be application/json for data updates',
                ], 415)
                ->send();
            return;
        }
        
        // Update regular data
        if ($this->dataHandler->exists($uri)) {
            $result = $this->dataHandler->update($uri, $body);
            $statusCode = 200;
            $message = 'Resource updated';
        } else {
            $result = $this->dataHandler->store($uri, $body);
            $statusCode = 201;
            $message = 'Resource created';
        }
        
        if ($result['success']) {
            $this->response
                ->json([
                    'message' => $message,
                    'resource' => $result['resource'],
                ], $statusCode)
                ->send();
        } else {
            $this->response
                ->json(['error' => 'Failed to update resource'], 500)
                ->send();
        }
    }
    
    private function handlePatch($uri)
    {
        $body = $this->request->getBody();
        $contentType = $this->request->getHeader('Content-Type') ?? '';
        
        // Validate Content-Type header for PATCH
        if (empty($contentType)) {
            $this->response
                ->json([
                    'error' => 'Bad Request',
                    'message' => 'Content-Type header is required for PATCH requests',
                ], 400)
                ->send();
            return;
        }
        
        // Validate JSON content type
        if (strpos($contentType, 'application/json') === false) {
            $this->response
                ->json([
                    'error' => 'Unsupported Media Type',
                    'message' => 'Content-Type must be application/json for PATCH requests',
                ], 415)
                ->send();
            return;
        }
        
        if (!$this->dataHandler->exists($uri)) {
            $this->response
                ->json(['error' => 'Resource not found'], 404)
                ->send();
            return;
        }
        
        // Retrieve existing data
        $existing = $this->dataHandler->retrieve($uri);
        
        if (!$existing['success']) {
            $this->response
                ->json(['error' => 'Failed to retrieve resource'], 500)
                ->send();
            return;
        }
        
        // Merge data
        $merged = array_merge(
            is_array($existing['data']) ? $existing['data'] : [],
            is_array($body) ? $body : []
        );
        
        // Update with merged data
        $result = $this->dataHandler->update($uri, $merged);
        
        if ($result['success']) {
            $this->response
                ->json([
                    'message' => 'Resource patched',
                    'resource' => $result['resource'],
                ])
                ->send();
        } else {
            $this->response
                ->json(['error' => 'Failed to patch resource'], 500)
                ->send();
        }
    }
    
    private function handleDelete($uri)
    {
        $result = $this->dataHandler->delete($uri);
        
        if ($result['success']) {
            $this->response
                ->json([
                    'message' => 'Resource deleted',
                    'resource' => $result['resource'],
                ])
                ->send();
        } else {
            $this->response
                ->json([
                    'error' => 'Not found',
                    'message' => $result['error'],
                ], 404)
                ->send();
        }
    }
    
    private function handleFileUpload()
    {
        $method = $this->request->getMethod();
        $headers = $this->request->getHeaders();
        $uri = $this->request->getUri();
        $contentType = $this->request->getHeader('Content-Type') ?? '';
        $maxUploadSize = $this->getMaxUploadSize();
        
        // Check for TUS protocol
        if (isset($headers['Tus-Resumable'])) {
            // Validate TUS headers
            $validation = HeaderValidator::validateTusHeaders($headers, $method);
            if (!$validation['valid']) {
                $this->response
                    ->json([
                        'error' => 'Bad Request',
                        'message' => $validation['error'],
                    ], 400)
                    ->send();
                return;
            }
            
            $result = $this->fileHandler->handleTusUpload($method, $headers, $maxUploadSize);
            
            if (isset($result['headers'])) {
                $this->response->setHeaders($result['headers']);
            }
            
            $statusCode = 200;
            if ($method === 'POST' && $result['success']) {
                $statusCode = 201;
            }
            
            $this->response
                ->json($result, $statusCode)
                ->send();
            return;
        }
        
        // Regular file upload
        if ($method === 'POST') {
            // Validate Content-Type for POST uploads
            if (empty($contentType)) {
                $this->response
                    ->json([
                        'error' => 'Bad Request',
                        'message' => 'Content-Type header is required for file uploads',
                    ], 400)
                    ->send();
                return;
            }
            
            // Validate upload size
            $contentLength = $this->request->getHeader('Content-Length');
            if ($contentLength && (int)$contentLength > $maxUploadSize) {
                $this->response
                    ->json([
                        'error' => 'Payload Too Large',
                        'message' => sprintf('Upload size exceeds maximum allowed size of %d bytes', $maxUploadSize),
                    ], 413)
                    ->send();
                return;
            }
            
            // Check for base64 encoded upload (JSON with type: base64)
            if (strpos($contentType, 'application/json') !== false) {
                $body = $this->request->getBody();
                if (is_array($body) && isset($body['type']) && $body['type'] === 'base64') {
                    $result = $this->fileHandler->handleBase64Upload($body, $maxUploadSize);
                    $this->response
                        ->json($result, $result['success'] ? 201 : 400)
                        ->send();
                    return;
                }
            }
            
            if (strpos($contentType, 'multipart/form-data') !== false) {
                $result = $this->fileHandler->handleMultipartUpload($maxUploadSize);
            } else {
                $result = $this->fileHandler->handleRawUpload(null, $maxUploadSize);
            }
            
            $this->response
                ->json($result, $result['success'] ? 201 : 400)
                ->send();
        } elseif ($method === 'PUT') {
            // Validate Content-Type for PUT uploads
            if (empty($contentType)) {
                $this->response
                    ->json([
                        'error' => 'Bad Request',
                        'message' => 'Content-Type header is required for file uploads',
                    ], 400)
                    ->send();
                return;
            }
            
            // Validate Content-Length
            $contentLength = HeaderValidator::getHeader($headers, 'Content-Length');
            if ($contentLength) {
                $validation = HeaderValidator::validateContentLength(
                    $contentLength,
                    $maxUploadSize
                );
                
                if (!$validation['valid']) {
                    $this->response
                        ->json([
                            'error' => 'Bad Request',
                            'message' => $validation['error'],
                        ], 400)
                        ->send();
                    return;
                }
            }
            
            // Extract filename from URI
            $filename = basename($uri);
            $result = $this->fileHandler->handleRawUpload($filename, $maxUploadSize);
            
            $this->response
                ->json($result, $result['success'] ? 201 : 400)
                ->send();
        } else {
            $this->response
                ->json(['error' => 'Method not allowed'], 405)
                ->send();
        }
    }
    
    private function handleListFiles()
    {
        $files = $this->fileHandler->listUploads();
        $this->response
            ->json(['files' => $files])
            ->send();
    }
    
    private function handleListResources()
    {
        $resources = $this->dataHandler->listResources();
        $this->response
            ->json(['resources' => $resources])
            ->send();
    }
    
    private function handleLogin()
    {
        $contentType = $this->request->getHeader('Content-Type') ?? '';
        
        // Validate Content-Type
        if (empty($contentType) || strpos($contentType, 'application/json') === false) {
            $this->response
                ->json([
                    'error' => 'Bad Request',
                    'message' => 'Content-Type must be application/json',
                ], 400)
                ->send();
            return;
        }
        
        $body = $this->request->getBody();
        
        if (!isset($body['username']) || !isset($body['password'])) {
            $this->response
                ->json(['error' => 'Username and password required'], 400)
                ->send();
            return;
        }
        
        $username = $body['username'];
        $password = $body['password'];
        
        // Validate credentials
        $validUsers = $this->config['auth']['basic']['users'];
        
        if (isset($validUsers[$username]) && $validUsers[$username] === $password) {
            // Generate JWT token
            $token = $this->authHandler->generateJWT($username, [
                'name' => $username,
                'role' => 'user',
            ]);
            
            $this->response
                ->json([
                    'success' => true,
                    'message' => 'Login successful',
                    'token' => $token,
                    'user' => [
                        'username' => $username,
                        'role' => 'user',
                    ],
                ])
                ->send();
        } else {
            $this->response
                ->json(['error' => 'Invalid credentials'], 401)
                ->send();
        }
    }
    
    private function handleOAuthToken()
    {
        $contentType = $this->request->getHeader('Content-Type') ?? '';
        
        // Validate Content-Type
        if (empty($contentType) || strpos($contentType, 'application/json') === false) {
            $this->response
                ->json([
                    'error' => 'invalid_request',
                    'error_description' => 'Content-Type must be application/json',
                ], 400)
                ->send();
            return;
        }
        
        $body = $this->request->getBody();
        
        $clientId = $body['client_id'] ?? null;
        $clientSecret = $body['client_secret'] ?? null;
        $grantType = $body['grant_type'] ?? null;
        
        if ($grantType !== 'client_credentials') {
            $this->response
                ->json(['error' => 'unsupported_grant_type'], 400)
                ->send();
            return;
        }
        
        // Validate client credentials
        if ($clientId === $this->config['auth']['oauth2']['client_id'] &&
            $clientSecret === $this->config['auth']['oauth2']['client_secret']) {
            
            $token = $this->authHandler->generateJWT('oauth2-client', [
                'client_id' => $clientId,
                'scope' => 'read write',
            ]);
            
            $this->response
                ->json([
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => $this->config['auth']['jwt']['expiration'],
                ])
                ->send();
        } else {
            $this->response
                ->json(['error' => 'invalid_client'], 401)
                ->send();
        }
    }
    
    /**
     * Check rate limits
     */
    private function checkRateLimits($uri)
    {
        $ip = $this->getClientIp();
        
        // Check IP-based rate limit
        $ipLimit = $this->rateLimiter->checkIpLimit($ip);
        if (!$ipLimit['allowed']) {
            return $ipLimit;
        }
        
        // Check global rate limit
        $globalLimit = $this->rateLimiter->checkGlobalLimit();
        if (!$globalLimit['allowed']) {
            return $globalLimit;
        }
        
        // Check endpoint-specific rate limit
        $endpointLimit = $this->rateLimiter->checkEndpointLimit($ip, $uri);
        if (!$endpointLimit['allowed']) {
            return $endpointLimit;
        }
        
        return ['allowed' => true];
    }
    
    /**
     * Get client IP address
     */
    private function getClientIp()
    {
        // In production, prefer REMOTE_ADDR to prevent header spoofing
        if ($this->isProductionMode()) {
            return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }
        
        // In local mode, allow forwarded headers for development/testing
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Get first IP from comma-separated list
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }
    }
    
    /**
     * Check if in production mode
     */
    private function isProductionMode()
    {
        return ($this->config['environment']['mode'] ?? 'local') === 'production';
    }
    
    /**
     * Check API key from headers
     */
    private function checkApiKey($headers)
    {
        $apiKey = $headers['X-API-Key'] ?? $headers['X-Api-Key'] ?? null;
        
        if (!$apiKey) {
            return ['valid' => false];
        }
        
        return $this->apiKeyManager->validateKey($apiKey);
    }
    
    /**
     * Handle API key generation
     * Requires Google OAuth authentication
     */
    private function handleGenerateApiKey()
    {
        // Check if Google authentication is required
        $requireAuth = $this->config['auth']['production_api_keys']['require_authentication'] ?? false;
        
        if ($requireAuth) {
            // Verify Google authentication using a dedicated method
            $authResult = $this->verifyGoogleAuth();
            
            if (!$authResult['success']) {
                $this->response
                    ->json([
                        'error' => 'Unauthorized',
                        'message' => 'Google authentication is required to generate API keys. Please login with Google first.',
                        'auth_url' => '/auth/google',
                    ], 401)
                    ->send();
                return;
            }
            
            $authenticatedUser = $authResult['user'];
        } else {
            $authenticatedUser = 'anonymous';
        }
        
        $contentType = $this->request->getHeader('Content-Type') ?? '';
        
        // Validate Content-Type
        if (!empty($contentType) && strpos($contentType, 'application/json') === false) {
            $this->response
                ->json([
                    'error' => 'Bad Request',
                    'message' => 'Content-Type must be application/json',
                ], 400)
                ->send();
            return;
        }
        
        $body = $this->request->getBody();
        $metadata = is_array($body) ? ($body['metadata'] ?? []) : [];
        
        // Add authenticated user to metadata
        if ($requireAuth) {
            $metadata['generated_by'] = $authenticatedUser;
            $metadata['auth_method'] = 'google';
        }
        
        // Generate new API key
        $keyData = $this->apiKeyManager->generateKey($metadata);
        
        $this->response
            ->json([
                'success' => true,
                'message' => 'API key generated successfully',
                'api_key' => $keyData['key'],
                'created_at' => $keyData['created_at'],
                'generated_by' => $authenticatedUser,
                'usage_instructions' => 'Include this API key in the X-API-Key header for all requests',
            ], 201)
            ->send();
    }
    
    /**
     * Handle API key listing
     */
    private function handleListApiKeys()
    {
        // In production mode, require API key for listing
        if ($this->isProductionMode()) {
            $headers = $this->request->getHeaders();
            $apiKeyResult = $this->checkApiKey($headers);
            
            if (!$apiKeyResult['valid']) {
                $this->response
                    ->json([
                        'error' => 'Unauthorized',
                        'message' => 'API key is required to list API keys',
                    ], 401)
                    ->send();
                return;
            }
        }
        
        $keys = $this->apiKeyManager->listKeys();
        
        // Mask API keys for security (show only first and last 4 characters)
        $maskedKeys = array_map(function($key) {
            $keyStr = $key['key'];
            $masked = substr($keyStr, 0, 7) . '...' . substr($keyStr, -4);
            $key['key_masked'] = $masked;
            unset($key['key']); // Don't expose full key
            return $key;
        }, $keys);
        
        $this->response
            ->json([
                'success' => true,
                'keys' => $maskedKeys,
                'total' => count($maskedKeys),
                'note' => 'Keys are masked for security. Use the full key provided during generation.',
            ])
            ->send();
    }
    
    /**
     * Get maximum upload size based on environment
     */
    private function getMaxUploadSize()
    {
        if ($this->isProductionMode()) {
            return $this->config['server']['production_max_upload_size'] ?? (1 * 1024); // 1KB default
        }
        return $this->config['server']['max_upload_size'] ?? (50 * 1024 * 1024); // 50MB default
    }
    
    /**
     * Verify Google authentication for API key generation
     * Returns authentication result without modifying global auth state
     */
    private function verifyGoogleAuth()
    {
        $headers = $this->request->getHeaders();
        
        // Create a temporary auth handler with Google method
        $tempAuthHandler = new AuthHandler($this->config);
        $tempAuthHandler->setAuthMethod('google');
        
        return $tempAuthHandler->authenticate($headers);
    }
    
    /**
     * Check if the client prefers JSON response
     * @return bool
     */
    private function isJsonRequest()
    {
        $acceptHeader = $this->request->getHeader('Accept') ?? '';
        return strpos($acceptHeader, 'application/json') !== false;
    }
    
    /**
     * Serve static HTML file
     */
    private function serveStaticFile($path)
    {
        $fullPath = __DIR__ . '/../' . $path;
        
        if (!file_exists($fullPath)) {
            $this->response
                ->json([
                    'error' => 'Not Found',
                    'message' => 'The requested page does not exist',
                ], 404)
                ->send();
            return;
        }
        
        header('Content-Type: text/html; charset=UTF-8');
        readfile($fullPath);
        exit;
    }
    
    /**
     * Handle Google OAuth authentication start
     */
    private function handleGoogleAuthStart()
    {
        $authUrl = $this->authHandler->getGoogleAuthUrl();
        
        if (!$authUrl) {
            $this->response
                ->json([
                    'error' => 'Configuration Error',
                    'message' => 'Google OAuth is not configured. Please set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET environment variables.',
                ], 500)
                ->send();
            return;
        }
        
        // Redirect to Google OAuth
        header('Location: ' . $authUrl);
        exit;
    }
    
    /**
     * Handle Google OAuth callback
     */
    private function handleGoogleAuthCallback()
    {
        $code = $_GET['code'] ?? null;
        $state = $_GET['state'] ?? null;
        $error = $_GET['error'] ?? null;
        
        if ($error) {
            if ($this->isJsonRequest()) {
                $this->response
                    ->json([
                        'error' => 'Authentication Failed',
                        'message' => 'Google authentication was cancelled or failed: ' . $error,
                    ], 400)
                    ->send();
                return;
            }
            
            // Redirect to login page with error
            header('Location: /auth/login?error=' . urlencode('Google authentication was cancelled or failed: ' . $error));
            exit;
        }
        
        if (!$code || !$state) {
            if ($this->isJsonRequest()) {
                $this->response
                    ->json([
                        'error' => 'Bad Request',
                        'message' => 'Missing code or state parameter',
                    ], 400)
                    ->send();
                return;
            }
            
            header('Location: /auth/login?error=' . urlencode('Missing authentication parameters'));
            exit;
        }
        
        $result = $this->authHandler->handleGoogleCallback($code, $state);
        
        if ($result['success']) {
            if ($this->isJsonRequest()) {
                $this->response
                    ->json([
                        'success' => true,
                        'message' => 'Google authentication successful',
                        'user' => [
                            'email' => $result['user']['email'] ?? null,
                            'name' => $result['user']['name'] ?? null,
                            'picture' => $result['user']['picture'] ?? null,
                        ],
                        'token' => $result['token'],
                        'usage' => 'Use this token in the Authorization header as "Bearer <token>" for API requests',
                    ])
                    ->send();
            } else {
                // Redirect to success page with token and user info
                $params = [
                    'token' => $result['token'],
                    'email' => $result['user']['email'] ?? '',
                    'name' => $result['user']['name'] ?? '',
                    'picture' => $result['user']['picture'] ?? '',
                ];
                
                header('Location: /auth/success?' . http_build_query($params));
                exit;
            }
        } else {
            if ($this->isJsonRequest()) {
                $this->response
                    ->json([
                        'error' => 'Authentication Failed',
                        'message' => $result['error'],
                    ], 401)
                    ->send();
            } else {
                header('Location: /auth/login?error=' . urlencode($result['error']));
                exit;
            }
        }
    }
    
    /**
     * Handle Google logout
     */
    private function handleGoogleLogout()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Clear Google authentication from session
        unset($_SESSION['google_authenticated']);
        unset($_SESSION['google_email']);
        unset($_SESSION['google_name']);
        unset($_SESSION['google_picture']);
        unset($_SESSION['google_id']);
        
        $this->response
            ->json([
                'success' => true,
                'message' => 'Logged out successfully',
            ])
            ->send();
    }
}
