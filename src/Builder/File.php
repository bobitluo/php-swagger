<?php
namespace bobitluo\Php\Swagger\Builder;

use phpDocumentor\Reflection\Php\File as RefFile;

class File{
    
    private $refFile;
    private $uriPath;
    private $swagger;

    public function __construct( RefFile $refFile, $uriPath = '' ){
        $this->refFile = $refFile;
        $this->uriPath = $uriPath;
    }

    public function build(){
        $refClasses = $this->refFile->getClasses();
        $paths = [];

        foreach( $refClasses as $refClass ){
            $class_ = new \bobitluo\Php\Swagger\Builder\Class_( $refClass, $this->uriPath );
            $paths += $class_->build();
        }

        return $paths;
    }

}
