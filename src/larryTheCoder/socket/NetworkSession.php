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
use larryTheCoder\socket\packets\impl\LoginPacket;
use larryTheCoder\socket\packets\Packet;
use larryTheCoder\socket\task\KeepAliveTask;
use pocketmine\Server;
use pocketmine\snooze\SleeperNotifier;
use pocketmine\utils\MainLogger;

class NetworkSession {

	/** @var NetworkThread */
	private static $networkThread;

	/** @var Packet[] */
	private $sendQueue = [];
	/** @var Packet[] */
	private $recvQueue = [];

	public function __construct(SecureSocket $plugin){
		if(self::$networkThread === null || !self::$networkThread->isRunning()){
			$loginSent = false;

			$notifier = new SleeperNotifier();
			Server::getInstance()->getTickSleeper()->addNotifier($notifier, function() use (&$loginSent): void{
				$thread = self::$networkThread;

				if(!$thread->isAuthenticated && !$loginSent){
					$this->sendPacket(new LoginPacket());

					$loginSent = true;
				}elseif(!$thread->isAuthenticated && $loginSent){
					$thread->isAuthenticated = true;

					$loginSent = false;
				}

				$this->handlePackets();
			});

			self::$networkThread = new NetworkThread(MainLogger::getLogger(), $notifier, []);
		}

		$plugin->getScheduler()->scheduleRepeatingTask(new KeepAliveTask($this), 5 * 20);
	}

	public function shutdown(): void{
		self::$networkThread->isRunning = false;
	}

	/**
	 * Handle inbound packets from remote connection.
	 */
	private function handlePackets(): void{
		self::$networkThread->handleNetworkPacket($this->recvQueue, $this->sendQueue);
	}

	public function sendPacket(Packet $packet){
		if(!$packet->isEncoded){
			$packet->encode();
		}

		$this->sendQueue[] = $packet;

		$this->handlePackets();
	}
}