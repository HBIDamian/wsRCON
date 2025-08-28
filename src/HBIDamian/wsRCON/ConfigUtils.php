<?php
declare(strict_types=1);

namespace HBIDamian\wsRCON;

class ConfigUtils {
    
    private Main $plugin;
    
    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }
    
    public function getWebSocketPassword(): string {
        return $this->plugin->getConfig()->get('websocket-password', '');
    }
    
    public function isPasswordRequired(): bool {
        return !empty($this->getWebSocketPassword());
    }
    
    public function getWebSocketHost(): string {
        return $this->plugin->getConfig()->get('websocket-host', '0.0.0.0');
    }
    
    public function getMaxConnections(): int {
        return $this->plugin->getConfig()->get('max-connections', 10);
    }
    
    public function isDebugMode(): bool {
        return $this->plugin->getConfig()->get('debug-logging', false);
    }
}
