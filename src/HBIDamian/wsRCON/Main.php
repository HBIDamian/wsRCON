<?php
declare(strict_types=1);

namespace HBIDamian\wsRCON;

use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat;

class Main extends PluginBase {
    
    private const HEARTBEAT_INTERVAL = 30;
    
    private \pocketmine\utils\Config $config;
    private int $lastHeartbeat = 0;
    private bool $debugMode = false;
    
    private WebSocketServer $webSocketServer;
    private ConnectionManager $connectionManager;
    private MessageHandler $messageHandler;
    private EventListener $eventListener;
    private FrameHandler $frameHandler;
    
    public function onEnable(): void {
        $this->initializePlugin();
        $this->initializeComponents();
        $this->startWebSocketServer();
        $this->setupConsoleCapture();
        $this->logStartupInformation();
        $this->registerCommands();
        $this->scheduleWebSocketHandler();
    }
    
    private function initializeComponents(): void {
        $this->frameHandler = new FrameHandler($this);
        $this->connectionManager = new ConnectionManager($this);
        $this->messageHandler = new MessageHandler($this, $this->frameHandler);
        $this->webSocketServer = new WebSocketServer($this);
        $this->eventListener = new EventListener($this);
        
        // Register the event listener
        $this->getServer()->getPluginManager()->registerEvents($this->eventListener, $this);
    }
    
    private function initializePlugin(): void {
        $this->ensureDataFolderExists();
        $this->saveDefaultConfig();
        $this->reloadConfig();
        $this->config = $this->getConfig();
        $this->initializeSecurePassword();
        $this->debugMode = $this->config->get('debug-logging', false);
        $this->debugLog("=== Plugin Starting ===");
        $this->debugLog("Config file location: " . $this->getDataFolder() . "config.yml");
        $this->debugLog("Debug mode enabled: " . ($this->debugMode ? 'yes' : 'no'));
    }
    
    private function ensureDataFolderExists(): void {
        if (!file_exists($this->getDataFolder())) {
            mkdir($this->getDataFolder(), 0755, true);
        }
    }
    
    private function logStartupInformation(): void {
        $this->logSecurityInfo();
    }
    
    private function scheduleWebSocketHandler(): void {
        $this->getScheduler()->scheduleRepeatingTask(
            new ClosureTask(function (): void {
                $this->handleWebSocket();
            }),
            20 // Run every second (20 ticks)
        );
    }
    
    public function onDisable(): void {
        $this->webSocketServer->stop();
    }
    
    private function initializeSecurePassword(): void {
        $currentPassword = $this->config->get('websocket-password', '');
        
        // Generate a secure random password if using default or empty password
        if ($currentPassword === 'change_this_password_123' || empty($currentPassword)) {
            $securePassword = base64_encode(random_bytes(13));
            $this->config->set('websocket-password', $securePassword);
            $this->saveConfigWithFormatting($securePassword);
            $this->getLogger()->info("Generated secure WebSocket password in the config.yml file.");
        }
    }
    
    private function saveConfigWithFormatting(string $newPassword): void {
        $configPath = $this->getDataFolder() . "config.yml";
        $resourcePath = $this->getResourcePath("config.yml");
        
        // Read the original template from resources
        if ($resourcePath !== null && file_exists($resourcePath)) {
            $template = file_get_contents($resourcePath);
            
            // Replace the default password with the new secure password
            $updatedConfig = str_replace(
                'websocket-password: "change_this_password_123"',
                'websocket-password: "' . $newPassword . '"',
                $template
            );
            
            // Write the formatted config to the data folder
            file_put_contents($configPath, $updatedConfig);
            
            $this->debugLog("Config saved with preserved formatting using template from resources");
        } else {
            // Fallback to regular config save if template is not available
            $this->config->save();
            $this->debugLog("Fallback: Config saved using standard method (template not found)");
        }
    }
    
    private function logSecurityInfo(): void {
        $password = $this->config->get('websocket-password', '');
        $port = $this->getWebSocketPort();
        $host = $this->config->get('websocket-host', '0.0.0.0');
        
        $this->getLogger()->info("WebSocket server will listen on {$host}:{$port}");
        
        if (empty($password)) {
            $this->getLogger()->warning(TextFormat::YELLOW . "NO PASSWORD SET! WebSocket is open to everyone!");
            $this->getLogger()->warning(TextFormat::YELLOW . "Set 'websocket-password' in config.yml for security.");
            $this->getLogger()->warning(TextFormat::YELLOW . "This is a SECURITY RISK for production servers!");
        } else {
            $this->getLogger()->info(TextFormat::GREEN . "Password authentication enabled for WebSocket connections.");
        }
    }
    
    public function getWebSocketServer(): WebSocketServer {
        return $this->webSocketServer;
    }
    
    public function getConnectionManager(): ConnectionManager {
        return $this->connectionManager;
    }
    
    public function getMessageHandler(): MessageHandler {
        return $this->messageHandler;
    }
    
    public function getFrameHandler(): FrameHandler {
        return $this->frameHandler;
    }
    
    public function getEventListener(): EventListener {
        return $this->eventListener;
    }
    
    public function getWebSocketPort(): int {
        return $this->webSocketServer->getWebSocketPort();
    }
    
    private function setupConsoleCapture(): void {
        $this->debugLog("Setting up comprehensive console capture...");
        
        $this->getScheduler()->scheduleRepeatingTask(
            new ClosureTask(function (): void {
                $this->monitorServerActivity();
            }),
            100 // Every 5 seconds (100 ticks)
        );
        
        $this->debugLog("Console capture setup complete - monitoring server events and activity");
    }
    
    private function monitorServerActivity(): void {
        if ($this->connectionManager->getConnectionCount() === 0) {
            return;
        }
        
        static $lastStatsBroadcast = 0;
        if (time() - $lastStatsBroadcast > 300) { // Every 5 minutes
            $playerCount = count($this->getServer()->getOnlinePlayers());
            $maxPlayers = $this->getServer()->getMaxPlayers();
            $this->broadcast("§7[SERVER] Players online: {$playerCount}/{$maxPlayers}");
            $lastStatsBroadcast = time();
        }
    }
    
    public function broadcastServerMessage(string $message, string $type = 'INFO', string $source = 'SERVER'): void {
        $formattedMessage = "§7[{$source}] {$message}";
        $this->broadcast($formattedMessage);
    }
    
    private function broadcastConsoleMessage(string $message, string $level = 'INFO'): void {
        if ($this->connectionManager->getConnectionCount() === 0) {
            return;
        }
        
        $consoleData = [
            'type' => 'console',
            'timestamp' => date('H:i:s'),
            'message' => $message,
            'level' => $level
        ];
        
        $jsonMessage = json_encode($consoleData);
        
        foreach ($this->connectionManager->getAllConnections() as $clientId => $connection) {
            if (isset($connection['authenticated']) && $connection['authenticated']) {
                $this->frameHandler->sendRawMessage($connection['socket'], $jsonMessage);
            }
        }
        
        $this->debugLog("Broadcasted console message to " . $this->connectionManager->getConnectionCount() . " clients: " . substr($message, 0, 50) . "...");
    }
    
    private function registerCommands(): void {
        // Commands will be registered here if needed in the future
    }
    
    private function startWebSocketServer(): void {
        $this->webSocketServer->start();
    }
    
    private function handleWebSocket(): void {
        $this->logHeartbeat();
        $this->webSocketServer->handleConnections();
    }
    
    private function logHeartbeat(): void {
        $now = time();
        if ($now - $this->lastHeartbeat < self::HEARTBEAT_INTERVAL) {
            return;
        }
        
        $this->debugLog("=== WebSocket handler heartbeat ===");
        $this->debugLog("Connections count: " . $this->connectionManager->getConnectionCount());
        $this->debugLog("Socket server state: " . ($this->webSocketServer->isRunning() ? "running" : "stopped"));
        
        $this->lastHeartbeat = $now;
    }
    
    public function broadcast(string $message): void {
        $this->messageHandler->broadcast($message);
    }
    
    public function broadcastConsoleOutput(string $message, string $level = 'INFO'): void {
        $this->messageHandler->broadcastConsoleOutput($message, $level);
    }
    
    public function debugLog(string $message): void {
        if ($this->debugMode) {
            $this->getLogger()->info("[DEBUG] " . $message);
        }
    }
    
    public function logAndBroadcast(string $message, string $level = "INFO"): void {
        switch (strtoupper($level)) {
            case "ERROR":
                $this->getLogger()->error($message);
                break;
            case "WARNING":
                $this->getLogger()->warning($message);
                break;
            case "DEBUG":
                $this->debugLog($message);
                return; // Don't broadcast debug messages
            default:
                $this->getLogger()->info($message);
                break;
        }
        
        $this->broadcast("§7[PLUGIN] " . $message);
    }
}
