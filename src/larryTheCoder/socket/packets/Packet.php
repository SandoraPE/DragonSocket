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

namespace larryTheCoder\socket\packets;

use pocketmine\network\mcpe\NetworkBinaryStream;
use ReflectionClass;
use UnexpectedValueException;

abstract class Packet extends NetworkBinaryStream {

	public const NETWORK_ID = 0;

	/** @var bool */
	public $isEncoded = false;

	/**
	 * @return int
	 */
	public function pid(){
		return $this::NETWORK_ID;
	}

	public function getName(): string{
		return (new ReflectionClass($this))->getShortName();
	}

	/**
	 * @internal
	 */
	public function decode(): void{
		$this->offset = 0;
		$pid = $this->getInt();
		if($pid !== static::NETWORK_ID){
			throw new UnexpectedValueException("Expected " . static::NETWORK_ID . " for packet ID, got $pid");
		}

		$this->decodePayload();
	}

	/**
	 * @internal
	 */
	public function encode(): void{
		$this->reset();
		$this->putInt(static::NETWORK_ID);
		$this->encodePayload();

		$this->isEncoded = true;
	}

	/**
	 * Decodes a buffer from the stream.
	 */
	public function decodePayload(): void{

	}

	/**
	 * Encodes a buffer from the stream.
	 */
	public function encodePayload(): void{

	}

}