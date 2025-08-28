<?php
declare(strict_types=1);

namespace HBIDamian\wsRCON;

use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat;

class MessageHandler {
    
    private Main $plugin;
    private FrameHandler $frameHandler;
    
    public function __construct(Main $plugin, FrameHandler $frameHandler) {
        $this->plugin = $plugin;
        $this->frameHandler = $frameHandler;
    }
    
    public function handleWebSocketMessage(string $rawMessage, mixed $socket, int $connectionId): void {
        $this->plugin->debugLog("Received message from {$connectionId}: " . substr($rawMessage, 0, 100));
        
        // Try to parse JSON message for authentication and commands
        $messageData = @json_decode($rawMessage, true);
        
        if ($messageData && is_array($messageData)) {
            // Handle JSON formatted messages
            $type = $messageData['type'] ?? 'unknown';
            
            switch ($type) {
                case 'auth':
                    $this->handleAuthentication($messageData, $socket, $connectionId);
                    break;
                    
                case 'command':
                    $this->handleAuthenticatedCommand($messageData, $socket, $connectionId);
                    break;
                    
                default:
                    $this->frameHandler->sendMessage($socket, "Unknown message type: " . $type);
            }
        } else {
            // Handle plain text commands (for backwards compatibility)
            if ($this->plugin->getConnectionManager()->isAuthenticated($connectionId)) {
                $this->handleCommand($rawMessage, $socket);
            } else {
                $this->frameHandler->sendMessage($socket, "Authentication required. Please authenticate first.");
            }
        }
    }
    
    public function handleAuthentication(array $messageData, mixed $socket, int $connectionId): void {
        $providedPassword = $messageData['password'] ?? '';
        $configPassword = $this->plugin->getConfig()->get('websocket-password', '');
        
        if (empty($configPassword)) {
            // No password required
            $this->plugin->getConnectionManager()->setAuthenticated($connectionId, true);
            $this->frameHandler->sendMessage($socket, "Authentication not required - access granted.");
            return;
        }
        
        if ($providedPassword === $configPassword) {
            $this->plugin->getConnectionManager()->setAuthenticated($connectionId, true);
            $this->frameHandler->sendMessage($socket, "Authentication successful! Console access granted.");
            $this->plugin->getLogger()->info("WebSocket client authenticated: " . $connectionId);
        } else {
            $this->frameHandler->sendMessage($socket, "Authentication failed - incorrect password.");
            $this->plugin->getLogger()->warning("WebSocket authentication failed for client: " . $connectionId);
        }
    }
    
    public function handleAuthenticatedCommand(array $messageData, mixed $socket, int $connectionId): void {
        if (!$this->plugin->getConnectionManager()->isAuthenticated($connectionId)) {
            // Check if password is provided with command
            $providedPassword = $messageData['password'] ?? '';
            $configPassword = $this->plugin->getConfig()->get('websocket-password', '');
            
            if (!empty($configPassword) && $providedPassword !== $configPassword) {
                $this->frameHandler->sendMessage($socket, "Authentication required or password incorrect.");
                return;
            }
            
            // Auto-authenticate if password matches
            if (!empty($configPassword) && $providedPassword === $configPassword) {
                $this->plugin->getConnectionManager()->setAuthenticated($connectionId, true);
                $this->frameHandler->sendMessage($socket, "Auto-authenticated with command.");
            }
        }
        
        $command = $messageData['command'] ?? '';
        if (!empty($command)) {
            $this->handleCommand($command, $socket);
        }
    }
    
    public function handleCommand(string $command, mixed $socket): void {
        $this->plugin->getLogger()->info("WebSocket command: " . $command);
        
        // Broadcast the command being executed to all clients
        $this->plugin->broadcast("> " . $command);
        
        // Execute on next tick to avoid threading issues
        $this->plugin->getScheduler()->scheduleDelayedTask(
            new ClosureTask(function () use ($command, $socket): void {
                $sender = new WebSocketCommandSender($this->plugin, $socket, $this->frameHandler);
                $success = $this->plugin->getServer()->getCommandMap()->dispatch($sender, $command);
                
                if (!$success) {
                    // Broadcast error message to all clients
                    $this->plugin->broadcast("Unknown command: " . $command);
                    $this->plugin->broadcast("Type 'help' to see available commands.");
                    
                    // Also send direct response to command sender
                    $errorResponse = json_encode([
                        'type' => 'response',
                        'message' => "Unknown command: " . $command,
                        'timestamp' => date('H:i:s')
                    ]);
                    $this->frameHandler->sendMessage($socket, $errorResponse);
                    
                    $helpResponse = json_encode([
                        'type' => 'response', 
                        'message' => "Type 'help' to see available commands.",
                        'timestamp' => date('H:i:s')
                    ]);
                    $this->frameHandler->sendMessage($socket, $helpResponse);
                }
            }),
            1
        );
    }
    
    public function sendWelcomeMessage(mixed $socket): void {
        $serverName = $this->plugin->getServer()->getName();
        $version = $this->plugin->getServer()->getVersion();
        $playerCount = count($this->plugin->getServer()->getOnlinePlayers());
        $maxPlayers = $this->plugin->getServer()->getMaxPlayers();
        
        $this->frameHandler->sendMessage($socket, "===== PocketMine WebSocket Console =====");
        $this->frameHandler->sendMessage($socket, "Server: " . $serverName);
        $this->frameHandler->sendMessage($socket, "Version: " . $version);
        $this->frameHandler->sendMessage($socket, "Players: {$playerCount}/{$maxPlayers}");
        $this->frameHandler->sendMessage($socket, "Connected at: " . date('Y-m-d H:i:s'));
        $this->frameHandler->sendMessage($socket, "==========================================");
    }
    
    public function sendAuthenticationStatus(mixed $socket, int $connectionId): void {
        $password = $this->plugin->getConfig()->get('websocket-password', '');
        
        if (empty($password)) {
            $this->frameHandler->sendMessage($socket, "No authentication required - console ready!");
        } else {
            if ($this->plugin->getConnectionManager()->isAuthenticated($connectionId)) {
                $this->frameHandler->sendMessage($socket, "Authentication not required for this session.");
            } else {
                $this->frameHandler->sendMessage($socket, "Authentication required. Please provide password.");
            }
        }
    }
    
    public function broadcast(string $message): void {
        $authenticatedCount = 0;
        $totalConnections = $this->plugin->getConnectionManager()->getConnectionCount();
        
        $cleanMessage = TextFormat::clean($message);
        $jsonMessage = json_encode([
            'type' => 'console',
            'message' => $cleanMessage,
            'timestamp' => date('H:i:s')
        ]);
        
        $this->plugin->getConnectionManager()->debugConnectionStatus();
        
        foreach ($this->plugin->getConnectionManager()->getAllConnections() as $id => $conn) {
            if ($this->plugin->getConnectionManager()->isConnectionReadyForBroadcast($conn)) {
                $socketType = SocketUtils::getSocketType($conn['socket']);
                $this->plugin->debugLog("  Sending to client {$id}: socket type = {$socketType}");
                $this->frameHandler->sendMessage($conn['socket'], $jsonMessage);
                $authenticatedCount++;
            }
        }
        
        if ($authenticatedCount > 0) {
            $this->plugin->debugLog("Broadcasted to {$authenticatedCount} authenticated clients: " . substr($cleanMessage, 0, 50));
        } else {
            $this->plugin->debugLog("No authenticated clients to broadcast to!");
        }
    }
    
    public function broadcastConsoleOutput(string $message, string $level = 'INFO'): void {
        $authenticatedCount = 0;
        
        $cleanMessage = TextFormat::clean($message);
        $jsonMessage = json_encode([
            'type' => 'console',
            'message' => $cleanMessage,
            'timestamp' => date('H:i:s'),
            'level' => $level
        ]);
        
        foreach ($this->plugin->getConnectionManager()->getAllConnections() as $conn) {
            if ($this->plugin->getConnectionManager()->isConnectionReadyForBroadcast($conn)) {
                $this->frameHandler->sendMessage($conn['socket'], $jsonMessage);
                $authenticatedCount++;
            }
        }
        
        if ($authenticatedCount > 0) {
            $this->plugin->debugLog("Broadcasted console output to {$authenticatedCount} clients: " . substr($cleanMessage, 0, 50));
        }
    }
}
