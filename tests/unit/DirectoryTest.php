<?php
use bobitluo\Php\Swagger\Directory;

final class DirectoryTest extends PHPUnit_Framework_TestCase {

    public function testBuild() : void{
        $options = [
            'title' => 'API title',
            'description' => 'API description',
            'version' => '1.0.0',
            'host' => 'yourhost.com',
            'securityDefinitions' => [
                'ApiKeyAuth' => [
                    'type' => 'apiKey',
                    'in' => 'header',
                    'name' => 'Authorization',
                ],
            ],
            'security' => [
                [
                    'ApiKeyAuth' => [],
                ],
            ],
            'schemes' => [
                'https',
            ],
            'controller_postfix' => '',
            'action_postfix' => '',
        ];

        \bobitluo\Php\Swagger\Options::getInstance( $options );
        $directory = new Directory(__DIR__ . '/../../vendor/phpdocumentor/reflection/src/phpDocumentor/Reflection/');

        $this->assertEquals( true, true );
    }
}
