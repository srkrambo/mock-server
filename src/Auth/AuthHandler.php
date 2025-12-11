<?php

namespace MockServer\Auth;

class AuthHandler
{
    private $config;
    private $method;
    
    public function __construct($config)
    {
        $this->config = $config;
        $this->method = $config['auth']['default_method'] ?? 'none';
    }
    
    public function authenticate($headers, $requestMethod = null)
    {
        if (!$this->config['auth']['enabled'] || $this->method === 'none') {
            return ['success' => true, 'user' => 'anonymous'];
        }
        
        switch ($this->method) {
            case 'basic':
                return $this->basicAuth($headers);
            case 'api_key':
                return $this->apiKeyAuth($headers);
            case 'jwt':
                return $this->jwtAuth($headers);
            case 'oauth2':
                return $this->oauth2Auth($headers);
            case 'mtls':
                return $this->mtlsAuth();
            case 'openid':
                return $this->openidAuth($headers);
            default:
                return ['success' => true, 'user' => 'anonymous'];
        }
    }
    
    private function basicAuth($headers)
    {
        if (!isset($headers['Authorization'])) {
            return ['success' => false, 'error' => 'Authorization header missing'];
        }
        
        $auth = $headers['Authorization'];
        if (!preg_match('/^Basic\s+(.+)$/i', $auth, $matches)) {
            return ['success' => false, 'error' => 'Invalid Basic Auth format. Expected: Basic <base64-credentials>'];
        }
        
        if (empty($matches[1])) {
            return ['success' => false, 'error' => 'Empty credentials in Authorization header'];
        }
        
        $credentials = base64_decode($matches[1]);
        list($username, $password) = explode(':', $credentials, 2);
        
        $validUsers = $this->config['auth']['basic']['users'];
        if (isset($validUsers[$username]) && $validUsers[$username] === $password) {
            return ['success' => true, 'user' => $username];
        }
        
        return ['success' => false, 'error' => 'Invalid credentials'];
    }
    
    private function apiKeyAuth($headers)
    {
        $apiKey = $headers['X-API-Key'] ?? $headers['X-Api-Key'] ?? null;
        
        if (!$apiKey) {
            return ['success' => false, 'error' => 'API key missing'];
        }
        
        $validKeys = $this->config['auth']['api_keys']['valid_keys'];
        if (in_array($apiKey, $validKeys)) {
            return ['success' => true, 'user' => 'api-user', 'key' => $apiKey];
        }
        
        return ['success' => false, 'error' => 'Invalid API key'];
    }
    
    private function jwtAuth($headers)
    {
        if (!isset($headers['Authorization'])) {
            return ['success' => false, 'error' => 'Authorization header missing'];
        }
        
        $auth = $headers['Authorization'];
        if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $matches)) {
            return ['success' => false, 'error' => 'Invalid Bearer token format. Expected: Bearer <token>'];
        }
        
        if (empty($matches[1])) {
            return ['success' => false, 'error' => 'Empty token in Authorization header'];
        }
        
        $token = $matches[1];
        $decoded = $this->decodeJWT($token);
        
        if ($decoded) {
            return ['success' => true, 'user' => $decoded['sub'] ?? 'jwt-user', 'token_data' => $decoded];
        }
        
        return ['success' => false, 'error' => 'Invalid or expired JWT token'];
    }
    
    private function oauth2Auth($headers)
    {
        if (!isset($headers['Authorization'])) {
            return ['success' => false, 'error' => 'Authorization header missing'];
        }
        
        $auth = $headers['Authorization'];
        if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $matches)) {
            return ['success' => false, 'error' => 'Invalid Bearer token format'];
        }
        
        // Mock OAuth2 token validation
        $token = $matches[1];
        if (strlen($token) > 10) { // Simple mock validation
            return ['success' => true, 'user' => 'oauth2-user', 'token' => $token];
        }
        
        return ['success' => false, 'error' => 'Invalid OAuth2 token'];
    }
    
    private function mtlsAuth()
    {
        // Check for client certificate
        if (isset($_SERVER['SSL_CLIENT_CERT']) && !empty($_SERVER['SSL_CLIENT_CERT'])) {
            $cert = $_SERVER['SSL_CLIENT_CERT'];
            $clientDN = $_SERVER['SSL_CLIENT_S_DN'] ?? 'unknown';
            
            return ['success' => true, 'user' => 'mtls-user', 'cert_dn' => $clientDN];
        }
        
        return ['success' => false, 'error' => 'Client certificate required'];
    }
    
    private function openidAuth($headers)
    {
        if (!isset($headers['Authorization'])) {
            return ['success' => false, 'error' => 'Authorization header missing'];
        }
        
        $auth = $headers['Authorization'];
        if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $matches)) {
            return ['success' => false, 'error' => 'Invalid Bearer token format'];
        }
        
        $token = $matches[1];
        $decoded = $this->decodeJWT($token);
        
        if ($decoded && isset($decoded['iss'])) {
            return ['success' => true, 'user' => $decoded['sub'] ?? 'openid-user', 'token_data' => $decoded];
        }
        
        return ['success' => false, 'error' => 'Invalid OpenID Connect token'];
    }
    
    private function decodeJWT($token)
    {
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return null;
            }
            
            list($header, $payload, $signature) = $parts;
            
            // Decode payload
            $payloadData = json_decode($this->base64UrlDecode($payload), true);
            
            // Check expiration
            if (isset($payloadData['exp']) && $payloadData['exp'] < time()) {
                return null;
            }
            
            // In production, verify signature here
            return $payloadData;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    private function base64UrlDecode($data)
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
    
    public function generateJWT($userId, $additionalClaims = [])
    {
        $header = ['typ' => 'JWT', 'alg' => $this->config['auth']['jwt']['algorithm']];
        $payload = array_merge([
            'sub' => $userId,
            'iat' => time(),
            'exp' => time() + $this->config['auth']['jwt']['expiration'],
            'iss' => $this->config['openid']['issuer'] ?? 'mock-server',
        ], $additionalClaims);
        
        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));
        
        $signature = $this->base64UrlEncode(
            hash_hmac('sha256', "$headerEncoded.$payloadEncoded", 
                $this->config['auth']['jwt']['secret'], true)
        );
        
        return "$headerEncoded.$payloadEncoded.$signature";
    }
    
    private function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    public function setAuthMethod($method)
    {
        $this->method = $method;
    }
}
