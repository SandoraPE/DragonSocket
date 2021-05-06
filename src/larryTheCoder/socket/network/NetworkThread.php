<?php
/**
 * DragonSocket
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

namespace larryTheCoder\socket\network;

use larryTheCoder\SecureSocket;
use larryTheCoder\socket\packets\Packet;
use larryTheCoder\socket\packets\PacketManager;
use Phar;
use pocketmine\snooze\SleeperNotifier;
use pocketmine\Thread;
use pocketmine\utils\MainLogger;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Utils;
use Threaded;
use ThreadedLogger;
use Throwable;

class NetworkThread extends Thread {

	/** @var Threaded|string[] */
	private Threaded $recvQueue;
	/** @var Threaded|string[] */
	private Threaded $sendQueue;

	/** @var string */
	private string $settings;
	/** @var ThreadedLogger */
	private ThreadedLogger $logger;

	/** @var bool */
	public bool $isRunning = true;
	/** @var bool */
	public bool $connected = false;
	/** @var bool */
	public bool $reconnecting = false;
	/** @var bool */
	public bool $dumpMemory = false;

	/** @var SleeperNotifier */
	private SleeperNotifier $notifier;
	/** @var string */
	private string $certificatePath;

	/**
	 * The networking thread that is responsible in handling server's client connection.
	 * This network uses React\Socket as an example for PocketMine-MP socket
	 *
	 * @param SecureSocket $socket
	 * @param ThreadedLogger $logger
	 * @param SleeperNotifier $notifier
	 * @param array $settings
	 */
	public function __construct(SecureSocket $socket, ThreadedLogger $logger, SleeperNotifier $notifier, array $settings){
		$this->recvQueue = new Threaded();
		$this->sendQueue = new Threaded();

		$this->logger = $logger;
		$this->notifier = $notifier;
		$this->settings = serialize($settings);
		$this->certificatePath = $socket->getDataFolder() . "certificate.crt";

		$this->start(PTHREADS_INHERIT_INI | PTHREADS_INHERIT_CONSTANTS);
	}

	public function run(): void{
		error_reporting(-1);

		if(is_file($path = $this->getSourcePath() . "/vendor/autoload.php")){
			include $path;
		}else{
			MainLogger::getLogger()->debug("[DragonNet] Composer autoloader not found.");
			MainLogger::getLogger()->debug("[DragonNet] Please install/update Composer dependencies or use provided releases.");

			trigger_error("[DragonNet] Couldn't find composer autoloader", E_USER_ERROR);
		}

		$this->registerClassLoader();

		set_error_handler([Utils::class, 'errorExceptionHandler']);

		if($this->logger instanceof MainLogger){
			$this->logger->registerStatic();
		}

		$container = new NetworkContainer($this, unserialize($this->settings));

		try{
			$container->run();
		}catch(Throwable $err){
			MainLogger::getLogger()->logException($err);
			MainLogger::getLogger()->critical("An unrecoverable error has occurred and the socket server has crashed.");

			// Force this socket to disconnect regardless of what.
			$container->disconnect();
		}

		$this->connected = false;
		$this->reconnecting = false;
		$this->isRunning = false;
	}

	/**
	 * @param Packet[] $recvQueue Returns an array of *raw* packet, this packet is not decoded properly,
	 *                            the server must decode the packet by themselves.
	 * @param Packet[] $sendQueue Returns an array of packets that needs to be sent to the remote server.
	 *                            This packet *must be* encoded first, the thread will only read its buffer.
	 */
	public function handleNetworkPacket(array &$recvQueue, array &$sendQueue): void{
		while(($packet = $this->recvQueue->shift()) !== null){
			$recvQueue[] = PacketManager::getInstance()->createPacketFromRaw($packet);
		}

		foreach($sendQueue as $packet){
			$this->sendQueue[] = $packet->getBuffer();
		}

		$sendQueue = [];
	}

	/**
	 * @param string[] $recvQueue The packets received from remote host server.
	 * @param string[] $sendQueue The packets that will be sent to the remote host server.
	 */
	public function handleServerPacket(array &$recvQueue, array &$sendQueue): void{
		foreach($recvQueue as $packet){
			$this->recvQueue[] = $packet;
		}

		if(!empty($recvQueue)){
			$this->notifyMainThread();

			$recvQueue = [];
		}

		while(($packet = $this->sendQueue->shift()) !== null){
			$sendQueue[] = $packet;

		}
	}

	public function sync(): void{
		$maxTries = 0;
		while(!$this->connected){
			$maxTries++;

			if($maxTries >= 2000){
				MainLogger::getLogger()->info("[DragonNet] " . TextFormat::RED . "Timed out while waiting for socket to connect to the server");
				break;
			}else{
				usleep(1000);
			}
		}
	}

	public function notifyMainThread(): void{
		$this->notifier->wakeupSleeper();
	}

	public function getNotifier(): SleeperNotifier{
		return $this->notifier;
	}

	public function getThreadName(): string{
		return "Socket";
	}

	public function quit(): void{
		$this->isRunning = false;

		parent::quit();
	}

	public function getSourcePath(): string{
		$phar = Phar::running(true);

		return empty($phar) ? dirname(__DIR__, 4) : $phar;
	}

	public function getCertificatePath(): string{
		return $this->certificatePath;
	}

	public function isConnected(): bool{
		return $this->connected;
	}
}