<?php
/**
 * Created by PhpStorm.
 * User: mallanic
 * Date: 01/04/16
 * Time: 18:48
 */

namespace Hos\Plugin\Router;


use Hos\Swagger\ApiClass;
use Hos\Swagger\ApiMethod;
use Hos\Swagger\ApiParameter;
use Hos\Swagger\Schema;
use Sabre\Xml\Writer;

class Api extends Schema
{

    CONST PATH = "/resources/%s.%s";
    CONST API_PATH_RESOURCES = "resources";
    CONST API_PATH_CLASS = "class";
    CONST API_PATH_METHOD = "function";
    CONST API_PATH_FORMAT = "format";
    CONST API_PATH = "/^(?<".self::API_PATH_RESOURCES.">\/?resources)?(?:\/?(?<".self::API_PATH_CLASS.">[a-z]+)(?:\/(?<".self::API_PATH_METHOD.">[a-z\/0-9-]+))?)?(?:\.(?<".self::API_PATH_FORMAT.">[a-z]+))?/";

    public function generateDoc($name) {
        return $this->generate($name);
    }

    /**
     * @param $method \Zend_Reflection_Method
     * @return array
     */
    /*
    public function getResourcesParameter($method) {
        $parameters = [];
        foreach ($method->getParameters() as $parameter) {
            $parameters[] = [
                "name" => $parameter->getName(),
                "description" => "Test",
                "paramType" => "body",
                "required" => !$parameter->isOptional(),
                //"defaultValue" => $parameter->isOptional() ? $parameter->getDefaultValue() : null,
                "type" => "int"
            ];
        }
        return $parameters;
    }

    public function getResourcesClass($className, $format) {
        $class = $this->apis[$className][0];
        $operations = [];
        $functions = [];
        foreach ($this->apis[$className] as $key => $method) {
            if (!is_numeric($key) && $key[0] != '_') {
                $functionName = $key;
                $tags = $this->getTagToArray($method);
                $operations = [
                        //"httpMethod" => isset($tags['url']) ? $tags['url'][0] : "GET",
                        "method" => isset($tags['url']) ? $tags['url'][0] : "GET",
                        "nickname" => $key,
                        "authorizations" => [],
                        "responseMessages" => [],
                        "type" => "void",
                       // "responseClass" => $method->getReturnType(),
                        "parameters" => $this->getResourcesParameter($method),
                        "summary" => $this->getDescription($class),
                        "notes" => $this->getDescription($method)
                ];
                $functions[] = [
                    "path" => "/$className/$functionName",
                    "description" => $this->getDescription($class),
                    "operations" => $operations
                ];
            }
        }
        return $functions;
    }

    public function getResourcesMethod($className, $methodName, $format) {

    }

    public function getResources($className = null, $functionName = null, $format = "json") {
        if ($className && isset($this->apis[$className])) {
            if ($functionName && isset($this->apis[$functionName]))
                $this->OUT['apis'][] = $this->getResourcesMethod($className, $functionName, $format);
            else {
                $this->OUT['apis'] = $this->getResourcesClass($className, $format);
                $this->OUT['resourcePath'] = sprintf(self::PATH, $className, $format);
            }
        }
        else {
            $classes = [];
            foreach ($this->apis as $name=>$class) {
                $class = $class[0];
                $this->OUT['apis'][] = [
                    "path" => sprintf(self::PATH, $name, $format),
                    "description" => $this->getDescription($class)
                ];
            }
        }
        return $this->OUT;
    }*/

    /**
     * @param ApiMethod $method
     * @return array
     */
    private function getParsingArgument($method) {
        $paramsSorted = [];
        /** @var ApiParameter[] $params */
        $params = $method->getApiParameters();
        /** @var ApiParameter $param */
        foreach ($params as $param)
            $paramsSorted[] = $param->getValueFromRequest();
        return $paramsSorted;
    }

    public function handle($request) {
        $format = 'json';
        try {
            if (!preg_match(self::API_PATH, $request, $api))
                throw new ExceptionExt("api.request_parsing");

            $format = isset($api[self::API_PATH_FORMAT]) && $api[self::API_PATH_FORMAT] ? $api[self::API_PATH_FORMAT] : 'json';
            if ($api[self::API_PATH_RESOURCES])
                $out = $this->generate($format, $api[self::API_PATH_CLASS], $api[self::API_PATH_METHOD]);
            else {
                $class = $this->getApiClass($api[self::API_PATH_CLASS]);
                $method = $class->getApiMethodFromRoute($api[self::API_PATH_METHOD]);
                $out = $method->invokeArgs($class->newInstance(), $this->getParsingArgument($method));
            }
        } catch (ExceptionExt $e) {
            $out = [
                'error' => [
                    'code' => $e->getCode(),
                    'message' => $e->getMessage()
                ]
            ];
        }

        switch ($format) {
            case 'xml':
                $xml = new Writer();
                $xml->openMemory();
                $xml->setIndent(true);
                $xml->startDocument();
                $xml->write($out);
                $out = $xml->outputMemory();
                break;
            default:
                $out = json_encode($out, Option::isDev() ? JSON_PRETTY_PRINT : 0);
                Header::add('Content-Type', 'application/json');
                break;
        }
        return $out;
    }

    /**
     * @param $class \Zend_Reflection_Class | \Zend_Reflection_Method
     * @return string
     */
    private function getDescription($class) {
        if (strlen($class->getDocComment()) > 0 && $description = $class->getDocblock()->getShortDescription())
            return $description;
        return "";
    }

    /**
     * @param $reflection \Zend_Reflection_Class | \Zend_Reflection_Method
     * @return string
     */
    private function getTagToArray($reflection) {
        if (strlen($reflection->getDocComment()) <= 0
            || !($doc = $reflection->getDocblock()))
            return [];
        $tags = [];
        /** @var \Zend_Reflection_Docblock_Tag $content */
        foreach ($doc->getTags() as $content) {
            $tags[$content->getName()] = explode(' ', $content->getDescription());
        }
        return $tags;
    }
}
