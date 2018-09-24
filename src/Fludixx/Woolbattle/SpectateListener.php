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

class SpectateListener
{
	protected $plugin;
	public function __construct(Woolbattle $plugin)
	{
		$this->plugin = $plugin;
	}

	public function onTransaction(Player $player, Item $itemClickedOn, Item $itemClickedWith): bool
	{
		if($itemClickedOn->getId() == Item::PAPER) {
			$itemname = $itemClickedOn->getCustomName();
			$c = new Config($this->plugin->levelcfg, $this->plugin->configtype);
			$arenaname = $c->get("arenaname");
			$id = filter_var($itemname, FILTER_SANITIZE_NUMBER_INT);
			$levelname = $arenaname . $id;
			$level = $this->plugin->getServer()->getLevelByName($levelname);
			$player->sendMessage("Teleporting to $levelname");
			$player->setGamemode(3);
			$player->teleport($level->getSafeSpawn());
			$player->addTitle(f::WHITE."Spectator", f::YELLOW."Use ".f::RED."/leave".f::YELLOW." to leave the Round!");
			return true;
		}
	}
}