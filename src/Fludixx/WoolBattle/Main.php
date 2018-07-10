<?php

namespace Fludixx\WoolBattle;

use pocketmine\entity\projectile\Arrow;
use pocketmine\entity\projectile\EnderPearl;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\entity\ProjectileHitBlockEvent;
use pocketmine\level\particle\DestroyBlockParticle;
use pocketmine\level\particle\DustParticle;
use pocketmine\level\particle\SnowballPoofParticle;
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
use pocketmine\scheduler\Task;
use pocketmine\scheduler\TaskScheduler;
use pocketmine\scheduler\TaskHandler;

class Main extends PluginBase implements Listener{

	public $version = "1.1.4";
    public $prefix = f::WHITE . "Wool" . f::GREEN . "Battle" . f::GRAY . " | " . f::WHITE;
    public $zuwenig = false;
    public $setup = 0;
    public $kabstand = 3;
    public $arenaids = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];

    public function onEnable() {
        $this->getServer()->getPluginManager()->registerEvents($this,$this);
		$this->getLogger()->info($this->prefix . f::WHITE . f::AQUA . "WoolBattle by Fludixx" . f::GREEN .  " wurde Erfolgreich Aktiviert!");
		$this->getLogger()->info($this->prefix . "Please Report Errors on: ".f::UNDERLINE.f::AQUA."https://github.com/Fludixx/WoolBattle");
        $this->getServer()->getNetwork()->setName(f::WHITE . "Wool" . f::GREEN . "Battle");
        $this->getLogger()->info(getcwd());
        // Clearing Arenas
	    if(!is_dir("/cloud")) {@mkdir("/cloud");}
	    if(!is_dir("/cloud/cfg")) {@mkdir("/cloud/cfg");}
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
        $kconfig->set("pw", false);
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
	        $seed = bcmul(microtime(), abs(ip2long($player->getAddress())), 2);
	        mt_srand($seed);
            $geelo = mt_rand(1,20);
            $celo = $celo+$geelo;
            $eloset->set("elo", $celo);
            $eloset->save();
            $otherplayer->sendMessage(f::GREEN . "+$geelo Elo");
            $welt = $this->getServer()->getLevelByName("lobby");
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
	        $this->getLogger()->info(f::WHITE."$arenaid");
	        $arena = new Config("/cloud/maps/woolconfig.yml", Config::YAML);
	        $arena->set("usew$arenaid", false);
	        $arena->save();
	        $this->getServer()->unloadLevel($this->getServer()->getLevelByName("$arenaname"));
	        $this->getServer()->loadLevel("$arenaname");
	        $this->getServer()->getLevelByName("$arenaname")->setAutoSave(false);
	        $this->getLogger()->info("Arena: $arenaname Geladen!");
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
		        $sender->sendMessage($this->prefix."Du kannst dich nicht Teleportieren wenn du in einer Runde bist!");
		        return false;
	        } else {
		        if (!empty($args['0'])) {
			        $player = $this->getServer()->getPlayer($args['0']);
			        if (!$player) {
				        $sender->sendMessage($this->prefix . "Spieler nicht gefunden!");
				        return false;
			        } else {
				        $level = $player->getLevel()->getFolderName();
				        if ($level == "lobby") {
					        $sender->sendMessage($this->prefix . "Spieler ist in der Lobby!");
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
			        $sender->sendMessage($this->prefix . "Kein Spielername angegeben!");
			        return false;
		        }
	        }
        }
        if($command->getName() == "lobby") {
        	$name = $sender->getName();
        	$cp = new Config("/cloud/users/$name.yml", Config::YAML);
        	$inGame = $cp->get("ingame");
        	if($inGame) {
        		$sender->sendMessage($this->prefix."Du kannst dich nicht Teleportieren wenn du in einer Runde bist!");
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
} elseif ($yaw < 135 && $yaw > 45) {
	$player->setMotion(new Vector3(-4, 3, 0));
} elseif ($yaw < 225 && $yaw > 135) {
	$player->setMotion(new Vector3(0, 3, -4));
} elseif($yaw < 315 && $yaw > 225) {
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
            $x = $player->getX();$y = $player->getY();$z = $player->getZ();$y = $y-(int)$this->kabstand;
            $pos = new Vector3($x, $y, $z);
            $level = $player->getLevel();
            $level->setBlock($pos, $block);
            $x = $player->getX()+1;$y = $player->getY();$z = $player->getZ();$y = $y-(int)$this->kabstand;
            $pos = new Vector3($x, $y, $z);
            $level->setBlock($pos, $block);
            $x = $player->getX()-1;$y = $player->getY();$z = $player->getZ();$y = $y-(int)$this->kabstand;
            $pos = new Vector3($x, $y, $z);
            $level->setBlock($pos, $block);
            $x = $player->getX();$y = $player->getY();$z = $player->getZ()-1;$y = $y-(int)$this->kabstand;
            $pos = new Vector3($x, $y, $z);
            $level->setBlock($pos, $block);
            $x = $player->getX();$y = $player->getY();$z = $player->getZ()+1;$y = $y-(int)$this->kabstand;
            $pos = new Vector3($x, $y, $z);
            $level->setBlock($pos, $block);
            $x = $player->getX()+1;$y = $player->getY();$z = $player->getZ()+1;$y = $y-(int)$this->kabstand;
            $pos = new Vector3($x, $y, $z);
            $level->setBlock($pos, $rand);
            $x = $player->getX()-1;$y = $player->getY();$z = $player->getZ()-1;$y = $y-(int)$this->kabstand;
            $pos = new Vector3($x, $y, $z);
            $level->setBlock($pos, $rand);
            $x = $player->getX()+1;$y = $player->getY();$z = $player->getZ()-1;$y = $y-(int)$this->kabstand;
            $pos = new Vector3($x, $y, $z);
            $level->setBlock($pos, $rand);
            $x = $player->getX()-1;$y = $player->getY();$z = $player->getZ()+1;$y = $y-(int)$this->kabstand;
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
                $player->sendMessage($this->prefix.f::RED."Zu wenig Elo. Mid. 1000");
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
            return true;
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
	                foreach($this->arenaids as $id) {
	                	$arenaid = "w$id";
		                $$arenaid = $arena->get("usew$id");
	                }
	                foreach ($this->arenaids as $id) {
	                	$arenaname = "w$id";
		                if (!$$arenaname) {
			                $this->getArena($player, $damager, "$id");
			                return true;
		                }
		                $lastarena = array_values(array_slice($this->arenaids, -1))[0];
			                if($id == $lastarena && $$arenaname)  {
			                	$player->sendMessage($this->prefix.f::RED."Alle Arenen sind besetzt!");
				                $damager->sendMessage($this->prefix.f::RED."Alle Arenen sind besetzt!");
				                return false;
			                }
		                }
	                }
                } else {
                    return false;
                }
            }
        }
    public function getArena($player, $player2, $level)
    {
	    $player->sendMessage($this->prefix . "Arena gefunden! (woolbattle$level)");
	    $player2->sendMessage($this->prefix . "Arena gefunden! (woolbattle$level)");
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
			    } /*
			    	if($proj instanceof Snowball) {
			    		$playername = $player->getNameTag();
			    		$inv = $player->getInventory();
					    $schneeball = Item::get(Item::SNOWBALL, 0, -1);
					    $air = Item::get(0,0,0);
					    $index = $inv->getHeldItemIndex();
					    $inv->addItem($schneeball);
				    }
				    elseif($proj instanceof EnderPearl) {
					    $playername = $player->getNameTag();
					    $inv = $player->getInventory();
					    $schneeball = Item::get(Item::ENDER_PEARL, 0, -1);
					    $air = Item::get(0,0,0);
					    $index = $inv->getHeldItemIndex();
					    $inv->addItem($schneeball);
				    } */
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
			$this->plugin->getScheduler()->cancelTask($this->getTaskId());
			$this->plugin->getLogger()->info("Task for $this->player was Disabled!");
		} else {
			$playername = $player->getName();
			$ig = new Config("/cloud/users/" . $playername . ".yml", Config::YAML);
			$isIngame = $ig->get("ingame");
			if ($isIngame == true) {
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
					$this->plugin->getServer()->unloadLevel($this->getServer()->getLevelByName("$arenaname"));
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
					}
				}
			} else {
				return false;
			}
		}
	}
}