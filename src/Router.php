<?php

namespace MockServer;

use MockServer\Auth\AuthHandler;
use MockServer\Handlers\DataHandler;
use MockServer\Handlers\FileUploadHandler;
use MockServer\Utils\Request;
use MockServer\Utils\Response;

class Router
{
    private $config;
    private $authHandler;
    private $dataHandler;
    private $fileHandler;
    private $request;
    private $response;
    
    public function __construct($config)
    {
        $this->config = $config;
        $this->authHandler = new AuthHandler($config);
        $this->dataHandler = new DataHandler($config);
        $this->fileHandler = new FileUploadHandler($config);
        $this->request = new Request();
        $this->response = new Response();
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
        
        // Special routes
        if ($uri === '/login' && $method === 'POST') {
            return $this->handleLogin();
        }
        
        if ($uri === '/oauth/token' && $method === 'POST') {
            return $this->handleOAuthToken();
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
        
        // Authenticate request
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
        $contentType = $this->request->getHeader('Content-Type') ?? '';
        
        // Check if this is a file upload
        if (strpos($contentType, 'multipart/form-data') !== false) {
            $result = $this->fileHandler->handleMultipartUpload();
            $this->response
                ->json($result, $result['success'] ? 201 : 400)
                ->send();
            return;
        }
        
        // Check if this is base64 encoded file
        if (is_array($body) && isset($body['type']) && $body['type'] === 'base64') {
            $result = $this->fileHandler->handleBase64Upload($body);
            $this->response
                ->json($result, $result['success'] ? 201 : 400)
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
        $contentType = $this->request->getHeader('Content-Type') ?? '';
        
        // Check if this is a raw binary upload
        if (strpos($contentType, 'application/octet-stream') !== false ||
            strpos($contentType, 'image/') !== false ||
            strpos($contentType, 'video/') !== false ||
            strpos($contentType, 'audio/') !== false) {
            
            // Extract filename from URI
            $filename = basename($uri);
            $result = $this->fileHandler->handleRawUpload($filename);
            
            $this->response
                ->json($result, $result['success'] ? 200 : 400)
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
        
        // Check for TUS protocol
        if (isset($headers['Tus-Resumable'])) {
            $result = $this->fileHandler->handleTusUpload($method, $headers);
            
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
            $contentType = $this->request->getHeader('Content-Type') ?? '';
            
            if (strpos($contentType, 'multipart/form-data') !== false) {
                $result = $this->fileHandler->handleMultipartUpload();
            } else {
                $result = $this->fileHandler->handleRawUpload();
            }
            
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
}
