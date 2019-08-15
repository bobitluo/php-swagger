<?php
namespace bobitluo\Php\Swagger;

use phpDocumentor\Reflection\Php\ProjectFactory;

use bobitluo\Php\Swagger\Builder\File as BuilderFile;

class Directory {

    private $path;
    private $callbackUriPath;
    private $files;
    private $swagger;

    public function __construct( $dir, Callable $callbackUriPath = null ){
        $this->files = [];
        $this->path = $dir;
        $this->callbackUriPath = $callbackUriPath;

        $objOptions = Options::getInstance();

        $this->swagger = [
            'swagger' => '2.0',
            'info' => [
                'title' => $objOptions->getOption('title'),
                'version' => $objOptions->getOption('version'),
                'description' => $objOptions->getOption('description'),
            ],
            'host' => $objOptions->getOption('host'),
            'schemes' => $objOptions->getOption('schemes'),
            'securityDefinitions' => $objOptions->getOption('securityDefinitions'),
            'security' => $objOptions->getOption('security'),
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
            $uriPath = $this->buildUriPath( $refFile->getPath() );
            $builderFile = new BuilderFile( $refFile, $uriPath );
            $this->swagger['paths'] += $builderFile->build();
        }

        return json_encode( $this->swagger );
    }

    private function buildUriPath( $path ){
        $pathinfo = pathinfo( $path );
        $pattern = str_replace('/', '\/', $this->path);
        $pattern = "/^({$pattern})/";
        $uriPath = preg_replace($pattern, '', $pathinfo['dirname']);

        if( $this->callbackUriPath ){
            $uriPath = call_user_func_array( $this->callbackUriPath, [$uriPath] );
        }

        return $uriPath;
    }

    private function traverseDir( $path ){
        $handle = opendir( $path );

        if( $handle === false ){
            error_log( "Can't open the directory [{$path}]" );
            return false;
        }

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
