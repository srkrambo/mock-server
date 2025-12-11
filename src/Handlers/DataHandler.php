<?php

namespace MockServer\Handlers;

class DataHandler
{
    private $config;
    private $dataDir;
    
    public function __construct($config)
    {
        $this->config = $config;
        $this->dataDir = $config['storage']['data'];
        
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }
    }
    
    /**
     * Store data (JSON or raw)
     */
    public function store($resource, $data, $format = 'json')
    {
        $filename = $this->getFilename($resource);
        $filepath = $this->dataDir . '/' . $filename;
        
        if ($format === 'json') {
            $content = json_encode($data, JSON_PRETTY_PRINT);
        } else {
            $content = $data;
        }
        
        if (file_put_contents($filepath, $content) !== false) {
            return [
                'success' => true,
                'resource' => $resource,
                'filename' => $filename,
            ];
        }
        
        return ['success' => false, 'error' => 'Failed to store data'];
    }
    
    /**
     * Retrieve data
     */
    public function retrieve($resource)
    {
        $filename = $this->getFilename($resource);
        $filepath = $this->dataDir . '/' . $filename;
        
        if (!file_exists($filepath)) {
            return ['success' => false, 'error' => sprintf("Resource '%s' not found", $resource)];
        }
        
        $content = file_get_contents($filepath);
        
        // Try to decode as JSON
        $data = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return [
                'success' => true,
                'data' => $data,
                'format' => 'json',
            ];
        }
        
        // Return as raw data
        return [
            'success' => true,
            'data' => $content,
            'format' => 'raw',
        ];
    }
    
    /**
     * Update data
     */
    public function update($resource, $data, $format = 'json')
    {
        $filename = $this->getFilename($resource);
        $filepath = $this->dataDir . '/' . $filename;
        
        if (!file_exists($filepath)) {
            return ['success' => false, 'error' => 'Resource not found'];
        }
        
        return $this->store($resource, $data, $format);
    }
    
    /**
     * Delete data
     */
    public function delete($resource)
    {
        $filename = $this->getFilename($resource);
        $filepath = $this->dataDir . '/' . $filename;
        
        if (!file_exists($filepath)) {
            return ['success' => false, 'error' => 'Resource not found'];
        }
        
        if (unlink($filepath)) {
            return [
                'success' => true,
                'resource' => $resource,
                'message' => 'Resource deleted',
            ];
        }
        
        return ['success' => false, 'error' => 'Failed to delete resource'];
    }
    
    /**
     * List all resources
     */
    public function listResources()
    {
        $resources = [];
        $dir = opendir($this->dataDir);
        
        if ($dir === false) {
            return $resources; // Return empty array if directory cannot be opened
        }
        
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $filepath = $this->dataDir . '/' . $file;
            if (is_file($filepath)) {
                $resource = $this->getResourceFromFilename($file);
                $resources[] = [
                    'resource' => $resource,
                    'filename' => $file,
                    'size' => filesize($filepath),
                    'modified' => filemtime($filepath),
                ];
            }
        }
        
        closedir($dir);
        return $resources;
    }
    
    /**
     * Check if resource exists
     */
    public function exists($resource)
    {
        $filename = $this->getFilename($resource);
        $filepath = $this->dataDir . '/' . $filename;
        return file_exists($filepath);
    }
    
    private function getFilename($resource)
    {
        // Convert resource path to filename
        // e.g., /users/123 -> users_123.json
        $resource = trim($resource, '/');
        $resource = str_replace('/', '_', $resource);
        
        if (empty($resource)) {
            $resource = 'root';
        }
        
        return $resource . '.json';
    }
    
    private function getResourceFromFilename($filename)
    {
        // Convert filename back to resource path
        $resource = str_replace('.json', '', $filename);
        $resource = str_replace('_', '/', $resource);
        
        if ($resource === 'root') {
            return '/';
        }
        
        return '/' . $resource;
    }
}
