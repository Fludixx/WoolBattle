<?php

namespace Fludixx\Woolbattle;

use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\scheduler\Task;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as f;

class DuellTask extends Task {

	public $level;
	public $pl;

	public function __construct(Woolbattle $pl, Level $level)
	{
		$this->level = $level;
		$this->pl = $pl;
	}

	public function onRun(int $currentTick)
	{
		$level = $this->level;
		$players = $level->getPlayers();
		$inLevel = count($players);
		foreach($players as $player) {
			$lifes = $this->pl->players[$player->getName()]["lifes"];
			$pos  = $this->pl->players[$player->getName()]["pos"];
			$oplayername = $this->pl->players[$player->getName()]["ms"];
			$olifes = $this->pl->players[$oplayername]["lifes"];
			if($lifes < 0) {
				$oplayer = $this->pl->getServer()->getPlayer($oplayername);
				mt_srand(ip2long($player->getAddress())+time());
				$elo = mt_rand(1, 45);
				$player->sendMessage($this->pl::PREFIX."You lost aginst {$oplayer->getName()}! ".f::RED." - $elo ELO");
				$c = new Config($this->pl->playercfg.$player->getName()
					.$this->pl->endings[$this->pl->configtype], $this->pl->configtype);
				$c->set("elo", (int)$c->get("elo")-$elo);
				$c->save();
				$oplayer->sendMessage($this->pl::PREFIX."You won aginst {$player->getName()}! ".f::GREEN." + $elo ELO");
				$c = new Config($this->pl->playercfg.$oplayer->getName()
					.$this->pl->endings[$this->pl->configtype], $this->pl->configtype);
				$c->set("elo", (int)$c->get("elo")+$elo);
				$c->save();
				$player->teleport($this->pl->getServer()->getDefaultLevel()->getSafeSpawn());
				$oplayer->teleport($this->pl->getServer()->getDefaultLevel()->getSafeSpawn());
				$level->unload();
				$id = (int) filter_var($level->getFolderName(), FILTER_SANITIZE_NUMBER_INT);
				$this->pl->resetArena($id);
				$this->pl->PlayerResetArray($player);
				$this->pl->PlayerResetArray($oplayer);
				$this->pl->getLobbyItems($player);
				$this->pl->getLobbyItems($oplayer);
				$this->pl->getScheduler()->cancelTask($this->getTaskId());
				return true;
			}
			if($inLevel == 1) {
				mt_srand(ip2long($player->getAddress())+time());
				$elo = mt_rand(1, 45);
				$player->sendMessage($this->pl::PREFIX."Looks like your opponent left the Game!".f::GREEN." + $elo ELO");
				$this->pl->PlayerResetArray($player);
				$c = new Config($this->pl->playercfg.$player->getName()
					.$this->pl->endings[$this->pl->configtype], $this->pl->configtype);
				$c->set("elo", (int)$c->get("elo")+$elo);
				$c->save();
				$player->teleport($this->pl->getServer()->getDefaultLevel()->getSafeSpawn());
				$level->unload();
				$id = (int) filter_var($level->getFolderName(), FILTER_SANITIZE_NUMBER_INT);
				$this->pl->resetArena($id);
				$this->pl->PlayerResetArray($player);
				$this->pl->getLobbyItems($player);
				$this->pl->getScheduler()->cancelTask($this->getTaskId());
				return true;
			}
			$player->addActionBarMessage(f::GRAY.$player->getName().f::YELLOW."($lifes)".f::WHITE." vs ".f::GRAY."$oplayername".f::YELLOW."($olifes)");
			$c = new Config($this->pl->playercfg.$player->getName()
				.$this->pl->endings[$this->pl->configtype], $this->pl->configtype);
			if($c->get("perk") == "enderpearl" and !$player->getInventory()->contains(Item::get(Item::ENDER_PEARL))) {
				$player->getInventory()->addItem(Item::get(Item::ENDER_PEARL)->setCustomName(f::LIGHT_PURPLE."Enderpearl"));
			}
			if($c->get("perk") == "switcher" and !$player->getInventory()->contains(Item::get(Item::SNOWBALL))) {
				$player->getInventory()->addItem(Item::get(Item::SNOWBALL)->setCustomName(f::YELLOW."Switcher"));
			}
		}
	}

}