<?php
/**
 * Created by PhpStorm.
 * User: mallanic
 * Date: 01/04/16
 * Time: 15:04
 */

namespace Hos\Plugin\Router;

use Hos\Option;
use Hos\Dispatcher;
use Hos\Error;
use Hos\Chronometer;
use League\Flysystem\Util\MimeType;
use League\Glide\ServerFactory;

class Route
{
    static $DEFAULT_ROUTE = [];
    private $query;
		private $cachedRoute = [];

    public function __construct()
    {

        self::$DEFAULT_ROUTE = [
            '/^\/api\/doc\/(.*)/' => function ($matches) {
							return Dispatcher::dispatch("cache.file", [
								"file" => Option::VENDOR_API_DOC_DIR.($matches[1] ? $matches[1] : "index.html")
							], true);
            },
            '/^\/api\/(.*)/' => function ($matches) {
							return Dispatcher::dispatch("api.generate", array(
								"process" => $matches[1]
							), true);
            },
						'/\/(.*)\.([a-zA-Z0-9]+)$/' => function ($matches) {
							return Dispatcher::dispatch("generate.$matches[2]", array(
								"originalFile" => $matches[0],
								"path" => Option::ASSET_DIR
							), true);
            },
            '/\/(.+)$/' => function ($matches) {
							$filename = $matches[1];

							$output = Dispatcher::dispatch("cache.file.get", [
								"file" => $filename,
								"path" => Option::ASSET_DIR
							], true);
							if (!$output) {
								$output = file_get_contents(Option::ASSET_DIR.$filename);
								Dispatcher::dispatch("cache.file.add", [
									"file" => $filename,
									"path" => Option::ASSET_DIR
								]);
							}
							$mimeType = MimeType::detectByFilename($filename);
							Dispatcher::dispatch('header.add', array(
								"Content-Type" => $mimeType,
								"Pragma" => "public",
								"Cache-Control" => "public",
								"Expires" => date('r', time() + 604800),
								"Last-Modified" => date('r', filemtime(file_exists(Option::ASSET_DIR.$filename) ? Option::ASSET_DIR.$filename : Option::TEMPORARY_ASSET_DIR.$filename))
							));
              return $output;
            },
            '/.*/' => function ($matches) {
							return Dispatcher::dispatch('generate.html', array(
								"originalFile" => "index.html",
								"path" => Option::ASSET_DIR
							), true);
            }
        ];
    }

    private function match($regex, $route) {
        if (!preg_match($regex, $route, $matches))
            return false;
        return $matches;
    }

    public function initiateAPI($request) {
        //$_SERVER['REQUEST_URI'] = $request;
        /** Initiate Restler Object */
        //$rest = new Restler(!Option::isDev());
        $rest = new Api();

        /** Configuration */
        $rest->setAPIVersion(1);
        $rest->setBaseUrl(Option::getBaseUrl()."/api");
        //$rest->setSupportedFormats('XmlFormat', 'JsonFormat');

        //$rest->addAPIClass("Resources");
        $rest->addApi('Hos\\Translator');

        /** Insert All PHP Class */
        foreach (Option::get()['api'] as $class)
            $rest->addApi($class);

        /** Start */
        return $rest->handle($request);
    }

    public function renderImage($file) {
        if (!file_exists(Option::ASSET_DIR.$file))
            return false;
        $service = ServerFactory::create([
            "source" => Option::ASSET_DIR,
            "cache" => Option::TEMPORARY_ASSET_DIR,
            "watermarks" => Option::ASSET_DIR
        ]);
        if (!isset($_GET['q']))
            $_GET['q'] = 50;
        $cachedPath = $service->makeImage($file, $_GET);
				Dispatcher::dispatch('header.add', array(
					"Content-Type" => MimeType::detectByFilename($file),
					"Pragma" => "public",
					"Cache-Control" => "public",
					"Expires" => date('r', time() + 604800),
					"Last-Modified" => date('r', filemtime(Option::ASSET_DIR.$file))
				));
        return file_get_contents(Option::TEMPORARY_ASSET_DIR.$cachedPath);
    }

    public function dispatch($route) {
			Chronometer::start("route");
			if ($content = Dispatcher::dispatch("cache.values.get", [$route])) {
				return $content;
			}

      foreach(self::$DEFAULT_ROUTE as $reg => $function) {

          if ($matches = $this->match($reg, $route))
              if ($result = $function($matches)) {
								Dispatcher::dispatch("cache.values.add", [$route => $result]);
								Chronometer::end("route");
                return $result;
							}

			}
			Chronometer::end("route");
      throw new Error("The path $route not Found", null, [], 404);
    }
}
