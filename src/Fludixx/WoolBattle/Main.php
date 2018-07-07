<?php

namespace Fludixx\WoolBattle;

use pocketmine\entity\projectile\Arrow;
use pocketmine\entity\projectile\EnderPearl;
use pocketmine\entity\projectile\Projectile;
use pocketmine\Server;
use pocketmine\Player;
use pocketmine\event\Listener;
use pocketmine\event\inventory\InventoryOpenEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\level\Location;
use pocketmine\level\Position;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\utils\Terminal;
use pocketmine\utils\Color;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerMoveEvent;
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
use pocketmine\level\Level;
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

class Main extends PluginBase implements Listener{

    public $prefix = f::WHITE . "Wool" . f::GREEN . "Battle" . f::GRAY . " | " . f::WHITE;
    public $zuwenig = false;
    public $setup = 0;
    public $kabstand = 3;

    public function onEnable() {
        $this->getServer()->getPluginManager()->registerEvents($this,$this);
		$this->getLogger()->info($this->prefix . f::WHITE . f::AQUA . "WoolBattle by Fludixx" . f::GREEN .  " wurde Erfolgreich Aktiviert!");
        $this->getLogger()->info(f::RED . "Be sure to have EloSystem by Fludixx installed!");
        $this->getLogger()->info(f::RED . "Without this Plugin WoolBattle won't work properly! " . f::AQUA . "https://github.com/Fludixx/EloSystem");
        $this->getServer()->getNetwork()->setName(f::WHITE . "Wool" . f::GREEN . "Battle");
        $this->getLogger()->info(getcwd());
        // Clearing Arenas
        $arena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
        $arena->set("usew1", false);
        $arena->set("usew2", false);
        $arena->set("usew3", false);
	    $arena->set("usew4", false);
	    $arena->set("usew5", false);
	    $arena->set("usew6", false);
	    $arena->set("usew7", false);
	    $arena->set("usew8", false);
	    $arena->set("usew9", false);
	    $arena->set("usew10", false);
        $arena->save();
        if(!$arena->get("spawnx") || !$arena->get("spawny") || !$arena->get("spawnz")) {
	        $arena->set("spawnx", 1);
	        $arena->set("spawny", 100);
	        $arena->set("spawnz", 1);
	        $arena->save();
        }
        $perks = new Config("/cloud/cfg/perks.yml", Config::YAML);
        if(!$perks || !$perks->get("kapsel_y")) {
        	@mkdir("/cloud/cfg/");
	        $perks = new Config("/cloud/cfg/perks.yml", Config::YAML);
	        $perks->set("kapsel_y", 3);
        }
		$this->kabstand = (int)$perks->get("kapsel_y");

        //Loading and Setting up levels
        $this->getServer()->loadLevel("lobby");
        $this->getServer()->getLevelByName("lobby")->setAutoSave(false);
        $this->getServer()->loadLevel("woolbattle");
        $this->getServer()->getLevelByName("woolbattle")->setAutoSave(false);
        $this->getServer()->loadLevel("woolbattle2");
        $this->getServer()->getLevelByName("woolbattle2")->setAutoSave(false);
        $this->getServer()->loadLevel("woolbattle3");
        $this->getServer()->getLevelByName("woolbattle3")->setAutoSave(false);
	    $this->getServer()->loadLevel("woolbattle4");
	    $this->getServer()->getLevelByName("woolbattle4")->setAutoSave(false);
	    $this->getServer()->loadLevel("woolbattle5");
	    $this->getServer()->getLevelByName("woolbattle5")->setAutoSave(false);
	    $this->getServer()->loadLevel("woolbattle6");
	    $this->getServer()->getLevelByName("woolbattle6")->setAutoSave(false);
	    $this->getServer()->loadLevel("woolbattle7");
	    $this->getServer()->getLevelByName("woolbattle7")->setAutoSave(false);
	    $this->getServer()->loadLevel("woolbattle8");
	    $this->getServer()->getLevelByName("woolbattle8")->setAutoSave(false);
	    $this->getServer()->loadLevel("woolbattle9");
	    $this->getServer()->getLevelByName("woolbattle9")->setAutoSave(false);
	    $this->getServer()->loadLevel("woolbattle10");
	    $this->getServer()->getLevelByName("woolbattle10")->setAutoSave(false);
    }
    public function onJoin(PlayerJoinEvent $event) {
        $player = $event->getPlayer();
        $name = $event->getPlayer()->getName();
        $this->getWoolLobby($player);
     $kconfig = new Config("/cloud/users/".$name.".yml", Config::YAML);
     if(!$kconfig->get("woolkills") && !$kconfig->get("wooltode")){
        $kconfig->set("woolkills", 1);
        $kconfig->set("wooltode", 1);
        $kconfig->save();
        $welt = $this->getServer()->getLevelByName("lobby");
        $cfg = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
        $x = $cfg->get("spawnx");
        $y = $cfg->get("spawny");
        $z = $cfg->get("spawnz");
        $pos = new Position($x, $y, $z, $welt);
        $player->teleport($pos);
         // Unbenuzte Config laden um bugs zu verhindern!
        $kconfig->set("ingame", false);
        $kconfig->set("woolcolor", false);
        $kconfig->set("ms", false);
        $kconfig->set("lives", 10);
        $kconfig->set("pos", 1);
        $kconfig->save();
     }
    }
    public function onQuit(PlayerQuitEvent $event) {
        $player = $event->getPlayer();
        $playername = $player->getName();
        $wspwh = new Config("/cloud/users/".$playername.".yml", Config::YAML);
        $otherplayer = $wspwh->get("pw");
        $ig = $wspwh->get("ingame");
        if($ig == true) {
            $otherplayer = $this->getServer()->getPlayer($otherplayer);
            $otherplayer->sendMessage($this->prefix . "Es sieht so aus als ob dein Gegener aus der Runde gegenagen ist...");
            $this->clearHotbar($otherplayer);
            $this->getWoolLobby($otherplayer);
            $opname = $otherplayer->getName();
            $eloset = new Config("/cloud/users/".$opname.".yml", Config::YAML);
            $celo = $eloset->get("elo");
            $celo = $celo+rand(1,50);
            $eloset->set("elo", $celo);
            $eloset->save();
            $otherplayer->sendMessage(f::GREEN . "+$celo Elo");
            $welt = $this->getServer()->getLevelByName("lobby");
            $pos = new Position(87 , 65 , -72 , $welt);
            $wspwh = new Config("/cloud/users/".$opname.".yml", Config::YAML);
            $wspwh->set("ingame", false);
            $wspwh->set("woolcolor", false);
            $wspwh->set("ms", false);
            $wspwh->set("lifes", 10);
            $wspwh->set("wooltode", $wspwh->get("wooltode")+1);
            $wspwh->save();
            $arenaname = $otherplayer->getLevel()->getName();
	        if($arenaname == "woolbattle") {
		        $arena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
		        $arena->set("usew1", false);
		        $arena->save();
		        $this->getServer()->unloadLevel($this->getServer()->getLevelByName("woolbattle"));
		        $this->getServer()->loadLevel("woolbattle");
		        $this->getServer()->getLevelByName("woolbattle")->setAutoSave(false);
		        $this->getLogger()->info("Arena: woolbattle Geladen!");
	        } elseif($arenaname == "woolbattle2") {
		        $arena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
		        $arena->set("usew2", false);
		        $arena->save();
		        $this->getServer()->unloadLevel($this->getServer()->getLevelByName("woolbattle2"));
		        $this->getServer()->loadLevel("woolbattle2");
		        $this->getServer()->getLevelByName("woolbattle2")->setAutoSave(false);
		        $this->getLogger()->info("Arena: woolbattle2 Geladen!");
	        } elseif($arenaname == "woolbattle3") {
		        $arena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
		        $arena->set("usew3", false);
		        $arena->save();
		        $this->getServer()->unloadLevel($this->getServer()->getLevelByName("woolbattle3"));
		        $this->getServer()->loadLevel("woolbattle3");
		        $this->getServer()->getLevelByName("woolbattle3")->setAutoSave(false);
		        $this->getLogger()->info("Arena: woolbattle3 Geladen!");
	        } elseif($arenaname == "woolbattle4") {
		        $arena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
		        $arena->set("usew4", false);
		        $arena->save();
		        $this->getServer()->unloadLevel($this->getServer()->getLevelByName("woolbattle4"));
		        $this->getServer()->loadLevel("woolbattle4");
		        $this->getServer()->getLevelByName("woolbattle4")->setAutoSave(false);
		        $this->getLogger()->info("Arena: woolbattle4 Geladen!");
	        } elseif($arenaname == "5") {
		        $arena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
		        $arena->set("usew5", false);
		        $arena->save();
		        $this->getServer()->unloadLevel($this->getServer()->getLevelByName("woolbattle5"));
		        $this->getServer()->loadLevel("woolbattle5");
		        $this->getServer()->getLevelByName("woolbattle5")->setAutoSave(false);
		        $this->getLogger()->info("Arena: woolbattle5 Geladen!");
	        } elseif($arenaname == "6") {
		        $arena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
		        $arena->set("usew6", false);
		        $arena->save();
		        $this->getServer()->unloadLevel($this->getServer()->getLevelByName("woolbattle6"));
		        $this->getServer()->loadLevel("woolbattle6");
		        $this->getServer()->getLevelByName("woolbattle6")->setAutoSave(false);
		        $this->getLogger()->info("Arena: woolbattle6 Geladen!");
	        } elseif($arenaname == "7") {
		        $arena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
		        $arena->set("usew7", false);
		        $arena->save();
		        $this->getServer()->unloadLevel($this->getServer()->getLevelByName("woolbattle7"));
		        $this->getServer()->loadLevel("woolbattle7");
		        $this->getServer()->getLevelByName("woolbattle7")->setAutoSave(false);
		        $this->getLogger()->info("Arena: woolbattle6 Geladen!");
	        } elseif($arenaname == "8") {
		        $arena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
		        $arena->set("usew8", false);
		        $arena->save();
		        $this->getServer()->unloadLevel($this->getServer()->getLevelByName("woolbattle8"));
		        $this->getServer()->loadLevel("woolbattle8");
		        $this->getServer()->getLevelByName("woolbattle8")->setAutoSave(false);
		        $this->getLogger()->info("Arena: woolbattle6 Geladen!");
	        } elseif($arenaname == "9") {
		        $arena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
		        $arena->set("usew9", false);
		        $arena->save();
		        $this->getServer()->unloadLevel($this->getServer()->getLevelByName("woolbattle9"));
		        $this->getServer()->loadLevel("woolbattle9");
		        $this->getServer()->getLevelByName("woolbattle9")->setAutoSave(false);
		        $this->getLogger()->info("Arena: woolbattle6 Geladen!");
	        } elseif($arenaname == "10") {
		        $arena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
		        $arena->set("usew10", false);
		        $arena->save();
		        $this->getServer()->unloadLevel($this->getServer()->getLevelByName("woolbattle10"));
		        $this->getServer()->loadLevel("woolbattle10");
		        $this->getServer()->getLevelByName("woolbattle10")->setAutoSave(false);
		        $this->getLogger()->info("Arena: woolbattle6 Geladen!");
	        }
            $otherplayer->teleport($pos);
            $wspwh = new Config("/cloud/users/".$playername.".yml", Config::YAML);
            $wspwh->set("ingame", false);
            $wspwh->set("woolcolor", false);
            $wspwh->set("ms", false);
            $wspwh->set("lifes", 10);
            $wspwh->set("wooltode", $wspwh->get("wooltode")+1);
            $wspwh->save();
        }
    }
    public function getEq($spieler) {
        $spielername = $spieler->getName();
        $schere = Item::get(359, 0, 1);
        $schere->setCustomName(f::GOLD . "Schere");
        $bow = Item::get(261, 0, 1);
        $bow->setCustomName(f::GOLD . "Bogen");
        $enderpearl = Item::get(368, 0, 1);
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
        $back->setCustomName(f::RED . "Zurück");
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
		$elytra = Item::get(Item::SNOWBALL);
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
		$elytra = Item::get(Item::SNOWBALL);
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
        $elytra->setCustomName(f::GREEN . "Kapsel" . f::WHITE . "Perk");
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
        $elytra->setCustomName(f::GREEN . "Kapsel" . f::WHITE . "Perk");
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
        $back->setCustomName(f::RED . "Zurück");
        $elytra = Item::get(444, 0, 1);
        $elytra->setCustomName(f::GREEN . "Elytra" . f::WHITE . "Perk" . f::AQUA . "  [FREE]");
        $unbreak = Enchantment::getEnchantment(17);
        $elytra->addEnchantment(new EnchantmentInstance($unbreak, 4));
        $slime = Item::get(165, 0, 1);
        $slime->setCustomName(f::GREEN . "Slime" . f::WHITE . "Perk" . f::GOLD . "  [600 ELO]");
        $slime->addEnchantment(new EnchantmentInstance($unbreak, 4));
        $kapsel = Item::get(341, 0, 1);
        $kapsel->setCustomName(f::GREEN . "Kapsel" . f::WHITE . "Perk" . f::GOLD . "  [800 ELO]");
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
        $back->setCustomName(f::RED . "Zurück");
        $elytra = Item::get(444, 0, 1);
        $elytra->setCustomName(f::GREEN . "Elytra" . f::WHITE . "Perk" . f::AQUA . "2  [FREE]");
        $unbreak = Enchantment::getEnchantment(17);
        $elytra->addEnchantment(new EnchantmentInstance($unbreak, 4));
        $slime = Item::get(165, 0, 1);
        $slime->setCustomName(f::GREEN . "Slime" . f::WHITE . "Perk" . f::GOLD . "2  [600 ELO]");
        $slime->addEnchantment(new EnchantmentInstance($unbreak, 4));
        $kapsel = Item::get(341, 0, 1);
        $kapsel->setCustomName(f::GREEN . "Kapsel" . f::WHITE . "Perk" . f::GOLD . "2  [800 ELO]");
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
    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool
    {
        if ($command->getName() == "wbgeteq") {
            $this->getEq($sender);
            return true;
        }
        if ($command->getName() == "eq") {
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
        if ($command->getName() == "lvs") {
            $levels = $this->getServer()->getLevels();
            $sender->sendMessage($levels);
            return true;
        }
        /*if($command->getName() == "setup") {
        	if($this->setup != 0) {
        		if($this->setup == 1) {
        			$x1 = $sender->getX;
        			$y1 = $sender->getY;
        			$z1 = $sender->getZ;
        			$c = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
        			$c->set("x1", $x1);
			        $c->set("y1", $y1);
			        $c->set("z1", $z1);
			        $c->save();
        			$sender->sendMessage($this->prefix."Gut! Jetzt geh auf den Spawn das 2. Spielers und füre /setup erneut aus!");
        			$this->setup = 2;
        			return true;
		        } elseif($this->setup == 2) {
			        $x2 = $sender->getX;
			        $y2 = $sender->getY;
			        $z2 = $sender->getZ;
			        $c = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
			        $c->set("x2", $x2);
			        $c->set("y2", $y2);
			        $c->set("z2", $z2);
			        $c->save();
			        $sender->sendMessage($this->prefix."Perfekt! Alles ist nun eingerichtet!");
			        $this->setup = 0;
			        $welt = $this->getServer()->getLevelByName("lobby");
			        $welt->getSafeSpawn();
			        $sender->teleport($welt);
			        return true;
		        }
	        }
        	if(!empty($args['0'])) {
		        $this->getServer()->loadLevel($args['0']);
		        $this->getServer()->getLevelByName($args['0'])->setAutoSave(false);
		        $welt = $this->getServer()->getLevelByName($args['0']);
		        $welt->getSafeSpawn();
		        $sender->teleport($welt);
		        $sender->sendMessage($this->prefix."Geh auf den Spawn des 1. Spielers und schreibe erneut /setup in den chat!");
		        $this->setup = 1;
	        } else {
        		return FALSE;
	        }
	        return TRUE;
        } */
        return TRUE;
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
        if ($item->getCustomName() == f::RED . "Zurück") {
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
                $player->sendMessage($this->prefix . f::RED . "Du hast das Kit schon Ausgewählt!");
                return 1;
            }
            $player->sendMessage($this->prefix . "Du hast Erfolgreich " . f::GREEN . " Elytra Perk " . f::WHITE . " ausgewählt!");
            $wool->set("woolperk", "elytra");
            $wool->save();
        }
        if ($item->getCustomName() == f::GREEN . "Elytra" . f::WHITE . "Perk" . f::AQUA . "2  [FREE]") {
            $click = new ClickSound($player);
            $player->getLevel()->addSound($click);
            $wool = new Config("/cloud/users/".$playername.".yml", Config::YAML);
            $ifalready = $wool->get("woolperk2");
            if($ifalready == "elytra") {
                $player->sendMessage($this->prefix . f::RED . "Du hast das Kit schon Ausgewählt!");
                return 1;
            }
            $player->sendMessage($this->prefix . "Du hast Erfolgreich " . f::GREEN . " Elytra Perk " . f::WHITE . "als 2tes Perk ausgewählt!");
            $wool->set("woolperk2", "elytra");
            $wool->save();
        }
        if ($item->getCustomName() == f::GREEN . "Slime" . f::WHITE . "Perk" . f::GOLD . "  [600 ELO]") {
            $click = new ClickSound($player);
            $player->getLevel()->addSound($click);
            $wool = new Config("/cloud/users/".$playername.".yml", Config::YAML);
            $ifalready = $wool->get("woolperk");
            if($ifalready == "slime") {
                $player->sendMessage($this->prefix . f::RED . "Du hast das Kit schon Ausgewählt!");
                return 1;
            }
            $elo = new Config("/cloud/elo/".$playername.".yml", Config::YAML);
            $celo = $elo->get("elo");
            if($celo < 600) {
                $player->sendMessage($this->prefix . f::RED . "Zu wenig Elo!");
                return false;
            }
            $player->sendMessage($this->prefix . "Du hast Erfolgreich " . f::GREEN . " Slime Perk " . f::WHITE . " ausgewählt!");
            $wool->set("woolperk", "slime");
            $wool->save();
        }
        if ($item->getCustomName() == f::GREEN . "Slime" . f::WHITE . "Perk" . f::GOLD . "2  [600 ELO]") {
            $click = new ClickSound($player);
            $player->getLevel()->addSound($click);
            $wool = new Config("/cloud/users/".$playername.".yml", Config::YAML);
            $ifalready = $wool->get("woolperk2");
            if($ifalready == "slime") {
                $player->sendMessage($this->prefix . f::RED . "Du hast das Kit schon Ausgewählt!");
                return 1;
            }
            $elo = new Config("/cloud/elo/".$playername.".yml", Config::YAML);
            $celo = $elo->get("elo");
            if($celo < 600) {
                $player->sendMessage($this->prefix . f::RED . "Zu wenig Elo!");
                return false;
            }
            $player->sendMessage($this->prefix . "Du hast Erfolgreich " . f::GREEN . " Slime Perk " . f::WHITE . "als 2tes Perk ausgewählt!");
            $wool->set("woolperk2", "slime");
            $wool->save();
        }
	    if($item->getCustomName() == f::GREEN . "Switcher" . f::WHITE . "Perk" . f::GOLD . "  [1000 ELO]") {
		    $click = new ClickSound($player);
		    $player->getLevel()->addSound($click);
		    $wool = new Config("/cloud/users/".$playername.".yml", Config::YAML);
		    $ifalready = $wool->get("woolperk");
		    if($ifalready == "switch") {
			    $player->sendMessage($this->prefix . f::RED . "Du hast das Kit schon Ausgewählt!");
			    return 1;
		    }
		    $elo = new Config("/cloud/elo/".$playername.".yml", Config::YAML);
		    $celo = $elo->get("elo");
		    if($celo < 1000) {
			    $player->sendMessage($this->prefix . f::RED . "Zu wenig Elo!");
			    return false;
		    }
		    $player->sendMessage($this->prefix . "Du hast Erfolgreich " . f::GREEN . " Switcher Perk " . f::WHITE . "als Perk ausgewählt!");
		    $wool->set("woolperk", "switch");
		    $wool->save();
	    }
	    if($item->getCustomName() == f::GREEN . "Switcher" . f::WHITE . "Perk" . f::GOLD . "2  [1000 ELO]") {
		    $click = new ClickSound($player);
		    $player->getLevel()->addSound($click);
		    $wool = new Config("/cloud/users/".$playername.".yml", Config::YAML);
		    $ifalready = $wool->get("woolperk2");
		    if($ifalready == "switch") {
			    $player->sendMessage($this->prefix . f::RED . "Du hast das Kit schon Ausgewählt!");
			    return 1;
		    }
		    $elo = new Config("/cloud/elo/".$playername.".yml", Config::YAML);
		    $celo = $elo->get("elo");
		    if($celo < 1000) {
			    $player->sendMessage($this->prefix . f::RED . "Zu wenig Elo!");
			    return false;
		    }
		    $player->sendMessage($this->prefix . "Du hast Erfolgreich " . f::GREEN . " Switcher Perk " . f::WHITE . "als 2tes Perk ausgewählt!");
		    $wool->set("woolperk2", "switch");
		    $wool->save();
	    }
        if ($item->getCustomName() == f::GREEN . "Slime" . f::WHITE . "Perk") {
            $this->setPrice($player, 32);
            if($this->zuwenig == true) {
                $this->zuwenig = false;
                return 1;
            }
            $yaw = $player->getYaw();
if ($yaw < 45 && $yaw > 0 || $yaw < 360 && $yaw > 315) {
            	
            	$player->setMotion(new Vector3(0, 3, 4));
            	
            } else if ($yaw < 135 && $yaw > 45) {
            	
            	$player->setMotion(new Vector3(-4, 3, 0));
            	
            } else if ($yaw < 225 && $yaw > 135) {
            	
            	$player->setMotion(new Vector3(0, 3, -4));
            	
            } elseif($yaw < 315 && $yaw > 225){
            	
                $player->setMotion(new Vector3(4, 3, 0));
               
            }
            
}
        if ($item->getCustomName() == f::GREEN . "Kapsel" . f::WHITE . "Perk") {
            $this->setPrice($player, 64);
            if($this->zuwenig == true) {
                $this->zuwenig = false;
                return 1;
            }
            $block = Block::get(165, 0);
            $name = $player->getName();
            $wool = new Config("/cloud/users/".$name.".yml", Config::YAML);
            $wcolor = $wool->get("woolcolor");
            if($wcolor == "red") {
                $rand = Block::get(35, 14);
            } else {
                $rand = Block::get(35, 11);
            }
            // RettungsKapsel
            $x = $player->getX();
            $y = $player->getY();
            $z = $player->getZ();
            $y = $y-(int)$this->kabstand;
            $pos = new Vector3($x, $y, $z);
            $level = $player->getLevel();
            $level->setBlock($pos, $block);
            $x = $player->getX()+1;
            $y = $player->getY();
            $z = $player->getZ();
	        $y = $y-(int)$this->kabstand;
            $pos = new Vector3($x, $y, $z);
            $level->setBlock($pos, $block);
            $x = $player->getX()-1;
            $y = $player->getY();
            $z = $player->getZ();
	        $y = $y-(int)$this->kabstand;
            $pos = new Vector3($x, $y, $z);
            $level->setBlock($pos, $block);
            $x = $player->getX();
            $y = $player->getY();
            $z = $player->getZ()-1;
	        $y = $y-(int)$this->kabstand;
            $pos = new Vector3($x, $y, $z);
            $level->setBlock($pos, $block);
            $x = $player->getX();
            $y = $player->getY();
            $z = $player->getZ()+1;
	        $y = $y-(int)$this->kabstand;
            $pos = new Vector3($x, $y, $z);
            $level->setBlock($pos, $block);
            $x = $player->getX()+1;
            $y = $player->getY();
            $z = $player->getZ()+1;
	        $y = $y-(int)$this->kabstand;
            $pos = new Vector3($x, $y, $z);
            $level->setBlock($pos, $rand);
            $x = $player->getX()-1;
            $y = $player->getY();
            $z = $player->getZ()-1;
	        $y = $y-(int)$this->kabstand;
            $pos = new Vector3($x, $y, $z);
            $level->setBlock($pos, $rand);
            $x = $player->getX()+1;
            $y = $player->getY();
            $z = $player->getZ()-1;
	        $y = $y-(int)$this->kabstand;
            $pos = new Vector3($x, $y, $z);
            $level->setBlock($pos, $rand);
            $x = $player->getX()-1;
            $y = $player->getY();
            $z = $player->getZ()+1;
	        $y = $y-(int)$this->kabstand;
            $pos = new Vector3($x, $y, $z);
            $level->setBlock($pos, $rand);
            // RettungsKapsel Ende
            
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
		    $player->sendMessage(f::GREEN."KD: ".f::WHITE."$kd");
	    }
        if ($item->getCustomName() == f::GREEN . "Kapsel" . f::WHITE . "Perk" . f::GOLD . "  [800 ELO]") {
            $click = new ClickSound($player);
            $player->getLevel()->addSound($click);
            $wool = new Config("/cloud/users/".$playername.".yml", Config::YAML);
            $ifalready = $wool->get("woolperk");
            if($ifalready == "kapsel") {
                $player->sendMessage($this->prefix . f::RED . "Du hast das Kit schon Ausgewählt!");
                return 1;
            }
            $elo = new Config("/cloud/elo/".$playername.".yml", Config::YAML);
            $celo = $elo->get("elo");
            if($celo < 800) {
                $player->sendMessage($this->prefix . f::RED . "Zu wenig Elo!");
                return false;
            }
            $player->sendMessage($this->prefix . "Du hast Erfolgreich " . f::GREEN . " Kapsel Perk " . f::WHITE . " ausgewählt!");
            $wool->set("woolperk", "kapsel");
            $wool->save();
        }
        if ($item->getCustomName() == f::GREEN . "Kapsel" . f::WHITE . "Perk" . f::GOLD . "2  [800 ELO]") {
            $click = new ClickSound($player);
            $player->getLevel()->addSound($click);
            $wool = new Config("/cloud/users/".$playername.".yml", Config::YAML);
            $ifalready = $wool->get("woolperk2");
            if($ifalready == "kapsel") {
                $player->sendMessage($this->prefix . f::RED . "Du hast das Kit schon Ausgewählt!");
                return 1;
            }
            $elo = new Config("/cloud/elo/".$playername.".yml", Config::YAML);
            $celo = $elo->get("elo");
            if($celo < 800) {
                $player->sendMessage($this->prefix . f::RED . "Zu wenig Elo!");
                return false;
            }
            $player->sendMessage($this->prefix . "Du hast Erfolgreich " . f::GREEN . " Kapsel Perk " . f::WHITE . "als 2tes Perk ausgewählt!");
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
                $player->sendMessage(f::RED."-> Zu wenig Elo. Mid. 1000");
                return false;
            }
        }
        /*
        if($item->getId() == 332) {
        	$inv = $player->getInventory();
        	$schneeball = Item::get(Item::SNOWBALL);
        	$schneeball->setCustomName(f::GREEN . "Swap" . f::WHITE . "Perk");
        	$inv->addItem($schneeball);
        	return true;
        }
        if($item->getId() == 368) {
	        $inv = $player->getInventory();
	        $schneeball = Item::get(Item::ENDER_PEARL);
	        $schneeball->setCustomName(f::GOLD . "Enderperle");
	        $inv->addItem($schneeball);
	        return true;
        }
        */
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
            if($woola < $price) {
                $player->sendMessage($this->prefix . f::RED . "Zu wenig Wolle!");
                $this->zuwenig = true;
                return false;
            }
            $this->zuwenig = false;
            $woolprice = $price;
            $wooltot = $woola-$woolprice;
            $this->rmWool($player);
            $this->addWool($player, $wooltot);
    }
    
    public function onDamage(EntityDamageEvent $event) {
        if ($event instanceof EntityDamageByEntityEvent) {
            $playername = $event->getEntity()->getName();
            $cplayer = new Config("/cloud/users/".$playername.".yml", Config::YAML);
            if($cplayer->get("ingame") == true) {
                return 1;
            }
            $event->setCancelled();
            $player = $event->getEntity();
            $damager = $event->getDamager();
            if ($player instanceof Player && $damager instanceof Player) {
                $arena = "woolbattle";
                $playername = $player->getName();
                $damagername = $damager->getName();
                $cplayer = new Config("/cloud/users/".$playername.".yml", Config::YAML);
                $cdamager = new Config("/cloud/users/".$damagername.".yml", Config::YAML);
                $cplayer->set("ms", $damagername);
                $cplayer->save();
                $player->sendMessage($this->prefix . f::GREEN . $damagername . f::WHITE . " hat dich heraus gefordert!");
                $damager->sendMessage($this->prefix . "Einladung an " . f::GREEN . $playername . f::WHITE . " erfolgreich verschickt!");
                if($cdamager->get("ms") == $playername) {
	                $arena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
	                $w1 = $arena->get("usew1");
	                $w2 = $arena->get("usew2");
	                $w3 = $arena->get("usew3");
	                $w4 = $arena->get("usew4");
	                $w5 = $arena->get("usew5");
	                $w6 = $arena->get("usew6");
	                $w7 = $arena->get("usew7");
	                $w8 = $arena->get("usew8");
	                $w9 = $arena->get("usew9");
	                $w10 = $arena->get("usew10");
	                if(!$w1) {
		                $this->getArena($player, $damager, "woolbattle");
	                } elseif(!$w2) {
		                $this->getArena($player, $damager, "woolbattle2");
	                } elseif(!$w3) {
		                $this->getArena($player, $damager, "woolbattle3");
	                } elseif(!$w4) {
		                $this->getArena($player, $damager, "woolbattle4");
	                } elseif(!$w5) {
		                $this->getArena($player, $damager, "woolbattle5");
	                } elseif(!$w6) {
		                $this->getArena($player, $damager, "woolbattle6");
	                } elseif(!$w7) {
		                $this->getArena($player, $damager, "woolbattle7");
	                } elseif(!$w8) {
		                $this->getArena($player, $damager, "woolbattle8");
	                } elseif(!$w9) {
		                $this->getArena($player, $damager, "woolbattle9");
	                } elseif(!$w10) {
		                $this->getArena($player, $damager, "woolbattle10");
                     }

	                else {
	                	$player->sendMessage($this->prefix.f::RED."Alle Arenen sind Voll! :(");
		                $damager->sendMessage($this->prefix.f::RED."Alle Arenen sind Voll! :(");
		                return FALSE;
	                }
                } else {
                    return false;
                }
            }
        }
    }
    public function getArena($player, $player2, $level) {
        if($level == "woolbattle") {
            $player->sendMessage($this->prefix . "Arena gefunden! (woolbattle1)");
            $player2->sendMessage($this->prefix . "Arena gefunden! (woolbattle1)");
            $arena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
            $posx = $arena->get("x1");
            $posy = $arena->get("y1");
            $posz = $arena->get("z1");
            $arena->set("usew1", true);
            $arena->save();
            if(!$this->getServer()->getLevelByName("woolbattle")) {
                $player->sendMessage(f::RED . "E: Level not found (???) Unexpected");
                $player2->sendMessage(f::RED . "E: Level not found (???) Unexpected");
            }
            $this->getServer()->getLevelByName("woolbattle")->setAutoSave(false);
            $welt = $this->getServer()->getLevelByName("woolbattle");
            $pos = new Position($posx , $posy , $posz , $welt);
            $player->teleport($pos);
            $posx = $arena->get("x2");
            $posy = $arena->get("y2");
            $posz = $arena->get("z2");
            $pos = new Position($posx , $posy , $posz , $welt);
            $player2->teleport($pos);
            $playername = $player->getName();
            $playername2 = $player2->getName();
            $cplayer = new Config("/cloud/users/".$playername.".yml", Config::YAML);
            $cplayer2 = new Config("/cloud/users/".$playername2.".yml", Config::YAML);
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
        } elseif($level == "woolbattle2") {
            $player->sendMessage($this->prefix . "Arena gefunden! (woolbattle2)");
            $player2->sendMessage($this->prefix . "Arena gefunden! (woolbattle2)");
            $arena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
            $posx = $arena->get("x1");
            $posy = $arena->get("y1");
            $posz = $arena->get("z1");
            $arena->set("usew2", true);
            $arena->save();
            if(!$this->getServer()->getLevelByName("woolbattle2")) {
                $player->sendMessage(f::RED . "E: Level not found (???) Unexpected");
                $player2->sendMessage(f::RED . "E: Level not found (???) Unexpected");
            }
            $this->getServer()->getLevelByName("woolbattle2")->setAutoSave(false);
            $welt = $this->getServer()->getLevelByName("woolbattle2");
            $pos = new Position($posx , $posy , $posz , $welt);
            $player->teleport($pos);
            $posx = $arena->get("x2");
            $posy = $arena->get("y2");
            $posz = $arena->get("z2");
            $pos = new Position($posx , $posy , $posz , $welt);
            $player2->teleport($pos);
            $playername = $player->getName();
            $playername2 = $player2->getName();
            $cplayer = new Config("/cloud/users/".$playername.".yml", Config::YAML);
            $cplayer2 = new Config("/cloud/users/".$playername2.".yml", Config::YAML);
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
        } elseif($level == "woolbattle3") {
            $player->sendMessage($this->prefix . "Arena gefunden! (woolbattle3)");
            $player2->sendMessage($this->prefix . "Arena gefunden! (woolbattle3)");
            $arena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
            $posx = $arena->get("x1");
            $posy = $arena->get("y1");
            $posz = $arena->get("z1");
            $arena->set("usew3", true);
            $arena->save();
            if(!$this->getServer()->getLevelByName("woolbattle3")) {
                $player->sendMessage(f::RED . "E: Level not found (???) Unexpected");
                $player2->sendMessage(f::RED . "E: Level not found (???) Unexpected");
            }
            $this->getServer()->getLevelByName("woolbattle3")->setAutoSave(false);
            $welt = $this->getServer()->getLevelByName("woolbattle3");
            $pos = new Position($posx , $posy , $posz , $welt);
            $player->teleport($pos);
            $posx = $arena->get("x2");
            $posy = $arena->get("y2");
            $posz = $arena->get("z2");
            $pos = new Position($posx , $posy , $posz , $welt);
            $player2->teleport($pos);
            $playername = $player->getName();
            $playername2 = $player2->getName();
            $cplayer = new Config("/cloud/users/".$playername.".yml", Config::YAML);
            $cplayer2 = new Config("/cloud/users/".$playername2.".yml", Config::YAML);
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
        } elseif($level == "woolbattle4") {
	        $player->sendMessage($this->prefix . "Arena gefunden! (woolbattle4)");
	        $player2->sendMessage($this->prefix . "Arena gefunden! (woolbattle4)");
	        $arena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
	        $posx = $arena->get("x1");
	        $posy = $arena->get("y1");
	        $posz = $arena->get("z1");
	        $arena->set("usew4", true);
	        $arena->save();
	        if(!$this->getServer()->getLevelByName("woolbattle4")) {
		        $player->sendMessage(f::RED . "E: Level not found (???) Unexpected");
		        $player2->sendMessage(f::RED . "E: Level not found (???) Unexpected");
	        }
	        $this->getServer()->getLevelByName("woolbattle4")->setAutoSave(false);
	        $welt = $this->getServer()->getLevelByName("woolbattle4");
	        $pos = new Position($posx , $posy , $posz , $welt);
	        $player->teleport($pos);
	        $posx = $arena->get("x2");
	        $posy = $arena->get("y2");
	        $posz = $arena->get("z2");
	        $pos = new Position($posx , $posy , $posz , $welt);
	        $player2->teleport($pos);
	        $playername = $player->getName();
	        $playername2 = $player2->getName();
	        $cplayer = new Config("/cloud/users/".$playername.".yml", Config::YAML);
	        $cplayer2 = new Config("/cloud/users/".$playername2.".yml", Config::YAML);
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
        } elseif($level == "woolbattle5") {
	        $player->sendMessage($this->prefix . "Arena gefunden! (woolbattle5)");
	        $player2->sendMessage($this->prefix . "Arena gefunden! (woolbattle5)");
	        $arena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
	        $posx = $arena->get("x1");
	        $posy = $arena->get("y1");
	        $posz = $arena->get("z1");
	        $arena->set("usew5", true);
	        $arena->save();
	        if (!$this->getServer()->getLevelByName("woolbattle5")) {
		        $player->sendMessage(f::RED . "E: Level not found (???) Unexpected");
		        $player2->sendMessage(f::RED . "E: Level not found (???) Unexpected");
	        }
	        $this->getServer()->getLevelByName("woolbattle5")->setAutoSave(false);
	        $welt = $this->getServer()->getLevelByName("woolbattle5");
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
        } elseif($level == "woolbattle6") {
	        $player->sendMessage($this->prefix . "Arena gefunden! (woolbattle6)");
	        $player2->sendMessage($this->prefix . "Arena gefunden! (woolbattle6)");
	        $arena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
	        $posx = $arena->get("x1");
	        $posy = $arena->get("y1");
	        $posz = $arena->get("z1");
	        $arena->set("usew6", true);
	        $arena->save();
	        if (!$this->getServer()->getLevelByName("woolbattle6")) {
		        $player->sendMessage(f::RED . "E: Level not found (???) Unexpected");
		        $player2->sendMessage(f::RED . "E: Level not found (???) Unexpected");
	        }
	        $this->getServer()->getLevelByName("woolbattle6")->setAutoSave(false);
	        $welt = $this->getServer()->getLevelByName("woolbattle6");
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
        } elseif($level == "woolbattle7") {
	        $player->sendMessage($this->prefix . "Arena gefunden! (woolbattle7)");
	        $player2->sendMessage($this->prefix . "Arena gefunden! (woolbattle7)");
	        $arena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
	        $posx = $arena->get("x1");
	        $posy = $arena->get("y1");
	        $posz = $arena->get("z1");
	        $arena->set("usew7", true);
	        $arena->save();
	        if (!$this->getServer()->getLevelByName("woolbattle7")) {
		        $player->sendMessage(f::RED . "E: Level not found (???) Unexpected");
		        $player2->sendMessage(f::RED . "E: Level not found (???) Unexpected");
	        }
	        $this->getServer()->getLevelByName("woolbattle7")->setAutoSave(false);
	        $welt = $this->getServer()->getLevelByName("woolbattle7");
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
        } elseif($level == "woolbattle8") {
	        $player->sendMessage($this->prefix . "Arena gefunden! (woolbattle8)");
	        $player2->sendMessage($this->prefix . "Arena gefunden! (woolbattle8)");
	        $arena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
	        $posx = $arena->get("x1");
	        $posy = $arena->get("y1");
	        $posz = $arena->get("z1");
	        $arena->set("usew8", true);
	        $arena->save();
	        if (!$this->getServer()->getLevelByName("woolbattle8")) {
		        $player->sendMessage(f::RED . "E: Level not found (???) Unexpected");
		        $player2->sendMessage(f::RED . "E: Level not found (???) Unexpected");
	        }
	        $this->getServer()->getLevelByName("woolbattle8")->setAutoSave(false);
	        $welt = $this->getServer()->getLevelByName("woolbattle8");
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
        } elseif($level == "woolbattle9") {
	        $player->sendMessage($this->prefix . "Arena gefunden! (woolbattle9)");
	        $player2->sendMessage($this->prefix . "Arena gefunden! (woolbattle9)");
	        $arena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
	        $posx = $arena->get("x1");
	        $posy = $arena->get("y1");
	        $posz = $arena->get("z1");
	        $arena->set("usew9", true);
	        $arena->save();
	        if (!$this->getServer()->getLevelByName("woolbattle9")) {
		        $player->sendMessage(f::RED . "E: Level not found (???) Unexpected");
		        $player2->sendMessage(f::RED . "E: Level not found (???) Unexpected");
	        }
	        $this->getServer()->getLevelByName("woolbattle9")->setAutoSave(false);
	        $welt = $this->getServer()->getLevelByName("woolbattle9");
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
        } elseif($level == "woolbattle10") {
	        $player->sendMessage($this->prefix . "Arena gefunden! (woolbattle10)");
	        $player2->sendMessage($this->prefix . "Arena gefunden! (woolbattle10)");
	        $arena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
	        $posx = $arena->get("x1");
	        $posy = $arena->get("y1");
	        $posz = $arena->get("z1");
	        $arena->set("usew10", true);
	        $arena->save();
	        if (!$this->getServer()->getLevelByName("woolbattle10")) {
		        $player->sendMessage(f::RED . "E: Level not found (???) Unexpected");
		        $player2->sendMessage(f::RED . "E: Level not found (???) Unexpected");
	        }
	        $this->getServer()->getLevelByName("woolbattle10")->setAutoSave(false);
	        $welt = $this->getServer()->getLevelByName("woolbattle10");
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
        else {
            $player->sendMessage($this->prefix . "Keine freie Arena vorhanden! :(");
            $player2->sendMessage($this->prefix . "Keine freie Arena vorhanden! :(");
            return false;
        }
    }
    public function onMove(PlayerMoveEvent $event){
        $player = $event->getPlayer();
        $playername = $player->getName();
        $ig = new Config("/cloud/users/".$playername.".yml", Config::YAML);
        $isIngame = $ig->get("ingame");
        if($isIngame == true) {
            $lifes = $ig->get("lifes");
            $op = $ig->get("pw");
            $op = $this->getServer()->getPlayer($op);
            $lifes = $ig->get("lifes");
            $opname = $op->getName();
            $opc = new Config("/cloud/users/".$opname.".yml", Config::YAML);
            $lifes2 = $opc->get("lifes");
            $player->sendPopup(f::GREEN . "$playername: ".f::WHITE."$lifes".f::GOLD . " vs ".f::GREEN."$opname: ".f::WHITE."$lifes2");
            if($lifes < 0) {
                $op = $ig->get("pw");
                $op = $this->getServer()->getPlayer($op);
                $op->sendMessage($this->prefix . "HGW, du hast Gewonnen!");
                $opname = $op->getName();
                $eloset = new Config("/cloud/elo/".$opname.".yml", Config::YAML);
                $celo = $eloset->get("elo");
	            $pelo = rand(20,50);
                $celo = $celo+$pelo;
                $eloset->set("elo", $celo);
                $eloset->save();
	            $op->sendMessage(f::GREEN."+ ".f::WHITE."$pelo ".f::GOLD."Elo");
                $welt = $this->getServer()->getLevelByName("lobby");
	            $cfg = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
	            $x = $cfg->get("spawnx");
	            $y = $cfg->get("spawny");
	            $z = $cfg->get("spawnz");
                $pos = new Position($x , $y , $z , $welt);
                $op->teleport($pos);
                $this->getWoolLobby($op);
                $player->sendMessage($this->prefix . "Du hast Leider Verloren");
                $arenaname = $player->getLevel()->getName();
                $eloset = new Config("/cloud/elo/".$playername.".yml", Config::YAML);
                $celo = $eloset->get("elo");
                $pelo = rand(20,40);
                $celo = $celo-$pelo;
                $eloset->set("elo", $celo);
                $eloset->save();
	            $player->sendMessage(f::RED."- ".f::WHITE."$pelo ".f::GOLD."Elo");
                $welt = $this->getServer()->getLevelByName("lobby");
	            $cfg = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
	            $x = $cfg->get("spawnx");
	            $y = $cfg->get("spawny");
	            $z = $cfg->get("spawnz");
	            $pos = new Position($x , $y , $z , $welt);
                $player->teleport($pos);
                $this->getWoolLobby($player);
                $wspwh = new Config("/cloud/users/".$playername.".yml", Config::YAML);
                $wspwh->set("ingame", false);
                $wspwh->set("woolcolor", false);
                $wspwh->set("ms", false);
                $wspwh->set("lifes", 10);
                $wspwh->set("wooltode", $wspwh->get("wooltode")+1);
                $wspwh->save();
                $wspwh = new Config("/cloud/users/".$opname.".yml", Config::YAML);
                $wspwh->set("ingame", false);
                $wspwh->set("woolcolor", false);
                $wspwh->set("ms", false);
                $wspwh->set("lifes", 10);
                $wspwh->set("woolkills", $wspwh->get("woolkills")+1);
                $wspwh->save();
	            if($arenaname == "woolbattle") {
		            $arena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
		            $arena->set("usew1", false);
		            $arena->save();
		            $this->getServer()->unloadLevel($this->getServer()->getLevelByName("woolbattle"));
		            $this->getServer()->loadLevel("woolbattle");
		            $this->getServer()->getLevelByName("woolbattle")->setAutoSave(false);
		            $this->getLogger()->info("Arena: woolbattle Geladen!");
	            } elseif($arenaname == "woolbattle2") {
		            $arena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
		            $arena->set("usew2", false);
		            $arena->save();
		            $this->getServer()->unloadLevel($this->getServer()->getLevelByName("woolbattle2"));
		            $this->getServer()->loadLevel("woolbattle2");
		            $this->getServer()->getLevelByName("woolbattle2")->setAutoSave(false);
		            $this->getLogger()->info("Arena: woolbattle2 Geladen!");
	            } elseif($arenaname == "woolbattle3") {
		            $arena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
		            $arena->set("usew3", false);
		            $arena->save();
		            $this->getServer()->unloadLevel($this->getServer()->getLevelByName("woolbattle3"));
		            $this->getServer()->loadLevel("woolbattle3");
		            $this->getServer()->getLevelByName("woolbattle3")->setAutoSave(false);
		            $this->getLogger()->info("Arena: woolbattle3 Geladen!");
	            } elseif($arenaname == "woolbattle4") {
		            $arena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
		            $arena->set("usew4", false);
		            $arena->save();
		            $this->getServer()->unloadLevel($this->getServer()->getLevelByName("woolbattle4"));
		            $this->getServer()->loadLevel("woolbattle4");
		            $this->getServer()->getLevelByName("woolbattle4")->setAutoSave(false);
		            $this->getLogger()->info("Arena: woolbattle4 Geladen!");
	            } elseif($arenaname == "5") {
		            $arena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
		            $arena->set("usew5", false);
		            $arena->save();
		            $this->getServer()->unloadLevel($this->getServer()->getLevelByName("woolbattle5"));
		            $this->getServer()->loadLevel("woolbattle5");
		            $this->getServer()->getLevelByName("woolbattle5")->setAutoSave(false);
		            $this->getLogger()->info("Arena: woolbattle5 Geladen!");
	            } elseif($arenaname == "6") {
		            $arena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
		            $arena->set("usew6", false);
		            $arena->save();
		            $this->getServer()->unloadLevel($this->getServer()->getLevelByName("woolbattle6"));
		            $this->getServer()->loadLevel("woolbattle6");
		            $this->getServer()->getLevelByName("woolbattle6")->setAutoSave(false);
		            $this->getLogger()->info("Arena: woolbattle6 Geladen!");
	            } elseif($arenaname == "7") {
		            $arena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
		            $arena->set("usew7", false);
		            $arena->save();
		            $this->getServer()->unloadLevel($this->getServer()->getLevelByName("woolbattle7"));
		            $this->getServer()->loadLevel("woolbattle7");
		            $this->getServer()->getLevelByName("woolbattle7")->setAutoSave(false);
		            $this->getLogger()->info("Arena: woolbattle6 Geladen!");
	            } elseif($arenaname == "8") {
		            $arena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
		            $arena->set("usew8", false);
		            $arena->save();
		            $this->getServer()->unloadLevel($this->getServer()->getLevelByName("woolbattle8"));
		            $this->getServer()->loadLevel("woolbattle8");
		            $this->getServer()->getLevelByName("woolbattle8")->setAutoSave(false);
		            $this->getLogger()->info("Arena: woolbattle6 Geladen!");
	            } elseif($arenaname == "9") {
		            $arena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
		            $arena->set("usew9", false);
		            $arena->save();
		            $this->getServer()->unloadLevel($this->getServer()->getLevelByName("woolbattle9"));
		            $this->getServer()->loadLevel("woolbattle9");
		            $this->getServer()->getLevelByName("woolbattle9")->setAutoSave(false);
		            $this->getLogger()->info("Arena: woolbattle6 Geladen!");
	            } elseif($arenaname == "10") {
		            $arena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
		            $arena->set("usew10", false);
		            $arena->save();
		            $this->getServer()->unloadLevel($this->getServer()->getLevelByName("woolbattle10"));
		            $this->getServer()->loadLevel("woolbattle10");
		            $this->getServer()->getLevelByName("woolbattle10")->setAutoSave(false);
		            $this->getLogger()->info("Arena: woolbattle6 Geladen!");
	            }
            } else {
                $hight = $player->getY();
                if($hight < 0) {
                    $opc = new Config("/cloud/users/".$playername.".yml", Config::YAML);
                    $clives = $opc->get("lifes");
                    $clives = $clives-1;
                    $opc->set("lifes", $clives);
                    $opc->save();
                    $welt = $player->getLevel();
                    $woolarena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
                    $pos = $opc->get("pos");
                    $x = $woolarena->get("x$pos");
                    $y = $woolarena->get("y$pos");
                    $z = $woolarena->get("z$pos");
                    $pos = new Position($x , $y , $z , $welt);
                    $player->teleport($pos);
                    $this->clearHotbar($player);
                    $this->getEq($player);
                }
            }
        }else{
            return false;
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
			    $this->setPrice($player, 32);
			    if ($this->zuwenig == true) {
				    $this->zuwenig = false;
				    $event->setCancelled(true);
			    }
			    	if($proj instanceof Snowball) {
			    		$playername = $player->getNameTag();
			    		$inv = $player->getInventory();
					    $schneeball = Item::get(Item::SNOWBALL);
					    $index = $inv->getHeldItemIndex();
					    $inv->setItem($index+1, $schneeball);
				    }
				    elseif($proj instanceof EnderPearl) {
					    $playername = $player->getNameTag();
					    $inv = $player->getInventory();
					    $schneeball = Item::get(Item::ENDER_PEARL);
					    $index = $inv->getHeldItemIndex();
					    $inv->setItem($index+1, $schneeball);
				    }
		    }
	    }
	}

}