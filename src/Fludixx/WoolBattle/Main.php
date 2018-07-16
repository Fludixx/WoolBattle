<?php

/*
Copyright 2018 Fludixx

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
*/

namespace Fludixx\WoolBattle;

use const pocketmine\COMPOSER_AUTOLOADER_PATH;
use pocketmine\entity\projectile\Arrow;
use pocketmine\event\entity\ProjectileHitBlockEvent;
use pocketmine\level\particle\DestroyBlockParticle;
use pocketmine\Player;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\level\Position;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\plugin\PluginBase;
use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\utils\Config;
use pocketmine\block\Block;
use pocketmine\utils\TextFormat as f;
use pocketmine\item\Item;
use pocketmine\entity\projectile\Snowball;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\math\Vector3;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\level\sound\ClickSound;
use pocketmine\event\entity\ProjectileHitEntityEvent;
use pocketmine\scheduler\Task;

class Main extends PluginBase implements Listener{

	public $lastquit = false;
    public $prefix = f::WHITE . "Wool" . f::GREEN . "Battle" . f::GRAY . " | " . f::WHITE;
    public $zuwenig = false;
    public $setup = 0;
    public $kabstand = 3;
    public $arenaids = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
    public $cooldown = [10, 9, 8, 7, 6, 5, 4, 3, 2, 1];
    public $lang = "eng";

    public function onEnable() {
        $this->getServer()->getPluginManager()->registerEvents($this,$this);
		$this->getLogger()->info($this->prefix . f::WHITE . f::AQUA . "WoolBattle by Fludixx" . f::GREEN .  " has been Enabled!");
		$this->getLogger()->info($this->prefix . "Please Report Errors on: ".f::UNDERLINE.f::AQUA."https://github.com/Fludixx/WoolBattle");
        $this->getServer()->getNetwork()->setName(f::WHITE . "Wool" . f::GREEN . "Battle");
        $this->getLogger()->info(getcwd());
        // Clearing Arenas
	    if(!is_dir("/cloud")) {@mkdir("/cloud");}
	    if(!is_dir("/cloud/cfg")) {@mkdir("/cloud/cfg");}
	    if(!is_dir("/cloud/maps")) {@mkdir("/cloud/maps");}
	    if(!is_dir("/cloud/elo")) {@mkdir("/cloud/elo");}

	    // language
	    $lang = new Config("/cloud/cfg/lang.yml", Config::YAML);
	    $this->lang = (string)$lang->get("lang");
	    if(!$this->lang) {
	    	$lang->set("lang", "eng");
	    }
	    if($this->lang == "deu") {$this->getLogger()->info($this->prefix . "Die Sprache wurde zu Deutsch gesetzt!");}
	    else {$this->getLogger()->info($this->prefix."The Language has been set to English!");}

        $arena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
        foreach($this->arenaids as $id) {
        	$arena->set("usew$id", false);
        }
        $arena->save();
        if(!$arena->get("spawnx") || !$arena->get("spawny") || !$arena->get("spawnz")) {
	        $arena->set("spawnx", 1);
	        $arena->set("spawny", 100);
	        $arena->set("spawnz", 1);
	        $arena->save();
        }
        if(!$arena->get("arenas")) {
        	$this->getLogger()->info(f::GREEN."Setting up Arenas...");
        	$arena->set("arenas", [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
        	$arena->save();
        }
        $this->arenaids = $arena->get("arenas");

        $perks = new Config("/cloud/cfg/perks.yml", Config::YAML);
        if(!$perks || !$perks->get("kapsel_y")) {
        	@mkdir("/cloud/cfg/");
	        $perks = new Config("/cloud/cfg/perks.yml", Config::YAML);
	        $perks->set("kapsel_y", 3);
	        $perks->save();
        }
		$this->kabstand = (int)$perks->get("kapsel_y");

        //Loading and Setting up levels
        $this->getServer()->loadLevel("lobby");
        $this->getServer()->getLevelByName("lobby")->setAutoSave(false);
	    foreach($this->arenaids as $id) {
		    $this->getServer()->loadLevel("woolbattle$id");
		    $this->getServer()->getLevelByName("woolbattle$id")->setAutoSave(false);
	    }

    }
    public function onJoin(PlayerJoinEvent $event) {
        $player = $event->getPlayer();
        $name = $event->getPlayer()->getName();
        $this->getWoolLobby($player);
	    $task = new Asker($this, $player);
	    $this->getScheduler()->scheduleRepeatingTask($task, 5);
     $kconfig = new Config("/cloud/users/".$name.".yml", Config::YAML);
     if(!$kconfig->get("woolkills") && !$kconfig->get("wooltode")){
        $kconfig->set("woolkills", 1);
        $kconfig->set("wooltode", 1);
        $kconfig->save();
     }
	    $welt = $this->getServer()->getLevelByName("lobby");
	    $cfg = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
	    $x = $cfg->get("spawnx");
	    $y = $cfg->get("spawny");
	    $z = $cfg->get("spawnz");
	    $pos = new Position($x, $y, $z, $welt);
	    $player->teleport($pos);
	    // Unbenuzte Config laden um bugs zu verhindern!
	    $c = new Config("/cloud/users/$name.yml", Config::YAML);
	    $c->set("ingame", false);
	    $c->set("woolcolor", false);
	    $c->set("ms", false);
	    $c->set("pw", false);
	    $c->set("leader", false);
	    $c->set("pos", 1);
	    $c->set("grouparray", array());
	    $c->save();
	    $c->set("spawnprotect", false);
	    $c->set("cooldown", false);
    }
    public function onQuit(PlayerQuitEvent $event) {
        $player = $event->getPlayer();
        $playername = $player->getName();
        $name = $player->getName();
	    $c = new Config("/cloud/users/$name.yml", Config::YAML);
	    //$c->set("ingame", false);
	    $c->set("woolcolor", false);
	    $c->set("ms", false);
	    //$c->set("pw", false);
	    $c->set("leader", false);
	    $c->set("pos", 1);
	    $c->set("grouparray", array());
	    $c->set("spawnprotect", false);
	    $c->set("cooldown", false);
	    $c->save();
	    $this->lastquit = $name;
    }
    public function getEq($spieler) {
        $spielername = $spieler->getName();
        $schere = Item::get(359, 0, 1);
        $schere->setCustomName(f::GOLD . "Schere");
        $bow = Item::get(261, 0, 1);
        $bow->setCustomName(f::GOLD . "Bogen");
        $enderpearl = Item::get(368, 0, 128);
        $enderpearl->setCustomName(f::GOLD . "Enderperle");
        $inventar = $spieler->getInventory();
        $arrow = Item::get(262, 0, 2);
        $knock = Enchantment::getEnchantment(12);
        $bowk = Enchantment::getEnchantment(20);
        $inf = Enchantment::getEnchantment(22);
        $effy = Enchantment::getEnchantment(15);
        $unbreak = Enchantment::getEnchantment(17);
        $bow->addEnchantment(new EnchantmentInstance($knock, 2));
        $schere->addEnchantment(new EnchantmentInstance($knock, 2));
        $schere->addEnchantment(new EnchantmentInstance($effy, 5));
        $bow->addEnchantment(new EnchantmentInstance($bowk, 2));
        $bow->addEnchantment(new EnchantmentInstance($inf, 1));
        $schere->addEnchantment(new EnchantmentInstance($unbreak, 4));
        $schere->setDamage(0);
        $bow->setDamage(0);
        $inventar->setItem(0, $schere);
        $inventar->setItem(1, $bow);
        $inventar->setItem(2, $enderpearl);
        $inventar->setItem(35, $arrow);
        $spieler->removeAllEffects();
       	$effect = Effect::getEffect(Effect::JUMP);
        $duration = 2333333;
        $amplification = 2;
        $visible = false;
        $instance = new EffectInstance($effect, $duration, $amplification, $visible);
        $spieler->addEffect($instance);
        $wool = new Config("/cloud/users/".$spielername.".yml", Config::YAML);
        $perk = $wool->get("woolperk");
        $perk2 = $wool->get("woolperk2");
        if($perk2 == "ekytra") {
            $this->getPerkElytra($spieler);
        }
        if($perk2 == "slime") {
            $this->getPerkSlime2($spieler);
        }
        if($perk2 == "kapsel") {
            $this->getPerkKapsel2($spieler);
        }
	    if($perk2 == "switch") {
		    $this->getPerkSwitch2($spieler);
	    }
        
        if($perk == "elytra") {
            $this->getPerkElytra($spieler);
        }
        if($perk == "slime") {
            $this->getPerkSlime($spieler);
        }
        if($perk == "kapsel") {
            $this->getPerkKapsel($spieler);
        }
	    if($perk == "switch") {
		    $this->getPerkSwitch($spieler);
	    }
        else {
            return false;
        }
    }
    public function getWoolLobby($player) {
        $this->clearHotbar($player);
        $playername = $player->getName();
        $inventar = $player->getInventory();
        $inventar->clearAll();
        $elytra = Item::get(188, 0, 1);
        $elytra->setCustomName(f::GREEN . "Perk" . f::WHITE . "Shop");
        $unbreak = Enchantment::getEnchantment(17);
        $elytra->addEnchantment(new EnchantmentInstance($unbreak, 4));
        $elytra2 = Item::get(189, 0, 1);
        $elytra2->setCustomName(f::GOLD . "2nd " . f::GREEN . "Perk" . f::WHITE . "Shop");
        $elytra2->addEnchantment(new EnchantmentInstance($unbreak, 4));
        $back = Item::get(351, 1, 1);
        $back->setCustomName(f::RED . "Back");
        $stats = Item::get(397, 0, 1);
        $stats->setCustomName(f::GOLD . "Stats");
        $inventar->setItem(0, $elytra);
        $inventar->setItem(1, $elytra2);
        $inventar->setItem(2, $stats);
	    $player->removeAllEffects();
    }
    public function getPerkElytra($player) {
        $playername = $player->getName();
        $inventar = $player->getInventory();
        $elytra = Item::get(444, 0, 1);
        $elytra->setCustomName(f::GREEN . "Elytra" . f::WHITE . "Perk");
        $unbreak = Enchantment::getEnchantment(17);
        $elytra->addEnchantment(new EnchantmentInstance($unbreak, 4));
        $air = Item::get(0, 0, 0);
        $player->getArmorInventory()->setChestplate($elytra);
        $player->getArmorInventory()->setHelmet($air);
        $player->getArmorInventory()->setLeggings($air);
        $player->getArmorInventory()->setBoots($air);
    }
    public function getPerkSlime($player) {
        $playername = $player->getName();
        $inventar = $player->getInventory();
        $elytra = Item::get(165, 0, 1);
        $elytra->setCustomName(f::GREEN . "Slime" . f::WHITE . "Perk");
        $unbreak = Enchantment::getEnchantment(17);
        $elytra->addEnchantment(new EnchantmentInstance($unbreak, 4));
        $inventar->setItem(2, $elytra);
        $air = Item::get(0, 0, 0);
        $player->getArmorInventory()->setChestplate($air);
        $player->getArmorInventory()->setHelmet($air);
        $player->getArmorInventory()->setLeggings($air);
        $player->getArmorInventory()->setBoots($air);
    }
	public function getPerkSwitch($player) {
		$playername = $player->getName();
		$inventar = $player->getInventory();
		$elytra = Item::get(Item::SNOWBALL, 0, 128);
		$unbreak = Enchantment::getEnchantment(17);
		$elytra->addEnchantment(new EnchantmentInstance($unbreak, 4));
		$inventar->setItem(2, $elytra);
		$air = Item::get(0, 0, 0);
		$player->getArmorInventory()->setChestplate($air);
		$player->getArmorInventory()->setHelmet($air);
		$player->getArmorInventory()->setLeggings($air);
		$player->getArmorInventory()->setBoots($air);
	}
	public function getPerkSwitch2($player) {
		$playername = $player->getName();
		$inventar = $player->getInventory();
		$elytra = Item::get(Item::SNOWBALL, 0, 128);
		$unbreak = Enchantment::getEnchantment(17);
		$elytra->addEnchantment(new EnchantmentInstance($unbreak, 4));
		$inventar->setItem(3, $elytra);
		$air = Item::get(0, 0, 0);
		$player->getArmorInventory()->setChestplate($air);
		$player->getArmorInventory()->setHelmet($air);
		$player->getArmorInventory()->setLeggings($air);
		$player->getArmorInventory()->setBoots($air);
	}
    public function getPerkSlime2($player) {
        $playername = $player->getName();
        $inventar = $player->getInventory();
        $elytra = Item::get(165, 0, 1);
        $elytra->setCustomName(f::GREEN . "Slime" . f::WHITE . "Perk");
        $unbreak = Enchantment::getEnchantment(17);
        $elytra->addEnchantment(new EnchantmentInstance($unbreak, 4));
        $inventar->setItem(3, $elytra);
        $air = Item::get(0, 0, 0);
        $player->getArmorInventory()->setChestplate($air);
        $player->getArmorInventory()->setHelmet($air);
        $player->getArmorInventory()->setLeggings($air);
        $player->getArmorInventory()->setBoots($air);
    }
    public function getPerkKapsel($player) {
        $playername = $player->getName();
        $inventar = $player->getInventory();
        $elytra = Item::get(341, 0, 1);
        $elytra->setCustomName(f::GREEN . "Platform" . f::WHITE . "Perk");
        $unbreak = Enchantment::getEnchantment(17);
        $elytra->addEnchantment(new EnchantmentInstance($unbreak, 4));
        $inventar->setItem(2, $elytra);
        $air = Item::get(0, 0, 0);
        $player->getArmorInventory()->setChestplate($air);
        $player->getArmorInventory()->setHelmet($air);
        $player->getArmorInventory()->setLeggings($air);
        $player->getArmorInventory()->setBoots($air);
    }
    public function getPerkKapsel2($player) {
        $playername = $player->getName();
        $inventar = $player->getInventory();
        $elytra = Item::get(341, 0, 1);
        $elytra->setCustomName(f::GREEN . "Platform" . f::WHITE . "Perk");
        $unbreak = Enchantment::getEnchantment(17);
        $elytra->addEnchantment(new EnchantmentInstance($unbreak, 4));
        $inventar->setItem(3, $elytra);
        $air = Item::get(0, 0, 0);
        $player->getArmorInventory()->setChestplate($air);
        $player->getArmorInventory()->setHelmet($air);
        $player->getArmorInventory()->setLeggings($air);
        $player->getArmorInventory()->setBoots($air);
    }
    public function getPerkShop($player) {
        $playername = $player->getName();
        $inventar = $player->getInventory();
        $back = Item::get(351, 1, 1);
        $back->setCustomName(f::RED . "Back");
        $elytra = Item::get(444, 0, 1);
        $elytra->setCustomName(f::GREEN . "Elytra" . f::WHITE . "Perk" . f::AQUA . "  [FREE]");
        $unbreak = Enchantment::getEnchantment(17);
        $elytra->addEnchantment(new EnchantmentInstance($unbreak, 4));
        $slime = Item::get(165, 0, 1);
        $slime->setCustomName(f::GREEN . "Slime" . f::WHITE . "Perk" . f::GOLD . "  [600 ELO]");
        $slime->addEnchantment(new EnchantmentInstance($unbreak, 4));
        $kapsel = Item::get(341, 0, 1);
        $kapsel->setCustomName(f::GREEN . "Platform" . f::WHITE . "Perk" . f::GOLD . "  [800 ELO]");
        $kapsel->addEnchantment(new EnchantmentInstance($unbreak, 4));
	    $switch = Item::get(Item::SNOWBALL);
	    $switch->setCustomName(f::GREEN . "Switcher" . f::WHITE . "Perk" . f::GOLD . "  [1000 ELO]");
	    $switch->addEnchantment(new EnchantmentInstance($unbreak, 4));
        $inventar->setItem(0, $elytra);
        $inventar->setItem(1, $slime);
        $inventar->setItem(2, $kapsel);
	    $inventar->setItem(3, $switch);
        $inventar->setItem(8, $back);
    }
    public function getPerkShop2($player) {
        $playername = $player->getName();
        $inventar = $player->getInventory();
        $back = Item::get(351, 1, 1);
        $back->setCustomName(f::RED . "Back");
        $elytra = Item::get(444, 0, 1);
        $elytra->setCustomName(f::GREEN . "Elytra" . f::WHITE . "Perk" . f::AQUA . "2  [FREE]");
        $unbreak = Enchantment::getEnchantment(17);
        $elytra->addEnchantment(new EnchantmentInstance($unbreak, 4));
        $slime = Item::get(165, 0, 1);
        $slime->setCustomName(f::GREEN . "Slime" . f::WHITE . "Perk" . f::GOLD . "2  [600 ELO]");
        $slime->addEnchantment(new EnchantmentInstance($unbreak, 4));
        $kapsel = Item::get(341, 0, 1);
        $kapsel->setCustomName(f::GREEN . "Platform" . f::WHITE . "Perk" . f::GOLD . "2  [800 ELO]");
        $kapsel->addEnchantment(new EnchantmentInstance($unbreak, 4));
	    $switch = Item::get(Item::SNOWBALL);
	    $switch->setCustomName(f::GREEN . "Switcher" . f::WHITE . "Perk" . f::GOLD . "2  [1000 ELO]");
	    $switch->addEnchantment(new EnchantmentInstance($unbreak, 4));
	    $inventar->setItem(0, $elytra);
	    $inventar->setItem(1, $slime);
	    $inventar->setItem(2, $kapsel);
	    $inventar->setItem(3, $switch);
	    $inventar->setItem(8, $back);
    }
    public function clearHotbar($spieler) {
        $spielername = $spieler->getName();
        $inventar = $spieler->getInventory();
        $air = Item::get(0, 0, 0);
        $inventar->setItem(0, $air);
        $inventar->setItem(1, $air);
        $inventar->setItem(2, $air);
        $inventar->setItem(3, $air);
        $inventar->setItem(4, $air);
        $inventar->setItem(5, $air);
        $inventar->setItem(6, $air);
        $inventar->setItem(7, $air);
        $inventar->setItem(8, $air);
    }
    
    public function onBreak(BlockBreakEvent $event) {
        $player = $event->getPlayer();
        $name = $player->getName();
        $inventar = $player->getInventory();
        $block = $event->getBlock();
        $redwool = Item::get(35, 14, 4);
        $bluewool = Item::get(35, 11, 4);
        $air = Item::get(0, 0, 0);
        if($event->getBlock()->getId() === 35) {
            $drops = array();
            $drops[] = $air;
            $event->setDrops($drops);
        }
        if ($block->getDamage() === 11 && $block->getId() === 35) {
            return true;
        }
        if ($block->getDamage() === 14 && $block->getId() === 35) {
            return true;
        }
        if ($block->getDamage() === 0 && $block->getId() === 165) {
            return true;
        } else {
            $event->setCancelled();
        }
        if($block->getId() === 35) {
            $wool = new Config("/cloud/users/".$name.".yml", Config::YAML);
            $wcolor = $wool->get("woolcolor");
            $event->setCancelled();
            if($wcolor == "red") {
                $inventar->addItem($redwool);
            } else {
                $inventar->addItem($bluewool);
            }
        }
    }
    public function onPlace(BlockPlaceEvent $event) {
    	$player = $event->getPlayer();
        $name = $event->getPlayer()->getName();
        $wool = new Config("/cloud/users/".$name.".yml", Config::YAML);
        $ingame = $wool->get("ingame");
        $block = $event->getBlock();
        if($ingame == true && !($block->getId() === 165) && $player->getLevel()->getName() != "lobby") {
            return true;
        } else {
            $event->setCancelled();
        }
    }

	function getOrdinalSuffix($number) {
		$ends = array('th','st','nd','rd','th','th','th','th','th','th');
		if ((($number % 100) >= 11) && (($number%100) <= 13))
			return $number. 'th';
		else
			return $number. $ends[$number % 10];
	}

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool
    {
        if ($command->getName() == "eq") {
            $this->getEq($sender);
            return true;
        }
        if ($command->getName() == "perkshop") {
            $this->getPerkShop($sender);
            return true;
        }
        if ($command->getName() == "cords") { 
            $x = $sender->getX();
            $y = $sender->getY();
            $z = $sender->getZ();
            $sender->sendMessage("X: $x Y: $y Z: $z");
            return true;
        }
        if ($command->getName() == "spectate") {
	        $name = $sender->getName();
	        $cp = new Config("/cloud/users/$name.yml", Config::YAML);
	        $inGame = $cp->get("ingame");
	        if($inGame) {
	        	if($this->lang == "deu") {
	        		$sender->sendMessage($this->prefix."Du kannst dich während einer Runde nicht teleportieren!");
		        } else {
			        $sender->sendMessage($this->prefix . "You cannot Teleport Yourself while u're in a Round!");
		        }
		        return false;
	        } else {
		        if (!empty($args['0'])) {
			        $player = $this->getServer()->getPlayer($args['0']);
			        if (!$player) {
				        if($this->lang == "deu") {
							$sender->sendMessage($this->prefix."Spieler nicht gefunden!");
				        } else {
					        $sender->sendMessage($this->prefix . "Player not found!");
				        }
				        return false;
			        } else {
				        $level = $player->getLevel()->getFolderName();
				        if ($level == "lobby") {
					        if($this->lang == "deu") {
								$sender->sendMessage($this->prefix."Spieler befindet sich in der Lobby!");
					        } else {
						        $sender->sendMessage($this->prefix . "Player is in the Lobby!");
					        }
					        return false;
				        } else {
					        $sender = $this->getServer()->getPlayer($sender->getName());
					        $pos = new Position($player->getX(), $player->getY(), $player->getZ(), $player->getLevel());
					        $sender->teleport($pos);
					        $sender->setGamemode(3);
					        return true;
				        }
			        }
		        } else {
			        if($this->lang == "deu") {
						$sender->sendMessage($this->prefix."Keinen Spielrnamen gefunden!");
			        } else {
				        $sender->sendMessage($this->prefix . "No Playername found!");
			        }
			        return false;
		        }
	        }
        }
        if($command->getName() == "lobby") {
        	$name = $sender->getName();
        	$cp = new Config("/cloud/users/$name.yml", Config::YAML);
        	$inGame = $cp->get("ingame");
        	if($inGame) {
		        if($this->lang == "deu") {
					$sender->sendMessage($this->prefix."Du kannst dich nicht während einer laufenden Runde Teleportieren!");
		        } else {
			        $sender->sendMessage($this->prefix . "You cannot Teleport Yourself while u're in a Round!");
		        }
        		return false;
	        } else {
		        $sender = $this->getServer()->getPlayer($name);
		        $level = $this->getServer()->getLevelByName("lobby");
		        $c = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
		        $x = $c->get("spawnx");
		        $y = $c->get("spawny");
		        $z = $c->get("spawnz");
		        $pos = new Position($x, $y, $z, $level);
		        $sender->setGamemode(0);
		        $sender->teleport($pos);
		        $sender->addTitle(f::GREEN . "Lobby");
		        return true;
	        }
        }
        if($command->getName() == "group") {
        	if(!empty($args['0'])) {

        		if($args['0'] == "inv" || $args['0'] == "invite" || $args['0'] == "add") {
					if(empty($args['1'])) {
						$this->throwNoPlayer($sender);
					} else {
						$name = $sender->getName();
						$c = new Config("/cloud/users/$name.yml", Config::YAML);
						$op = $this->getServer()->getPlayer($args['1']);
						$oname = $op->getName();
						if($this->lang == "deu") {
							$sender->sendMessage($this->prefix . "Erfolgreich $oname eingelden!");
							$op->sendMessage($this->prefix . "$name hat dich in deine Gruppe eingeladen!");
							$op->sendMessage($this->prefix . "Verwende /g accept um ihr beizutreten!");
						} else {
							$sender->sendMessage($this->prefix . "Successfully invited $oname!");
							$op->sendMessage($this->prefix . "$name wants you tp be in his Group!");
							$op->sendMessage($this->prefix . "Use /g accept to accept the Invite!");
						}
						$oc = new Config("/cloud/users/$oname.yml", Config::YAML);
						$oc->set("invited", "$name");
						$oc->save();
						return true;
					}
		        }
		        if($args['0'] == "accept" || $args['0'] == "ok" || $args['0'] == "apt") {
				        $name = $sender->getName();
				        $c = new Config("/cloud/users/$name.yml", Config::YAML);
				        $op = $this->getServer()->getPlayer((string)$c->get("invited"));
				        if(!$op) {
					        if($this->lang == "deu") {
					        	$sender->sendMessage($this->prefix."Ein Fehler ist aufgetretten!");
					        } else {
						        $sender->sendMessage($this->prefix . "An Error occured");
					        }
				        	return false;
				        } else {
				        	$oname = $op->getName();
				        	$oc = new Config("/cloud/users/$oname.yml", Config::YAML);
				        	$grouparray = (array)$c->get("grouparray");
				        	$grouparray[0] = $name;
				        	$lastindex = key( array_slice( $grouparray, -1, 1, TRUE ) );
				        	$nextIndex = $lastindex+1;
					        $grouparray[$nextIndex] = $oname;
				        	$oc->set("grouparray", $grouparray);
				        	$c->set("leader", "$oname");
				        	$oc->set("leader", "$oname");
				        	$c->set("lastaction", "Added $name to grouparray in /cloud/users/$oname.yml");
				        	$oc->save();
				        	$c->save();
					        if($this->lang == "deu") {
					        	$sender->sendMessage($this->prefix."Einladung angenommen!");
					        	$op->sendMessage($this->prefix."$name ist deine Gruppe beigetreten!");
					        } else {
						        $sender->sendMessage($this->prefix . "Sucessfully accepted");
						        $op->sendMessage($this->prefix . "$name has Joind you're Group");
					        }
				        	return true;
				        }
		        }
		        if($args['0'] == "lst" || $args['0'] == "list" || $args['0'] == "show") {
        			$name = $sender->getName();
			        $c = new Config("/cloud/users/$name.yml", Config::YAML);
			        $leader = $this->getServer()->getPlayer((string)$c->get("leader"));
			        if(!$leader) {
				        if($this->lang == "deu") {
				        	$sender->sendMessage($this->prefix."Du bist in keiner Gruppe!");
				        } else {
					        $sender->sendMessage($this->prefix . "You're not in a Group");
				        }
			        	return false;
			        } else {
			        	$lname = $leader->getName();
			        	$lc = new Config("/cloud/users/$lname.yml", Config::YAML);
			        	$grouparray = (array)$lc->get("grouparray");
				        if($this->lang == "deu") {
							$sender->sendMessage($this->prefix."Hier ist eine Liste deiner Gruppe:");
				        } else {
					        $sender->sendMessage($this->prefix . "Here's a List of your Group:");
				        }
			        	$counter = 1;
			        	foreach($grouparray as $member) {
			        		if($counter != 0) {
						        $sender->sendMessage(f::YELLOW . $this->getOrdinalSuffix($counter) . " -> " . f::WHITE . "$member");
						        $counter++;
					        } else {
						        $counter++;
					        }
				        }
				        return true;
			        }
		        }
		        if($args['0'] == "kck" || $args['0'] == "kick" || $args['0'] == "remove") {
			        if(empty($args['1'])) {
				        $this->throwNoPlayer($sender);
			        } else {
						$name = $sender->getName();
			        	$c = new Config("/cloud/users/$name.yml");
			        	$leadername = $c->get("leader");
			        	if($leadername == $name) {
			        		$kick = $this->getServer()->getPlayer((string)$args['1']);
							$kickname = $kick->getName();
							$grouparray = (array)$c->get("grouparray");
							$kickindex = array_search($kickname, $grouparray);
							unset($grouparray[$kickindex]);
							$c->set("grouparray", (array)$grouparray);
							$c->save();
							$kick->sendMessage($this->prefix."You got kicked by $name!");
							$sender->sendMessage($this->prefix."You kicked $kickname!");
							return true;
				        } else {
							$sender->sendMessage($this->prefix."You're not the Leader of the Group!");
							return false;
				        }
			        }
		        }
		        if($args['0'] == "del" || $args['0'] == "dl" || $args['0'] == "delete" || $args['0'] == "leave" || $args['0'] == "l") {
				        $name = $sender->getName();
				        $c = new Config("/cloud/users/$name.yml");
				        $leadername = $c->get("leader");
				        if($leadername == $name) {
				        	$grouparray = $c->get("grouparray");
				        	foreach($grouparray as $member) {
				        		$player = $this->getServer()->getPlayer((string)$member);
						        if($this->lang == "deu") {
									$player->sendMessage($this->prefix,"Die Gruppe wurde aufgelöst!");
						        } else {
							        $player->sendMessage($this->prefix . "The Group got Deleted!");
						        }
				        		$name = $player->getName();
				        		$c = new Config("/cloud/users/$name.yml", Config::YAML);
				        		$c->set("leader", false);
				        		$c->set("grouparray", array());
				        		$c->save();
				        		return true;
					        }
					        if($this->lang == "deu") {
								$sender->sendMessage($this->prefix."Du hast die Gruppe verlassen!");
					        } else {
						        $sender->sendMessage($this->prefix . "You left the Group!");
					        }
					        return true;
				        } else {
				        	$c->set("leader", false);
				        	$leader = $this->getServer()->getPlayer($leadername);
				        	$cl = new Config("/cloud/users/$leadername.yml", Config::YAML);
				        	$grouparray =  (array)$cl->get("grouparray");
					        $playerindex = array_search($name, $grouparray);
					        unset($grouparray[$playerindex]);
					        $cl->set("grouparray", $grouparray);
					        $cl->save();
					        foreach($grouparray as $member) {
						        $player = $this->getServer()->getPlayer((string)$member);
						        if($this->lang == "deu") {
						        	$player->sendMessage($this->prefix."$name hat die Gruppe Verlassen!");
						        } else {
							        $player->sendMessage($this->prefix . "$name has left the Group");
						        }
					        }
					        if($this->lang == "deu") {
								$sender->sendMessage($this->prefix."Du hast die Gruppe verlassen!");
					        } else {
						        $sender->sendMessage($this->prefix . "You left the Group");
					        }
					        return true;
				        }
			        }
		        }

	        } else {
        		$sender->sendMessage($this->prefix."/group <kick/invite/accept/list/leave/delete>");
        		return false;
	        }
	        if($command->getName() == "lastaction") {
        		$name = $sender->getName();
        		$c = new Config("/cloud/users/$name.yml", Config::YAML);
        		$sender->sendMessage($c->get("lastaction"));
        		return true;
	        }
        }
    public function onInteract(PlayerInteractEvent $event) {
    	$player = $event->getPlayer();
        $playername = $player->getName();
        $inventar = $player->getInventory();
        $item = $player->getInventory()->getItemInHand();
        if ($item->getCustomName() == f::GREEN . "Perk" . f::WHITE . "Shop") {
            $click = new ClickSound($player);
            $player->getLevel()->addSound($click);
            $this->clearHotbar($player);
            $this->getPerkShop($player);
        }
        if ($item->getCustomName() == f::RED . "Back") {
            $click = new ClickSound($player);
            $player->getLevel()->addSound($click);
            $this->getWoolLobby($player);
    }
        if ($item->getCustomName() == f::GREEN . "Elytra" . f::WHITE . "Perk" . f::AQUA . "  [FREE]") {
            $click = new ClickSound($player);
            $player->getLevel()->addSound($click);
            $wool = new Config("/cloud/users/".$playername.".yml", Config::YAML);
            $ifalready = $wool->get("woolperk");
            if($ifalready == "elytra") {
	            if($this->lang == "deu") {
					$player->sendMessage($this->prefix.f::RED."Du hast dieses Perk schon ausgerüstet!");
	            } else {
		            $player->sendMessage($this->prefix . f::RED . "You already have this Perk selected!");
	            }
                return 1;
            }
            $player->sendMessage($this->prefix . "You selected" . f::GREEN . " Elytra Perk " . f::WHITE . "as Perk!");
            $wool->set("woolperk", "elytra");
            $wool->save();
        }
        if ($item->getCustomName() == f::GREEN . "Elytra" . f::WHITE . "Perk" . f::AQUA . "2  [FREE]") {
            $click = new ClickSound($player);
            $player->getLevel()->addSound($click);
            $wool = new Config("/cloud/users/".$playername.".yml", Config::YAML);
            $ifalready = $wool->get("woolperk2");
            if($ifalready == "elytra") {
	            if($this->lang == "deu") {
		            $player->sendMessage($this->prefix.f::RED."Du hast dieses Perk schon ausgerüstet!");
	            } else {
		            $player->sendMessage($this->prefix . f::RED . "You already have this Perk selected!");
	            }
                return 1;
            }
            $player->sendMessage($this->prefix . "You selected" . f::GREEN . " Elytra Perk " . f::WHITE . "as Perk!");
            $wool->set("woolperk2", "elytra");
            $wool->save();
        }
        if ($item->getCustomName() == f::GREEN . "Slime" . f::WHITE . "Perk" . f::GOLD . "  [600 ELO]") {
            $click = new ClickSound($player);
            $player->getLevel()->addSound($click);
            $wool = new Config("/cloud/users/".$playername.".yml", Config::YAML);
            $ifalready = $wool->get("woolperk");
            if($ifalready == "slime") {
	            if($this->lang == "deu") {
		            $player->sendMessage($this->prefix.f::RED."Du hast dieses Perk schon ausgerüstet!");
	            } else {
		            $player->sendMessage($this->prefix . f::RED . "You already have this Perk selected!");
	            }
                return 1;
            }
            $elo = new Config("/cloud/elo/".$playername.".yml", Config::YAML);
            $celo = $elo->get("elo");
            if($celo < 600) {
                $player->sendMessage($this->prefix . f::RED . "Not enough elo!");
                return false;
            }
            $player->sendMessage($this->prefix . "You selected" . f::GREEN . " Slime Perk " . f::WHITE . "as Perk!");
            $wool->set("woolperk", "slime");
            $wool->save();
        }
        if ($item->getCustomName() == f::GREEN . "Slime" . f::WHITE . "Perk" . f::GOLD . "2  [600 ELO]") {
            $click = new ClickSound($player);
            $player->getLevel()->addSound($click);
            $wool = new Config("/cloud/users/".$playername.".yml", Config::YAML);
            $ifalready = $wool->get("woolperk2");
            if($ifalready == "slime") {
	            if($this->lang == "deu") {
		            $player->sendMessage($this->prefix.f::RED."Du hast dieses Perk schon ausgerüstet!");
	            } else {
		            $player->sendMessage($this->prefix . f::RED . "You already have this Perk selected!");
	            }
                return 1;
            }
            $elo = new Config("/cloud/elo/".$playername.".yml", Config::YAML);
            $celo = $elo->get("elo");
            if($celo < 600) {
                $player->sendMessage($this->prefix . f::RED . "Not enough elo!");
                return false;
            }
            $player->sendMessage($this->prefix . "You selected" . f::GREEN . " Slime Perk " . f::WHITE . "as 2nd Perk!");
            $wool->set("woolperk2", "slime");
            $wool->save();
        }
	    if($item->getCustomName() == f::GREEN . "Switcher" . f::WHITE . "Perk" . f::GOLD . "  [1000 ELO]") {
		    $click = new ClickSound($player);
		    $player->getLevel()->addSound($click);
		    $wool = new Config("/cloud/users/".$playername.".yml", Config::YAML);
		    $ifalready = $wool->get("woolperk");
		    if($ifalready == "switch") {
			    if($this->lang == "deu") {
				    $player->sendMessage($this->prefix.f::RED."Du hast dieses Perk schon ausgerüstet!");
			    } else {
				    $player->sendMessage($this->prefix . f::RED . "You already have this Perk selected!");
			    }
			    return 1;
		    }
		    $elo = new Config("/cloud/elo/".$playername.".yml", Config::YAML);
		    $celo = $elo->get("elo");
		    if($celo <= 1000) {
			    $player->sendMessage($this->prefix . f::RED . "Not enough elo!");
			    return false;
		    }
		    $player->sendMessage($this->prefix . "You selected" . f::GREEN . " Switcher Perk " . f::WHITE . "as Perk!");
		    $wool->set("woolperk", "switch");
		    $wool->save();
	    }
	    if($item->getCustomName() == f::GREEN . "Switcher" . f::WHITE . "Perk" . f::GOLD . "2  [1000 ELO]") {
		    $click = new ClickSound($player);
		    $player->getLevel()->addSound($click);
		    $wool = new Config("/cloud/users/".$playername.".yml", Config::YAML);
		    $ifalready = $wool->get("woolperk2");
		    if($ifalready == "switch") {
			    if($this->lang == "deu") {
				    $player->sendMessage($this->prefix.f::RED."Du hast dieses Perk schon ausgerüstet!");
			    } else {
				    $player->sendMessage($this->prefix . f::RED . "You already have this Perk selected!");
			    }
			    return 1;
		    }
		    $elo = new Config("/cloud/elo/".$playername.".yml", Config::YAML);
		    $celo = $elo->get("elo");
		    if($celo <= 1000) {
			    $player->sendMessage($this->prefix . f::RED . "Not enough elo!");
			    return false;
		    }
		    $player->sendMessage($this->prefix . "You selected" . f::GREEN . " Switcher Perk " . f::WHITE . "as 2nd Perk!");
		    $wool->set("woolperk2", "switch");
		    $wool->save();
	    }
        if ($item->getCustomName() == f::GREEN . "Slime" . f::WHITE . "Perk") {
	        $name = $player->getName();
	        $c = new Config("/cloud/users/$name.yml", Config::YAML);
	        if ($c->get("cooldown") == true) {
		        if($this->lang == "deu") {
			        $player->sendMessage($this->prefix.f::RED."5 Sekunden Cooldown!");
		        } else {
			        $player->sendMessage($this->prefix . f::RED . "5 Seconds Cooldown!");
		        }
		        return false;
	        } else {
		        $c->set("cooldown", true);
		        $c->save();
		        $task = new Cooldown($this, $player);
		        $this->getScheduler()->scheduleDelayedTask($task, 100);
		        $this->setPrice($player, 32);
		        if ($this->zuwenig == true) {
			        $this->zuwenig = false;
			        return 1;
		        }
		        $yaw = $player->getYaw();
		        if ($yaw < 45 && $yaw > 0 || $yaw < 360 && $yaw > 315) {
			        $player->setMotion(new Vector3(0, 3, 4));
		        } elseif ($yaw < 135 && $yaw > 45) {
			        $player->setMotion(new Vector3(-4, 3, 0));
		        } elseif ($yaw < 225 && $yaw > 135) {
			        $player->setMotion(new Vector3(0, 3, -4));
		        } elseif ($yaw < 315 && $yaw > 225) {
			        $player->setMotion(new Vector3(4, 3, 0));
		        }
	        }
        }
        if ($item->getCustomName() == f::GREEN . "Platform" . f::WHITE . "Perk") {
	        $name = $player->getName();
	        $c = new Config("/cloud/users/$name.yml", Config::YAML);
	        if ($c->get("cooldown") == true) {
		        if($this->lang == "deu") {
			        $player->sendMessage($this->prefix.f::RED."10 Sekunden Cooldown!");
		        } else {
			        $player->sendMessage($this->prefix . f::RED . "10 Seconds Cooldown!");
		        }
		        return false;
	        } else {
		        $c->set("cooldown", true);
		        $c->save();
		        $task = new Cooldown($this, $player);
		        $this->getScheduler()->scheduleDelayedTask($task, 200);
		        $this->setPrice($player, 64);
		        if ($this->zuwenig == true) {
			        $this->zuwenig = false;
			        return 1;
		        }
		        $block = Block::get(165, 0);
		        $name = $player->getName();
		        $wool = new Config("/cloud/users/" . $name . ".yml", Config::YAML);
		        $wcolor = $wool->get("woolcolor");
		        if ($wcolor == "red") {
			        $rand = Block::get(35, 14);
		        } else {
			        $rand = Block::get(35, 11);
		        }
		        // RettungsKapsel
		        $x = $player->getX();
		        $y = $player->getY();
		        $z = $player->getZ();
		        $y = $y - (int)$this->kabstand;
		        $pos = new Vector3($x, $y, $z);
		        $level = $player->getLevel();
		        $level->setBlock($pos, $block);
		        $x = $player->getX() + 1;
		        $y = $player->getY();
		        $z = $player->getZ();
		        $y = $y - (int)$this->kabstand;
		        $pos = new Vector3($x, $y, $z);
		        $level->setBlock($pos, $block);
		        $x = $player->getX() - 1;
		        $y = $player->getY();
		        $z = $player->getZ();
		        $y = $y - (int)$this->kabstand;
		        $pos = new Vector3($x, $y, $z);
		        $level->setBlock($pos, $block);
		        $x = $player->getX();
		        $y = $player->getY();
		        $z = $player->getZ() - 1;
		        $y = $y - (int)$this->kabstand;
		        $pos = new Vector3($x, $y, $z);
		        $level->setBlock($pos, $block);
		        $x = $player->getX();
		        $y = $player->getY();
		        $z = $player->getZ() + 1;
		        $y = $y - (int)$this->kabstand;
		        $pos = new Vector3($x, $y, $z);
		        $level->setBlock($pos, $block);
		        $x = $player->getX() + 1;
		        $y = $player->getY();
		        $z = $player->getZ() + 1;
		        $y = $y - (int)$this->kabstand;
		        $pos = new Vector3($x, $y, $z);
		        $level->setBlock($pos, $rand);
		        $x = $player->getX() - 1;
		        $y = $player->getY();
		        $z = $player->getZ() - 1;
		        $y = $y - (int)$this->kabstand;
		        $pos = new Vector3($x, $y, $z);
		        $level->setBlock($pos, $rand);
		        $x = $player->getX() + 1;
		        $y = $player->getY();
		        $z = $player->getZ() - 1;
		        $y = $y - (int)$this->kabstand;
		        $pos = new Vector3($x, $y, $z);
		        $level->setBlock($pos, $rand);
		        $x = $player->getX() - 1;
		        $y = $player->getY();
		        $z = $player->getZ() + 1;
		        $y = $y - (int)$this->kabstand;
		        $pos = new Vector3($x, $y, $z);
		        $level->setBlock($pos, $rand);
		        // RettungsKapsel Ende

	        }
        }
	    if ($item->getCustomName() == f::GOLD . "Stats") {
		    $click = new ClickSound($player);
		    $player->getLevel()->addSound($click);
		    $name = $player->getName();
		    $c = new Config("/cloud/users/$name.yml", Config::YAML);
		    $kills = $c->get("woolkills");
		    $tode = $c->get("wooltode");
		    $kd = $kills / $tode;
		    $player->sendMessage(f::GREEN."Kills: ".f::WHITE."$kills");
		    $player->sendMessage(f::GREEN."Tode: ".f::WHITE."$tode");
		    $player->sendMessage(f::GREEN."K/D: ".f::WHITE."$kd");
	    }
        if ($item->getCustomName() == f::GREEN . "Platform" . f::WHITE . "Perk" . f::GOLD . "  [800 ELO]") {
            $click = new ClickSound($player);
            $player->getLevel()->addSound($click);
            $wool = new Config("/cloud/users/".$playername.".yml", Config::YAML);
            $ifalready = $wool->get("woolperk");
            if($ifalready == "kapsel") {
	            if($this->lang == "deu") {
		            $player->sendMessage($this->prefix.f::RED."Du hast dieses Perk schon ausgerüstet!");
	            } else {
		            $player->sendMessage($this->prefix . f::RED . "You already have this Perk selected!");
	            }
                return 1;
            }
            $elo = new Config("/cloud/elo/".$playername.".yml", Config::YAML);
            $celo = $elo->get("elo");
            if($celo < 800) {
                $player->sendMessage($this->prefix . f::RED . "Zu wenig Elo!");
                return false;
            }
            $player->sendMessage($this->prefix . "You selected" . f::GREEN . " Platform Perk " . f::WHITE . " as your Perk!");
            $wool->set("woolperk", "kapsel");
            $wool->save();
        }
        if ($item->getCustomName() == f::GREEN . "Platform" . f::WHITE . "Perk" . f::GOLD . "2  [800 ELO]") {
            $click = new ClickSound($player);
            $player->getLevel()->addSound($click);
            $wool = new Config("/cloud/users/".$playername.".yml", Config::YAML);
            $ifalready = $wool->get("woolperk2");
            if($ifalready == "kapsel") {
	            if($this->lang == "deu") {
		            $player->sendMessage($this->prefix.f::RED."Du hast dieses Perk schon ausgerüstet!");
	            } else {
		            $player->sendMessage($this->prefix . f::RED . "You already have this Perk selected!");
	            }
                return 1;
            }
            $elo = new Config("/cloud/elo/".$playername.".yml", Config::YAML);
            $celo = $elo->get("elo");
            if($celo < 800) {
                $player->sendMessage($this->prefix . f::RED . "Zu wenig Elo!");
                return false;
            }
            $player->sendMessage($this->prefix . "You selected" . f::GREEN . " Platform Perk " . f::WHITE . "as 2nd Perk!");
            $wool->set("woolperk2", "kapsel");
            $wool->save();
        }
        if($item->getCustomName() == f::GOLD . "2nd " . f::GREEN . "Perk" . f::WHITE . "Shop") {
            $config = new Config("/cloud/elo/".$playername.".yml");
            $elo = $config->get("elo");
            if($elo > 1000) {
            $click = new ClickSound($player);
            $player->getLevel()->addSound($click);
            $this->clearHotbar($player);
            $this->getPerkShop2($player);
            } else {
	            if($this->lang == "deu") {
		            $player->sendMessage($this->prefix.f::RED."Du brauchst 1000 Elo um das zu verwenden!");
	            } else {
		            $player->sendMessage($this->prefix . f::RED . "You need at least 1000 Elo to use that!");
	            }
                return false;
            }
        }
    }
public function onHunger(PlayerExhaustEvent $event) {
    $player = $event->getPlayer();
    $player->setFood(20);
    $player->setHealth(20);
}
    public function onEntityDamage(EntityDamageEvent $event){
        if($event->getCause() == EntityDamageEvent::CAUSE_FALL){
            $event->setCancelled();
        }elseif($event instanceof EntityDamageByEntityEvent){
            $damager = $event->getDamager();
            $entity = $event->getEntity();
            if($damager instanceof Player && $entity instanceof Player){
            }
        }
    }
    public function countWool(Player $player): int{
        $all = 0;
        $inv = $player->getInventory();
        $content = $inv->getContents();
        foreach ($content as $item) {
            if ($item->getId() == 35) {
                $c = $item->count;

                $all = $all + $c;
            }
        }

        return $all;
    }

    public function rmWool(Player $player){
        $name = $player->getName();
        $wool = new Config("/cloud/users/".$name.".yml", Config::YAML);
        $wcolor = $wool->get("woolcolor");
        if($wcolor == "red") {
        $player->getInventory()->remove(Item::get(35, 14, 1));
        } else {
        $player->getInventory()->remove(Item::get(35, 11, 1));
        }
    }

    public function addWool(Player $player, int $i){
        $name = $player->getName();
        $wool = new Config("/cloud/users/".$name.".yml", Config::YAML);
        $wcolor = $wool->get("woolcolor");
        $inv = $player->getInventory();
        $c = 0;

        while($c < $i){
            if($wcolor == "red") {
            $inv->addItem(Item::get(35, 14, 1));
            } else {
            $inv->addItem(Item::get(35, 11, 1));
            }
            $c++;
        }
    }
    public function setPrice($player, int $price) {
            $woola = $this->countWool($player);
	        $name = $player->getName();
	        $wool = new Config("/cloud/users/".$name.".yml", Config::YAML);
	        $wcolor = $wool->get("woolcolor");
            if($woola < $price) {
            	$need = (int)$price-(int)$woola;
	            if($this->lang == "deu") {
	            	if($wcolor == "red") {
			            $player->sendMessage($this->prefix . f::BOLD . f::RED . "Du hast nicht genügend Wolle! " .
				            f::RESET .
				            f::WHITE . "Dir fehlen: " . f::RED . "$need Wolle" . f::WHITE . "!");
		            } else {
			            $player->sendMessage($this->prefix . f::BOLD . f::RED . "Du hast nicht genügend Wolle! " .
				            f::RESET .
			            f::WHITE .
			            "Dir fehlen: " . f::BLUE . "$need Wolle" . f::WHITE . "!");
		            }
	            } else {
		            if($wcolor == "red") {
			            $player->sendMessage($this->prefix . f::BOLD . f::RED . "Not enough Wool! " . f::RESET .
				            f::WHITE . "You need: " .
				            f::RED . "$need " . f::WHITE . "more".f::RED." Wool".f::WHITE."!");
		            } else {
			            $player->sendMessage($this->prefix . f::BOLD . f::RED . "Not enough Wool! " . f::RESET . f::WHITE .
			            "You need: " .
				            f::BLUE . "$need " . f::WHITE . "more".f::BLUE." Wool".f::WHITE."!");
		            }
	            }
                $this->zuwenig = true;
                return false;
            }
            $this->zuwenig = false;
            $woolprice = $price;
            $wooltot = $woola-$woolprice;
            $this->rmWool($player);
            $this->addWool($player, $wooltot);
            return true;
    }
    
    public function onDamage(EntityDamageEvent $event) {
        if ($event instanceof EntityDamageByEntityEvent) {
            $playername = $event->getEntity()->getName();
            $cplayer = new Config("/cloud/users/".$playername.".yml", Config::YAML);
            $spawnprtotection = $cplayer->get("spawnprotect");
	        $damager = $event->getDamager();
            if($cplayer->get("ingame") == true) {
            	if($spawnprtotection == true && $damager instanceof Player) {
            		$event->setCancelled(true);
            		$damager->sendMessage($this->prefix."Der Spieler hat Spawnschutz!");
            		return false;
	            }
                return 1;
            }
            $event->setCancelled();
            $player = $event->getEntity();
            $damager = $event->getDamager();
            if ($player instanceof Player && $damager instanceof Player) {
                $playername = $player->getName();
                $damagername = $damager->getName();
                $cplayer = new Config("/cloud/users/".$playername.".yml", Config::YAML);
                $cdamager = new Config("/cloud/users/".$damagername.".yml", Config::YAML);
                if($cdamager->get("leader") != false) {
	                if ($cdamager->get("leader") == $damagername) {
						if($cplayer->get("leader") == false) {
							if($this->lang == "deu") {
								$damager->sendMessage($this->prefix . "Du musst der Leader sein um einen Gruppen kampf zu starten!");
							} else {
								$damager->sendMessage($this->prefix."Only the Leader of an Group can start a Battle!");
							}
							return false;
						} else {
							if($this->lang == "deu") {
								$player->sendMessage($this->prefix . "$damagername hat dich zu Einem Gruppenkampf herrausgefordert!");
							} else {
								$player->sendMessage($this->prefix."$damagername Challanged wants to start a Groupbattle with you!");
							}
						}
	                } else {
		                if($this->lang == "deu") {
			                $damager->sendMessage($this->prefix . "Dein Gegner muss in einer Gruppe sein!");
		                } else {
		                	$damager->sendMessage($this->prefix."Your opponent mut be in a Group!");
		                }
	                	return false;
	                }
                }
                $cplayer->set("ms", $damagername);
                $cplayer->save();
	            if($this->lang == "deu") {
		            $player->sendMessage($this->prefix . f::GREEN . $damagername . f::WHITE . " hat dich heraus gefordert!");
	            } else {
	            	$player->sendMessage($this->prefix.f::GREEN."$damagername".f::WHITE." challenged you!");
	            }
	            if($this->lang == "deu") {
		            $damager->sendMessage($this->prefix . "Kampfaufforderung an " . f::GREEN . $playername . f::WHITE . " erfolgreich verschickt!");#
	            } else {
	            	$damager->sendMessage($this->prefix."Request send to ".f::GREEN."$playername");
	            }
                if($cdamager->get("ms") == $playername) {
	                $arena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
	                foreach($this->arenaids as $id) {
	                	$arenaid = "w$id";
		                $$arenaid = $arena->get("usew$id");
	                }
	                foreach ($this->arenaids as $id) {
	                	$arenaname = "w$id";
		                if (!$$arenaname) {
			                $this->getArena($player, $damager, "$id", false);
			                return true;
		                }
		                $lastarena = array_values(array_slice($this->arenaids, -1))[0];
			                if($id == $lastarena && $$arenaname)  {
			                	$player->sendMessage($this->prefix.f::RED."All Arenas are full!");
				                $damager->sendMessage($this->prefix.f::RED."All Arenas are full!");
				                return false;
			                }
		                }
	                }
                } else {
                    return false;
                }
            }
        }
    public function getArena($player, $player2, $level, bool $clanwar)
    {
	    $player->sendMessage($this->prefix . "Arena found! (woolbattle$level)");
	    $player2->sendMessage($this->prefix . "Arena found! (woolbattle$level)");
	    $arena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
	    $posx = $arena->get("x1");
	    $posy = $arena->get("y1");
	    $posz = $arena->get("z1");
	    $arena->set("usew$level", true);
	    $arena->save();
	    if (!$this->getServer()->getLevelByName("woolbattle$level")) {
		    $player->sendMessage(f::RED . "E: Level not found (???) Unexpected");
		    $player2->sendMessage(f::RED . "E: Level not found (???) Unexpected");
	    }
	    $this->getServer()->getLevelByName("woolbattle$level")->setAutoSave(false);
	    $welt = $this->getServer()->getLevelByName("woolbattle$level");

	    if($clanwar == false) {
		    $pos = new Position($posx, $posy, $posz, $welt);
		    $player->teleport($pos);
		    $posx = $arena->get("x2");
		    $posy = $arena->get("y2");
		    $posz = $arena->get("z2");
		    $pos = new Position($posx, $posy, $posz, $welt);
		    $player2->teleport($pos);
		    $playername = $player->getName();
		    $playername2 = $player2->getName();
		    $cplayer = new Config("/cloud/users/" . $playername . ".yml", Config::YAML);
		    $cplayer2 = new Config("/cloud/users/" . $playername2 . ".yml", Config::YAML);
		    $cplayer->set("ingame", true);
		    $cplayer->set("woolcolor", "red");
		    $cplayer2->set("ingame", true);
		    $cplayer->set("ms", false);
		    $cplayer2->set("ms", false);
		    $cplayer->set("pw", $playername2);
		    $cplayer2->set("pw", $playername);
		    $cplayer->set("lifes", 10);
		    $cplayer2->set("lifes", 10);
		    $cplayer->set("pos", 1);
		    $cplayer2->set("pos", 2);
		    $cplayer->save();
		    $cplayer2->save();
		    $this->getEq($player);
		    $this->getEq($player2);
		    return true;
	    }
	    elseif($clanwar == true) {
		    $playername = $player->getName();
		    $playername2 = $player2->getName();
		    $c1 = new Config("/cloud/users/" . $playername . ".yml", Config::YAML);
		    $c2 = new Config("/cloud/users/" . $playername2 . ".yml", Config::YAML);
		    $squad1 = (array)$c1->get("grouparray");
		    $squad2 = (array)$c2->get("grouparray");
		    $arena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
		    $arena->set("usew$level", true);
		    $arena->save();
		    $posx = $arena->get("x1");
		    $posy = $arena->get("y1");
		    $posz = $arena->get("z1");
		    $pos = new Position($posx, $posy, $posz, $welt);
		    foreach($squad1 as $member) {
		    	$player = $this->getServer()->getPlayer((string)$member);
		    	$player->teleport($pos);
		    	$pn = $player->getName();
		    	$c = new Config("/cloud/users/$pn.yml", Config::YAML);
		    	$c->set("lifes", 20);
		    	$c->set("pos", 1);
		    	$c->set("ingame", true);
			    $c->set("clanwar", true);
		    	$c->set("ms", false);
		    	$c->set("pw", $playername2);
		    	$c->set("woolcolor", "red");
		    	$c->save();
		    	$this->getEq($player);
		    }
		    $posx = $arena->get("x2");
		    $posy = $arena->get("y2");
		    $posz = $arena->get("z2");
		    $pos = new Position($posx, $posy, $posz, $welt);
		    foreach($squad2 as $member) {
			    $player = $this->getServer()->getPlayer((string)$member);
			    $player->teleport($pos);
			    $pn = $player->getName();
			    $c = new Config("/cloud/users/$pn.yml", Config::YAML);
			    $c->set("lifes", 20);
			    $c->set("pos", 1);
			    $c->set("ingame", true);
			    $c->set("clanwar", true);
			    $c->set("ms", false);
			    $c->set("pw", $playername);
			    $c->set("woolcolor", "blue");
			    $c->save();
			    $this->getEq($player);
		    }
	    	return true;
	    }
    }

	public function onHitwithProj(ProjectileHitEntityEvent $event) {
		$proj = $event->getEntity();
		if($proj instanceof Snowball) {
			$player = $proj->getOwningEntity();
			$opfer = $event->getEntityHit();
			$pos = $player->getPosition()->asPosition();
			$pos2 = $opfer->getPosition()->asPosition();
			$player->teleport($pos2);
			$opfer->teleport($pos);
		}
	}
	public function onProjLaunch(ProjectileLaunchEvent $event) {
    	$player = $event->getEntity()->getOwningEntity();
    	$welt = $player->getLevel()->getName();
    	if($welt == "lobby") {
    		$event->setCancelled(true);
    		return false;
	    } else {
		    $proj = $event->getEntity();
		    if ($proj instanceof Arrow) {
			    return true;
		    } else {
			    $this->getLogger()->info($player->getNameTag());
			    $c = new Config("/cloud/users/" . $player->getNameTag() . ".yml", Config::YAML);
			    if ($c->get("cooldown") == true) {
				    $player->sendMessage($this->prefix . "5 Sekunden Cooldown!");
				    $event->setCancelled(true);
				    return false;
			    } else {
				    $c->set("cooldown", true);
				    $c->save();
				    $task = new Cooldown($this, $player);
				    $this->getScheduler()->scheduleDelayedTask($task, 100);
				    $this->setPrice($player, 32);
				    if ($this->zuwenig == true) {
					    $this->zuwenig = false;
					    $event->setCancelled(true);
				    }
			    }
		    }
	    }
	}

	public function onProjHit(ProjectileHitBlockEvent $event) {
    	$block = $event->getBlockHit();
    	$this->getLogger()->info($block);
    	if($block->getId() == 35 && $block->getDamage() == 11) { // BLUE WOOL
		    $x = $block->getX();
		    $y = $block->getY();
		    $z = $block->getZ();
		    $level = $event->getEntity()->getLevel();
		    $pos = new Position($x, $y, $z, $level);
		    $air = Block::get(0);
		    $wool = Block::get(35, 11);
		    $level->setBlock($pos, $air);
		    $level->addParticle(new DestroyBlockParticle($pos, $wool));
	    }
		elseif($block->getId() == 35 && $block->getDamage() == 14) { // RED WOOL
			$x = $block->getX();
			$y = $block->getY();
			$z = $block->getZ();
			$level = $event->getEntity()->getLevel();
			$pos = new Position($x, $y, $z, $level);
			$air = Block::get(0);
			$wool = Block::get(35, 14);
			$level->setBlock($pos, $air);
			$level->addParticle(new DestroyBlockParticle($pos, $wool));
		} else {
    		return false;
	    }
	}

	public function throwNoPlayer($player) {
    	$player->sendMessage($this->prefix." Keinen Spielernamen angegeben!");
    	return true;
	}

}

class Asker extends Task
{
	public $plugin;
	public $player;

	public function __construct(Main $plugin, Player $player)
	{
		$this->plugin = $plugin;
		$this->player = $player;
	}

	public function onRun(int $tick)
	{
		$player = $this->player;
		if (!$player->isOnline()) {
			$playername = $this->plugin->lastquit;
			$wspwh = new Config("/cloud/users/$playername.yml", Config::YAML);
			$otherplayer = $wspwh->get("pw");
			$ig = $wspwh->get("ingame");
			if($ig == true) {
				$otherplayer = $this->plugin->getServer()->getPlayer($otherplayer);
				$otherplayer->sendMessage($this->plugin->prefix . "Es sieht so aus als ob dein Gegener aus der Runde gegenagen ist...");
				$this->plugin->clearHotbar($otherplayer);
				$this->plugin->getWoolLobby($otherplayer);
				$opname = $otherplayer->getName();
				$eloset = new Config("/cloud/users/".$opname.".yml", Config::YAML);
				$celo = $eloset->get("elo");
				$seed = bcmul(microtime(), abs(ip2long($player->getAddress())), 2);
				mt_srand($seed);
				$geelo = mt_rand(1,20);
				$celo = $celo+$geelo;
				$eloset->set("elo", $celo);
				$eloset->save();
				$otherplayer->sendMessage(f::GREEN . "+$geelo Elo");
				$welt = $this->plugin->getServer()->getLevelByName("lobby");
				$pos = new Position(87 , 65 , -72 , $welt);
				$wspwh = new Config("/cloud/users/".$opname.".yml", Config::YAML);
				$wspwh->set("ingame", false);
				$wspwh->set("woolcolor", false);
				$wspwh->set("ms", false);
				$wspwh->set("lifes", 10);
				$wspwh->set("wooltode", $wspwh->get("wooltode")+1);
				$wspwh->save();
				$arenaname = $otherplayer->getLevel()->getFolderName();
				$arenaid = (int) filter_var($arenaname, FILTER_SANITIZE_NUMBER_INT);
				$this->plugin->getLogger()->info(f::WHITE."$arenaid");
				$arena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
				$arena->set("usew$arenaid", false);
				$arena->save();
				$this->plugin->getServer()->unloadLevel($this->plugin->getServer()->getLevelByName("$arenaname"));
				$this->plugin->getServer()->loadLevel("$arenaname");
				$this->plugin->getServer()->getLevelByName("$arenaname")->setAutoSave(false);
				$this->plugin->getLogger()->info("Arena: $arenaname Geladen!");
				$otherplayer->teleport($pos);
				$wspwh = new Config("/cloud/users/".$playername.".yml", Config::YAML);
				$wspwh->set("ingame", false);
				$wspwh->set("woolcolor", false);
				$wspwh->set("ms", false);
				$wspwh->set("lifes", 10);
				$wspwh->set("wooltode", $wspwh->get("wooltode")+1);
				$wspwh->save();
				$name = $player->getName();
				$this->plugin->getScheduler()->cancelTask($this->getTaskId());
				$this->plugin->getLogger()->info("$this->player quited the in an running Game!");
			}
			$name = $player->getName();
			$c = new Config("/cloud/users/$name.yml", Config::YAML);
			$c->set("ingame", false);
			$c->set("woolcolor", false);
			$c->set("ms", false);
			$c->set("pw", false);
			$c->set("leader", false);
			$c->set("pos", 1);
			$c->set("grouparray", array());
			$c->set("spawnprotect", false);
			$c->set("cooldown", false);
			$c->save();
			$this->plugin->getScheduler()->cancelTask($this->getTaskId());
			$this->plugin->getLogger()->info("Task for $this->player was Disabled!");
		} else {
			$playername = $player->getName();
			$ig = new Config("/cloud/users/" . $playername . ".yml", Config::YAML);
			$isIngame = $ig->get("ingame");
			$clanWar = $ig->get("clanwar");
			if ($isIngame == true) {
				if ($clanWar == false) {
					$lifes = $ig->get("lifes");
					$op = $ig->get("pw");
					$op = $this->plugin->getServer()->getPlayer($op);
					$lifes = $ig->get("lifes");
					$opname = $op->getName();
					$opc = new Config("/cloud/users/" . $opname . ".yml", Config::YAML);
					$lifes2 = $opc->get("lifes");
					$player->sendPopup(f::GREEN . "$playername: " . f::WHITE . "$lifes" . f::GOLD . " vs " . f::GREEN . "$opname: " . f::WHITE . "$lifes2");
					if ($lifes < 0) {
						$op = $ig->get("pw");
						$op = $this->plugin->getServer()->getPlayer($op);
						$op->sendMessage($this->plugin->prefix . "HGW, du hast Gewonnen!");
						$opname = $op->getName();
						$eloset = new Config("/cloud/elo/" . $opname . ".yml", Config::YAML);
						$celo = $eloset->get("elo");
						$seed = bcmul(microtime(), abs(ip2long($op->getAddress())), 2);
						mt_srand($seed);
						$pelo = mt_rand(20, 50);
						$celo = $celo + $pelo;
						$eloset->set("elo", $celo);
						$eloset->save();
						$op->sendMessage(f::GREEN . "+ $pelo Elo");
						$op->sendPopup(f::GREEN . "+ $pelo Elo");
						$welt = $this->plugin->getServer()->getLevelByName("lobby");
						$cfg = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
						$x = $cfg->get("spawnx");
						$y = $cfg->get("spawny");
						$z = $cfg->get("spawnz");
						$pos = new Position($x, $y, $z, $welt);
						$op->teleport($pos);
						$this->plugin->getWoolLobby($op);
						$player->sendMessage($this->plugin->prefix . "Du hast Leider Verloren");
						$arenaname = $player->getLevel()->getFolderName();
						$this->plugin->getLogger()->info(f::WHITE . $arenaname);
						$eloset = new Config("/cloud/elo/" . $playername . ".yml", Config::YAML);
						$celo = $eloset->get("elo");
						$seed = bcmul(microtime(), abs(ip2long($player->getAddress())), 2);
						mt_srand($seed);
						$pelo = mt_rand(20, 40);
						$celo = $celo - $pelo;
						$eloset->set("elo", $celo);
						$eloset->save();
						$player->sendMessage(f::RED . "- $pelo Elo");
						$player->sendPopup(f::RED . "- $pelo Elo");
						$welt = $this->plugin->getServer()->getLevelByName("lobby");
						$cfg = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
						$x = $cfg->get("spawnx");
						$y = $cfg->get("spawny");
						$z = $cfg->get("spawnz");
						$pos = new Position($x, $y, $z, $welt);
						$player->teleport($pos);
						$this->plugin->getWoolLobby($player);
						$wspwh = new Config("/cloud/users/" . $playername . ".yml", Config::YAML);
						$wspwh->set("ingame", false);
						$wspwh->set("woolcolor", false);
						$wspwh->set("ms", false);
						$wspwh->set("lifes", 10);
						$wspwh->set("wooltode", $wspwh->get("wooltode") + 1);
						$wspwh->save();
						$wspwh = new Config("/cloud/users/" . $opname . ".yml", Config::YAML);
						$wspwh->set("ingame", false);
						$wspwh->set("woolcolor", false);
						$wspwh->set("ms", false);
						$wspwh->set("lifes", 10);
						$wspwh->set("woolkills", $wspwh->get("woolkills") + 1);
						$wspwh->save();
						$arenaid = (int)filter_var($arenaname, FILTER_SANITIZE_NUMBER_INT);
						$this->plugin->getLogger()->info(f::WHITE . "$arenaid");
						$arena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
						$arena->set("usew$arenaid", false);
						$arena->save();
						$this->plugin->getServer()->unloadLevel($this->plugin->getServer()->getLevelByName("$arenaname"));
						$this->plugin->getServer()->loadLevel("$arenaname");
						$this->plugin->getServer()->getLevelByName("$arenaname")->setAutoSave(false);
						$this->plugin->getLogger()->info("Arena: $arenaname Geladen!");
					} else {
						$hight = $player->getY();
						if ($hight < 0) {
							$opc = new Config("/cloud/users/" . $playername . ".yml", Config::YAML);
							$clives = $opc->get("lifes");
							$clives = $clives - 1;
							$opc->set("lifes", $clives);
							$opc->save();
							$welt = $player->getLevel();
							$woolarena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
							$pos = $opc->get("pos");
							$x = $woolarena->get("x$pos");
							$y = $woolarena->get("y$pos");
							$z = $woolarena->get("z$pos");
							$pos = new Position($x, $y, $z, $welt);
							$player->teleport($pos);
							$this->plugin->clearHotbar($player);
							$this->plugin->getEq($player);
							$opc->set("spawnprotect", true);
							$opc->save();
							$task = new Spawnprotection($this->plugin, $player);
							$this->plugin->getScheduler()->scheduleDelayedTask($task, 60);

						}
					}
				} elseif($clanWar == true) {
					$color = $ig->get("woolcolor");
					$lifes = $ig->get("lifes");
					$oplayername = $ig->get("pw");
					$oplayer = $this->plugin->getServer()->getPlayer($oplayername);
					$ig2 = new Config("/cloud/users/$oplayername.yml", Config::YAML);
					$olifes = $ig2->get("lifes");
					$leadername = $ig->get("leader");
					$oleadername = $ig2->get("leader");
					$leader = $this->plugin->getServer()->getPlayer((string)$leadername);
					$oleader = $this->plugin->getServer()->getPlayer((string)$oleadername);
					if($color == "red") {
						$player->sendPopup(f::DARK_GRAY."[".f::RED."RED".f::DARK_GRAY."] ".f::YELLOW."$lifes ".f::GRAY."vs ".f::YELLOW."$olifes ".f::DARK_GRAY."[".f::BLUE."BLUE".f::DARK_GRAY."]");
					} else {
						$player->sendPopup(f::DARK_GRAY."[".f::BLUE."BLUE".f::DARK_GRAY."] ".f::YELLOW."$lifes ".f::GRAY."vs ".f::YELLOW."$olifes ".f::DARK_GRAY."[".f::RED."RED".f::DARK_GRAY."]");
					}
					$h = $player->getY();
					if($h < 0) {
						$lc = new Config("/cloud/users/$leadername.yml", Config::YAML);
						$lc->set("lifes", (int)$lc->get("lifes")-1);
						$lc->save();
						$welt = $player->getLevel();
						$woolarena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
						$pos = $ig->get("pos");
						$x = $woolarena->get("x$pos");
						$y = $woolarena->get("y$pos");
						$z = $woolarena->get("z$pos");
						$pos = new Position($x, $y, $z, $welt);
						$player->teleport($pos);
						$this->plugin->clearHotbar($player);
						$this->plugin->getEq($player);
					}
					if($lifes < 0) {
						$lc = new Config("/cloud/users/$leadername.yml", Config::YAML);
						$squad1 = (array)$lc->get("grouparray");
						$lc2 = new Config("/cloud/users/$oleadername.yml", Config::YAML);
						$squad2 = (array)$lc2->get("grouparray");
						$allplayers = array_merge($squad1, $squad2);
						$cfg = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
						$x = $cfg->get("spawnx");
						$y = $cfg->get("spawny");
						$z = $cfg->get("spawnz");
						$pos = new Position($x, $y, $z, $welt);
						foreach($allplayers as $playername) {
							$player = $this->plugin->getServer()->getPlayer($playername);
							$player->teleport($pos);
							$ig = new Config("/cloud/users/$playername.yml", Config::YAML);
							$ig->set("ingame", false);
							$ig->set("woolcolor", false);
							$ig->set("ms", false);
							$ig->set("pw", false);
							$ig->set("lives", 10);
							$ig->set("pos", 1);
							$ig->set("grouparray", array());
							$ig->set("leader", false);
							$ig->save();
						}
						$arenaname = $player->getLevel()->getFolderName();
						$arenaid = (int)filter_var($arenaname, FILTER_SANITIZE_NUMBER_INT);
						$this->plugin->getLogger()->info(f::WHITE . "$arenaid");
						$arena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
						$arena->set("usew$arenaid", false);
						$arena->save();
						$this->plugin->getServer()->unloadLevel($this->plugin->getServer()->getLevelByName("$arenaname"));
						$this->plugin->getServer()->loadLevel("$arenaname");
						$this->plugin->getServer()->getLevelByName("$arenaname")->setAutoSave(false);
						$this->plugin->getLogger()->info("Arena: $arenaname Geladen!");
					}

				}
				} else {
					return false;
				}
		}
	}
}

class Cooldown extends Task
{
	public $plugin;
	public $player;

	public function __construct(Main $plugin, Player $player)
	{
		$this->plugin = $plugin;
		$this->player = $player;
	}

	public function onRun(int $tick)
	{
		$player = $this->player;
		$name = $player->getName();
		$c = new Config("/cloud/users/$name.yml", Config::YAML);
		$c->set("cooldown", false);
		$c->save();
		$this->plugin->getScheduler()->cancelTask($this->getTaskId());
	}
}
class Spawnprotection extends Task
{
	public $plugin;
	public $player;

	public function __construct(Main $plugin, Player $player)
	{
		$this->plugin = $plugin;
		$this->player = $player;
	}

	public function onRun(int $tick)
	{
		$player = $this->player;
		$name = $player->getName();
		$c = new Config("/cloud/users/$name.yml", Config::YAML);
		$c->set("spawnprotect", false);
		$c->save();
		$player->addActionBarMessage(f::GREEN."Du bist nun Verwundbar!");
		$this->plugin->getScheduler()->cancelTask($this->getTaskId());
	}
}