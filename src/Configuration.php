<?php
declare(strict_types=1);

namespace Bruteforce;

use InvalidArgumentException;

class Configuration
{
    private int $timeWindow = 300;

    private int $totalAttemptsLimit = 8;

    private ?string $stricterLimitKey = null;

    private ?int $stricterLimitAttempts = null;

    /**
     * Retained for source compatibility. Values are never stored or logged in plaintext.
     *
     * @var array<string>
     */
    public array $unencryptedKeyNames = [];

    /**
     * @return int|null
     */
    public function getStricterLimitAttempts(): ?int
    {
        return $this->stricterLimitAttempts;
    }

    /**
     * @return string|null
     */
    public function getStricterLimitKey(): ?string
    {
        return $this->stricterLimitKey;
    }

    /**
     * @return int
     */
    public function getTimeWindow(): int
    {
        return $this->timeWindow;
    }

    /**
     * @param int $timeWindow
     * @return static
     */
    public function setTimeWindow(int $timeWindow): static
    {
        if ($timeWindow < 1) {
            throw new InvalidArgumentException('timeWindow must be greater than 0');
        }
        $this->timeWindow = $timeWindow;

        return $this;
    }

    /**
     * @return int
     */
    public function getTotalAttemptsLimit(): int
    {
        return $this->totalAttemptsLimit;
    }

    /**
     * @param int $totalAttemptsLimit
     * @return static
     */
    public function setTotalAttemptsLimit(int $totalAttemptsLimit): static
    {
        if ($this->stricterLimitAttempts !== null && $totalAttemptsLimit <= $this->stricterLimitAttempts) {
            throw new InvalidArgumentException(
                'If a stricter limit on a key is set, totalAttemptsLimit must be greater',
            );
        }
        $this->totalAttemptsLimit = $totalAttemptsLimit;

        return $this;
    }

    /**
     * @return array<string>
     */
    public function getUnencryptedKeyNames(): array
    {
        return $this->unencryptedKeyNames;
    }

    /**
     * Retained for backwards compatibility; the plugin no longer permits plaintext storage or logging.
     */
    public function addUnencryptedKey(string $unencryptedKeyName): static
    {
        if (!in_array($unencryptedKeyName, $this->unencryptedKeyNames, true)) {
            $this->unencryptedKeyNames[] = $unencryptedKeyName;
        }

        return $this;
    }

    /**
     * @param string $unencryptedKeyName
     * @return static
     */
    public function removeUnencryptedKey(string $unencryptedKeyName): static
    {
        $key = array_search($unencryptedKeyName, $this->unencryptedKeyNames, true);
        if ($key !== false) {
            unset($this->unencryptedKeyNames[$key]);
            $this->unencryptedKeyNames = array_values($this->unencryptedKeyNames);
        }

        return $this;
    }

    /**
     * @return static
     */
    public function removeAllUnencryptedKeys(): static
    {
        $this->unencryptedKeyNames = [];

        return $this;
    }

    /**
     * @param string $key
     * @param int $attempts
     * @return static
     */
    public function setStricterLimitOnKey(string $key, int $attempts): static
    {
        if ($attempts >= $this->totalAttemptsLimit) {
            throw new InvalidArgumentException(
                'If a stricter limit is set on a key, the limit must be fewer than totalAttemptsLimit',
            );
        }
        $this->stricterLimitKey = $key;
        $this->stricterLimitAttempts = $attempts;

        return $this;
    }

    /**
     * @return static
     */
    public function removeStricterLimit(): static
    {
        $this->stricterLimitAttempts = null;
        $this->stricterLimitKey = null;

        return $this;
    }

    /**
     * @param string $keyName
     * @return bool
     */
    public function isKeyEncrypted(string $keyName): bool
    {
        return true;
    }
}
