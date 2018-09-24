<?php

namespace Fludixx\Woolbattle;

use pocketmine\Player;
use pocketmine\scheduler\Task;

class Cooldown extends Task {

	public $pl;
	public $player;

	public function __construct(Woolbattle $pl, Player $player)
	{
		$this->pl = $pl;
		$this->player = $player;
	}

	public function onRun(int $currentTick)
	{
		$this->pl->players[$this->player->getName()]["cooldown"] = FALSE;
		$this->pl->getScheduler()->cancelTask($this->getTaskId());
	}
}