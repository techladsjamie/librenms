<?php
/* Copyright (C) 2014 Daniel Preussker <f0o@devilcode.org>
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>. */

/**
 * API Transport
 *
 * @author f0o <f0o@devilcode.org>
 * @author PipoCanaja (github.com/PipoCanaja)
 * @copyright 2014 f0o, LibreNMS
 * @license GPL
 */

namespace LibreNMS\Alert\Transport;

use App\View\SimpleTemplate;
use LibreNMS\Alert\Transport;
use LibreNMS\Util\Proxy;

class Api extends Transport
{
    protected $name = 'API';

    public function deliverAlert($obj, $opts)
    {
        $url = $this->config['api-url'];
        $options = $this->config['api-options'];
        $headers = $this->config['api-headers'];
        $body = $this->config['api-body'];
        $method = $this->config['api-method'];
        $auth = [$this->config['api-auth-username'], $this->config['api-auth-password']];

        return $this->contactAPI($obj, $url, $options, $method, $auth, $headers, $body);
    }

    private function contactAPI($obj, $api, $options, $method, $auth, $headers, $body)
    {
        $request_opts = [];

        $method = strtolower($method);
        $host = explode('?', $api, 2)[0]; //we don't use the parameter part, cause we build it out of options.

        //get each line of key-values and process the variables for Headers;
        $request_heads = $this->parseUserOptions(SimpleTemplate::parse($headers, $obj));
        //get each line of key-values and process the variables for Options;
        $query = $this->parseUserOptions(SimpleTemplate::parse($options, $obj));

        $client = new \GuzzleHttp\Client();
        $request_opts['proxy'] = Proxy::forGuzzle();
        if (isset($auth) && ! empty($auth[0])) {
            $request_opts['auth'] = $auth;
        }
        if (count($request_heads) > 0) {
            $request_opts['headers'] = $request_heads;
        }
        if ($method == 'get') {
            $request_opts['query'] = $query;
            $res = $client->request('GET', $host, $request_opts);
        } elseif ($method == 'put') {
            $request_opts['query'] = $query;
            $request_opts['body'] = $body;
            $res = $client->request('PUT', $host, $request_opts);
        } else { //Method POST
            $request_opts['query'] = $query;
            $request_opts['body'] = SimpleTemplate::parse($body, $obj);
            $res = $client->request('POST', $host, $request_opts);
        }

        $code = $res->getStatusCode();
        if ($code != 200) {
            var_dump("API '$host' returned Error");
            var_dump('Params:');
            var_dump($query);
            var_dump('Response headers:');
            var_dump($res->getHeaders());
            var_dump('Return: ' . $res->getReasonPhrase());

            return 'HTTP Status code ' . $code;
        }

        return true;
    }

    public static function configTemplate()
    {
        return [
            'config' => [
                [
                    'title' => 'API Method',
                    'name' => 'api-method',
                    'descr' => 'API Method: GET, POST or PUT',
                    'type' => 'select',
                    'options' => [
                        'GET' => 'GET',
                        'POST' => 'POST',
                        'PUT' => 'PUT',
                    ],
                ],
                [
                    'title' => 'API URL',
                    'name' => 'api-url',
                    'descr' => 'API URL',
                    'type' => 'text',
                ],
                [
                    'title' => 'Options',
                    'name' => 'api-options',
                    'descr' => 'Enter the options (format: option=value separated by new lines)',
                    'type' => 'textarea',
                ],
                [
                    'title' => 'headers',
                    'name' => 'api-headers',
                    'descr' => 'Enter the headers (format: option=value separated by new lines)',
                    'type' => 'textarea',
                ],
                [
                    'title' => 'body',
                    'name' => 'api-body',
                    'descr' => 'Enter the body (only used by PUT/POST method, discarded GET)',
                    'type' => 'textarea',
                ],
                [
                    'title' => 'Auth Username',
                    'name' => 'api-auth-username',
                    'descr' => 'Auth Username',
                    'type' => 'text',
                ],
                [
                    'title' => 'Auth Password',
                    'name' => 'api-auth-password',
                    'descr' => 'Auth Password',
                    'type' => 'password',
                ],
            ],
            'validation' => [
                'api-method' => 'in:GET,POST,PUT',
                'api-url' => 'required|url',
            ],
        ];
    }
}
