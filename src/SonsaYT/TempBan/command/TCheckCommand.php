<?php

namespace SonsaYT\TempBan\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;

use SonsaYT\TempBan\Main;

class TCheckCommand extends Command implements PluginOwned {

    public function __construct(
        private Main $plugin
    ){
        parent::__construct("tcheck", "Check ban list", "tempban.command.tcheck", ["tcheck"]);
    }

    public function execute(CommandSender $sender, string $cmdLabel, array $args): bool{
        if(!($sender instanceof Player)){
            $sender->sendMessage("run command in-game only!");
            return false;
        }

        if($sender->hasPermission("tempban.command.tcheck")){
			$this->plugin->openTcheckForm($sender);
		}

        return true;
    }

    public function getOwningPlugin(): Plugin
    {
        return $this->plugin;
    }
}