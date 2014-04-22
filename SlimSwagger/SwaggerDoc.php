<?php
/**
 * Slim Swagger Documentation - A Slim middleware to generate swagger
 * documentation automatically from an application routes
 *
 * @author      Jared Loosli
 * @link        https://github.com/jloosli/slimswagger
 * @package     Jloosli
 *
 * MIT LICENSE
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

namespace Jloosli;

use Slim\Middleware;

class SwaggerDoc extends Middleware
{

    /**
     * API options
     * @var array
     */
    public $options;

    /**
     * Swagger information
     * @var array
     */
    public $swaggerInfo;

    private $defaultRouteObject = array(
        'method'           => '',
        'summary'          => '',
        'notes'            => '',
        'type'             => '',
        'nickname'         => '',
        'queryparameters'  => array(),
        'parameters'       => array(),
        'parameterType'    => 'body', // body or post
        'responseMessages' => array()
    );

    /**
     * Constructor
     * @param string $docPath The route pattern to match
     * @param array  $options Api settings
     */
    public function __construct($docPath = '/swagger.json', $options = array())
    {
        $this->docPath = $docPath;
        $this->options = $options;
    }

    public function setup()
    {
        $app = $this->app;
        $this->options = array_merge(array(
            'apiVersion'     => '1.0',
            'swaggerVersion' => '1.2',
            'basePath'       => $app->environment()['slim.url_scheme'] . "://" . $_SERVER['HTTP_HOST'] . $this->app->environment()['SCRIPT_NAME'],
            'resourcePath'   => "/api",
            'docPath'        => $this->docPath
        ), $this->options);

    }

    /**
     * Slim Route Middleware (optional) Add swagger information to route
     * @param array $swaggerInfo
     * @return callable
     */
    public static function routeDoc($swaggerInfo = array())
    {
        return function (\Slim\Route $route) use ($swaggerInfo) {
            $swaggerInfo = array_merge(array(
                "PATH" => array(),
                "GET"  => array()
            ), $swaggerInfo);
//            foreach ($route->getHttpMethods() as $method) {
//                $swaggerInfo[$method][] = true;
//            }
            $route->swaggerInfo = $swaggerInfo;
        };
    }

    protected function getPathArguments($pattern)
    {
        $arguments = array();
        $argPattern = '#(\(?):([\w_-]+)(\+?)\)?#';
        $swaggerPathFormat = preg_replace_callback($argPattern, function ($match) use (&$arguments) {
            $arguments[] = array(
                'name'      => $match[2],
                'required'  => $match[1] == '(' ? false : true,
                'type'      => 'string',
                'paramType' => 'path'
            );
            return "{" . $match[2] . "}";
        }, $pattern);
        return array($swaggerPathFormat, $arguments);
    }

    protected function getSwaggerInfo($route)
    {
        $swagger = isset($route->swagger) ? $route->swagger : array();
        return array_merge([
            'summary'          => '',
            'notes'            => '',
            'type'             => '',
            'nickname'         => '',
            'queryparameters'  => array(),
            'parameters'       => array(),
            'useBody'    => false,
            'responseMessages' => array()
        ], $swagger);
    }

    public function call()
    {
        // The Slim application
        $app = $this->app;

        // If this the special documentation generating route
        // then we short circuit the app and output the swagger documentation json
        if ($app->request()->getPathInfo() === $this->docPath) {

            // Generate base api info
            $apiData = $this->options;

            // Init empty array
            $apiData['apis'] = [];

            // Iterate through named routes
            foreach ($app->router()->getNamedRoutes() as $routeName => $route) {
                $swagger = $this->getSwaggerInfo($route);

                // Get the pattern for the current route
                $pattern = $route->getPattern();

                list($swaggerPath, $parameters) = $this->getPathArguments($pattern);

                // Init empty array to store all the HTTP operations for the route
                $operations = [];

                // Iterate through the HTTP methods for the route.
                // This is how we build the "operations" array for the swagger doc
                foreach ($route->getHttpMethods() as $method) {

                    // Only process if it is not empty and an array
                    if (!empty($route_query_parms) && is_array($route_query_parms)) {
                        foreach ($route_query_parms as $value) {
                            array_push($query_parms,
                                [
                                    "name"          => (!empty($value['name'])) ? $value['name'] : "",
                                    "description"   => (!empty($value['description'])) ? $value['description'] : "",
                                    "paramType"     => "query",
                                    "required"      => (isset($value['required']) && is_bool($value['required'])) ? $value['required'] : true,
                                    "allowMultiple" => (!empty($value['allowMultiple']) && is_bool($value['allowMultiple'])) ? $value['allowMultiple'] : false,
                                    "dataType"      => (!empty($value['dataType'])) ? $value['dataType'] : "String"
                                ]
                            );
                        }
                    }


                    if ($swagger['useBody'] === true) {
                        array_push($parameters, [
                            "name"          => "body",
                            "description"   => "body",
                            "paramType"     => "body",
                            "required"      => "true",
                            "allowMultiple" => false,
                            "dataType"      => "String"
                        ]);
                    } else {
                        foreach ($swagger['parameters'] as $query_param) {
                            array_push($parameters, [
                                "name"          => $query_param,
                                "description"   => $query_param,
                                "paramType"     => "form",
                                "required"      => "true",
                                "allowMultiple" => false,
                                "dataType"      => "String"
                            ]);
                        }
                    }

                    // Add a new operation definition merging in all the parameter definitions.
                    $operations[] = [
                        "httpMethod"     => $method,
                        "summary"        => (!empty($swagger['summary'])) ? $swagger['summary'] : $route->getName(),
                        "responseClass"  => (!empty($swagger['responseClass'])) ? $swagger['responseClass'] : "void",
                        "errorResponses" => (!empty($swagger['errorResponses'])) ? $this->swagger['errorResponses'] : "",
                        "nickname"       => $route->getName(),
                        "parameters"     => $parameters
                    ];


                    // For now only get the first of the http methods.
                    // We probably shouldn't have more than one HTTP method per named route
                    break;
                }

                $route_details = [
                    "path"        => $swaggerPath,
                    "description" => $route->getName() . " action",
                    "operations"  => $operations
                ];

                // Add new route details
                array_push($apiData['apis'], $route_details);
            }

            // output as json
            $app->response()['Content-Type'] = 'application/json';
            $app->response()->body(json_encode($apiData, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        } else {
            $this->next->call();
        }

    }
} 