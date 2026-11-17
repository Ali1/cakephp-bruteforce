<?php
// Pin the existing reviewed development graph without publishing a package lock.
if (!copy(__DIR__ . '/../local-test-composer.lock', dirname(__DIR__, 2) . '/composer.lock')) { exit(1); }
