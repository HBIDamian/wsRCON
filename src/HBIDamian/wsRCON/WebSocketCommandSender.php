<?php
declare(strict_types=1);

namespace HBIDamian\wsRCON;

use pocketmine\command\CommandSender;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\lang\Language;
use pocketmine\lang\Translatable;

class WebSocketCommandSender implements CommandSender {
    
    private Main $plugin;
    private mixed $socket;
    private FrameHandler $frameHandler;
    
    public function __construct(Main $plugin, mixed $socket, FrameHandler $frameHandler) {
        $this->plugin = $plugin;
        $this->socket = $socket;
        $this->frameHandler = $frameHandler;
    }
    
    public function sendMessage(Translatable|string $message): void {
        if ($message instanceof Translatable) {
            $message = $this->plugin->getServer()->getLanguage()->translate($message);
        }
        
        $clean = TextFormat::clean($message);
        $jsonMessage = json_encode([
            'type' => 'response',
            'message' => $clean,
            'timestamp' => date('H:i:s')
        ]);
        
        $this->plugin->debugLog("Sending command response: " . substr($clean, 0, 100));
        $this->plugin->broadcastConsoleOutput("Command response: " . $clean, 'RESPONSE');
        $this->frameHandler->sendMessage($this->socket, $jsonMessage);
    }
    
    public function getServer(): Server {
        return $this->plugin->getServer();
    }
    
    public function getName(): string {
        return "wsRCON";
    }
    
    public function isOp(): bool {
        return true;
    }
    
    public function setOp(bool $value): void {
        // WebSocket console always has operator privileges
    }
    
    public function hasPermission(string|\pocketmine\permission\Permission $name): bool {
        return true;
    }
    
    public function addAttachment(\pocketmine\plugin\Plugin $plugin, ?string $name = null, ?bool $value = null): \pocketmine\permission\PermissionAttachment {
        return new \pocketmine\permission\PermissionAttachment($plugin, $this);
    }
    
    public function removeAttachment(\pocketmine\permission\PermissionAttachment $attachment): void {
        // No implementation needed
    }
    
    public function recalculatePermissions(): array {
        return [];
    }
    
    public function getEffectivePermissions(): array {
        return [];
    }
    
    public function isPermissionSet(string|\pocketmine\permission\Permission $name): bool {
        return true;
    }
    
    public function getPermissionRecalculationCallbacks(): \pocketmine\utils\ObjectSet {
        return new \pocketmine\utils\ObjectSet();
    }
    
    public function getLanguage(): \pocketmine\lang\Language {
        return $this->plugin->getServer()->getLanguage();
    }
    
    public function getScreenLineHeight(): int {
        return 25;
    }
    
    public function setScreenLineHeight(?int $height): void {
        // No implementation needed for WebSocket console
    }
    
    public function setBasePermission(\pocketmine\permission\Permission|string $name, bool $grant): void {
        // No implementation needed for WebSocket console
    }
    
    public function unsetBasePermission(\pocketmine\permission\Permission|string $name): void {
        // No implementation needed for WebSocket console
    }
}
