<?php
/**
 * PHP Secure Socket Transfer
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
use larryTheCoder\socket\packets\impl\DisconnectPacket;
use larryTheCoder\socket\packets\impl\KeepAlivePacket;
use larryTheCoder\socket\packets\impl\LoginPacket;
use larryTheCoder\socket\packets\Packet;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\snooze\SleeperHandler;
use pocketmine\snooze\SleeperNotifier;
use pocketmine\utils\MainLogger;
use pocketmine\utils\TextFormat;

class NetworkSession {

	/** @var NetworkThread */
	private static $networkThread;

	/** @var Packet[] */
	private $sendQueue = [];
	/** @var Packet[] */
	private $recvQueue = [];
	/** @var NetworkEvent */
	private $networkEvent;

	/** @var int */
	private $assignedNode;
	/** @var mixed[] */
	private $config;
	/** @var SecureSocket */
	private $plugin;
	/*** @var SleeperHandler */
	private $eventLoop;

	public static function getNetworkThread(): NetworkThread{
		return self::$networkThread;
	}

	public function __construct(SecureSocket $plugin){
		$this->eventLoop = Server::getInstance()->getTickSleeper();
		$this->config = $plugin->getConfig()->getAll();
		$this->plugin = $plugin;

		if(self::$networkThread === null || !self::$networkThread->isRunning()){
			$this->eventLoop->addNotifier($notifier = new SleeperNotifier(), function() use (&$loginSent): void{
				$this->processPackets();
			});

			self::$networkThread = new NetworkThread(MainLogger::getLogger(), $notifier, $this->config);
			self::$networkThread->sync();
		}

		$this->networkEvent = new NetworkEvent();
		$this->networkEvent->listenOnlyPackets(function(Packet $packet): void{
			$thread = self::getNetworkThread();
			if($packet instanceof LoginPacket){
				$this->assignedNode = $packet->assignedNode;

				$thread->isAuthenticated = true;

				MainLogger::getLogger()->info("[DragonNet] " . TextFormat::GREEN . "Successfully authenticated to the DragonServer socket. (Node " . $this->assignedNode . ")");
			}elseif($packet instanceof DisconnectPacket && !$thread->isAuthenticated){
				MainLogger::getLogger()->info("[DragonNet] " . TextFormat::RED . "Unable to authenticate to the DragonServer socket. (Error code {$packet->errorCode}))");
			}
		}, LoginPacket::NETWORK_ID, DisconnectPacket::NETWORK_ID);

		$plugin->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(int $currentTick): void{
			$this->sendPacket(new KeepAlivePacket());

			var_dump("Sending KeepAlive");
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

		$this->processPackets(false);
	}

	private function processLogin(): void{
		if(!self::getNetworkThread()->loginSent){
			$pk = new LoginPacket();
			$pk->password = (string)$this->config['authentication']['password'];
			$pk->isPublicKeyAuth = (bool)$this->config['authentication']['passwordless'];
			$pk->publicKeyData = file_get_contents($this->plugin->getDataFolder() . $this->config['authentication']['public-key']);

			$this->sendPacket($pk);

			self::getNetworkThread()->loginSent = true;
		}else{
			$this->pollPackets();
		}
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

	/**
	 * Process sent and received packets from this client and the server, this is
	 * considered as a duplex packet operation.
	 *
	 * @param bool $authenticate
	 * @internal
	 */
	private function processPackets(bool $authenticate = true): void{
		$thread = self::getNetworkThread();

		$thread->handleNetworkPacket($this->recvQueue, $this->sendQueue);
		if(!$thread->isAuthenticated && $authenticate){
			$this->processLogin();
		}else{
			$this->pollPackets();
		}
	}
}