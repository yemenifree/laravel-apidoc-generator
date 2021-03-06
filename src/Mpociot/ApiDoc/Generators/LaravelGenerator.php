<?php

namespace Mpociot\ApiDoc\Generators;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Request;
use League\Fractal\Manager;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use ReflectionClass;

class LaravelGenerator extends AbstractGenerator
{
    /**
     * @param  \Illuminate\Routing\Route $route
     * @param array $bindings
     * @param array $headers
     * @param bool $withResponse
     *
     * @return array
     * @throws \Exception
     */
    public function processRoute($route, $bindings = [], $headers = [], $withResponse = true)
    {
        $content = '';

        $routeAction = $route->getAction();
        $routeGroup = $this->getRouteGroup($routeAction['uses']);
        $routeDescription = $this->getRouteDescription($routeAction['uses']);
        $showresponse = null;

        if ($withResponse) {
            $response = null;
            $docblockResponse = $this->getDocblockResponse($routeDescription['tags']);
            if ($docblockResponse) {
                // we have a response from the docblock ( @response )
                $response = $docblockResponse;
                $showresponse = true;
            }
            if (!$response) {
                $transformerResponse = $this->getTransformerResponse($routeDescription['tags']);
                if ($transformerResponse) {
                    // we have a transformer response from the docblock ( @transformer || @transformercollection )
                    $response = $transformerResponse;
                    $showresponse = true;
                }
            }
            if (!$response) {
                $responderResponse = $this->getResponderResponse($routeDescription['tags']);
                if ($responderResponse) {
                    // we have a transformer response from the docblock ( @responder )
                    $response = $responderResponse;
                    $showresponse = true;
                }
            }
            if (!$response) {
                $responseClassResponse = $this->getResponseClassResponse($routeDescription['tags']);
                if ($responseClassResponse) {
                    // we have a class response from the docblock ( @responseClass )
                    $response = $responseClassResponse;
                    $showresponse = true;
                }
            }
            if (!$response) {
                $dataResponse = $this->getDataResponse($routeDescription['tags']);
                if ($dataResponse) {
                    // we have a data response from the docblock ( @data )
                    $response = $dataResponse;
                    $showresponse = true;
                }
            }
            if (!$response) {
                $response = $this->getRouteResponse($route, $bindings, $headers);
            }
            if ($response->headers->get('Content-Type') === 'application/json') {
                $content = json_encode(json_decode($response->getContent()), JSON_PRETTY_PRINT);
            } else {
                $content = $response->getContent();
            }
        }

        return $this->getParameters([
            'id' => md5($this->getUri($route) . ':' . implode($this->getMethods($route))),
            'resource' => $routeGroup,
            'middleware' => $route->middleware(),
            'title' => $routeDescription['short'],
            'description' => $routeDescription['long'],
            'methods' => $this->getMethods($route),
            'uri' => $this->getUri($route),
            'parameters' => [],
            'response' => $content,
            'showresponse' => $showresponse,
        ], $routeAction, $bindings);
    }

    /**
     * Get a response from the transformer tags.
     *
     * @param array $tags
     *
     * @return mixed
     */
    protected function getTransformerResponse($tags)
    {
        try {
            $transFormerTag = $this->getFirstTagFromDocblock($tags, ['transformer', 'transformercollection']);

            if (empty($transFormerTag) || count($transFormerTag) == 0) {
                // we didn't have any of the tags so goodbye
                return false;
            }

            $modelTag = $this->getFirstTagFromDocblock($tags, ['transformermodel']);
            $transformer = $transFormerTag->getContent();
            if (!\class_exists($transformer)) {
                // if we can't find the transformer we can't generate a response
                return;
            }

            $demoData = [];

            $reflection = new ReflectionClass($transformer);
            $method = $reflection->getMethod('transform');
            $parameter = \array_first($method->getParameters());
            $type = null;
            if ($modelTag) {
                $type = $modelTag->getContent();
            }
            if (version_compare(PHP_VERSION, '7.0.0') >= 0 && \is_null($type)) {
                // we can only get the type with reflection for PHP 7
                if ($parameter->hasType() && !$parameter->getType()->isBuiltin() && \class_exists((string)$parameter->getType())) {
                    //we have a type
                    $type = (string)$parameter->getType();
                }
            }

            // if we have modelTag
            if ($type) {
                // we have a class so we try to create an instance
                $demoData = new $type;
                try {
                    // try a factory
                    $demoData = \factory($type)->make();
                } catch (\Exception $e) {
                    if ($demoData instanceof \Illuminate\Database\Eloquent\Model) {
                        // we can't use a factory but can try to get one from the database
                        try {
                            // check if we can find one
                            $newDemoData = $type::first();
                            if ($newDemoData) {
                                $demoData = $newDemoData;
                            }
                        } catch (\Exception $e) {
                            // do nothing
                        }
                    }
                }
            } else {
                // or try get data use ( @data ) tag
                $demoData = $this->getDataTag($tags);
            }

            $serializerTags = $this->getFirstTagFromDocblock($tags, 'serializer');

            if (is_object($serializerTags)) {
                $serializer = $serializerTags->getContent();
            }

            $fractal = new Manager();
            $resource = [];
            // allow use custom serializer
            if (!empty($serializer) && class_exists($serializer)) {
                $fractal->setSerializer(new $serializer());
            }
            if ($transFormerTag->getName() == 'transformer') {
                // just one
                $resource = new Item($demoData, new $transformer);
            }
            if ($transFormerTag->getName() == 'transformercollection') {
                // a collection
                $resource = new Collection([$demoData, $demoData], new $transformer);
            }

            // if has responder
            return $this->getResponderResponse($tags, $resource, $transformer);

            return \response($fractal->createData($resource)->toJson());
        } catch (\Exception $e) {
            // it isn't possible to parse the transformer
            return;
        }
    }

    /**
     * Get Custom Data from data tag.
     *
     * @param $tags
     * @param array $default
     *
     * @return array|null
     */
    protected function getDataTag($tags, $default = [])
    {
        $additionData = $this->getFirstTagFromDocblock($tags, 'data');

        return $this->explodeData($additionData, $default);
    }

    /**
     * @param $additionData
     * @param array $default
     *
     * @return array
     */
    protected function explodeData($additionData, $default = [])
    {
        if (empty($additionData) || count($additionData) == 0) {
            // we didn't have any of the tags so goodbye
            return $default;
        }

        $additionData = explode(',', $additionData->getContent());

        $additionData = array_column(array_map(function ($v) {
            return explode('|', $v);
        }, $additionData), 1, 0);

        return $additionData;
    }

    /**
     * @param $tags
     * @param null $resource
     * @param null $transformer
     *
     * @return mixed
     */
    protected function getResponderResponse($tags, $resource = null, $transformer = null)
    {
        // todo :: add more options to responder.
        $responderTags = $this->getFirstTagFromDocblock($tags, 'responder');
        if ($responderTags) {
            if ($resource == null) {
                // try get from @data tag
                $resource = $this->getDataTag($tags, null);
            }

            if ($transformer == null) {
                // try get from @transformer tag
                $transformer = $this->getFirstTagFromDocblock($tags, 'transformer');
                $transformer = $transformer ? $transformer->getContent() : null;
            }

            // get status
            $status = $this->getFirstTagFromDocblock($tags,'status');
            $status = $status ? $status->getContent() : null;

            $resourceKey = $this->getFirstTagFromDocblock($tags, 'resource-key');
            $resourceKey = $resourceKey ? $resourceKey->getContent() : null;

            $responder = responder()->{$responderTags->getContent()}($resource, $transformer, $resourceKey);
            if ($responderTags->getContent() !== 'error') {
                // try get meta.
                $metaData = $this->explodeData($this->getFirstTagFromDocblock($tags, 'meta'), []);
                $responder = $responder->meta($metaData);
            }

            return $responder->respond($status);
        }
    }

    /**
     * Get response content use responseclass tag.
     *
     * @param $tags
     *
     * @return bool|void
     * @throws \Exception
     */
    protected function getResponseClassResponse($tags)
    {
        try {
            $responseClassTag = $this->getFirstTagFromDocblock($tags, ['responseclass']);

            if (empty($responseClassTag) || count($responseClassTag) == 0) {
                // we didn't have any of the tags so goodbye
                return false;
            }

            $responseClass = $responseClassTag->getContent();
            if (!\class_exists($responseClass)) {
                // if we can't find the response class we can't generate a response
                return;
            }

            /** @var Resource|ResourceCollection $demoData */
            $demoData = app()->make($responseClass, ['resource' => collect()]);

            // add additional data.
            $additionalTag = $this->getFirstTagFromDocblock($tags, 'additional');
            if ($additionalTag) {
                $demoData->additional($this->explodeData($additionalTag));
            }

            // check if warp setup.
            $warpTag = $this->getFirstTagFromDocblock($tags, 'wrap');
            if ($warpTag) {
                $warpTag->getContent() === 'null'
                    ? $demoData->withoutWrapping()
                    : $demoData->wrap($warpTag->getContent());
            }

            return $demoData->toResponse(null);
        } catch (\Exception $exception) {
            throw $exception;
            return;
        }
    }

    /**
     * Get Response use @data tag.
     *
     * @param $tags
     *
     * @return bool|\Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    protected function getDataResponse($tags)
    {
        $data = $this->getDataTag($tags, false);

        return $data ? \response($data) : false;
    }

    /**
     * @param Route $route
     *
     * @return mixed
     */
    public function getUri($route)
    {
        if (version_compare(app()->version(), '5.4', '<')) {
            return $route->getUri();
        }

        return $route->uri();
    }

    /**
     * @param Route $route
     *
     * @return mixed
     */
    public function getMethods($route)
    {
        if (version_compare(app()->version(), '5.4', '<')) {
            return $route->getMethods();
        }

        return $route->methods();
    }

    /**
     * Prepares / Disables route middlewares.
     *
     * @param  bool $disable
     *
     * @return  void
     */
    public function prepareMiddleware($disable = true)
    {
        App::instance('middleware.disable', true);
    }

    /**
     * Call the given URI and return the Response.
     *
     * @param  string $method
     * @param  string $uri
     * @param  array $parameters
     * @param  array $cookies
     * @param  array $files
     * @param  array $server
     * @param  string $content
     *
     * @return \Illuminate\Http\Response
     */
    public function callRoute($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $server = collect([
            'CONTENT_TYPE' => 'application/json',
            'Accept' => 'application/json',
        ])->merge($server)->toArray();

        $request = Request::create($uri, $method, $parameters, $cookies, $files, $this->transformHeadersToServerVars($server), $content);

        $kernel = App::make('Illuminate\Contracts\Http\Kernel');
        $response = $kernel->handle($request);

        $kernel->terminate($request, $response);

        if (file_exists($file = App::bootstrapPath() . '/app.php')) {
            $app = require $file;
            $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
        }

        return $response;
    }

    /**
     * @param  string $route
     * @param  array $bindings
     *
     * @return array
     */
    protected function getRouteRules($route, $bindings)
    {
        list($class, $method) = explode('@', $route);
        $reflection = new ReflectionClass($class);
        $reflectionMethod = $reflection->getMethod($method);

        foreach ($reflectionMethod->getParameters() as $parameter) {
            $parameterType = $parameter->getClass();
            if (!is_null($parameterType) && class_exists($parameterType->name)) {
                $className = $parameterType->name;

                if (is_subclass_of($className, FormRequest::class)) {
                    $parameterReflection = new $className;
                    $parameterReflection->setContainer(app());
                    // Add route parameter bindings
                    $parameterReflection->query->add($bindings);
                    $parameterReflection->request->add($bindings);

                    if (method_exists($parameterReflection, 'validator')) {
                        return app()->call([$parameterReflection, 'validator'])->getRules();
                    } else {
                        return app()->call([$parameterReflection, 'rules']);
                    }
                }
            }
        }

        return [];
    }
}
