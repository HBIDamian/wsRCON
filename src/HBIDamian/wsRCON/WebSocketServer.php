<?php
declare(strict_types=1);

namespace HBIDamian\wsRCON;

use pocketmine\utils\TextFormat;

class WebSocketServer {
    
    private const WEBSOCKET_MAGIC_STRING = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
    
    private Main $plugin;
    private mixed $socketServer = null;
    private bool $socketCreationAttempted = false;
    
    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }
    
    public function start(): void {
        $host = $this->plugin->getConfig()->get('websocket-host', '0.0.0.0');
        $port = $this->getWebSocketPortForStart();
        
        $this->socketCreationAttempted = true;
        $this->plugin->debugLog("=== Attempting to create socket server on {$host}:{$port} ===");
        
        $this->closeExistingSocket();
        
        if (!$this->createSocket()) {
            return;
        }
        
        if (!$this->bindSocket($host, $port)) {
            return;
        }
        
        if (!$this->listenOnSocket()) {
            return;
        }
        
        $this->plugin->getLogger()->info("WebSocket server started on {$host}:{$port}");
        $this->debugSocketState();
    }
    
    public function stop(): void {
        if ($this->socketServer && $this->isValidSocket($this->socketServer)) {
            socket_close($this->socketServer);
        }
        $this->socketServer = null;
    }
    
    public function getSocket(): mixed {
        return $this->socketServer;
    }
    
    public function isValid(): bool {
        if (!$this->socketServer) {
            $attemptedMsg = $this->socketCreationAttempted ? "attempted" : "not attempted";
            $this->plugin->debugLog("No valid socket server - socket is null (socket creation: {$attemptedMsg})");
            return false;
        }
        
        if (!$this->isValidSocket($this->socketServer)) {
            $type = gettype($this->socketServer);
            $class = is_object($this->socketServer) ? get_class($this->socketServer) : 'not an object';
            $this->plugin->debugLog("Socket server is not valid - type: {$type}, class: {$class}");
            return false;
        }
        
        return true;
    }
    
    public function acceptNewConnection(): mixed {
        $newSocket = @socket_accept($this->socketServer);
        if ($newSocket === false) {
            $error = socket_last_error($this->socketServer);
            $this->plugin->debugLog("socket_accept failed: " . socket_strerror($error) . " (code: {$error})");
            return false;
        }
        
        $this->plugin->getLogger()->info("New WebSocket connection attempt detected");
        socket_set_nonblock($newSocket);
        return $newSocket;
    }
    
    public function hasNewConnections(): bool {
        if (!$this->isValid()) {
            return false;
        }
        
        $read = [$this->socketServer];
        $write = null;
        $except = null;
        $ready = @socket_select($read, $write, $except, 0);
        
        if ($ready === false) {
            $error = socket_last_error();
            $this->plugin->debugLog("socket_select failed: " . socket_strerror($error) . " (code: {$error})");
            return false;
        }
        
        if ($ready > 0) {
            $this->plugin->debugLog("socket_select detected pending connection");
            return true;
        }
        
        return false;
    }
    
    public function performHandshake(mixed $socket, string $request): bool {
        $this->plugin->debugLog("=== Performing WebSocket handshake ===");
        $this->plugin->debugLog("Request length: " . strlen($request) . " bytes");
        $this->plugin->debugLog("Request headers:\n" . substr($request, 0, 500));
        
        $headers = $this->parseHttpHeaders($request);
        $this->plugin->debugLog("Parsed headers: " . json_encode($headers));
        
        if (!$this->validateWebSocketHeaders($headers)) {
            return false;
        }
        
        $acceptKey = $this->generateAcceptKey($headers['sec-websocket-key']);
        $response = $this->buildHandshakeResponse($acceptKey);
        
        return $this->sendHandshakeResponse($socket, $response);
    }
    
    public function isValidSocket(mixed $socket): bool {
        return SocketUtils::isValidSocket($socket);
    }
    
    public function getSocketPeerName(mixed $socket): string {
        return SocketUtils::getSocketPeerName($socket);
    }
    
    public function getWebSocketPort(): int {
        $configPort = $this->plugin->getConfig()->get('websocket-port', 'default');
        
        // If port is not set or is "default", use the server port
        if ($configPort === 'default' || $configPort === '' || $configPort === null) {
            $serverPort = $this->plugin->getServer()->getPort();
            $this->plugin->debugLog("Using server port for WebSocket: {$serverPort}");
            return $serverPort;
        }
        
        // Otherwise use the configured port
        $port = (int) $configPort;
        $this->plugin->debugLog("Using configured WebSocket port: {$port}");
        return $port;
    }

    private function getWebSocketPortForStart(): int {
        return $this->getWebSocketPort();
    }
    
    private function closeExistingSocket(): void {
        if ($this->socketServer && $this->isValidSocket($this->socketServer)) {
            $this->plugin->debugLog("Closing existing socket before creating new one");
            socket_close($this->socketServer);
            $this->socketServer = null;
        }
    }
    
    private function createSocket(): bool {
        $this->socketServer = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($this->socketServer === false) {
            $error = socket_last_error();
            $this->plugin->getLogger()->error("Failed to create WebSocket server socket: " . socket_strerror($error));
            $this->plugin->debugLog("socket_create failed with error: " . socket_strerror($error) . " (code: {$error})");
            $this->socketServer = null;
            return false;
        }
        
        $this->plugin->debugLog("Socket created successfully: " . (is_resource($this->socketServer) ? 'resource' : 'not resource'));
        socket_set_option($this->socketServer, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_set_nonblock($this->socketServer);
        $this->plugin->debugLog("Socket options set (reuse_addr, nonblock)");
        
        return true;
    }
    
    private function bindSocket(string $host, int $port): bool {
        if (!socket_bind($this->socketServer, $host, $port)) {
            $error = socket_last_error($this->socketServer);
            $this->plugin->getLogger()->error("Failed to bind WebSocket server to {$host}:{$port}: " . socket_strerror($error));
            $this->plugin->debugLog("socket_bind failed: " . socket_strerror($error) . " (code: {$error})");
            socket_close($this->socketServer);
            $this->socketServer = null;
            $this->plugin->debugLog("Socket closed and set to null due to bind failure");
            return false;
        }
        
        $this->plugin->debugLog("Socket bound successfully to {$host}:{$port}");
        return true;
    }
    
    private function listenOnSocket(): bool {
        if (!socket_listen($this->socketServer, 5)) {
            $error = socket_last_error($this->socketServer);
            $this->plugin->getLogger()->error("Failed to listen on WebSocket server: " . socket_strerror($error));
            $this->plugin->debugLog("socket_listen failed: " . socket_strerror($error) . " (code: {$error})");
            socket_close($this->socketServer);
            $this->socketServer = null;
            $this->plugin->debugLog("Socket closed and set to null due to listen failure");
            return false;
        }
        
        $this->plugin->debugLog("Socket listening with backlog of 5");
        return true;
    }
    
    private function debugSocketState(): void {
        $this->plugin->debugLog("Socket state after creation: " . ($this->socketServer ? "exists" : "null"));
        if ($this->socketServer) {
            $this->plugin->debugLog("Socket is resource after creation: " . (is_resource($this->socketServer) ? "yes" : "no"));
            $this->plugin->debugLog("Socket type after creation: " . gettype($this->socketServer));
            $this->plugin->debugLog("Socket server resource type: " . gettype($this->socketServer));
            $this->plugin->debugLog("Socket server is valid resource: " . (is_resource($this->socketServer) ? 'yes' : 'no'));
        }
    }
    
    private function parseHttpHeaders(string $request): array {
        $lines = explode("\r\n", $request);
        $headers = [];
        
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $headers[strtolower(trim($key))] = trim($value);
            }
        }
        
        return $headers;
    }
    
    private function validateWebSocketHeaders(array $headers): bool {
        if (!isset($headers['sec-websocket-key'])) {
            $this->plugin->debugLog("Missing sec-websocket-key header");
            return false;
        }
        
        if (!isset($headers['upgrade']) || strtolower($headers['upgrade']) !== 'websocket') {
            $this->plugin->debugLog("Missing or invalid upgrade header: " . ($headers['upgrade'] ?? 'not set'));
            return false;
        }
        
        return true;
    }
    
    private function generateAcceptKey(string $key): string {
        $acceptKey = base64_encode(pack('H*', sha1($key . self::WEBSOCKET_MAGIC_STRING)));
        $this->plugin->debugLog("WebSocket key: " . $key);
        $this->plugin->debugLog("Accept key: " . $acceptKey);
        return $acceptKey;
    }
    
    private function buildHandshakeResponse(string $acceptKey): string {
        return "HTTP/1.1 101 Switching Protocols\r\n" .
               "Upgrade: websocket\r\n" .
               "Connection: Upgrade\r\n" .
               "Sec-WebSocket-Accept: " . $acceptKey . "\r\n\r\n";
    }
    
    private function sendHandshakeResponse(mixed $socket, string $response): bool {
        $this->plugin->debugLog("Sending handshake response: " . strlen($response) . " bytes");
        $this->plugin->debugLog("Response:\n" . $response);
        
        $result = @socket_write($socket, $response);
        if ($result === false) {
            $error = socket_last_error($socket);
            $this->plugin->debugLog("Failed to send handshake response: " . socket_strerror($error));
            return false;
        }
        
        $this->plugin->debugLog("Handshake response sent successfully: " . $result . " bytes written");
        return true;
    }
    
    public function handleConnections(): void {
        if (!$this->isValid()) {
            return;
        }
        
        if ($this->hasNewConnections()) {
            $this->handleNewConnections();
        }
        
        $this->handleExistingConnections();
        $this->plugin->getConnectionManager()->cleanupOldConnections();
    }
    
    public function isRunning(): bool {
        return $this->socketServer !== null && $this->isValidSocket($this->socketServer);
    }
    
    private function handleNewConnections(): void {
        $newSocket = $this->acceptNewConnection();
        if ($newSocket !== false) {
            $connectionId = $this->plugin->getConnectionManager()->addConnection($newSocket);
            $this->plugin->debugLog("New connection added with ID: " . $connectionId);
        }
    }
    
    private function handleExistingConnections(): void {
        foreach ($this->plugin->getConnectionManager()->getAllConnections() as $id => $conn) {
            $data = $this->readFromSocket($conn['socket']);
            if ($data === false || $data === '') {
                continue;
            }

            $this->plugin->getConnectionManager()->appendToBuffer($id, $data);
            $this->plugin->getConnectionManager()->updateLastActivity($id);
            
            $buffer = $this->plugin->getConnectionManager()->getBuffer($id);
            
            if (!$conn['handshake_done']) {
                if (strpos($buffer, "\r\n\r\n") !== false) {
                    if ($this->performHandshake($conn['socket'], $buffer)) {
                        $this->plugin->getConnectionManager()->setHandshakeDone($id, true);
                        $this->plugin->getConnectionManager()->clearBuffer($id);
                        $this->plugin->getMessageHandler()->sendWelcomeMessage($conn['socket']);
                        $this->plugin->getMessageHandler()->sendAuthenticationStatus($conn['socket'], $id);
                    } else {
                        $this->plugin->getConnectionManager()->removeConnection($id);
                    }
                }
            } else {
                $this->processWebSocketFrames($id, $conn['socket']);
            }
        }
    }
    
    private function readFromSocket(mixed $socket): string|false {
        return @socket_read($socket, 4096);
    }
    
    private function processWebSocketFrames(int $connectionId, mixed $socket): void {
        $buffer = $this->plugin->getConnectionManager()->getBuffer($connectionId);
        
        while (strlen($buffer) > 0) {
            $message = $this->plugin->getFrameHandler()->decodeFrame($buffer);
            if ($message === null) {
                break;
            }
            
            $this->plugin->getMessageHandler()->handleWebSocketMessage(trim($message), $socket, $connectionId);
        }
        
        // Update the buffer in the connection manager
        $this->plugin->getConnectionManager()->updateConnection($connectionId, ['buffer' => $buffer]);
    }
}
