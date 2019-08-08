<?php
namespace bobitluo\Php\Swagger\Builder;

use phpDocumentor\Reflection\Php\File as RefFile;

class File{
    
    private $refFile;
    private $swagger;

    public function __construct( RefFile $refFile ){
        $this->refFile = $refFile;
    }

    public function build(){
        $refClasses = $this->refFile->getClasses();
        $paths = [];

        foreach( $refClasses as $refClass ){
            $class_ = new \bobitluo\Php\Swagger\Builder\Class_( $refClass );
            $paths += $class_->build();
        }

        return $paths;
    }

}
