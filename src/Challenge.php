<?php

namespace Bruteforce;

class Challenge {

	public $data = [];

	public $unencryptedData = []; // will be destroyed before serialize into Cache or Log

	public $encryptedKeyNames = [];

	/**
	 * @return void
	 */
	public function addData(string $keyName, string $data, bool $hashed) {
		$this->unencryptedData[$keyName] = $data;
		$this->data[$keyName] = $hashed ? password_hash($data, PASSWORD_DEFAULT) : $data;
		if ($hashed) {
			$this->encryptedKeyNames[] = $keyName;
		}
	}

	public function matchesAnOldChallenge(Challenge $oldChallenge, $onlyFirstKey = false) {
		if (array_keys($this->unencryptedData) !== array_keys($oldChallenge->data)) {
			return false;
		}

		foreach ($this->unencryptedData as $keyName => $datum) {
			if ($oldChallenge->isKeyEncrypted($keyName)) {
				if (!password_verify($datum, $oldChallenge->data[$keyName])) {
					return false;
				}
			} else {
				if ($datum !== $oldChallenge->data[$keyName]) {
					return false;
				}
			}
			if ($onlyFirstKey) {
				return true;
			}
		}
		return true;
	}

	/**
	 * @param string $keyName
	 *
	 * @return bool
	 */
	private function isKeyEncrypted(string $keyName): bool {
		return in_array($keyName, $this->encryptedKeyNames, true);
	}

	/**
	 * Return an array contain property names that you want included in object serialization
	 *
	 * @return array
	 */
	public function __sleep() {
		return ['data', 'encryptedKeyNames'];
	}

}
