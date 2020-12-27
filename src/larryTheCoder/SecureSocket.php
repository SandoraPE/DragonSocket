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

declare(strict_types = 1);

namespace larryTheCoder;

use larryTheCoder\socket\NetworkSession;
use pocketmine\plugin\PluginBase;

class SecureSocket extends PluginBase {

	/** @var NetworkSession */
	private $session;

	public function onLoad(){
		$this->saveResource("config.yml", false);

		$this->session = new NetworkSession($this);
	}

	public function onDisable(){
		$this->session->shutdown();
	}

	public function getNetworkSession(): NetworkSession{
		return $this->session;
	}
}