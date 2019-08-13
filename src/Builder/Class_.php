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
            $swaggerOperation = $this->buildSwaggerOperation( $docMethod, $httpMethod );

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

    private function buildSwaggerOperation( $docMethod, $httpMethod ){
        $docPackages = $docMethod->getTagsByName('package');
        $docParams = $docMethod->getTagsByName('param');
        $swaggerParams = $this->buildSwaggerParams( $docParams, $httpMethod );
        $docReturns = $docMethod->getTagsByName('return');
        $swaggerResponses = $this->buildSwaggerResponses( $docReturns );

        $swaggerOperation = [
            'tags' => $this->buildSwaggerTags($docPackages) ?: $this->classPackages, // 方法无@package时使用类@package摘要作为分类
            'summary' => $docMethod->getSummary(),
            'description' => $docMethod->getDescription()->render(),
        ];

        if( $swaggerParams ){
            $swaggerOperation['parameters'] = $swaggerParams;
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

    private function buildSwaggerParams( $docParams, $httpMethod ){
        $swaggerParams = [];

        foreach( $docParams as $paramDocBlock ){
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

            $type = (string)$paramDocBlock->getType();
            if( $type == 'int' ){
                $type = 'integer';
            }
            if( $type == 'bool' ){
                $type = 'boolean';
            }

            $swaggerParam = [
                'name' => $variableName,
                'in' => $in,
                'description' => $paramDocBlock->getDescription()->render(),
                'required' => $required,
                'type' => $type,
            ];

            $swaggerParams[] = $swaggerParam;
        }

        return $swaggerParams;
    }

    private function buildSwaggerResponses( $docReturns ){
        $swaggerResponses = [];
        foreach( $docReturns as $returnDocBlock ){
            $returnDescription = (string)$returnDocBlock->getDescription();

            if( preg_match_all('/(\|.+\| *\n)+/', $returnDescription, $wikiTables) > 0 ){
                foreach( $wikiTables[0] as $wikiTable ){
                    $htmlTable = '<table>';
                    $wikiTable = trim($wikiTable);
                    $wikiRows = preg_split('/[\n\r]+/', $wikiTable);

                    foreach( $wikiRows as $i=>$wikiRow ){
                        $htmlTable .= '<tr>';
                        $wikiRow = trim($wikiRow);
                        $wikiRow = trim($wikiRow, '|');
                        $wikiCells = explode('|', $wikiRow);

                        foreach( $wikiCells as $wikiCell ){
                            $htmlTable .= $i==0 ? '<th>' : '<td>';
                            $htmlTable .= trim($wikiCell);
                            $htmlTable .= $i==0 ? '</th>' : '</td>';
                        }
                        $htmlTable .= '</tr>';
                    }
                    $htmlTable .= '</table>';
                    $returnDescription = str_replace($wikiTable, $htmlTable, $returnDescription);
                }
            }

            $swaggerExample = null;
            if( preg_match('/\n\s*\{(.*\n)*.*\}(.*\n)*$/', $returnDescription, $exampleJson ) ){
                $swaggerExample = json_decode(trim($exampleJson[0]));
                $returnDescription = str_replace($exampleJson[0], '', $returnDescription);
            }

            $returnDescription = preg_replace( '/\n/', '<br/>', $returnDescription );

            $swaggerResponse = [
                'description' => $returnDescription,
            ];

            if( $swaggerExample ){
                $swaggerResponse['example'] = $swaggerExample;
            }

            $swaggerCode = (string)$returnDocBlock->getType();
            $swaggerCode = ltrim($swaggerCode, "\\");
            $swaggerResponses[$swaggerCode] = $swaggerResponse;

        }

        return $swaggerResponses;
    }

}
