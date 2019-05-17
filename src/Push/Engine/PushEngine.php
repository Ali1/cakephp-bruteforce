<?php

namespace CakePush\Push\Engine;

abstract class PushEngine {

    abstract function fire($push_opts);

}