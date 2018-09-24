<?php
namespace Fludixx\Woolbattle;
use Fludixx\Woolbattle\Woolbattle;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Item;
use pocketmine\level\sound\AnvilBreakSound;
use pocketmine\level\sound\ClickSound;
use pocketmine\level\sound\NoteblockSound;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\Player;
use pocketmine\utils\TextFormat as f;
use pocketmine\utils\Config;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;

class PerkshopListener
{
	protected $plugin;
	public function __construct(Woolbattle $plugin)
	{
		$this->plugin = $plugin;
	}

	public function onTransaction(Player $player, Item $itemClickedOn, Item $itemClickedWith): bool
	{
		$itemname = $itemClickedOn->getCustomName();
		if($itemname == f::LIGHT_PURPLE."Enderpearl") {
			$c = new Config($this->plugin->playercfg.$player->getName()
				.$this->plugin->endings[$this->plugin->configtype], $this->plugin->configtype);
			$elo = $c->get("elo");
			$c->set("perk", "enderpearl");
			$c->save();
			$player->sendMessage($this->plugin::PREFIX."Sucessfully selected ".f::GRAY."ENDERPEARL".f::WHITE." Perk!");
			$player->getLevel()->addSound(new ClickSound($player->asVector3()));
		}
		if($itemname == f::GREEN."Powerjump") {
			$c = new Config($this->plugin->playercfg.$player->getName()
				.$this->plugin->endings[$this->plugin->configtype], $this->plugin->configtype);
			$elo = $c->get("elo");
			if($elo >= 200) {
				$c->set("perk", "jump");
				$c->save();
				$player->sendMessage($this->plugin::PREFIX."Sucessfully selected ".f::GRAY."POWERJUMP".f::WHITE." Perk!");
				$player->getLevel()->addSound(new ClickSound($player->asVector3()));
				return true;
			} else {
				$player->sendMessage($this->plugin::PREFIX."Sorry! You need at least ".f::GRAY."200 ELO".f::WHITE." to equip that!");
				$player->getLevel()->addSound(new AnvilBreakSound($player->asVector3()));
				return false;
			}
		}
		if($itemname == f::YELLOW."Switcher") {
			$c = new Config($this->plugin->playercfg.$player->getName()
				.$this->plugin->endings[$this->plugin->configtype], $this->plugin->configtype);
			$elo = $c->get("elo");
			if($elo >= 500) {
				$c->set("perk", "switcher");
				$c->save();
				$player->sendMessage($this->plugin::PREFIX."Sucessfully selected ".f::GRAY."SWITCHER".f::WHITE." Perk!");
				$player->getLevel()->addSound(new ClickSound($player->asVector3()));
				return true;
			} else {
				$player->sendMessage($this->plugin::PREFIX."Sorry! You need at least ".f::GRAY."500 ELO".f::WHITE." to equip that!");
				$player->getLevel()->addSound(new AnvilBreakSound($player->asVector3()));
				return false;
			}
		}
		if($itemname == f::GOLD."Plattform") {
			$c = new Config($this->plugin->playercfg.$player->getName()
				.$this->plugin->endings[$this->plugin->configtype], $this->plugin->configtype);
			$elo = $c->get("elo");
			if($elo >= 800) {
				$c->set("perk", "plattform");
				$c->save();
				$player->sendMessage($this->plugin::PREFIX."Sucessfully selected ".f::GRAY."PLATTFORM".f::WHITE." Perk!");
				$player->getLevel()->addSound(new ClickSound($player->asVector3()));
				return true;
			} else {
				$player->sendMessage($this->plugin::PREFIX."Sorry! You need at least ".f::GRAY."800 ELO".f::WHITE." to equip that!");
				$player->getLevel()->addSound(new AnvilBreakSound($player->asVector3()));
				return false;
			}
		}
		return false;
	}
}