<?php

namespace MockServer\Utils;

class Request
{
    private $method;
    private $uri;
    private $headers;
    private $body;
    private $queryParams;
    
    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $this->headers = $this->getHeaders();
        $this->body = $this->getBody();
        $this->queryParams = $_GET;
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
            $this->body = json_decode($raw, true);
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
