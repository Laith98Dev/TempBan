<?php

namespace SonsaYT\TempBan\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;

use SonsaYT\TempBan\Main;

class BanCommand extends Command implements PluginOwned {

    public function __construct(
        private Main $plugin
    ){
        parent::__construct("ban", "Open Ban Form", "tempban.command", ["tban"]);
    }

    public function execute(CommandSender $sender, string $cmdLabel, array $args): bool{
        if(!($sender instanceof Player)){
            $sender->sendMessage("run command in-game only!");
            return false;
        }

        if($sender->hasPermission("tempban.command")){
            if(count($args) == 0){
                $this->plugin->openPlayerListUI($sender);
            }
            if(count($args) == 1){
                if($args[0] == "on"){
                    if(!isset($this->plugin->staffList[$sender->getName()])){
                        $this->staffList[$sender->getName()] = $sender;
                        $sender->sendMessage($this->plugin->message["BanModeOn"]);
                    }
                } else if ($args[0] == "off"){
                    if(isset($this->plugin->staffList[$sender->getName()])){
                        unset($this->plugin->staffList[$sender->getName()]);
                        $sender->sendMessage($this->plugin->message["BanModeOff"]);
                    }
                } else {
                    $this->plugin->targetPlayer[$sender->getName()] = $args[0];
                    $this->plugin->openTbanUI($sender);
                }
            }
        }

        return true;
    }

    public function getOwningPlugin(): Plugin
    {
        return $this->plugin;
    }
}