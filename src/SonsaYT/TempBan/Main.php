<?php

namespace SonsaYT\TempBan;

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

use SQLite3;

class Main extends PluginBase implements Listener {
	
	public array $staffList = [];
	
	public array $targetPlayer = [];
	
	public SQLITE3 $db;
	
	public array $message = [];
	
    public function onEnable(): void {
		@mkdir($this->getDataFolder());

		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		if(($cmd = $this->getServer()->getCommandMap()->getCommand("ban")) instanceof Command){
			$this->getServer()->getCommandMap()->unregister($cmd);
		}
		$this->getServer()->getCommandMap()->register($this->getName(), new BanCommand($this));
		$this->getServer()->getCommandMap()->register($this->getName(), new TCheckCommand($this));

		$this->db = new SQLite3($this->getDataFolder() . "TempBan.db");
		$this->db->exec("CREATE TABLE IF NOT EXISTS banPlayers(player TEXT PRIMARY KEY, banTime INT, reason TEXT, staff TEXT);");
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
			"InfoUIUnBanButton" => "Unban Player",
		)))->getAll();
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
			if($result === null){
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
				$banInfo = $this->db->prepare("INSERT OR REPLACE INTO banPlayers (player, banTime, reason, staff) VALUES (:player, :banTime, :reason, :staff);");
				$banInfo->bindValue(":player", $this->targetPlayer[$player->getName()]);
				$banInfo->bindValue(":banTime", $banTime);
				$banInfo->bindValue(":reason", $data[4]);
				$banInfo->bindValue(":staff", $player->getName());
				$banInfo->execute();
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

			$banInfo = $this->db->query("SELECT * FROM banPlayers;");
			$i = -1;

			$players = [];

			while ($resultArr = $banInfo->fetchArray(SQLITE3_ASSOC)) {
				$j = $i + 1;
				$banPlayer = $resultArr['player'];
				$players[] = strtolower($banPlayer);
				$i = $i + 1;
			}
			
			if(in_array(strtolower($name), $players)){
				$this->targetPlayer[$player->getName()] = $name;
				$this->openInfoUI($player);
			} else {
				$player->sendMessage(TextFormat::RED . "Player are not banned or not exist!");
			}
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
		$banInfo = $this->db->query("SELECT * FROM banPlayers;");
		$array = $banInfo->fetchArray(SQLITE3_ASSOC);	
		if (empty($array)) {
			$player->sendMessage($this->message["NoBanPlayers"]);
			return true;
		}
		$form->setTitle($this->message["BanListTitle"]);
		$form->setContent($this->message["BanListContent"]);
		$banInfo = $this->db->query("SELECT * FROM banPlayers;");
		$i = -1;

		$players = [];

		while ($resultArr = $banInfo->fetchArray(SQLITE3_ASSOC)) {
			$j = $i + 1;
			$banPlayer = $resultArr['player'];
			$players[] = $banPlayer;
			$i = $i + 1;
		}

		sort($players);

		foreach ($players as $pp){
			$form->addButton(TextFormat::BOLD . "$pp", -1, "", $pp);
		}

		$form->sendToPlayer($player);
		return $form;
	}
	
	public function openInfoUI($player){
		$form = new SimpleForm(function (Player $player, int $data = null){
		$result = $data;
		if($result === null){
			return true;
		}
			switch($result){
				case 0:
					$banplayer = $this->targetPlayer[$player->getName()];
					$banInfo = $this->db->query("SELECT * FROM banPlayers WHERE player = '$banplayer';");
					$array = $banInfo->fetchArray(SQLITE3_ASSOC);
					if (!empty($array)) {
						$this->db->query("DELETE FROM banPlayers WHERE player = '$banplayer';");
						$player->sendMessage(str_replace(["{player}"], [$banplayer], $this->message["UnBanPlayer"]));
					}
					unset($this->targetPlayer[$player->getName()]);
				break;
			}
		});
		$banPlayer = $this->targetPlayer[$player->getName()];
		$banInfo = $this->db->query("SELECT * FROM banPlayers WHERE player = '$banPlayer';");
		$array = $banInfo->fetchArray(SQLITE3_ASSOC);
		$text = TextFormat::RED . "An error with load " . $banPlayer . " ban data!";
		if (!empty($array)) {
			$banTime = $array['banTime'];
			$reason = $array['reason'];
			$staff = $array['staff'];
			$now = time();
			if($banTime < $now){
				$banplayer = $this->targetPlayer[$player->getName()];
				$banInfo = $this->db->query("SELECT * FROM banPlayers WHERE player = '$banplayer';");
				$array = $banInfo->fetchArray(SQLITE3_ASSOC);
				if (!empty($array)) {
					$this->db->query("DELETE FROM banPlayers WHERE player = '$banplayer';");
					$player->sendMessage(str_replace(["{player}"], [$banplayer], $this->message["AutoUnBanPlayer"]));
				}
				unset($this->targetPlayer[$player->getName()]);
				return true;
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
		$form->setTitle(TextFormat::BOLD . $banPlayer);
		$form->setContent($text);
		$form->addButton($this->message["InfoUIUnBanButton"]);
		$form->sendToPlayer($player);
		return $form;
	}
	
	public function onPlayerLogin(PlayerLoginEvent $event){
		$player = $event->getPlayer();
		$banplayer = $player->getName();
		$banInfo = $this->db->query("SELECT * FROM banPlayers WHERE player = '$banplayer';");
		$array = $banInfo->fetchArray(SQLITE3_ASSOC);
		if (!empty($array)) {
			$banTime = $array['banTime'];
			$reason = $array['reason'];
			$staff = $array['staff'];
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
				$this->db->query("DELETE FROM banPlayers WHERE player = '$banplayer';");
			}
		}
		if(isset($this->staffList[$player->getName()])){
			unset($this->staffList[$player->getName()]);
		}
	}
}
