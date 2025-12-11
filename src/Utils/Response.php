<?php

namespace MockServer\Utils;

class Response
{
    private $statusCode = 200;
    private $headers = [];
    private $body = null;
    
    public function setStatusCode($code)
    {
        $this->statusCode = $code;
        return $this;
    }
    
    public function setHeader($name, $value)
    {
        $this->headers[$name] = $value;
        return $this;
    }
    
    public function setHeaders($headers)
    {
        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }
        return $this;
    }
    
    public function json($data, $statusCode = null)
    {
        if ($statusCode !== null) {
            $this->statusCode = $statusCode;
        }
        
        $this->setHeader('Content-Type', 'application/json');
        $this->body = json_encode($data, JSON_PRETTY_PRINT);
        return $this;
    }
    
    public function text($text, $statusCode = null)
    {
        if ($statusCode !== null) {
            $this->statusCode = $statusCode;
        }
        
        $this->setHeader('Content-Type', 'text/plain');
        $this->body = $text;
        return $this;
    }
    
    public function html($html, $statusCode = null)
    {
        if ($statusCode !== null) {
            $this->statusCode = $statusCode;
        }
        
        $this->setHeader('Content-Type', 'text/html');
        $this->body = $html;
        return $this;
    }
    
    public function send()
    {
        http_response_code($this->statusCode);
        
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }
        
        if ($this->body !== null) {
            echo $this->body;
        }
    }
    
    public function cors($config)
    {
        if (!$config['cors']['enabled']) {
            return $this;
        }
        
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
        
        if (in_array('*', $config['cors']['origins']) || in_array($origin, $config['cors']['origins'])) {
            $this->setHeader('Access-Control-Allow-Origin', $origin);
        }
        
        $this->setHeader('Access-Control-Allow-Methods', implode(', ', $config['cors']['methods']));
        $this->setHeader('Access-Control-Allow-Headers', implode(', ', $config['cors']['headers']));
        $this->setHeader('Access-Control-Max-Age', '86400');
        
        return $this;
    }
}
