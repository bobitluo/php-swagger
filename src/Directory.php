<?php
namespace bobitluo\Php\Swagger;

use phpDocumentor\Reflection\Php\ProjectFactory;

use bobitluo\Php\Swagger\Builder\File as BuilderFile;

class Directory {

    private $path;
    private $files;
    private $swagger;

    public function __construct( $path, $title, $version ){
        $this->files = [];
        $this->path = $path;

        $this->swagger = [
            'swagger' => '2.0',
            'info' => [
                'title' => $title,
                'version' => $version,
            ],
            'consumes' => [
                'application/x-www-form-urlencoded',
            ],
            'paths' => [
            ],
        ];
    }

    public function build(){
        $this->files = [];
        $this->swagger['paths'] = [];

        $this->traverseDir( $this->path );

        $sourceFiles = [];

        foreach( $this->files as $file ){
            $sourceFiles[] = new \phpDocumentor\Reflection\File\LocalFile( $file );
        }

        $projectFactory = ProjectFactory::createInstance();
        $project = $projectFactory->create('controllers', $sourceFiles);
        $refFiles = $project->getFiles();

        foreach( $refFiles as $refFile ){
            $builderFile = new BuilderFile( $refFile );
            $this->swagger['paths'] += $builderFile->build();
        }

        return json_encode( $this->swagger );
    }

    private function traverseDir( $path ){
        $handle = opendir( $path );

        try{
            while( false !== ($file = readdir($handle)) ){
                if( in_array($file, ['.', '..']) ){
                    continue;
                }

                $file = $path . DIRECTORY_SEPARATOR . $file;

                if( is_file($file) && strtolower(pathinfo($file, PATHINFO_EXTENSION)) == 'php' ){
                    $this->files[] = $file;
                }elseif( is_dir($file) ){
                    $this->traverseDir( $file );
                }
            }

            closedir( $handle );
        }catch( \Throwable $e ){
            error_log( $e );
            closedir( $handle );
        }
    }
}
