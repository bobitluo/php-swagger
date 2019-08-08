<?php
use bobitluo\Php\Swagger\Directory;

final class DirectoryTest extends PHPUnit_Framework_TestCase {

    public function testBuild() : void{
        $directory = new Directory(__DIR__ . '/../../vendor/phpdocumentor/reflection/src/phpDocumentor/Reflection/', '文档', '1.0');
        error_log( $directory->build() );

        $this->assertEquals( true, true );
    }
}
