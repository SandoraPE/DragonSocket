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

namespace larryTheCoder\socket\packets\impl;

use larryTheCoder\socket\packets\Packet;
use larryTheCoder\socket\packets\protocol\DragonNetProtocol;

class DisconnectPacket extends Packet {

	public const NETWORK_ID = DragonNetProtocol::DISCONNECT_PACKET;

	public const REMOTE_SERVER_CLOSED = 1;
	public const CLIENT_SERVER_CLOSED = 2;
	public const AUTHENTICATION_FAIL = 3;
	public const RATE_LIMITED = 4;

	/** @var int */
	public int $errorCode = self::CLIENT_SERVER_CLOSED;
	/** @var int */
	public int $rateLimit = 0;

	public function decodePayload(): void{
		$this->errorCode = $this->getInt();
		$this->rateLimit = $this->getInt();
	}

	public function encodePayload(): void{
		$this->putInt($this->errorCode);
		$this->putInt($this->rateLimit);
	}
}