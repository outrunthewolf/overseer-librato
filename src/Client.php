<?php

namespace Overseer\Librato;

use Overseer\Librato\Exception\ConnectionException;
use Overseer\Librato\Exception\ConfigurationException;

/**
 * StatsD Client Class
 *
 * @author Marc Qualie <marc@marcqualie.com>
 */
class Client
{

    /**
     * Instance instances array
     * @var array
     */
    protected static $instances = array();


    /**
     * Instance ID
     * @var string
     */
    protected $instance_id;


    /**
     * Server Host
     * @var string
     */
    protected $host = '127.0.0.1';


    /**
     * Server Port
     * @var integer
     */
    protected $port = 8125;


    /**
     * Last message sent to the server
     * @var string
     */
    protected $message = '';


    /**
     * Class namespace
     * @var string
     */
    protected $namespace = '';


    /**
     * Singleton Reference
     * @param  string $name Instance name
     * @return Client Client instance
     */
    public static function instance($name = 'default')
    {
        if (! isset(self::$instances[$name])) {
            self::$instances[$name] = new Client($name);
        }
        return self::$instances[$name];
    }


    /**
     * Create a new instance
     * @return void
     */
    public function __construct($instance_id = null)
    {
        $this->instance_id = $instance_id ?: uniqid();
    }


    /**
     * Get string value of instance
     * @return string String representation of this instance
     */
    public function __toString()
    {
        return 'StatsD\Client::[' . $this->instance_id . ']';
    }


    /**
     * Initialize Connection Details
     * @param array $options Configuration options
     * @return Client This instance
     * @throws ConfigurationException If port is invalid
     */
    public function configure(array $options = array())
    {
        if (isset($options['host'])) {
            $this->host = $options['host'];
        }
        if (isset($options['port'])) {
            $port = (int) $options['port'];
            if (! $port || !is_numeric($port) || $port > 65535) {
                throw new ConfigurationException($this, 'Port is out of range');
            }
            $this->port = $port;
        }
        if (isset($options['namespace'])) {
            $this->namespace = $options['namespace'];
        }
        return $this;
    }


    /**
     * Get Host
     * @return string Host
     */
    public function getHost()
    {
        return $this->host;
    }


    /**
     * Get Port
     * @return string Port
     */
    public function getPort()
    {
        return $this->port;
    }


    /**
     * Get Namespace
     * @return string Namespace
     */
    public function getNamespace()
    {
        return $this->namespace;
    }


    /**
     * Get Last Message
     * @return string Last message sent to server
     */
    public function getLastMessage()
    {
        return $this->message;
    }

    /**
     * Gauges
     * @param  string $metric Metric to gauge
     * @param  array $data The array data for the gauge
     * @return Client This instance
     */
    public function gauge($data)
    {
        // Prefix the namespace
        $prefix = $this->namespace ? $this->namespace . '.' : '';

        // Send the actual data
        return $this->send(
            array(
                "gauges" => array(
                    array(
                        "name" => isset($data['name']) ? $prefix . $data['name'] : "",
                        "description" => isset($data['description']) ? $data['description'] : "",
                        "value" => isset($data['value']) ? $data['value'] : ""
                    )
                )
            )
        );
    }

    /**
     * Annotations
     * @param  string $metric Metric to gauge
     * @param  array $data Set the value of the gauge
     * @return Client This instance
     */
    public function annotation($data)
    {
        // Prefix the namespace
        $prefix = $this->namespace ? $this->namespace : '';

        // Send the actual data
        return $this->send(
             array(
                "annotations" => array(
                    array(
                        "description" => isset($data['description']) ? $data['description'] : "",
                        "title" => isset($data['title']) ? $data['title'] : "",
                        "start_time" => isset($data['start_time']) ? $data['start_time'] : "",
                        "end_time" => isset($data['end_time']) ? $data['end_time'] : "",
                        "source" => $prefix
                    )
                )
            )
        );
    }

    /**
     * Send Data to UDP Server
     * @param  array $data A list of messages to send to the server
     * @return Client This instance
     * @throws ConnectionException If there is a connection problem with the host
     */
    protected function send(array $data)
    {
        // Open a socket connection
        $socket = @fsockopen('udp://' . $this->host, $this->port, $errno, $errstr);
        if (! $socket) {
            throw new ConnectionException($this, '(' . $errno . ') ' . $errstr);
        }

        // Build the message data
        $this->messages = array();
        $this->messages[] = json_encode($data);
        $this->message = implode("\n", $this->messages);

        // Send it off
        @fwrite($socket, $this->message);
        fclose($socket);
        return $this;
    }
}
