<?php
/**
 * PHP Secure Socket Client
 *
 * Copyright (C) 2020 larryTheCoder
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace larryTheCoder\socket;

use larryTheCoder\SecureSocket;
use larryTheCoder\socket\network\NetworkThread;
use larryTheCoder\socket\packets\impl\KeepAlivePacket;
use larryTheCoder\socket\packets\Packet;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\snooze\SleeperHandler;
use pocketmine\snooze\SleeperNotifier;
use pocketmine\utils\MainLogger;

class NetworkSession {

	/** @var NetworkThread|null */
	private static ?NetworkThread $networkThread = null;

	/** @var Packet[] */
	private array $sendQueue = [];
	/** @var Packet[] */
	private array $recvQueue = [];
	/** @var NetworkEvent */
	private NetworkEvent $networkEvent;

	/** @var array */
	private array $config;
	/*** @var SleeperHandler */
	private SleeperHandler $eventLoop;

	public static function getNetworkThread(): NetworkThread{
		return self::$networkThread;
	}

	public function __construct(SecureSocket $plugin){
		$this->eventLoop = Server::getInstance()->getTickSleeper();
		$this->config = $plugin->getConfig()->getAll();

		$plugin->saveResource("certificate.crt");

		if(self::$networkThread === null || !self::$networkThread->isRunning()){
			$this->eventLoop->addNotifier($notifier = new SleeperNotifier(), function(): void{
				$this->processPackets();
			});

			self::$networkThread = new NetworkThread($plugin, MainLogger::getLogger(), $notifier, $this->config);
			self::$networkThread->sync();
		}

		$this->networkEvent = new NetworkEvent();

		$plugin->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(int $currentTick): void{
			$pk = new KeepAlivePacket();

			$this->sendPacket($pk);
		}), 10 * 20);
	}

	public function getEventHandler(): NetworkEvent{
		return $this->networkEvent;
	}

	public function shutdown(): void{
		$thread = self::getNetworkThread();
		$thread->isRunning = false;

		$this->eventLoop->removeNotifier($thread->getNotifier());
	}

	public function sendPacket(Packet $packet){
		if(!$packet->isEncoded){
			$packet->encode();
		}

		$this->sendQueue[] = $packet;

		$this->processPackets();
	}

	/**
	 * Process packets from a received packets queue. This function will
	 * call an event for the packet
	 *
	 * @internal
	 */
	private function pollPackets(): void{
		foreach($this->recvQueue as $packet){
			$this->networkEvent->handleDataPacket($packet);
		}

		$this->recvQueue = [];
	}

	private function processPackets(): void{
		$thread = self::getNetworkThread();

		$thread->handleNetworkPacket($this->recvQueue, $this->sendQueue);
		$this->pollPackets();
	}
}