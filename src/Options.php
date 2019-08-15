<?php
namespace bobitluo\Php\Swagger;

class Options {

    private $options = [
        'description' => '',
        'title' => '',
        'version' => '',
        'host' => '',
        'schemes' => '',
        'securityDefinitions' => [],
        'security' => [],
        'controller_prefix' => '',
        'controller_postfix' => '',
        'action_prefix' => '',
        'action_postfix' => '',
    ];

    static private $instance;

    static public function getInstance( $options = [] ){
        if( $options ){
            self::$instance = new Options( $options );
        }

        return self::$instance;
    }

    public function __construct( $options ){
        foreach( $this->options as $k => $v ){
            if( isset($options[$k]) && $options[$k] ){
                $this->options[$k] = $options[$k];
            }
        }
    }

    public function getOption( $key ){
        return $this->options[$key];
    }

}
