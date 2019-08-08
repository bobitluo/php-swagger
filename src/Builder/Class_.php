<?php
namespace bobitluo\Php\Swagger\Builder;

use phpDocumentor\Reflection\Php\Class_ as RefClass;

class Class_{
    
    private $refClass;
    private $swagger;

    public function __construct( RefClass $refClass ){
        $this->refClass = $refClass;
    }

    public function build( $postfix = '' ){
        $classDocBlock = $this->refClass->getDocBlock();
        //$docPackages = $classDocBlock->getTagsByName( 'package' );
        //$swaggerTags = $this->buildSwaggerTags( $docPackages );
        $swaggerTags = '';

        $refMethods = $this->refClass->getMethods();
        $paths = [];

        foreach( $refMethods as $refMethod ){

            if ( $refMethod->getVisibility() != 'public' ){
                continue;
            }

            if( $postfix && ($postfix != substr($refMethod->getName(), 0-strlen($postfix))) ){
                continue;
            }

            $uri = strtolower($this->refClass->getName()) . '/' . strtolower(rtrim($refMethod->getName(), $postfix));
            $httpMethod = 'post';
            $docMethod = $refMethod->getDocBlock();

            if( $docMethod ){
                $swaggerOperation = $this->buildSwaggerOperation( $docMethod, $swaggerTags, $httpMethod );

                $paths[$uri] = [
                    $httpMethod => $swaggerOperation,
                ];
            }
        }

        return $paths;
    }

    private function buildSwaggerOperation( $docMethod, $parentTags, $httpMethod ){
        $docPackages = $docMethod->getTagsByName('package');
        $docParams = $docMethod->getTagsByName('param');
        $swaggerParams = $this->buildSwaggerParams( $docParams, $httpMethod );
        $docReturns = $docMethod->getTagsByName('return');
        $swaggerResponses = $this->buildSwaggerResponses( $docReturns );

        $swaggerOperation = [
            'tags' => $this->buildSwaggerTags($docPackages) ?: $parentTags, // 方法无@package时使用类@package摘要作为分类
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
