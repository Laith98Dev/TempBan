<?php

namespace SonsaYT\TempBan\command;

/*
 *	This program is free software: you can redistribute it and/or modify
 *	it under the terms of the GNU General Public License as published by
 *	the Free Software Foundation, either version 3 of the License, or
 *	(at your option) any later version.
 *
 *	This program is distributed in the hope that it will be useful,
 *	but WITHOUT ANY WARRANTY; without even the implied warranty of
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	GNU General Public License for more details.
 *
 *	You should have received a copy of the GNU General Public License
 *	along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * 	
 */

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
                        $this->plugin->staffList[$sender->getName()] = $sender;
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
