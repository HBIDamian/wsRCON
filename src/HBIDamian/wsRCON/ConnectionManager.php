<?php
declare(strict_types=1);

namespace HBIDamian\wsRCON;

class ConnectionManager {
    
    private const CONNECTION_TIMEOUT = 300;
    private const DEFAULT_MAX_CONNECTIONS = 10;
    
    private Main $plugin;
    private array $connections = [];
    
    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }
    
    public function addConnection(mixed $socket): int {
        $password = $this->plugin->getConfig()->get('websocket-password', '');
        $peerName = $this->plugin->getWebSocketServer()->getSocketPeerName($socket);
        $connectionId = is_resource($socket) ? (int)$socket : spl_object_id($socket);
        
        $this->connections[$connectionId] = [
            'socket' => $socket,
            'handshake_done' => false,
            'authenticated' => empty($password),
            'buffer' => '',
            'connected_at' => time(),
            'last_activity' => time()
        ];
        
        $this->plugin->getLogger()->info("WebSocket client connected from: {$peerName} (ID: " . $connectionId . ")");
        $this->plugin->debugLog("New connection attempt from: " . $peerName);
        
        return $connectionId;
    }
    
    public function removeConnection(int $id): void {
        if (isset($this->connections[$id])) {
            if ($this->plugin->getWebSocketServer()->isValidSocket($this->connections[$id]['socket'])) {
                socket_close($this->connections[$id]['socket']);
            }
            unset($this->connections[$id]);
        }
    }
    
    public function getConnection(int $id): ?array {
        return $this->connections[$id] ?? null;
    }
    
    public function updateConnection(int $id, array $data): void {
        if (isset($this->connections[$id])) {
            $this->connections[$id] = array_merge($this->connections[$id], $data);
        }
    }
    
    public function getAllConnections(): array {
        return $this->connections;
    }
    
    public function getConnectionCount(): int {
        return count($this->connections);
    }
    
    public function canAcceptNewConnection(): bool {
        $maxConnections = $this->plugin->getConfig()->get('max-connections', self::DEFAULT_MAX_CONNECTIONS);
        return count($this->connections) < $maxConnections;
    }
    
    public function getMaxConnections(): int {
        return $this->plugin->getConfig()->get('max-connections', self::DEFAULT_MAX_CONNECTIONS);
    }
    
    public function isAuthenticated(int $connectionId): bool {
        return isset($this->connections[$connectionId]) && 
               $this->connections[$connectionId]['authenticated'] === true;
    }
    
    public function setAuthenticated(int $connectionId, bool $authenticated): void {
        if (isset($this->connections[$connectionId])) {
            $this->connections[$connectionId]['authenticated'] = $authenticated;
        }
    }
    
    public function isHandshakeDone(int $connectionId): bool {
        return isset($this->connections[$connectionId]) && 
               $this->connections[$connectionId]['handshake_done'] === true;
    }
    
    public function setHandshakeDone(int $connectionId, bool $done): void {
        if (isset($this->connections[$connectionId])) {
            $this->connections[$connectionId]['handshake_done'] = $done;
        }
    }
    
    public function updateLastActivity(int $connectionId): void {
        if (isset($this->connections[$connectionId])) {
            $this->connections[$connectionId]['last_activity'] = time();
        }
    }
    
    public function appendToBuffer(int $connectionId, string $data): void {
        if (isset($this->connections[$connectionId])) {
            $this->connections[$connectionId]['buffer'] .= $data;
        }
    }
    
    public function getBuffer(int $connectionId): string {
        return $this->connections[$connectionId]['buffer'] ?? '';
    }
    
    public function clearBuffer(int $connectionId): void {
        if (isset($this->connections[$connectionId])) {
            $this->connections[$connectionId]['buffer'] = '';
        }
    }
    
    public function hasDataInSocket(mixed $socket): string|false|null {
        $read = [$socket];
        $write = null;
        $except = null;
        $ready = @socket_select($read, $write, $except, 0);
        
        if ($ready === false) {
            $error = socket_last_error($socket);
            $this->plugin->debugLog("socket_select failed for connection: " . socket_strerror($error));
            return null;
        }
        
        if ($ready === 0) {
            return false; // No data available, continue
        }
        
        $data = @socket_read($socket, 4096, PHP_BINARY_READ);
        if ($data === false) {
            $error = socket_last_error($socket);
            if ($error !== SOCKET_EWOULDBLOCK && $error !== SOCKET_EAGAIN && $error !== 0) {
                $this->plugin->debugLog("Socket read error {$error}: " . socket_strerror($error));
                return null;
            }
            return false; // Would block, continue
        }
        
        return $data;
    }
    
    public function cleanupOldConnections(): void {
        $now = time();
        
        foreach ($this->connections as $id => $conn) {
            if (($now - $conn['last_activity']) > self::CONNECTION_TIMEOUT) {
                $this->plugin->debugLog("Cleaning up inactive connection: " . $id);
                $this->removeConnection($id);
            }
        }
    }
    
    public function closeAllConnections(): void {
        foreach ($this->connections as $conn) {
            if ($this->plugin->getWebSocketServer()->isValidSocket($conn['socket'])) {
                socket_close($conn['socket']);
            }
        }
        
        $this->connections = [];
    }
    
    public function debugConnectionStatus(): void {
        $totalConnections = $this->getConnectionCount();
        $this->plugin->debugLog("Broadcasting to {$totalConnections} total connections:");
        foreach ($this->connections as $id => $conn) {
            $handshake = isset($conn['handshake_done']) ? ($conn['handshake_done'] ? 'YES' : 'NO') : 'UNKNOWN';
            $auth = isset($conn['authenticated']) ? ($conn['authenticated'] ? 'YES' : 'NO') : 'UNKNOWN';
            $this->plugin->debugLog("  Client {$id}: Handshake={$handshake}, Auth={$auth}");
        }
    }
    
    public function isConnectionReadyForBroadcast(array $conn): bool {
        return $conn['handshake_done'] && $conn['authenticated'];
    }
}
