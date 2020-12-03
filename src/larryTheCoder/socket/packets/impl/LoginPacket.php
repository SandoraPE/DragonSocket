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

namespace larryTheCoder\socket\packets\impl;

use larryTheCoder\socket\packets\Packet;
use larryTheCoder\socket\packets\protocol\DragonNetProtocol;

class LoginPacket extends Packet {

	public const NETWORK_ID = DragonNetProtocol::LOGIN_PROTOCOL;

	const PUBLIC_KEY_INVALID = 1;
	const PASSWORD_INVALID = 2;

	/** @var int */
	public $assignedNode = 0;
	/** @var string */
	public $password = '';
	/** @var bool */
	public $isPublicKeyAuth = false;
	/** @var string */
	public $publicKeyData = '';

	public function decodePayload(): void{
		$this->assignedNode = $this->getInt();
		$this->password = $this->getString();
		$this->isPublicKeyAuth = $this->getBool();

		$length = $this->getInt();
		$this->publicKeyData = $this->get($length);
	}

	public function encodePayload(): void{
		$this->putInt($this->assignedNode);
		$this->putString($this->password);
		$this->putBool($this->isPublicKeyAuth);

		$this->putInt(strlen($this->publicKeyData));
		$this->put($this->publicKeyData);
	}
}