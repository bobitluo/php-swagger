<?php
namespace bobitluo\Php\Swagger\Builder;

use phpDocumentor\Reflection\Php\Class_ as RefClass;

use bobitluo\Php\Swagger\Options;

class Class_{
    
    private $refClass;
    private $uriPath;

    private $controllerPrefix;
    private $controllerPostfix;
    private $actionPrefix;
    private $actionPostfix;

    private $classPackages;

    public function __construct( RefClass $refClass, $uriPath = '' ){
        $this->refClass = $refClass;
        $this->uriPath = $uriPath;

        $options = Options::getInstance();
        $this->controllerPrefix = $options->getOption('controller_prefix');
        $this->controllerPostfix = $options->getOption('controller_postfix');
        $this->actionPrefix = $options->getOption('action_prefix');
        $this->actionPostfix = $options->getOption('action_postfix');

        $this->classPackages = [];
    }

    public function build(){
        $classDocBlock = $this->refClass->getDocBlock();

        if( $classDocBlock ){
            $docPackages = $classDocBlock->getTagsByName( 'package' );
        }else{
            $docPackages = [];
        }

        $this->classPackages = $this->buildSwaggerTags( $docPackages );
        $refMethods = $this->refClass->getMethods();
        $paths = [];

        foreach( $refMethods as $refMethod ){
            if( ! $this->isActionMethod( $refMethod ) ){
                continue;
            }

            $docMethod = $refMethod->getDocBlock();

            if( ! $docMethod ){
                continue;
            }

            $uri = $this->buildUri( $this->refClass, $refMethod );
            $httpMethod = $this->buildHttpMethod( $docMethod );
            $swaggerOperation = $this->buildMethod( $docMethod, $httpMethod );

            $paths[$uri] = [
                $httpMethod => $swaggerOperation,
            ];
        }

        return $paths;
    }

    private function isActionMethod( $refMethod ){
        if ( $refMethod->getVisibility() != 'public' ){
            return false;
        }

        if( $this->actionPrefix && ($this->actionPrefix != substr($refMethod->getName(), 0, strlen($this->actionPrefix))) ){
            return false;
        }

        if( $this->actionPostfix && ($this->actionPostfix != substr($refMethod->getName(), 0-strlen($this->actionPostfix))) ){
            return false;
        }

        return true;
    }

    private function buildUri( $refClass, $refMethod ){
        $controller = $refClass->getName();
        $controller = preg_replace("/^({$this->controllerPrefix})/", '', $controller);
        $controller = preg_replace("/({$this->controllerPostfix})$/", '', $controller);

        $action = $refMethod->getName();
        $action = preg_replace("/^({$this->actionPrefix})/", '', $action);
        $action = preg_replace("/({$this->actionPostfix})$/", '', $action);

        $uri = strtolower("{$this->uriPath}/{$controller}/{$action}");
        return $uri;
    }

    private function buildHttpMethod( $docMethod ){
        $httpMethodTags = $docMethod->getTagsByName('http-method');

        if( count($httpMethodTags) > 0 ){
            return $httpMethodTags[0]->__toString();
        }else{
            return 'get';
        }
    }

    private function buildMethod( $docMethod, $httpMethod ){
        $docPackages = $docMethod->getTagsByName('package');
        $docParams = $docMethod->getTagsByName('param');
        $swaggerParams = $this->buildMethodParams( $docParams, $httpMethod );
        $docReturns = $docMethod->getTagsByName('return');
        $swaggerResponses = $this->buildMethodResponses( $docReturns, $swaggerProduces );

        $swaggerOperation = [
            'tags' => $this->buildSwaggerTags($docPackages) ?: $this->classPackages, // 方法无@package时使用类@package摘要作为分类
            'summary' => $docMethod->getSummary(),
            'description' => $docMethod->getDescription()->render(),
        ];

        if( $swaggerParams ){
            $swaggerOperation['parameters'] = $swaggerParams;
        }

        if( $swaggerProduces ){
            $swaggerOperation['produces'] = $swaggerProduces;
        }

        if( $swaggerResponses ){
            $swaggerOperation['responses'] = $swaggerResponses;
        }

        return $swaggerOperation;
    }

    private function buildSwaggerTags( $docPackages ){
        $tags = [];

        foreach( $docPackages as $docPackage ){
            $tags[] = (string)$docPackage->getDescription();
        }

        return $tags;
    }

    private function buildMethodParams( $docParams, $httpMethod ){
        $swaggerParams = [];

        foreach( $docParams as $paramDocBlock ){
            if( $paramDocBlock instanceof \phpDocumentor\Reflection\DocBlock\Tags\InvalidTag ){
                error_log( var_export($paramDocBlock, true) );
                continue;
            }

            $variableName = $paramDocBlock->getVariableName();

            // *号结尾的参数名标识必填字段
            if( substr($variableName, -1) == '*' ){
                $variableName = rtrim($variableName, '*');
                $required = true;
            }else{
                $required = false;
            }

            // h- 开头的参数使用header方式传递给服务器
            if( substr($variableName, 0, 2) == 'h-' ){
                $variableName = substr($variableName, 2);
                $in = 'header';
            }else{
                switch( $httpMethod ){
                    case 'get':
                        $in = 'query';
                        break;
                    case 'post':
                        $in = 'formData';
                        break;
                    default:
                        $in = 'query';
                }
            }

            $type = ltrim( $paramDocBlock->getType(), '\\' );

            if( $type == 'int' ){
                $type = 'integer';
            }

            if( $type == 'bool' ){
                $type = 'boolean';
            }

            $split = preg_split('/\\s+/Su', $paramDocBlock->getDescription()->render());
            $description = $split[0] ?? '';
            $default = $split[1] ?? '';

            $swaggerParam = [
                'name' => $variableName,
                'in' => $in,
                'description' => $description,
                'required' => $required,
                'type' => $type,
            ];

            if( $default !== '' ) {
                $swaggerParam['default'] = $default;
            }

            if( strtolower($swaggerParam['type']) == 'array' ) {
                $swaggerParam['collectionFormat'] = 'multi';
                $swaggerParam['items'] = [ 'type'=>'string' ];
                $swaggerParam['default'] = [$default ?? ''];
            }

            $swaggerParams[] = $swaggerParam;
        }

        return $swaggerParams;
    }

    private function buildMethodResponses( $docReturns, & $produces ){
        $swaggerResponses = [];
        $parsedown = new \Parsedown();

        foreach( $docReturns as $returnDocBlock ){
            $returnDescription = (string)$returnDocBlock->getDescription();

            $pattern = '/^\h*(\d+)\h+(\S+)\h*\n?/';
            preg_match($pattern, $returnDescription, $codeDescs);
            $returnDescription = preg_replace($pattern, '', $returnDescription);

            $code = $codeDescs[1] ?? '';
            $description = $codeDescs[2] ?? '';

            $description .= $parsedown->text( $returnDescription );
            $produces[] = $this->resolveType( $returnDocBlock->getType() );

            $swaggerResponses[$code] = [
                'description' => $description,
            ];
        }

        if( $produces ) {
            $produces = array_unique( $produces );
        }

        return $swaggerResponses;
    }

    private function resolveType( $type ){
        $type = strtolower( ltrim($type, '\\') );

        if( in_array($type, ['json', 'xml']) ){
            $type = "application/{$type}";
        }

        return $type;
    }

}
