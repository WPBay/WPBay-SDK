<?php
namespace WPBaySDK;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class API_Manager {
    private static $instances = array();

    private $cache_time;
    private $rate_limit;
    private $retry_count;
    private $retry_delay;
    private $rate_limit_period;
    private $instance_key;
    private $debug_mode;

    private $rate_limit_transient_key;

    private function __construct($instance_key, $args = array(), $debug_mode = false) 
    {
        $defaults = array(
            'cache_time'         => 3600, // default cache time in seconds
            'rate_limit'         => 60,   // default rate limit: max requests per period
            'retry_count'        => 3,    // number of times to retry on failure
            'retry_delay'        => 2,    // delay between retries in seconds
            'rate_limit_period'  => 60,   // rate limit period in seconds
        );
        $args = wp_parse_args($args, $defaults);

        $this->cache_time         = $args['cache_time'];
        $this->rate_limit         = $args['rate_limit'];
        $this->retry_count        = $args['retry_count'];
        $this->retry_delay        = $args['retry_delay'];
        $this->rate_limit_period  = $args['rate_limit_period'];

        $this->rate_limit_transient_key = 'wpbay_sdk_api_rate_limit';
        $this->instance_key       = $instance_key;
        $this->debug_mode         = $debug_mode;
    }

    public static function get_instance($instance_key, $args = array(), $debug_mode = false) 
    {
        if(empty($instance_key))
        {
            $instance_key = 'default';
        }
        if (!isset(self::$instances[$instance_key])) {
            self::$instances[$instance_key] = new self($instance_key, $args, $debug_mode);
        }
        return self::$instances[$instance_key];
    }

    public function post_request($url, $args = array(), $no_cache = false) 
    {
        $defaults = array(
            'body'          => array(),
            'headers'       => array(),
            'timeout'       => 90,
            'user-agent'    => 'WPBay-HTTP-Client/1.0',
            'sslverify'     => false,
            'redirection'   => 5
        );
        $args = array_merge($defaults, $args);
        if($no_cache !== true)
        {
            $cache_key = 'wpbay_sdk_api_' . md5($url . serialize($args));

            $cached_response = get_transient($cache_key);
            if ($cached_response !== false) {
                return $cached_response;
            }
        }

        if ($this->is_rate_limited()) {
            return array(
                'body'     => false,
                'response' => array(
                    'code'    => 429, 
                    'message' => 'API rate limit exceeded'
                ),
                'error'    => 'API rate limit exceeded',
            );
        }
        $request_args = array(
            'method'      => 'POST',
            'timeout'     => $args['timeout'],
            'sslverify'   => $args['sslverify'],
            'headers'     => $args['headers'],
            'body'        => $args['body'], 
            'user-agent'  => $args['user-agent'],
            'redirection' => $args['redirection'] ?? 5,
        );
        $attempt = 0;
        do 
        {
            $response = wp_remote_post($url, $request_args);
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                if ($this->debug_mode === true) {
                    wpbay_log_to_file('Failed to execute wp_remote_post in API request: ' . $error_message);
                }
                sleep($this->retry_delay);
            } else {
                $response_code = wp_remote_retrieve_response_code($response);
                $response_body = wp_remote_retrieve_body($response);
                $response_message = wp_remote_retrieve_response_message($response);
    
                $result = array(
                    'body'     => $response_body,
                    'response' => array(
                        'code'    => $response_code,
                        'message' => $response_message ? $response_message : 'OK'
                    ),
                    'error'    => false,
                );
                if ($no_cache !== true) {
                    set_transient($cache_key, $result, $this->cache_time);
                }
    
                $this->update_rate_limit();
                return $result;
            }
            $attempt++;
        } while ($attempt < $this->retry_count);

        return array(
            'body'     => false,
            'response' => array(
                'code'    => 0, 
                'message' => $error_message ?? 'Request failed after retries'
            ),
            'error'    => $error_message ?? 'Request failed after retries',
        );
    }

    public function get_request($url, $args = array(), $no_cache = false) 
    {
        $defaults = array(
            'body'          => array(),
            'headers'       => array(),
            'timeout'       => 90,
            'user-agent'    => 'WPBay-HTTP-Client/1.0',
            'sslverify'     => false,
            'redirection'   => 5
        );
        $args = wp_parse_args($args, $defaults);
        if (!empty($args['body'])) {
            $url .= '?' . http_build_query($args['body']);
        }
        if($no_cache !== true)
        {
            $cache_key = 'wpbay_sdk_api_' . md5($url . serialize($args));

            $cached_response = get_transient($cache_key);
            if ($cached_response !== false) {
                return $cached_response;
            }
        }

        if ($this->is_rate_limited()) {
            return array(
                'body'     => false,
                'response' => array(
                    'code'    => 429, 
                    'message' => 'API rate limit exceeded'
                ),
                'error'    => 'API rate limit exceeded',
            );
        }
        $request_args = array(
            'method'      => 'GET', 
            'timeout'     => $args['timeout'],
            'sslverify'   => $args['sslverify'],
            'headers'     => $args['headers'],
            'user-agent'  => $args['user-agent'],
            'redirection' => $args['redirection'] ?? 5,
        );
        $attempt = 0;
        do 
        {
            $response = wp_remote_get($url, $request_args);
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                if ($this->debug_mode === true) {
                    wpbay_log_to_file('Failed to execute wp_remote_get in API request: ' . $error_message);
                }
                sleep($this->retry_delay);
            } else {
                $response_code = wp_remote_retrieve_response_code($response);
                $response_body = wp_remote_retrieve_body($response);
                $response_message = wp_remote_retrieve_response_message($response);
    
                $result = array(
                    'body'     => $response_body,
                    'response' => array(
                        'code'    => $response_code,
                        'message' => $response_message ? $response_message : 'OK'
                    ),
                    'error'    => false,
                );
                if ($no_cache !== true) {
                    set_transient($cache_key, $result, $this->cache_time);
                }
    
                $this->update_rate_limit();
                return $result;
            }
            $attempt++;
        } while ($attempt < $this->retry_count);

        return array(
            'body'     => false,
            'response' => array(
                'code'    => 0, 
                'message' => $error_message ?? 'Request failed after retries'
            ),
            'error'    => $error_message ?? 'Request failed after retries',
        );
    }

    private function is_rate_limited() 
    {
        $rate_data = get_transient($this->rate_limit_transient_key);

        if ($rate_data === false) 
        {
            $rate_data = array(
                'count'      => 0,
                'start_time' => time(),
            );
            set_transient($this->rate_limit_transient_key, $rate_data, $this->rate_limit_period);
        }

        if (time() - $rate_data['start_time'] > $this->rate_limit_period) 
        {
            $rate_data = array(
                'count'      => 0,
                'start_time' => time(),
            );
            set_transient($this->rate_limit_transient_key, $rate_data, $this->rate_limit_period);
        }

        if ($rate_data['count'] >= $this->rate_limit) 
        {
            return true;
        }

        return false;
    }

    private function update_rate_limit() 
    {
        $rate_data = get_transient($this->rate_limit_transient_key);

        if ($rate_data === false) 
        {
            $rate_data = array(
                'count'      => 1,
                'start_time' => time(),
            );
        } else {
            $rate_data['count']++;
        }

        set_transient($this->rate_limit_transient_key, $rate_data, $this->rate_limit_period);
    }
}
?>
