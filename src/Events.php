<?php
namespace Hos\Plugin\Router;
/**
  * @author Maxime Allanic maxime@allanic.me
  * @license GPL
  * @internal Created 2016-07-15 12:48:28
  */
class Events{
	static private $router = null;

	static function route($arguments) {
			if (!self::$router)
				self::$router = new Route();
			return self::$router->dispatch($arguments['route']);
	}
}
