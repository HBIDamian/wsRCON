<?php
declare(strict_types=1);

namespace HBIDamian\wsRCON;

use pocketmine\event\Listener;
use pocketmine\event\server\CommandEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerDeathEvent;

class EventListener implements Listener {
    
    private Main $plugin;
    
    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }
    
    public function onPlayerJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();
        $message = "§a[JOIN] " . $player->getName() . " joined the game";
        $this->plugin->broadcast($message);
    }
    
    public function onPlayerQuit(PlayerQuitEvent $event): void {
        $player = $event->getPlayer();
        $message = "§c[QUIT] " . $player->getName() . " left the game";
        $this->plugin->broadcast($message);
    }
    
    public function onPlayerChat(PlayerChatEvent $event): void {
        $player = $event->getPlayer();
        $message = "§f<" . $player->getName() . "> " . $event->getMessage();
        $this->plugin->broadcast($message);
    }
    
    public function onPlayerDeath(PlayerDeathEvent $event): void {
        $player = $event->getPlayer();
        $deathMessage = $event->getDeathMessage();
        $message = "§4[DEATH] " . $deathMessage;
        $this->plugin->broadcast($message);
    }
    
    public function onServerCommand(CommandEvent $event): void {
        $command = $event->getCommand();
        $sender = $event->getSender();
        
        if ($sender->getName() === "CONSOLE") {
            $this->plugin->broadcast("§e[CONSOLE] > " . $command);
        } else if ($sender->getName() !== "wsRCON") {
            $this->plugin->broadcast("§e[" . $sender->getName() . "] > " . $command);
        }
    }
}
