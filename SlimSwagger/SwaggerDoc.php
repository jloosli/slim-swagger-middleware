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

class SwaggerDoc extends Middleware {

    /**
     * Add swagger information to route
     * @param array $swaggerInfo
     * @return callable
     */
    public static function routeDoc($swaggerInfo=array()) {
        return function (\Slim\Route $route) use ($swaggerInfo) {
            $route->swaggerInfo = $swaggerInfo;
        };
    }

    public function call() {

    }
} 