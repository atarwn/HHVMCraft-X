<?php
/**
 * This file is part of HHVMCraft - a Minecraft server implemented in PHP
 *
 * @copyright Andrew Vy 2015
 * @license MIT <https://github.com/andrewvy/HHVMCraft/blob/master/LICENSE.md>
 */
namespace HHVMCraft\Core\Networking\Packets;

class DisconnectPacket {
	const id = 0xFF;
	public $reason;

	public function __construct($reason="") {
		$this->reason = $reason;
	}

	public function writePacket($StreamWrapper) {
		$str = $StreamWrapper->writeInt8(self::id) .
		$StreamWrapper->writeInt16(strlen($this->reason)) .
		$StreamWrapper->writeString16($this->reason);

		return $StreamWrapper->writePacket($str);
	}

	public function readPacket($StreamWrapper) {
		$this->reason = $StreamWrapper->readString16();
	}
}
