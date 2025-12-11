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
            case 'google':
                return $this->googleAuth($headers);
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
        
        $credentials = base64_decode($matches[1], true);
        
        if ($credentials === false) {
            return ['success' => false, 'error' => 'Invalid base64 encoding in Authorization header'];
        }
        
        $parts = explode(':', $credentials, 2);
        
        if (count($parts) !== 2) {
            return ['success' => false, 'error' => 'Invalid credentials format. Expected username:password'];
        }
        
        list($username, $password) = $parts;
        
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
    
    /**
     * Google OAuth authentication
     * Validates Google OAuth token from session or Authorization header
     */
    private function googleAuth($headers)
    {
        // Check for Bearer token in Authorization header
        if (isset($headers['Authorization'])) {
            $auth = $headers['Authorization'];
            if (preg_match('/^Bearer\s+(.+)$/i', $auth, $matches)) {
                $token = $matches[1];
                
                // Verify the JWT token contains Google issuer
                $decoded = $this->decodeJWT($token);
                if ($decoded && isset($decoded['iss']) && 
                    (strpos($decoded['iss'], 'accounts.google.com') !== false || 
                     strpos($decoded['iss'], 'mock-server') !== false)) {
                    return [
                        'success' => true, 
                        'user' => $decoded['email'] ?? $decoded['sub'] ?? 'google-user',
                        'token_data' => $decoded
                    ];
                }
            }
        }
        
        // Check session for Google authentication
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['google_authenticated']) && $_SESSION['google_authenticated'] === true) {
            return [
                'success' => true,
                'user' => $_SESSION['google_email'] ?? 'google-user',
                'user_data' => [
                    'email' => $_SESSION['google_email'] ?? null,
                    'name' => $_SESSION['google_name'] ?? null,
                    'picture' => $_SESSION['google_picture'] ?? null,
                ]
            ];
        }
        
        return ['success' => false, 'error' => 'Google authentication required. Please login with Google first.'];
    }
    
    /**
     * Generate Google OAuth authorization URL
     */
    public function getGoogleAuthUrl($state = null)
    {
        $config = $this->config['auth']['google'];
        
        if (empty($config['client_id'])) {
            return null;
        }
        
        $state = $state ?? bin2hex(random_bytes(16));
        
        // Store state in session for verification
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['google_oauth_state'] = $state;
        
        $params = [
            'client_id' => $config['client_id'],
            'redirect_uri' => $config['redirect_uri'],
            'response_type' => 'code',
            'scope' => implode(' ', $config['scopes']),
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent',
        ];
        
        return $config['auth_uri'] . '?' . http_build_query($params);
    }
    
    /**
     * Handle Google OAuth callback
     */
    public function handleGoogleCallback($code, $state)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Verify state to prevent CSRF
        if (!isset($_SESSION['google_oauth_state']) || $_SESSION['google_oauth_state'] !== $state) {
            return ['success' => false, 'error' => 'Invalid state parameter'];
        }
        
        unset($_SESSION['google_oauth_state']);
        
        $config = $this->config['auth']['google'];
        
        // Exchange code for access token
        $tokenData = $this->exchangeGoogleCode($code, $config);
        if (!$tokenData) {
            return ['success' => false, 'error' => 'Failed to exchange authorization code'];
        }
        
        // Get user info
        $userInfo = $this->getGoogleUserInfo($tokenData['access_token'], $config);
        if (!$userInfo) {
            return ['success' => false, 'error' => 'Failed to get user information'];
        }
        
        // Store in session
        $_SESSION['google_authenticated'] = true;
        $_SESSION['google_email'] = $userInfo['email'] ?? null;
        $_SESSION['google_name'] = $userInfo['name'] ?? null;
        $_SESSION['google_picture'] = $userInfo['picture'] ?? null;
        $_SESSION['google_id'] = $userInfo['id'] ?? null;
        
        // Generate JWT token for API usage
        $jwt = $this->generateJWT($userInfo['email'] ?? $userInfo['id'], [
            'email' => $userInfo['email'] ?? null,
            'name' => $userInfo['name'] ?? null,
            'picture' => $userInfo['picture'] ?? null,
            'iss' => 'accounts.google.com',
        ]);
        
        return [
            'success' => true,
            'user' => $userInfo,
            'token' => $jwt,
        ];
    }
    
    /**
     * Exchange authorization code for access token
     */
    private function exchangeGoogleCode($code, $config)
    {
        $data = [
            'code' => $code,
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'redirect_uri' => $config['redirect_uri'],
            'grant_type' => 'authorization_code',
        ];
        
        $ch = curl_init($config['token_uri']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return null;
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Get Google user information
     */
    private function getGoogleUserInfo($accessToken, $config)
    {
        $ch = curl_init($config['user_info_uri']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return null;
        }
        
        return json_decode($response, true);
    }
}
