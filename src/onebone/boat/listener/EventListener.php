<?php

namespace onebone\boat\listener;

use onebone\boat\entity\Boat as BoatEntity;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\{
	InteractPacket, InventoryTransactionPacket, MoveEntityAbsolutePacket, PlayerInputPacket, SetEntityLinkPacket, SetEntityMotionPacket
};
use pocketmine\network\mcpe\protocol\types\EntityLink;
use pocketmine\Server;

class EventListener implements Listener{
	/** @var int[] */
	private $riding = [];

	/**
	 * @param PlayerQuitEvent $event
	 */
	public function onPlayerQuitEvent(PlayerQuitEvent $event) : void{
		if(isset($this->riding[$event->getPlayer()->getName()])){
			unset($this->riding[$event->getPlayer()->getName()]);
		}
	}

	/**
	 * @param DataPacketReceiveEvent $event
	 */
	public function onDataPacketReceiveEvent(DataPacketReceiveEvent $event) : void{
		$packet = $event->getPacket();
		$player = $event->getPlayer();
		if($packet instanceof InventoryTransactionPacket && $packet->transactionType === InventoryTransactionPacket::TYPE_USE_ITEM_ON_ENTITY){
			$boat = $player->getLevel()->getEntity($packet->trData->entityRuntimeId);
			if($boat instanceof BoatEntity){
				if($packet->trData->actionType === InventoryTransactionPacket::USE_ITEM_ON_ENTITY_ACTION_INTERACT){
					$pk = new SetEntityLinkPacket();
					$pk->link = new EntityLink($boat->getId(), $player->getId(), EntityLink::TYPE_RIDER);
					Server::getInstance()->broadcastPacket($player->getViewers(), $pk);
					$player->dataPacket($pk);

					$this->riding[$player->getName()] = $packet->trData->entityRuntimeId;
					$event->setCancelled();
				}
			}
		}elseif($packet instanceof InteractPacket){
			$boat = $player->getLevel()->getEntity($packet->target);
			if($boat instanceof BoatEntity){
				if($packet->action === InteractPacket::ACTION_LEAVE_VEHICLE){
					$pk = new SetEntityLinkPacket();
					$pk->link = new EntityLink($boat->getId(), $player->getId(), EntityLink::TYPE_REMOVE);
					Server::getInstance()->broadcastPacket($player->getViewers(), $pk);
					$player->dataPacket($pk);

					if(isset($this->riding[$event->getPlayer()->getName()])){
						unset($this->riding[$event->getPlayer()->getName()]);
					}
				}
				$event->setCancelled();
			}
		}elseif($packet instanceof MoveEntityAbsolutePacket){
			if(isset($this->riding[$player->getName()])){
				$boat = $player->getLevel()->getEntity($this->riding[$player->getName()]);
				if($boat instanceof BoatEntity){
					$boat->teleport($packet->position, $packet->xRot, $packet->zRot);
					$event->setCancelled();
				}
			}
		}elseif($packet instanceof PlayerInputPacket || $packet instanceof SetEntityMotionPacket){
			if(isset($this->riding[$player->getName()])){
				//TODO: Handle PlayerInputPacket and SetEntityMotionPacket
				$event->setCancelled();
			}
		}
	}
}