<?php

declare(strict_types=1);

namespace Fludixx\Woolbattle;

use muqsit\invmenu\InvMenu;
use muqsit\invmenu\InvMenuHandler;
use pocketmine\block\Block;
use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\entity\projectile\Arrow;
use pocketmine\entity\projectile\EnderPearl;
use pocketmine\entity\projectile\Snowball;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\ProjectileHitBlockEvent;
use pocketmine\event\entity\ProjectileHitEntityEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\level\particle\DestroyBlockParticle;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as f;

class Woolbattle extends PluginBase implements Listener {

	const PREFIX = f::GRAY."Wool".f::WHITE."Battle ".f::DARK_GRAY."» ".f::WHITE;
	const VERSION = 2.0;
	public $cloud = "/cloud";
	public $configtype = 1;
	public $endings = [ -1 => ".config", 0 => ".properties", 1 => ".json", 2 => ".yml"];
	public $levelcfg = "";
	public $playercfg = "";
	public $players = NULL;
	public $cooldown = 100;

	public function onEnable() : void{
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getLogger()->info(f::GRAY."Woolbattle v".self::VERSION);
		if(!is_file($this->getDataFolder()."/settings.yml")) {
			@mkdir($this->getDataFolder());
			$configuration = new Config($this->getDataFolder()."/settings.yml", 2);
			$configuration->set("cloud", "/cloud");
			$configuration->set("config_type", 1);
			$configuration->set("cooldown", 5);
			$configuration->save();
		}
		@mkdir("$this->cloud/users/");
		$cfg = new Config($this->getDataFolder()."/settings.yml", 2);
		$this->cloud = $cfg->get("cloud");
		$this->configtype = $cfg->get("config_type");
		$this->levelcfg = "$this->cloud/levels{$this->endings[$this->configtype]}";
		$this->playercfg = "$this->cloud/users/";
		$this->cooldown = (int)$cfg->get("cooldown")*20;

		if(!is_file($this->levelcfg)) {
			$c = new Config($this->levelcfg, $this->configtype);
			$c->set("player1", ["x" => 0, "y" => 0, "z" => 0]);
			$c->set("player2", ["x" => 0, "y" => 0, "z" => 0]);
			$c->save();
			$this->getLogger()->info(f::GRAY."├ ".f::GREEN."Levelconfig created! ".f::WHITE."use '/woolbattle configure' to change the configuration!");
		}
		$this->getLogger()->info(f::GRAY."├ ".f::WHITE."Config: ".f::AQUA."$this->configtype");
		$this->getLogger()->info(f::GRAY."├ ".f::WHITE."Config Ending: ".f::AQUA.$this->endings[$this->configtype]);
		$this->getLogger()->info(f::GRAY."├ ".f::WHITE."Cloud: ".f::AQUA."$this->cloud");
		$this->getLogger()->info(f::GRAY."└ ".f::AQUA.f::UNDERLINE."www.github.com/Fludixx/WoolBattle".f::RESET);
	}

	public function getLobbyItems(Player $player) {
		$player->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn());
		$sword = Item::get(Item::IRON_SWORD);
		$perkshop = Item::get(Item::CHEST);
		$settings = Item::get(Item::PAPER);
		$platzhalter = Item::get(Item::GLASS_PANE, 7);
		$sword->setCustomName(f::AQUA."Send Request");
		$perkshop->setCustomName(f::GREEN."Buy Perks");
		$settings->setCustomName(f::WHITE."Settings");
		$inv = $player->getInventory();
		$inv->clearAll();
		$inv->setItem(0, $sword);
		$inv->setItem(1, $platzhalter);
		$inv->setItem(2, $platzhalter);
		$inv->setItem(3, $platzhalter);
		$inv->setItem(4, $perkshop);
		$inv->setItem(5, $platzhalter);
		$inv->setItem(6, $platzhalter);
		$inv->setItem(7, $platzhalter);
		$inv->setItem(8, $settings);
		$player->removeAllEffects();
	}

	public function onJoin(PlayerJoinEvent $event) {
		$this->getLobbyItems($event->getPlayer());
		if($event->getPlayer()->isOp()) {
			$c = new Config($this->levelcfg, $this->configtype);
			$changed = $c->get("player1");
			if($changed["x"] == 0) {
				$event->getPlayer()->sendMessage(f::LIGHT_PURPLE."Hey!\nLooks like Woolbattle isen't configured!\nTry using the command \"/woolbattle configure\"!");
			}
		}
		$this->players[$event->getPlayer()->getName()] = [
			"setup" => NULL,
			"challanged" => NULL,
			"ingame" => FALSE,
			"cooldown" => FALSE,
			"ms" => NULL,
			"lifes" => 10,
			"pos" => 1];
	}

	public function onQuit(PlayerQuitEvent $event) {
		//$this->PlayerRemoveFromArray($event->getPlayer());
	}

	public function PlayerResetArray(Player $player) {
		$name = $player->getName();
		unset($this->players[$name]);
		$this->players[$name] = [
			"setup" => NULL,
			"challanged" => NULL,
			"ingame" => FALSE,
			"cooldown" => FALSE,
			"ms" => NULL,
			"lifes" => 10,
			"pos" => 1];
	}

	public function onVoid(EntityDamageEvent $event) {
		if($event->getCause() == EntityDamageEvent::CAUSE_VOID) {
			$player = $event->getEntity();
			if($player instanceof Player) {
				$event->setCancelled(true);
				$ingame = $this->players[$player->getName()]["ingame"];
				if($ingame == TRUE) {
					$this->players[$player->getName()]["lifes"] = $this->players[$player->getName()]["lifes"]-1;
					$pos = $this->players[$player->getName()]["pos"];
					$c = new Config($this->levelcfg, $this->configtype);
					$p = $c->get("player$pos");
					$pos = new Position($p["x"], $p["y"], $p["z"], $player->getLevel());
					$player->teleport($pos);
					$this->getEq($player);
					return true;
				} else {
					$player->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn());
					return true;
				}
			}
		}
		elseif($event->getCause() == EntityDamageEvent::CAUSE_FALL) {
			$event->setCancelled(true);
		}
	}

	public function onDrop(PlayerDropItemEvent $event) {
		$event->setCancelled(true);
	}

	public function onPlace(BlockPlaceEvent $event) {
		if($event->getBlock()->getId() == Block::WOOL) {
			return true;
		} else {
			$event->setCancelled(true);
			return false;
		}
	}

	public function onBreak(BlockBreakEvent $event) {
		$player = $event->getPlayer();
		$block = $event->getBlock();
		$event->setDrops(array(Item::get(0, 0, 0)));
		if($block->getId() == 35 and $block->getDamage() == 11) {
			return true;
		}
		elseif($block->getId() == 35 and $block->getDamage() == 14) {
			return true;
		}
		elseif($block->getId() == 35) {
			$event->setCancelled(true);
			$pos = $this->players[$player->getName()]["pos"];
			if($pos == 1) {
				$player->getInventory()->addItem(Item::get(35, 14, 2));
			} else {
				$player->getInventory()->addItem(Item::get(35, 11, 2));
			}
			return true;
		} else {
			$event->setCancelled(true);
		}
		return false;
	}

	public function PlayerRemoveFromArray(Player $player) {
		$name = $player->getName();
		unset($this->players[$name]);
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
	{
		if($command->getName() == "woolbattle") {
			if(empty($args[0]) or $args[0] == "help") {
				$sender->sendMessage(f::GRAY."Woolbattle v".self::VERSION."\n".f::WHITE."-> configure"."\n".f::WHITE."-> help"."\n".f::WHITE."-> state");
				return true;
			}
			elseif($args[0] == "configure" && $sender->isOp()) {
				if($sender instanceof Player) {
					$sender->sendMessage(f::WHITE . "Woolbbattle - v" . self::VERSION .
						"\n" . f::GRAY. "How many Arenas do you want to Register? (Write Answare in Chat)");
					$this->players[$sender->getName()]["setup"] = 1;
					return true;
				}
			}
			elseif($args[0] == "state" && $sender->isOp()) {
				$c = new Config($this->levelcfg, $this->configtype);
				$arenas = $c->get("arenas");
				$arenasuffix = $c->get("arenaname");
				$free = 0;
				$inuse = 0;
				foreach($arenas as $id => $state) {
					$arenaname = $arenasuffix.$id;
					$msg = f::RED."ERROR";
					if($state == TRUE) {
						$msg = f::RED."IN USE";
						$inuse++;
					} else {
						$msg = f::GREEN."FREE";
						$free++;
					}
					$sender->sendMessage($arenaname."  ".$msg);
				}
				$sender->sendMessage(f::WHITE."---------------");
				$sender->sendMessage(f::YELLOW."$free/".($free+$inuse)." FREE");
				$sender->sendMessage(f::WHITE."==== GRAPH ====");
				$pro1 = 10/($free+$inuse);
				$green = floor($pro1*$free);
				$red = floor(($free+$inuse)-$green);
				$graph = f::GREEN.str_repeat("█", (int)$green).f::RED.str_repeat("█", (int)$red);
				$sender->sendMessage($graph);
				return true;
			}
		}
	}

	function copydir($src,$dst) {
		$dir = opendir($src);
		@mkdir($dst);
		while(false !== ( $file = readdir($dir)) ) {
			if (( $file != '.' ) && ( $file != '..' )) {
				if ( is_dir($src . '/' . $file) ) {
					$this->copydir($src . '/' . $file,$dst . '/' . $file);
				}
				else {
					copy($src . '/' . $file,$dst . '/' . $file);
				}
			}
		}
		closedir($dir);
	}

	public function onChat(PlayerChatEvent $event) {
		$player = $event->getPlayer();
		$setup = $this->players[$player->getName()]["setup"];
		if($setup == 1) {
			$event->setCancelled(true);
			$c = new Config($this->levelcfg, $this->configtype);
			$numbers = range(1, (int)$event->getMessage());
			$arenas = [];
			foreach($numbers as $number) {
				$arenas[$number] = FALSE;
			}
			$c->set("arenas", $arenas);
			$c->save();
			$player->sendMessage(f::GREEN."OK! Registered ".(int)$event->getMessage()." arenas!");
			$player->sendMessage(f::GRAY."Whats the name of your Arena? (Foldername)");
			$this->players[$player->getName()]["setup"] = 2;
		}
		elseif($setup == 2) {
			$event->setCancelled(true);
			$levelname = $event->getMessage();
			if($this->getServer()->loadLevel($levelname)) {
				$this->getServer()->loadLevel($levelname);
				$level = $this->getServer()->getLevelByName($levelname);
				$player->sendMessage(f::GREEN."OK! $levelname was found!");
				$c = new Config($this->levelcfg, $this->configtype);
				$levels = $c->get("arenas");
				$this->getLogger()->info(f::YELLOW."Genaratig Arenas... This can take a while!");
				foreach($levels as $num => $state) {
					$this->copydir(getcwd()."/worlds/$levelname", getcwd()."/worlds/$levelname$num");
				}
				$c->set("arenaname", $levelname);
				$c->save();
				$player->teleport($level->getSafeSpawn());
				$player->setGamemode(1);
				$player->getInventory()->setItem(0, Item::get(Item::WOOL, 14));
				$player->sendMessage(f::GRAY."Please place a Block at the Spawn of the ".f::RED."RED".f::GRAY." Player!");
				$this->players[$player->getName()]["setup"] = 3;
			} else {
				$player->sendMessage(f::RED."Woops! This level does not exist! Please try again!");
			}
		}
	}

	public function onPlaceSetup(BlockPlaceEvent $event) {
		$player = $event->getPlayer();
		$setup = $this->players[$player->getName()]["setup"];
		if($setup == 3) {
			$event->setCancelled(true);
			$player->sendMessage(f::GRAY."Please place a Block at the Spawn of the ".f::BLUE."BLUE".f::GRAY." Player!");
			$player->getInventory()->setItem(0, Item::get(Item::WOOL, 11));
			$c = new Config($this->levelcfg, $this->configtype);
			$c->set("player1", ["x" => $event->getBlock()->getX(), "y" => $event->getBlock()->getY(),
				"z" => $event->getBlock()->getZ()]);
			$c->save();
			$this->players[$player->getName()]["setup"] = 4;
		}
		elseif($setup == 4) {
			$event->setCancelled(true);
			$player->getInventory()->setItem(0, Item::get(Item::WOOL, 11));
			$c = new Config($this->levelcfg, $this->configtype);
			$c->set("player2", ["x" => $event->getBlock()->getX(), "y" => $event->getBlock()->getY(),
				"z" => $event->getBlock()->getZ()]);
			$c->save();
			$player->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn());
			$player->setGamemode(0);
			$player->sendMessage(f::GREEN."OK! Everything is setted up!");
			$this->getLobbyItems($player);
			$this->players[$player->getName()]["setup"] = NULL;
		}
	}

	public function onInteract(PlayerInteractEvent $event) {
		$itemname = $event->getItem()->getName();
		$player = $event->getPlayer();
		if($itemname == f::GREEN."Buy Perks") {
			if (!InvMenuHandler::isRegistered()) {
				InvMenuHandler::register($this);
			}
			$inv = InvMenu::create(InvMenu::TYPE_CHEST);
			$inv->setName(f::DARK_PURPLE."Perks Shop");
			$inv->readonly();
			$chest = $inv->getInventory();
			$chest->setItem(10, Item::get(Item::ENDER_PEARL)->setCustomName(f::LIGHT_PURPLE."Enderpearl"));
			$chest->setItem(12, Item::get(Item::SLIME_BLOCK)->setCustomName(f::GREEN."Powerjump"));
			$chest->setItem(14, Item::get(Item::SNOWBALL)->setCustomName(f::YELLOW."Switcher"));
			$chest->setItem(16, Item::get(Item::STONE_SLAB)->setCustomName(f::GOLD."Plattform"));
			$inv->send($event->getPlayer());
			$inv->setListener([new \Fludixx\Woolbattle\PerkshopListener($this), "onTransaction"]);
		}
		elseif($itemname == f::GREEN."Powerjump") {
			$cooldown = $this->players[$player->getName()]["cooldown"];
			if ($cooldown == TRUE) {
				$player->sendMessage(self::PREFIX."Please wait ".f::GRAY."$this->cooldown"."s".f::WHITE." until you can use it again!");
				$event->setCancelled(true);
				return false;
			} else {
				$canBuy = $this->setPrice($player, 32);
				if ($canBuy) {
					$event->setCancelled(false);
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
					$this->players[$player->getName()]["cooldown"] = TRUE;
					$this->getScheduler()->scheduleDelayedTask(new Cooldown($this, $player), $this->cooldown);
					return true;
				} else {
					$event->setCancelled(true);
					return false;
				}
			}
		}
		elseif($itemname == f::GOLD."Plattform") {
			$cooldown = $this->players[$player->getName()]["cooldown"];
			if ($cooldown == TRUE) {
				$player->sendMessage(self::PREFIX."Please wait ".f::GRAY."$this->cooldown"."s".f::WHITE." until you can use it again!");
				$event->setCancelled(true);
				return false;
			} else {
				$canBuy = $this->setPrice($player, 32);
				if ($canBuy) {
					if ($this->players[$player->getName()]["pos"] == 1) {
						$rand = Block::get(35, 14);
						$block = Block::get(Item::GLASS_PANE, 14);
					} else {
						$rand = Block::get(35, 11);
						$block = Block::get(Item::GLASS_PANE, 11);
					}
					$x = $player->getX();
					$y = $player->getY();
					$z = $player->getZ();
					$y = $y - 1;
					$pos = new Vector3($x, $y, $z);
					$level = $player->getLevel();
					$level->setBlock($pos, $block);
					$x = $player->getX() + 1;
					$y = $player->getY();
					$z = $player->getZ();
					$y = $y - 6;
					$pos = new Vector3($x, $y, $z);
					$level->setBlock($pos, $block);
					$x = $player->getX() - 1;
					$y = $player->getY();
					$z = $player->getZ();
					$y = $y - 6;
					$pos = new Vector3($x, $y, $z);
					$level->setBlock($pos, $block);
					$x = $player->getX();
					$y = $player->getY();
					$z = $player->getZ() - 1;
					$y = $y - 6;
					$pos = new Vector3($x, $y, $z);
					$level->setBlock($pos, $block);
					$x = $player->getX();
					$y = $player->getY();
					$z = $player->getZ() + 1;
					$y = $y - 6;
					$pos = new Vector3($x, $y, $z);
					$level->setBlock($pos, $block);
					$x = $player->getX() + 1;
					$y = $player->getY();
					$z = $player->getZ() + 1;
					$y = $y - 6;
					$pos = new Vector3($x, $y, $z);
					$level->setBlock($pos, $rand);
					$x = $player->getX() - 1;
					$y = $player->getY();
					$z = $player->getZ() - 1;
					$y = $y - 6;
					$pos = new Vector3($x, $y, $z);
					$level->setBlock($pos, $rand);
					$x = $player->getX() + 1;
					$y = $player->getY();
					$z = $player->getZ() - 1;
					$y = $y - 6;
					$pos = new Vector3($x, $y, $z);
					$level->setBlock($pos, $rand);
					$x = $player->getX() - 1;
					$y = $player->getY();
					$z = $player->getZ() + 1;
					$y = $y - 6;
					$pos = new Vector3($x, $y, $z);
					$level->setBlock($pos, $rand);
					$this->players[$player->getName()]["cooldown"] = TRUE;
					$this->getScheduler()->scheduleDelayedTask(new Cooldown($this, $player), $this->cooldown);
					return true;
				} else {
					$event->setCancelled(true);
					return false;
				}
			}
		}
	}

	public function onHit(EntityDamageByEntityEvent $event) {
		$player = $event->getEntity();
		$oplayer = $event->getDamager();
		if($player instanceof Player and $oplayer instanceof Player) {
				if ($this->players[$oplayer->getName()]["ingame"] == FALSE) {
					if($oplayer->getInventory()->getItemInHand()->getId() == Item::IRON_SWORD) {
						$event->setCancelled(true);
						$player->sendMessage(self::PREFIX . f::GRAY . $oplayer->getName() . f::WHITE . " has challanged you!");
						$oplayer->sendMessage(self::PREFIX . "You challanged " . f::GRAY . $player->getName() . f::WHITE . "!");
						$this->players[$player->getName()]["challanged"] = $oplayer->getName();
						if ($this->players[$player->getName()]["challanged"] == $oplayer->getName() and
							$this->players[$oplayer->getName()]["challanged"] == $player->getName()) {
							$this->getArena($player, $oplayer);
						}
				} else {
					$event->setCancelled(true);
					return true;
				}

				}else {
					$player->setHealth(20);
					return true;
				}
		}
	}

	public function resetArena(int $id) {
		$c = new Config($this->levelcfg, $this->configtype);
		$arenas = $c->get("arenas");
		$arenas[$id] = FALSE;
		$c->set("arenas", $arenas);
		$c->save();
		return true;
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
		$pos = $this->players[$name]["pos"];
		if($pos == 1) {
			$player->getInventory()->remove(Item::get(35, 14, 1));
		} else {
			$player->getInventory()->remove(Item::get(35, 11, 1));
		}
	}
	public function addWool(Player $player, int $i){
		$name = $player->getName();
		$pos = $this->players[$name]["pos"];
		$inv = $player->getInventory();
		$c = 0;
		while($c < $i){
			if($pos == 1) {
				$inv->addItem(Item::get(35, 14, 1));
			} else {
				$inv->addItem(Item::get(35, 11, 1));
			}
			$c++;
		}
	}
	public function setPrice(Player $player, int $price):bool {
		$woola = $this->countWool($player);
		if($woola < $price) {
			$need = (int)$price-(int)$woola;
				$player->sendMessage(self::PREFIX."Not enough Wool! You need $need more Wool!");
			return false;
		}
		$woolprice = $price;
		$wooltot = $woola-$woolprice;
		$this->rmWool($player);
		$this->addWool($player, $wooltot);
		return true;
	}

	public function getEq(Player $player) {
		$inv = $player->getInventory();
		$inv->clearAll();
		$schere = Item::get(Item::SHEARS);
		$schere->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::KNOCKBACK), 1));
		$schere->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::EFFICIENCY), 5));
		$bow = Item::get(Item::BOW);
		$bow->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::PUNCH), 2));
		$bow->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::INFINITY), 1));
		$arrow = Item::get(Item::ARROW, 0, 1);
		$inv->setItem(0, $schere);
		$inv->setItem(1, $bow);
		$inv->setItem(9, $arrow);
		$c = new Config($this->playercfg.$player->getName().$this->endings[$this->configtype], $this->configtype);
		$perk = $c->get("perk");
		if($perk == "enderpearl") {
			$inv->addItem(Item::get(Item::ENDER_PEARL)->setCustomName(f::LIGHT_PURPLE."Enderpearl"));
		}
		elseif($perk == "jump") {
			$inv->addItem(Item::get(Item::SLIME_BALL)->setCustomName(f::GREEN."Powerjump"));
		}
		elseif($perk == "switcher") {
			$inv->addItem(Item::get(Item::SNOWBALL)->setCustomName(f::YELLOW."Switcher"));
		}
		elseif($perk == "plattform") {
			$inv->addItem(Item::get(Item::BLAZE_ROD)->setCustomName(f::GOLD."Plattform"));
		}
		$this->players[$player->getName()]["lifes"] = 10;
		$player->addEffect(new EffectInstance(Effect::getEffect(Effect::JUMP_BOOST), 2333333, 2, FALSE));
	}

	public function getArena(Player $player1, Player $player2) {
		$player1->sendMessage(self::PREFIX."Searching for Arenas...");
		$player2->sendMessage(self::PREFIX."Searching for Arenas...");
		$c = new Config($this->levelcfg, $this->configtype);
		$arenas = $c->get("arenas");
		$arenaprefix = $c->get("arenaname");
		$arenafound = FALSE;
		foreach($arenas as $arena => $state) {
			if($state == FALSE) {
				$arenas[$arena] = TRUE;
				$c->set("arenas", $arenas);
				$c->save();
				$arenafound = $arena;
				break;
			}
		}
		if($arenafound == FALSE) {
			$player1->sendMessage(self::PREFIX.f::RED."All Arenas are in use!");
			$player2->sendMessage(self::PREFIX.f::RED."All Arenas are in use!");
		} else {
			$player1->sendMessage(self::PREFIX."Found arena ".f::GRAY."$arenaprefix$arenafound".f::WHITE."! Teleporting...");
			$player2->sendMessage(self::PREFIX."Found arena ".f::GRAY."$arenaprefix$arenafound".f::WHITE."! Teleporting...");
			$levelname = (string)$arenaprefix.$arenafound;
			$this->getServer()->loadLevel($levelname);
			$level = $this->getServer()->getLevelByName($levelname);
			$level->setAutoSave(false);
			$p1 = $c->get("player1");
			$pos1 = new Position($p1["x"], $p1["y"], $p1["z"], $level);
			$p2 = $c->get("player2");
			$pos2 = new Position($p2["x"], $p2["y"], $p2["z"], $level);
			$player1->teleport($pos1);
			$player2->teleport($pos2);
			$this->players[$player1->getName()]["pos"] = 1;
			$this->players[$player2->getName()]["pos"] = 2;
			$this->players[$player1->getName()]["ms"] = $player2->getName();
			$this->players[$player2->getName()]["ms"] = $player1->getName();
			$this->players[$player1->getName()]["ingame"] = TRUE;
			$this->players[$player2->getName()]["ingame"] = TRUE;
			$this->getEq($player1);
			$this->getEq($player2);
			$this->getScheduler()->scheduleRepeatingTask(new DuellTask($this, $level), 15);
		}
	}



	public function onBow(ProjectileLaunchEvent $event) {
		$player = $event->getEntity()->getOwningEntity();
		$arrow = $event->getEntity();
		if($arrow instanceof \pocketmine\entity\projectile\Arrow and $player instanceof Player) {
			$canBuy = $this->setPrice($player, 5);
			if($canBuy) {
				$event->setCancelled(false);
				return true;
			} else {
				$event->setCancelled(true);
				return false;
			}
		}
		return false;
	}

	public function onEnderpearl(ProjectileLaunchEvent $event) {
		$player = $event->getEntity()->getOwningEntity();
		$ep = $event->getEntity();
		if($ep instanceof EnderPearl and $player instanceof Player) {
			$cooldown = $this->players[$player->getName()]["cooldown"];
			if ($cooldown == TRUE) {
				$player->sendMessage(self::PREFIX."Please wait ".f::GRAY."$this->cooldown"."s".f::WHITE." until you can use it again!");
				$event->setCancelled(true);
				return false;
			} else {
				$canBuy = $this->setPrice($player, 32);
				if ($canBuy) {
					$event->setCancelled(false);
					$this->players[$player->getName()]["cooldown"] = TRUE;
					$this->getScheduler()->scheduleDelayedTask(new Cooldown($this, $player), $this->cooldown);
					return true;
				} else {
					$event->setCancelled(true);
					return false;
				}
			}
		}
		return false;
	}
	public function onSwitchThrow(ProjectileLaunchEvent $event) {
		$player = $event->getEntity()->getOwningEntity();
		$snowball = $event->getEntity();
		if($snowball instanceof Snowball and $player instanceof Player) {
			$cooldown = $this->players[$player->getName()]["cooldown"];
			if ($cooldown == TRUE) {
				$player->sendMessage(self::PREFIX."Please wait ".f::GRAY."$this->cooldown"."s".f::WHITE." until you can use it again!");
				$event->setCancelled(true);
				return false;
			} else {
				$canBuy = $this->setPrice($player, 32);
				if ($canBuy) {
					$event->setCancelled(false);
					$this->players[$player->getName()]["cooldown"] = TRUE;
					$this->getScheduler()->scheduleDelayedTask(new Cooldown($this, $player), $this->cooldown);
					return true;
				} else {
					$event->setCancelled(true);
					return false;
				}
			}
		}
		return false;
	}

	public function onSwitchLand(ProjectileHitEntityEvent $event) {
		$player = $event->getEntity()->getOwningEntity();
		$hittedPlayer = $event->getEntityHit();
		$snowball = $event->getEntity();
		if($player instanceof Player and $hittedPlayer instanceof Player and $snowball instanceof Snowball) {
			$pos1 = $player->asPosition();
			$pos2 = $hittedPlayer->asPosition();
			$player->teleport($pos2);
			$hittedPlayer->teleport($pos1);
			$player->sendMessage(self::PREFIX."You switched places with ".f::GRAY.$hittedPlayer->getName());
			$hittedPlayer->sendMessage(self::PREFIX."You switched places with ".f::GRAY.$player->getName());
		}
	}

	public function onBlockHit(ProjectileHitBlockEvent $event) {
		$player = $event->getEntity()->getOwningEntity();
		$snowball = $event->getEntity();
		if($snowball instanceof Snowball and $player instanceof Player) {
			$player->sendMessage(self::PREFIX."Your switcher missed!");
		}
		if($snowball instanceof Arrow) {
			$arrow = $snowball;
			$block = $event->getBlockHit();
			if($block->getId() == 35 and $block->getDamage() == 14) {
				$block->getLevel()->setBlock($block->asVector3(), Block::get(0));
				$block->getLevel()->addParticle(new DestroyBlockParticle($block->asVector3(), $block));
				$arrow->despawnFromAll();
				$arrow->kill();
			}
			if($block->getId() == 35 and $block->getDamage() == 11) {
				$block->getLevel()->setBlock($block->asVector3(), Block::get(0));
				$block->getLevel()->addParticle(new DestroyBlockParticle($block->asVector3(), $block));
				$arrow->despawnFromAll();
				$arrow->kill();
			}
		}
	}

	public function onHunger(PlayerExhaustEvent $event) {
		$event->getPlayer()->setFood(20);
	}

	public function onDisable() : void{
		$this->getLogger()->info(f::GRAY."Woolbattle v".self::VERSION);
		$this->getLogger()->info(f::GRAY."└ ".f::AQUA.f::UNDERLINE."www.github.com/Fludixx/WoolBattle".f::RESET);
	}
}
