<?php

namespace MockServer\Auth;

/**
 * API Key Manager for production use
 * Manages API keys stored in file system (no database required)
 */
class ApiKeyManager
{
    private $config;
    private $storageDir;
    private $keysFile;
    
    public function __construct($config)
    {
        $this->config = $config;
        $this->storageDir = $config['storage']['api_keys'] ?? '/tmp/api_keys';
        $this->keysFile = $this->storageDir . '/keys.json';
        
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
        
        // Initialize keys file if it doesn't exist
        if (!file_exists($this->keysFile)) {
            file_put_contents($this->keysFile, json_encode([]));
        }
    }
    
    /**
     * Generate a new API key
     * 
     * @param array $metadata Optional metadata (description, user info, etc.)
     * @return array ['key' => string, 'created_at' => timestamp]
     */
    public function generateKey($metadata = [])
    {
        // Check if a user identifier is provided in metadata
        $userId = $metadata['generated_by'] ?? null;
        
        // If user is identified, revoke any existing active keys for this user
        if ($userId) {
            $this->revokeUserKeys($userId);
        }
        
        $key = 'mk_' . bin2hex(random_bytes(32)); // mk_ prefix for "mock key"
        
        $keyData = [
            'key' => $key,
            'created_at' => time(),
            'metadata' => $metadata,
            'active' => true,
            'last_used' => null,
            'usage_count' => 0,
        ];
        
        $this->saveKey($keyData);
        
        return $keyData;
    }
    
    /**
     * Validate an API key
     * 
     * @param string $key API key to validate
     * @return array ['valid' => bool, 'key_data' => array|null]
     */
    public function validateKey($key)
    {
        // For local development, check static keys first
        if ($this->isLocalEnvironment()) {
            $validKeys = $this->config['auth']['api_keys']['valid_keys'] ?? [];
            if (in_array($key, $validKeys)) {
                return [
                    'valid' => true,
                    'key_data' => [
                        'key' => $key,
                        'type' => 'local',
                    ],
                ];
            }
        }
        
        // Check stored keys
        $keys = $this->getAllKeys();
        
        if (isset($keys[$key]) && $keys[$key]['active']) {
            // Update last used time and usage count
            $keys[$key]['last_used'] = time();
            $keys[$key]['usage_count']++;
            $this->saveAllKeys($keys);
            
            return [
                'valid' => true,
                'key_data' => $keys[$key],
            ];
        }
        
        return [
            'valid' => false,
            'key_data' => null,
        ];
    }
    
    /**
     * Revoke an API key
     */
    public function revokeKey($key)
    {
        $keys = $this->getAllKeys();
        
        if (isset($keys[$key])) {
            $keys[$key]['active'] = false;
            $keys[$key]['revoked_at'] = time();
            $this->saveAllKeys($keys);
            return true;
        }
        
        return false;
    }
    
    /**
     * Revoke all active API keys for a specific user
     * @param string $userId User identifier from metadata
     * @return int Number of keys revoked
     */
    private function revokeUserKeys($userId)
    {
        $keys = $this->getAllKeys();
        $revokedCount = 0;
        
        foreach ($keys as $key => $data) {
            // Check if this key belongs to the user and is active
            if ($data['active'] && 
                isset($data['metadata']['generated_by']) && 
                $data['metadata']['generated_by'] === $userId) {
                $keys[$key]['active'] = false;
                $keys[$key]['revoked_at'] = time();
                $keys[$key]['revoked_reason'] = 'Replaced by new key';
                $revokedCount++;
            }
        }
        
        if ($revokedCount > 0) {
            $this->saveAllKeys($keys);
        }
        
        return $revokedCount;
    }
    
    /**
     * List all API keys
     */
    public function listKeys($includeInactive = false)
    {
        $keys = $this->getAllKeys();
        $result = [];
        
        foreach ($keys as $key => $data) {
            if ($includeInactive || $data['active']) {
                $result[] = [
                    'key' => $key,
                    'created_at' => $data['created_at'],
                    'active' => $data['active'],
                    'last_used' => $data['last_used'],
                    'usage_count' => $data['usage_count'],
                    'metadata' => $data['metadata'] ?? [],
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * Save a new key
     */
    private function saveKey($keyData)
    {
        $keys = $this->getAllKeys();
        $keys[$keyData['key']] = $keyData;
        $this->saveAllKeys($keys);
    }
    
    /**
     * Get all keys from storage
     */
    private function getAllKeys()
    {
        if (!file_exists($this->keysFile)) {
            return [];
        }
        
        $content = file_get_contents($this->keysFile);
        $keys = json_decode($content, true);
        
        return is_array($keys) ? $keys : [];
    }
    
    /**
     * Save all keys to storage
     */
    private function saveAllKeys($keys)
    {
        file_put_contents($this->keysFile, json_encode($keys, JSON_PRETTY_PRINT), LOCK_EX);
    }
    
    /**
     * Check if we're in local environment
     */
    private function isLocalEnvironment()
    {
        $mode = $this->config['environment']['mode'] ?? 'local';
        return $mode === 'local';
    }
    
    /**
     * Check if we're in production environment
     */
    public function isProductionEnvironment()
    {
        $mode = $this->config['environment']['mode'] ?? 'local';
        return $mode === 'production';
    }
}
