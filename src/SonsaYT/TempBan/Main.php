<?php

namespace SonsaYT\TempBan;

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

use pocketmine\plugin\PluginBase;
use pocketmine\player\Player;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\network\mcpe\protocol\TextPacket;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

use SonsaYT\TempBan\command\BanCommand;
use SonsaYT\TempBan\command\TCheckCommand;

use SonsaYT\TempBan\libs\jojoe77777\FormAPI\SimpleForm;
use SonsaYT\TempBan\libs\jojoe77777\FormAPI\CustomForm;
use SonsaYT\TempBan\libs\jojoe77777\FormAPI\ModalForm;

use poggit\libasynql\libasynql;
use poggit\libasynql\SqlError;

class Main extends PluginBase implements Listener {
	
	public const DB_INIT = "tempban.init";
	public const DB_BAN_PLAYER = "tempban.ban-player";
	public const DB_UNBAN_PLAYER = "tempban.unban-player";
	public const DB_GET_BAN_INFO = "tempban.get-ban-info";
	public const DB_GET_ALL_BANS = "tempban.get-all-bans";
	
	public array $staffList = [];
	
	public array $targetPlayer = [];
	
	public $db;
	
	/** @var Config */
	public $cfg;
	
	public array $message = [];
	
    public function onEnable(): void {
		@mkdir($this->getDataFolder());
		
		$this->saveResource("config.yml");
		
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		if(($cmd = $this->getServer()->getCommandMap()->getCommand("ban")) instanceof Command){
			$this->getServer()->getCommandMap()->unregister($cmd);
		}
		
		$this->getServer()->getCommandMap()->register($this->getName(), new BanCommand($this));
		$this->getServer()->getCommandMap()->register($this->getName(), new TCheckCommand($this));
		
		$this->cfg = new Config($this->getDataFolder() . "config.yml", Config::YAML);
		
		$this->initDatabase();
				
		$this->message = (new Config($this->getDataFolder() . "Message.yml", Config::YAML, array(
			"BroadcastBanMessage" => "§b{player} §dhas been banned by §b{staff} §dfor §b{day} §dday/s, §b{hour} §dhour/s, §b{minute} §dminute/s. §dReason: §b{reason}",
			"KickBanMessage" => "§dYou are banned by §b{staff} §dfor §b{day} §dday/s, §b{hour} §dhour/s, §b{minute} §dminute/s. \n§dReason: §b{reason}",
			"LoginBanMessage" => "§dYou are still banned for §b{day} §dday/s, §b{hour} §dhour/s, §b{minute} §dminute/s, §b{second} §dsecond/s. \n§dReason: §b{reason} \n§dBanned by: §b{staff}",
			"BanMyself" => "§cYou can't ban yourself",
			"BanModeOn" => "§bBan mode on",
			"BanModeOff" => "§cBan mode off",
			"NoBanPlayers" => "§bNo ban players",
			"UnBanPlayer" => "§b{player} has been unban",
			"AutoUnBanPlayer" => "§b{player} has been auto unban. Ban time already done",
			"BanListTitle" => "§lBAN PLAYER LIST",
			"BanListContent" => "Choose player",
			"PlayerListTitle" => "§lPLAYER LIST",
			"PlayerListContent" => "Choose player",
			"InfoUIContent" => "§dInformation: \nDay: §b{day} \n§dHour: §b{hour} \n§dMinute: §b{minute} \n§dSecond: §b{second} \n§dReason: §b{reason} \n§dBanned by: §b{staff}\n\n\n",
			"InfoUIUnBanButton" => "Unban Player"
		)))->getAll();
    }
	
	public function initDatabase(): void
    {
        if ($this->isDisabled()) return;
		
        try {
            $this->db = $db = libasynql::create(
                $this,
                $this->cfg->get('database'),
                ["sqlite" => "TempBan.sql"]
            );
        } catch (\Error $e) {
            $this->getLogger()->error($e->getMessage());
            return;
        }

        $error = null;
        $db->executeGeneric(
            self::DB_INIT,
            [],
            null,
            function (SqlError $error_) use (&$error) {
                $error = $error_;
            }
        );
		
        $db->waitAll();

        if ($error !== null) {
            $this->getLogger()->error($error->getMessage());
            return;
        }
    }
	
	public function openPlayerListUI($player){
		$form = new SimpleForm(function (Player $player, $data = null){
			$target = $data;
			if($target === null){
				return true;
			}
			
			$this->targetPlayer[$player->getName()] = $target;
			$this->openTbanUI($player);
		});
		
		$form->setTitle($this->message["PlayerListTitle"]);
		$form->setContent($this->message["PlayerListContent"]);
		
		foreach($this->getServer()->getOnlinePlayers() as $online){
			$form->addButton($online->getName(), -1, "", $online->getName());
		}
		
		$form->sendToPlayer($player);
		return $form;
	}
	
	public function hitBan(EntityDamageEvent $event){
		if($event instanceof EntityDamageByEntityEvent) {
			$damager = $event->getDamager();
			$victim = $event->getEntity();
			if($damager instanceof Player && $victim instanceof Player){
				if(isset($this->staffList[$damager->getName()])){
					$event->cancel();
					$this->targetPlayer[$damager->getName()] = $victim->getName();
					$this->openTbanUI($damager);
				}
			}
		}
	}
	
	public function openTbanUI($player){
		$form = new CustomForm(function (Player $player, array $data = null){
			if($data === null){
				return true;
			}
			$result = $data[0];
			if(isset($this->targetPlayer[$player->getName()])){
				if($this->targetPlayer[$player->getName()] == $player->getName()){
					$player->sendMessage($this->message["BanMyself"]);
					return true;
				}
				$now = time();
				$day = ($data[1] * 86400);
				$hour = ($data[2] * 3600);
				if($data[3] > 1){
					$min = ($data[3] * 60);
				} else {
					$min = 60;
				}
				$banTime = $now + $day + $hour + $min;
				
				$this->db->executeInsert(
					self::DB_BAN_PLAYER,
					["player" => $this->targetPlayer[$player->getName()], "banTime" => $banTime, "reason" => $data[4], "staff" => $player->getName()]
				);
				
				$target = $this->getServer()->getPlayerExact($this->targetPlayer[$player->getName()]);
				if($target instanceof Player){
					$target->kick(str_replace(["{day}", "{hour}", "{minute}", "{reason}", "{staff}"], [$data[1], $data[2], $data[3], $data[4], $player->getName()], $this->message["KickBanMessage"]));
				}
				$this->getServer()->broadcastMessage(str_replace(["{player}", "{day}", "{hour}", "{minute}", "{reason}", "{staff}"], [$this->targetPlayer[$player->getName()], $data[1], $data[2], $data[3], $data[4], $player->getName()], $this->message["BroadcastBanMessage"]));
				unset($this->targetPlayer[$player->getName()]);

			}
		});
		$list[] = $this->targetPlayer[$player->getName()];
		$form->setTitle(TextFormat::BOLD . "TEMPORARY BAN");
		$form->addDropdown("\nTarget", $list);
		$form->addSlider("Day/s", 0, 30, 1);
		$form->addSlider("Hour/s", 0, 24, 1);
		$form->addSlider("Minute/s", 0, 60, 5);
		$form->addInput("Reason");
		$form->sendToPlayer($player);
		return $form;
	}

	public function openTcheckForm($player){
		$form = new SimpleForm(function (Player $player, ?int $data = null){
			if($data === null){
				return;
			}
			
			switch ($data){
				case 0:
					$this->OpenTCheckSearchForm($player);
					break;
				case 1:
					$this->openTcheckUI($player);
					break;
			}
		});

		$form->setTitle("TCheck Form");

		$form->addButton("Search by name");
		$form->addButton("Select form list");

		$form->sendToPlayer($player);
	}

	public function OpenTCheckSearchForm(Player $player){
		$form = new CustomForm(function (Player $player, $data = null){
			if($data === null){
				return false;
			}

			$name = null;
			if(isset($data[0])){
				$name = $data[0];
			}
			
			if($name == null){
				$player->sendMessage(TextFormat::RED . "Please enter a valid name!");
				return false;
			}

			// $banInfo = $this->db->query("SELECT * FROM banPlayers;");
			$this->db->executeSelect(self::DB_GET_ALL_BANS, [], function(array $rows) use ($name, $player) : void {
				$all = [];
				
				if(isset($rows[0])){
					$all = $rows;
					var_dump($all);
				}
				
				$players = [];
				$i = -1;
				
				foreach ($all as $resultArr){
					$j = $i + 1;
					$banPlayer = $resultArr['player'];
					$players[] = $banPlayer;
					$i = $i + 1;
				}
				
				if(in_array(strtolower($name), $players)){
					$this->targetPlayer[$player->getName()] = $name;
					$this->openInfoUI($player);
				} else {
					$player->sendMessage(TextFormat::RED . "Player are not banned or not exist!");
				}
				
			});
		});

		$form->setTitle("TCSearch Form");

		$form->addInput("Name", "", "");

		$form->sendToPlayer($player);
	}

	public function openTcheckUI($player){
		$form = new SimpleForm(function (Player $player, $data = null){
			if($data === null){
				return true;
			}
			
			$this->targetPlayer[$player->getName()] = $data;
			$this->openInfoUI($player);
		});
		
		$this->db->executeSelect(self::DB_GET_ALL_BANS, [], function(array $rows) use ($player, $form): void {
			$all = [];
			
			if(isset($rows[0])){
				// var_dump($rows);
				$all = $rows;
				var_dump($all);
			}
			
			if (empty($all)) {
				$player->sendMessage($this->message["NoBanPlayers"]);
				return;
			}
			
			$form->setTitle($this->message["BanListTitle"]);
			$form->setContent($this->message["BanListContent"]);
			
			$i = -1;

			$players = [];

			foreach ($all as $resultArr){
				$j = $i + 1;
				$banPlayer = $resultArr['player'];
				$players[] = $banPlayer;
				$i = $i + 1;
			}

			sort($players);

			foreach ($players as $pp){
				$form->addButton(TextFormat::BOLD . $pp, -1, "", $pp);
			}

			$form->sendToPlayer($player);
		});
	}
	
	public function openInfoUI($player){
		if(!isset($this->targetPlayer[$player->getName()]))
			return false;
		
		$banplayer = $this->targetPlayer[$player->getName()];
		$this->db->executeSelect(self::DB_GET_BAN_INFO, ["player" => $banplayer], function(array $rows) use ($banplayer, $player): void {
			$banInfo = [];
			
			if(isset($rows[0])){
				$banInfo = $rows[0];
				var_dump($banInfo);
			}
			
			$form = new SimpleForm(function (Player $player, int $data = null) use ($banInfo, $banplayer){
				$result = $data;
				if($result === null){
					return false;
				}
				
				switch($result){
					case 0:
						if (!empty($banInfo)) {
							$this->db->executeSelect(self::DB_UNBAN_PLAYER, ["player" => $banplayer], function(array $rows) use ($player): void {
								$player->sendMessage(str_replace(["{player}"], [$banplayer], $this->message["UnBanPlayer"]));
							});
						}
						
						unset($this->targetPlayer[$player->getName()]);
					break;
				}
			});
			
			$text = TextFormat::RED . "An error with load " . $banplayer . " ban data!";
			
			if(!empty($banInfo)){
				$banTime = $banInfo['banTime'];
				$reason = $banInfo['reason'];
				$staff = $banInfo['staff'];
				$now = time();
				
				if($banTime < $now){
					$banplayer = $this->targetPlayer[$player->getName()];
					$this->db->executeSelect(self::DB_UNBAN_PLAYER, ["player" => $banplayer], function(array $rows) use ($player) : void {
						$player->sendMessage(str_replace(["{player}"], [$banplayer], $this->message["AutoUnBanPlayer"]));
					});
					
					unset($this->targetPlayer[$player->getName()]);
					return;
				}
				
				$remainingTime = $banTime - $now;
				$day = floor($remainingTime / 86400);
				$hourSeconds = $remainingTime % 86400;
				$hour = floor($hourSeconds / 3600);
				$minuteSec = $hourSeconds % 3600;
				$minute = floor($minuteSec / 60);
				$remainingSec = $minuteSec % 60;
				$second = ceil($remainingSec);
				
				$text = str_replace(["{day}", "{hour}", "{minute}", "{second}", "{reason}", "{staff}"], [$day, $hour, $minute, $second, $reason, $staff], $this->message["InfoUIContent"]);
			}
			
			$form->setTitle(TextFormat::BOLD . $banplayer);
			$form->setContent($text);
			
			$form->addButton($this->message["InfoUIUnBanButton"]);
			
			$form->sendToPlayer($player);
		});
	}
	
	public function onPlayerLogin(PlayerLoginEvent $event){
		$player = $event->getPlayer();
		$banplayer = $player->getName();
		
		$this->db->executeSelect(self::DB_GET_BAN_INFO, ["player" => $banplayer], function(array $rows) use ($player, $banplayer): void {
			$banInfo = [];
			
			if(isset($rows[0])){
				$banInfo = $rows[0];
				var_dump($banInfo);
			}
			
			if (!empty($banInfo)) {
				$banTime = $banInfo['banTime'];
				$reason = $banInfo['reason'];
				$staff = $banInfo['staff'];
				$now = time();
				if($banTime > $now){
					$remainingTime = $banTime - $now;
					$day = floor($remainingTime / 86400);
					$hourSeconds = $remainingTime % 86400;
					$hour = floor($hourSeconds / 3600);
					$minuteSec = $hourSeconds % 3600;
					$minute = floor($minuteSec / 60);
					$remainingSec = $minuteSec % 60;
					$second = ceil($remainingSec);
					
					$player->kick(str_replace(["{day}", "{hour}", "{minute}", "{second}", "{reason}", "{staff}"], [$day, $hour, $minute, $second, $reason, $staff], $this->message["LoginBanMessage"]));
				} else {
					$this->db->executeSelect(self::DB_UNBAN_PLAYER, ["player" => $banplayer], function(array $rows) : void {});
				}
			}
		});
		
		if(isset($this->staffList[$player->getName()])){
			unset($this->staffList[$player->getName()]);
		}
	}
}
