<?php
/*
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

declare(strict_types = 1);

namespace larryTheCoder\socket\packets;

use larryTheCoder\socket\packets\impl\DisconnectPacket;
use larryTheCoder\socket\packets\impl\GenericPacket;
use larryTheCoder\socket\packets\impl\KeepAlivePacket;
use larryTheCoder\socket\packets\protocol\DragonNetProtocol;
use pocketmine\utils\Binary;
use pocketmine\utils\SingletonTrait;

class PacketManager {
	use SingletonTrait;

	/** @var string[] */
	private array $packets = [];

	public function __construct(){
		$this->registerPacket(DragonNetProtocol::DISCONNECT_PACKET, DisconnectPacket::class);
		$this->registerPacket(DragonNetProtocol::KEEP_ALIVE_PACKET, KeepAlivePacket::class);
	}

	public function registerPacket(int $pid, string $className): void{
		$this->packets[$pid] = $className;
	}

	public function createPacketFromRaw(string $packet): Packet{
		$pid = Binary::readInt($packet);
		$pk = $this->packets[$pid] ?? GenericPacket::class;

		return new $pk($packet);
	}

}