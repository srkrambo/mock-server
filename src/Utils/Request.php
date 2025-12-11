<?php

namespace MockServer\Utils;

class Request
{
    private $method;
    private $uri;
    private $headers;
    private $body;
    private $queryParams;
    private $basePath;
    
    public function __construct($config = null)
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->basePath = $this->detectBasePath($config);
        $this->uri = $this->parseUri();
        $this->headers = $this->getHeaders();
        $this->body = $this->getBody();
        $this->queryParams = $_GET;
    }
    
    /**
     * Detect the base path of the application
     */
    private function detectBasePath($config)
    {
        // If base_path is explicitly set in config, use it
        if ($config && isset($config['server']['base_path'])) {
            if ($config['server']['base_path'] !== null) {
                return rtrim($config['server']['base_path'], '/');
            }
        }
        
        // Auto-detect based on SCRIPT_NAME
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        $basePath = str_replace('/index.php', '', $scriptName);
        
        // If running at root or with PHP built-in server, basePath will be empty or '/'
        return ($basePath === '/' || $basePath === '') ? '' : $basePath;
    }
    
    /**
     * Parse URI and strip base path
     */
    private function parseUri()
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        
        // Strip base path from URI
        if ($this->basePath !== '' && strpos($uri, $this->basePath) === 0) {
            $uri = substr($uri, strlen($this->basePath));
        }
        
        // Ensure URI starts with /
        if (empty($uri)) {
            $uri = '/';
        } elseif ($uri[0] !== '/') {
            $uri = '/' . $uri;
        }
        
        return $uri;
    }
    
    public function getMethod()
    {
        return $this->method;
    }
    
    public function getUri()
    {
        return $this->uri;
    }
    
    public function getHeaders()
    {
        if ($this->headers !== null) {
            return $this->headers;
        }
        
        $headers = [];
        
        foreach ($_SERVER as $key => $value) {
            if (substr($key, 0, 5) === 'HTTP_') {
                $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
                $headers[$header] = $value;
            }
        }
        
        // Also check for Content-Type and Content-Length
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
        }
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['Content-Length'] = $_SERVER['CONTENT_LENGTH'];
        }
        
        $this->headers = $headers;
        return $headers;
    }
    
    public function getHeader($name)
    {
        return $this->headers[$name] ?? null;
    }
    
    public function getBody()
    {
        if ($this->body !== null) {
            return $this->body;
        }
        
        $contentType = $this->getHeader('Content-Type') ?? '';
        
        if (strpos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw, true);
            // If JSON decode fails, keep raw content
            $this->body = ($decoded === null && json_last_error() !== JSON_ERROR_NONE) ? $raw : $decoded;
        } elseif (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
            $this->body = $_POST;
        } elseif (strpos($contentType, 'multipart/form-data') !== false) {
            $this->body = $_POST;
        } else {
            $this->body = file_get_contents('php://input');
        }
        
        return $this->body;
    }
    
    public function getQueryParams()
    {
        return $this->queryParams;
    }
    
    public function getQueryParam($name, $default = null)
    {
        return $this->queryParams[$name] ?? $default;
    }
    
    public function getFiles()
    {
        return $_FILES;
    }
}
