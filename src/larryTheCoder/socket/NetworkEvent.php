<?php
/*
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

declare(strict_types = 1);

namespace larryTheCoder\socket;

use InvalidArgumentException;
use larryTheCoder\socket\packets\Packet;
use pocketmine\utils\MainLogger;
use pocketmine\utils\Utils;
use Throwable;

/**
 * A representation of {@see Packet} event handler. This class contains
 * all event-like features to read these packets orderly. The class uses
 * callable based events with signature of:
 *
 *     function(Packet $event): void {
 *         // Handles packet
 *     }
 *
 * @package larryTheCoder\socket
 */
class NetworkEvent {

	private const SCOPE_BLACKLIST = 0;
	private const SCOPE_WHITELIST = 1;

	/** @var int */
	private $callableId = 0;
	/** @var mixed */
	private $events = [];

	public function handleDataPacket(Packet $packet): void{
		$packet->decode();
		if(!$packet->feof()){
			$remains = substr($packet->buffer, $packet->offset);

			MainLogger::getLogger()->debug("Still " . strlen($remains) . " bytes unread in " . $packet->getName() . ": 0x" . bin2hex($remains));
		}

		foreach($this->events as [$callable, $eventType, $packets]){
			try{
				if($eventType === self::SCOPE_BLACKLIST && !in_array($packet->pid(), $packets, true)){
					$callable($packet);
				}elseif($eventType === self::SCOPE_WHITELIST && in_array($packet->pid(), $packets, true)){
					$callable($packet);
				}
			}catch(Throwable $error){
				MainLogger::getLogger()->critical('Unhandled callable function of ' . Utils::getNiceClosureName($callable) . '.');
				MainLogger::getLogger()->logException($error);
			}
		}
	}

	/**
	 * Listen to a packet that are not in the ignored packet scope. All
	 * packets that are not listed in the scope will be returned in the callable function.
	 *
	 * @param callable $onRetrieve
	 * @param int ...$blacklistPacket
	 * @return int
	 */
	public function listenToPacket(callable $onRetrieve, int ...$blacklistPacket): int{
		$this->events[$callableId = $this->callableId++] = [$onRetrieve, self::SCOPE_BLACKLIST, $blacklistPacket];

		return $callableId;
	}

	/**
	 * Listen to a packet that are in the whitelisted packet scope. Only
	 * these packets will be returned in the callable function.
	 *
	 * @param callable $onRetrieve
	 * @param int ...$whitelistPacket
	 * @return int
	 */
	public function listenOnlyPackets(callable $onRetrieve, int ...$whitelistPacket): int{
		if(empty($whitelistPacket)){
			throw new InvalidArgumentException("Whitelist packets must have at least 1 pid entry.");
		}

		$this->events[$callableId = $this->callableId++] = [$onRetrieve, self::SCOPE_WHITELIST, $whitelistPacket];

		return $callableId;
	}

	/**
	 * Cancels an event listener.
	 *
	 * @param int $callableId
	 */
	public function cancelListener(int $callableId): void{
		unset($this->events[$callableId]);
	}
}