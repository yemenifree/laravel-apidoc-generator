<?php

namespace Mpociot\ApiDoc\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TestController extends Controller
{
    public function dummy()
    {
        return '';
    }

    /**
     * Example title.
     * This will be the long description.
     * It can also be multiple lines long.
     */
    public function parseMethodDescription()
    {
        return '';
    }

    public function parseFormRequestRules(TestRequest $request)
    {
        return '';
    }

    public function addRouteBindingsToRequestClass(DynamicRequest $request)
    {
        return '';
    }

    public function checkCustomHeaders(Request $request)
    {
        return $request->headers->all();
    }

    public function fetchRouteResponse()
    {
        $fixture = new \stdClass();
        $fixture->id = 1;
        $fixture->name = 'banana';
        $fixture->color = 'red';
        $fixture->weight = 300;
        $fixture->delicious = 1;

        return [
            'id' => (int) $fixture->id,
            'name' => ucfirst($fixture->name),
            'color' => ucfirst($fixture->color),
            'weight' => $fixture->weight.' grams',
            'delicious' => (bool) $fixture->delicious,
        ];
    }

    public function dependencyInjection(DependencyInjection $dependency, TestRequest $request)
    {
        return '';
    }

    public function utf8()
    {
        return ['result' => 'Лорем ипсум долор сит амет'];
    }

    /**
     * @hideFromAPIDocumentation
     */
    public function skip()
    {
    }

    /**
     * @response {
     *  data: [],
     *}
     */
    public function responseTag()
    {
        return '';
    }

    /**
     * @transformer \Mpociot\ApiDoc\Tests\Fixtures\TestTransformer
     */
    public function transformerTag()
    {
        return '';
    }

    /**
     * @transformer \Mpociot\ApiDoc\Tests\Fixtures\TestTransformer
     * @transformermodel \Mpociot\ApiDoc\Tests\Fixtures\TestModel
     */
    public function transformerTagWithModel()
    {
        return '';
    }

    /**
     * @transformercollection \Mpociot\ApiDoc\Tests\Fixtures\TestTransformer
     */
    public function transformerCollectionTag()
    {
        return '';
    }

    /**
     * @transformercollection \Mpociot\ApiDoc\Tests\Fixtures\TestTransformer
     * @transformermodel \Mpociot\ApiDoc\Tests\Fixtures\TestModel
     */
    public function transformerCollectionTagWithModel()
    {
        return '';
    }

    /**
     * @transformer \Mpociot\ApiDoc\Tests\Fixtures\TestTransformer
     * @transformermodel \Mpociot\ApiDoc\Tests\Fixtures\TestModel
     * @serializer \League\Fractal\Serializer\ArraySerializer
     */
    public function transformerTagWithCustomSerializer()
    {
        return '';
    }

    /**
     * @responseclass \Mpociot\ApiDoc\Tests\Fixtures\TestStaticMessageResponse
     * @wrap null
     */
    public function responseClassTagWithCustomStaticResponseData()
    {
        return '';
    }

    /**
     * @responseclass \Mpociot\ApiDoc\Tests\Fixtures\TestMessageResponse
     * @additional message|test,status_code|200
     */
    public function responseClassTagWithCustomDynamicResponseData()
    {
        return '';
    }

    /**
     * @transformer \Mpociot\ApiDoc\Tests\Fixtures\TestTransformerUseData
     * @data id|1,description|Welcome on this test versions,name|TestName
     */
    public function transformerTagWithData()
    {
        return '';
    }

    /**
     * @transformercollection \Mpociot\ApiDoc\Tests\Fixtures\TestTransformerUseData
     * @data id|1,description|Welcome on this test versions,name|TestName
     */
    public function transformerCollectionTagWithData()
    {
        return '';
    }

    /**
     * @data id|1,description|Welcome on this test versions,name|TestName
     */
    public function dataTag()
    {
        return '';
    }
}
