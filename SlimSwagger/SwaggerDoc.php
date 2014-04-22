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
    public $routeDoc;

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
            'parameterType'    => 'form', // body or form
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
            $apiData['alternateapis'] = [];

            // Iterate through named routes
            foreach ($app->router()->getNamedRoutes() as $routeName => $route) {
                var_dump($route);
                $swagger = $this->getSwaggerInfo($route);
                var_dump($swagger);

                $parameters = array();

                // Init array to store the path paramater names
                $path_param_names = [];

                // Get the pattern for the current route
                $pattern = $route->getPattern();

                // Convert path parameters in the pattern to swagger style params
                $swagger_pattern = preg_replace_callback('#:([\w]+)\+?#', function ($match) use (&$path_param_names) {
                    // Store the parameter name, (minus the colon)
                    $path_param_names[] = $match[1];

                    // Return parameter formatted for swagger
                    return "{" . $match[1] . "}";
                }, $pattern);

                list($swaggerPath, $parameters) = $this->getPathArguments($pattern);

                // Init empty array to store all the HTTP operations for the route
                $operations = [];

                // Iterate through the HTTP methods for the route.
                // This is how we build the "operations" array for the swagger doc
                foreach ($route->getHttpMethods() as $method) {

                    // Get path parameter options
                    $route_path_parms = $this->routeDoc[$routeName]['PATH'];

                    // Init array to store path parameters
                    $path_params = [];

                    // Iterate through path parameters extracted from the route
                    foreach ($path_param_names as $param_name) {
                        // Set defaults and add new parameter
                        array_push($path_params,
                            array_merge([
                                "name"          => $param_name,
                                "description"   => $param_name,
                                "paramType"     => "path",
                                "required"      => true,
                                "allowMultiple" => false,
                                "dataType"      => "String"
                            ], (isset($route_path_parms[$param_name]) ? $route_path_parms[$param_name] : []))
                        );
                    }

                    // Get the querystring parameter options
                    $route_query_parms = $this->routeDoc[$routeName]['GET'];

                    // Init array to store querystring parameters
                    $query_parms = [];

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

                    // We only need either post or body params
                    // Body params are for json payloads and POST are submitted by forms
                    // Post params will take precedent
                    if (!empty($this->routeDoc[$routeName]['POST']) && is_array($this->routeDoc[$routeName]['POST'])) {
                        $route_body_params = $this->routeDoc[$routeName]['POST'];
                        $body_param_type = 'form';
                    } else if (!empty($this->routeDoc[$routeName]['BODY']) && is_array($this->routeDoc[$routeName]['BODY'])) {
                        $route_body_params = $this->routeDoc[$routeName]['BODY'];
                        $body_param_type = 'body';
                    } else {
                        $route_body_params = [];
                        $body_param_type = 'form';
                    }

                    $defaultRouteObject = array(
                        'method'           => '',
                        'summary'          => '',
                        'notes'            => '',
                        'type'             => '',
                        'nickname'         => '',
                        'parameters'       => array(),
                        'useBody'          => false, // body or post
                        'responseMessages' => array()
                    );

                    $params = [];


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
                                "paramType"     => "query",
                                "required"      => "true",
                                "allowMultiple" => false,
                                "dataType"      => "String"
                            ]);
                        }
                    }

                    // Init array to story querystring parameters
                    $body_parms = [];

                    // Only process if it is not empty and an array
                    if (!empty($route_body_params) && is_array($route_body_params)) {
                        // Iterate through body parameters
                        foreach ($route_body_params as $value) {
                            // Set defaults and add new parameter
                            array_push($body_parms,
                                array_merge([
                                    "name"          => "",
                                    "description"   => "",
                                    "paramType"     => $body_param_type,
                                    "required"      => true,
                                    "allowMultiple" => false,
                                    "dataType"      => "String"
                                ], $value)
                            );
                        }
                    }

                    // Add a new operation definition merging in all the parameter definitions.
                    $operations[] = [
                        "httpMethod"     => $method,
                        "summary"        => (!empty($this->routeDoc[$routeName]['summary'])) ? $this->routeDoc[$routeName]['summary'] : $route->getName(),
                        "responseClass"  => (!empty($this->routeDoc[$routeName]['responseClass'])) ? $this->routeDoc[$routeName]['responseClass'] : "void",
                        "errorResponses" => (!empty($this->routeDoc[$routeName]['errorResponses'])) ? $this->routeDoc[$routeName]['errorResponses'] : "",
                        "nickname"       => $route->getName(),
                        "parameters"     => array_merge($path_params, $query_parms, $body_parms)
                    ];
                    // Add a new operation definition merging in all the parameter definitions.
                    $newOperations[] = [
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

                // Now we construct the overall route details
                $route_details = [
                    "path"        => $swagger_pattern,
                    "description" => $route->getName() . " action",
                    "operations"  => $operations
                ];

                $newRouteDetails = [
                    "path"        => $swaggerPath,
                    "description" => $route->getName() . " action",
                    "operations"  => $newOperations
                ];

                // Add new route details
                array_push($apiData['apis'], $route_details);
                array_push($apiData['alternateapis'], $newRouteDetails);
            }

            // output as json
            $app->response()['Content-Type'] = 'application/json';
            $app->response()->body("<pre>" . json_encode($apiData, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        } else {
            $this->next->call();
        }

    }
} 